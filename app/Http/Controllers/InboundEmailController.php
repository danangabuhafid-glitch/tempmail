<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use App\Services\EmailIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Endpoint webhook penerima email dari Cloudflare Email Worker.
 * Body = raw MIME; identitas envelope lewat header X-Envelope-*.
 * Semua request dicatat ke webhook_logs agar kegagalan bisa didiagnosis.
 */
class InboundEmailController extends Controller
{
    public function store(Request $request, EmailIngestService $ingest)
    {
        $envelopeTo = Str::lower(trim((string) $request->header('X-Envelope-To', '')));
        $envelopeFrom = $request->header('X-Envelope-From');
        $size = strlen($request->getContent());
        $ip = $request->ip();

        $tolak = function (int $status, string $pesan) use ($envelopeTo, $envelopeFrom, $size, $ip) {
            WebhookLog::catat($status, $pesan, $envelopeTo, $envelopeFrom, $size, $ip);
            return response()->json(['message' => $pesan], $status);
        };

        // Token diperiksa DULU: kalau tidak, penyerang anonim bisa menghabiskan
        // jatah rate limit global (semua email asli ikut tertolak) dan membanjiri
        // tabel webhook_logs. Log 401 dibatasi per IP supaya tetap terlihat
        // tanpa bisa dijadikan alat banjir baris log.
        $token = (string) config('tempmail.webhook_token');
        $given = (string) $request->header('X-Webhook-Token', '');

        if ($token === '' || !hash_equals($token, $given)) {
            if (!RateLimiter::tooManyAttempts('inbound-auth:' . $ip, 5)) {
                RateLimiter::hit('inbound-auth:' . $ip, 3600);
                WebhookLog::catat(401, 'Unauthorized', $envelopeTo, $envelopeFrom, $size, $ip);
            }

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Pagar banjir: catch-all bisa disemprot alamat acak tanpa batas
        $rpm = max(1, (int) config('tempmail.inbound_rpm', 60));
        if (RateLimiter::tooManyAttempts('inbound-email', $rpm)) {
            // 429 = kegagalan sementara → worker melempar error, MTA retry nanti
            return $tolak(429, 'Terlalu banyak email per menit');
        }
        RateLimiter::hit('inbound-email', 60);

        $raw = $request->getContent();
        if ($raw === '') {
            return $tolak(422, 'Body kosong');
        }
        if (strlen($raw) > config('tempmail.max_email_bytes')) {
            return $tolak(413, 'Email terlalu besar');
        }

        if ($envelopeTo === '' || !str_contains($envelopeTo, '@')) {
            return $tolak(422, 'Header X-Envelope-To wajib diisi');
        }

        $domain = substr($envelopeTo, strrpos($envelopeTo, '@') + 1);
        if (!in_array($domain, config('tempmail.domains'), true)) {
            return $tolak(422, "Domain {$domain} tidak dilayani");
        }

        // Alias sekali pakai yang sudah lewat masa aktifnya: tolak final (422)
        // → worker me-reject sehingga pengirim menerima bounce yang jelas
        $aliasPart = substr($envelopeTo, 0, strrpos($envelopeTo, '@'));
        $aliasRow = \App\Models\EmailAlias::where('alias', $aliasPart)->where('domain', $domain)->first();
        if ($aliasRow && $aliasRow->sudahKedaluwarsa()) {
            return $tolak(422, 'Alamat sekali pakai sudah kedaluwarsa');
        }

        try {
            $email = $ingest->ingest($raw, $envelopeTo, $envelopeFrom);
        } catch (\Throwable $e) {
            Log::error('Gagal ingest email masuk: ' . $e->getMessage());
            return $tolak(500, 'Gagal memproses email');
        }

        // null = pengirim diblokir; balas 200 supaya worker TIDAK retry (dibuang final)
        if ($email === null) {
            WebhookLog::catat(200, 'Pengirim diblokir — email dibuang', $envelopeTo, $envelopeFrom, $size, $ip);
            return response()->json(['message' => 'Pengirim diblokir'], 200);
        }

        WebhookLog::catat(201, null, $envelopeTo, $envelopeFrom, $size, $ip);

        return response()->json(['message' => 'OK', 'id' => $email->id], 201);
    }
}
