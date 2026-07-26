<?php

namespace App\Services;

use App\Models\BlockedSender;
use App\Models\Email;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Message;

/**
 * Menerima raw MIME dari webhook (Cloudflare Email Worker), mem-parse,
 * dan menyimpannya sebagai record Email + Attachment.
 */
class EmailIngestService
{
    public function __construct(
        private OtpExtractor $otp,
        private PushNotifier $notifier,
    ) {
    }

    /**
     * @param string $raw          Raw MIME lengkap (header + body)
     * @param string $envelopeTo   Alamat penerima dari envelope SMTP (paling akurat utk catch-all)
     * @param string|null $envelopeFrom
     * @return Email|null          null = pengirim diblokir (email dibuang diam-diam)
     */
    public function ingest(string $raw, string $envelopeTo, ?string $envelopeFrom = null): ?Email
    {
        $toAddress = Str::lower(trim($envelopeTo));

        // Hash memperhitungkan penerima: email yang sama dikirim ke 2 alias
        // (BCC/CC) tetap tersimpan di kedua kotak masuk
        $dedupHash = hash('sha256', $toAddress . '|' . $raw);

        // Idempotent: worker Cloudflare bisa retry — email yang sama tidak digandakan
        $existing = Email::where('dedup_hash', $dedupHash)->first();
        if ($existing) {
            return $existing;
        }

        [$alias, $domain] = $this->splitAddress($toAddress);

        $message = Message::from($raw, true);

        $fromHeader = $message->getHeader(HeaderConsts::FROM);
        $fromAddress = $fromHeader ? Str::lower((string) $fromHeader->getEmail()) : ($envelopeFrom ? Str::lower($envelopeFrom) : null);
        $fromName = $fromHeader ? $fromHeader->getPersonName() : null;

        // Pengirim terblokir: buang tanpa error supaya worker tidak retry
        if (BlockedSender::isBlocked($fromAddress) || BlockedSender::isBlocked($envelopeFrom)) {
            return null;
        }

        $headersRaw = $this->extractRawHeaders($raw);
        $subject = $message->getHeaderValue(HeaderConsts::SUBJECT);
        $textBody = $message->getTextContent();

        // Hasil autentikasi HANYA dibaca dari Authentication-Results milik Cloudflare
        // (authserv-id mengandung "cloudflare") — header serupa yang disisipkan
        // pengirim untuk memalsukan "pass" diabaikan.
        $authSegment = $this->trustedAuthResults($headersRaw);
        $spf = $this->extractAuthResult($authSegment, 'spf');
        $dkim = $this->extractAuthResult($authSegment, 'dkim');
        $dmarc = $this->extractAuthResult($authSegment, 'dmarc');

        // Folder spam: DMARC gagal, atau SPF & DKIM dua-duanya gagal
        $isSpam = $dmarc === 'fail' || ($spf === 'fail' && $dkim === 'fail');

        try {
            $email = DB::transaction(function () use ($message, $raw, $dedupHash, $domain, $alias, $toAddress, $fromAddress, $fromName, $headersRaw, $subject, $textBody, $spf, $dkim, $dmarc, $isSpam) {
                $email = Email::create([
                    'dedup_hash' => $dedupHash,
                    'message_id' => Str::limit((string) $message->getHeaderValue(HeaderConsts::MESSAGE_ID), 250, ''),
                    'domain' => $domain,
                    'alias' => $alias,
                    'to_address' => $toAddress,
                    'from_address' => $fromAddress ? Str::limit($fromAddress, 250, '') : null,
                    'from_name' => $fromName ? Str::limit($fromName, 250, '') : null,
                    'subject' => $subject,
                    'otp_code' => $this->otp->extract($subject, $textBody),
                    'text_body' => $textBody,
                    'html_body' => $message->getHtmlContent(),
                    'headers_raw' => $headersRaw,
                    'spf_result' => $spf,
                    'dkim_result' => $dkim,
                    'dmarc_result' => $dmarc,
                    'is_spam' => $isSpam,
                    'size_bytes' => strlen($raw),
                    'received_at' => now(),
                    'expires_at' => now()->addDays(config('tempmail.retention_days')),
                ]);

                // Simpan MIME mentah agar bisa diunduh sebagai .eml / diarsipkan
                $rawPath = 'raw/' . $email->id . '.eml';
                Storage::disk('local')->put($rawPath, $raw);
                $email->update(['raw_path' => $rawPath]);

                foreach ($message->getAllAttachmentParts() as $part) {
                    $content = $part->getContent();
                    if ($content === null || $content === '') {
                        continue;
                    }

                    $filename = $this->safeFilename($part->getFilename());
                    $path = 'attachments/' . $email->id . '/' . Str::random(8) . '_' . $filename;
                    Storage::disk('local')->put($path, $content);

                    $email->attachments()->create([
                        'filename' => $filename,
                        'mime_type' => Str::limit((string) $part->getContentType(), 250, ''),
                        'size_bytes' => strlen($content),
                        'path' => $path,
                        // Content-ID → gambar inline (src="cid:...") bisa dirender
                        'content_id' => Str::limit(trim((string) $part->getContentId(), '<> '), 250, '') ?: null,
                        // Skrining: tipe berbahaya diberi badge peringatan + konfirmasi unduh
                        'is_flagged' => $this->lampiranBerbahaya($filename, (string) $part->getContentType()),
                    ]);
                }

                return $email;
            });
        } catch (QueryException $e) {
            // Race saat worker retry paralel: dua request lolos cek dedup, yang kalah
            // menabrak unique constraint — kembalikan record pemenang (tetap idempotent)
            $existing = Email::where('dedup_hash', $dedupHash)->first();
            if ($existing) {
                return $existing;
            }
            throw $e;
        }

        try {
            $this->notifier->emailBaru($email);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi email baru gagal: ' . $e->getMessage());
        }

        return $email;
    }

    // Pecah alamat jadi [alias, domain]; alias huruf kecil tanpa spasi
    private function splitAddress(string $address): array
    {
        $pos = strrpos($address, '@');
        if ($pos === false) {
            return [$address, ''];
        }

        return [substr($address, 0, $pos), substr($address, $pos + 1)];
    }

    // Ambil blok header mentah (sebelum baris kosong pertama)
    private function extractRawHeaders(string $raw): string
    {
        $limit = 65535; // muat di MEDIUMTEXT dengan aman, header normal jauh di bawah ini
        $sep = strpos($raw, "\r\n\r\n");
        if ($sep === false) {
            $sep = strpos($raw, "\n\n");
        }

        $headers = $sep === false ? $raw : substr($raw, 0, $sep);

        return mb_strimwidth($headers, 0, $limit, '', 'UTF-8');
    }

    /**
     * Ambil isi header Authentication-Results yang authserv-id-nya milik
     * Cloudflare saja. Pengirim jahat bisa menyisipkan header tiruan berisi
     * "spf=pass" — dengan cek authserv-id, tiruan itu tidak pernah dibaca.
     */
    private function trustedAuthResults(string $headers): string
    {
        // Satukan header terlipat (folded): baris lanjutan diawali spasi/tab
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $headers);

        $segments = [];
        foreach (preg_split('/\r?\n/', $unfolded) as $line) {
            if (preg_match('/^Authentication-Results:\s*([^;]+);(.*)$/i', $line, $m)
                && str_contains(Str::lower($m[1]), 'cloudflare')) {
                $segments[] = $m[2];
            }
        }

        return implode('; ', $segments);
    }

    // Baca hasil spf=/dkim=/dmarc= dari segmen Authentication-Results tepercaya
    private function extractAuthResult(string $authSegment, string $mechanism): ?string
    {
        if (preg_match('/\b' . preg_quote($mechanism, '/') . '=([a-z]+)/i', $authSegment, $m)) {
            return Str::lower($m[1]);
        }

        return null;
    }

    // Deteksi lampiran berisiko: ekstensi eksekusi/skrip, HTML, atau ekstensi ganda
    private function lampiranBerbahaya(string $filename, string $mime): bool
    {
        $bahaya = ['exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif', 'js', 'jse', 'vbs', 'vbe',
            'wsf', 'ps1', 'jar', 'apk', 'html', 'htm', 'svg', 'hta', 'lnk', 'iso', 'img'];

        $lower = Str::lower($filename);
        $ext = pathinfo($lower, PATHINFO_EXTENSION);

        if (in_array($ext, $bahaya, true)) {
            return true;
        }

        // Ekstensi ganda menyamar: "tagihan.pdf.exe" tertangkap di atas,
        // tapi "tagihan.exe.pdf" juga mencurigakan
        if (preg_match('/\.(' . implode('|', $bahaya) . ')\.[a-z0-9]{1,5}$/', $lower)) {
            return true;
        }

        return str_contains(Str::lower($mime), 'text/html');
    }

    // Nama file lampiran aman: tanpa path traversal, panjang terbatas
    private function safeFilename(?string $filename): string
    {
        $filename = basename(trim((string) $filename));
        $filename = preg_replace('/[^\w.\-]+/u', '_', $filename) ?: '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'lampiran.bin';
        }

        return Str::limit($filename, 120, '');
    }
}
