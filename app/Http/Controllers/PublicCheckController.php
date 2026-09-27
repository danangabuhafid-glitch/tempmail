<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Email;
use App\Services\EmailHtmlSanitizer;
use App\Services\OtpExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller untuk halaman web publik: /cek
 * Memungkinkan siapa saja mengecek isi email masuk untuk alamat tertentu
 * secara transparan, lengkap, dan tanpa memerlukan login.
 */
class PublicCheckController extends Controller
{
    public function __construct(
        private OtpExtractor $otpExtractor
    ) {
    }

    /**
     * Halaman utama pengecekan email publik
     */
    public function index(Request $request)
    {
        $domains = config('tempmail.domains', []);
        if (empty($domains)) {
            $domains = ['danang.biz.id', 'danangabuhafid.my.id', 'projectdanang.biz.id'];
        }

        $inputEmail = trim((string) $request->query('email', ''));
        $inputAlias = trim((string) $request->query('alias', ''));
        $inputDomain = trim((string) $request->query('domain', ''));

        // Jika user memasukkan email lengkap di parameter email atau alias
        $alias = '';
        $domain = $inputDomain;

        if ($inputEmail !== '') {
            if (str_contains($inputEmail, '@')) {
                [$alias, $domain] = explode('@', $inputEmail, 2);
            } else {
                $alias = $inputEmail;
            }
        } elseif ($inputAlias !== '') {
            if (str_contains($inputAlias, '@')) {
                [$alias, $domain] = explode('@', $inputAlias, 2);
            } else {
                $alias = $inputAlias;
            }
        }

        $alias = Str::lower(preg_replace('/[^a-zA-Z0-9._-]+/', '', $alias));
        if ($domain !== '' && $domain !== 'all' && !in_array($domain, $domains, true)) {
            $domain = $domains[0];
        }

        // Query email jika alias diisi
        $emails = collect();
        if ($alias !== '') {
            $query = Email::query()
                ->select(['id', 'to_address', 'alias', 'domain', 'from_address', 'from_name', 'subject', 'otp_code', 'received_at'])
                ->withExists('attachments')
                ->where('alias', $alias)
                ->where('is_spam', false)
                ->orderByDesc('id')
                ->limit(30);

            if ($domain !== '' && $domain !== 'all') {
                $query->where('domain', $domain);
            }

            $emails = $query->get()->map(function (Email $e) {
                return [
                    'id' => $e->id,
                    'to' => $e->to_address,
                    'alias' => $e->alias,
                    'domain' => $e->domain,
                    'from' => $e->from_address,
                    'from_name' => $e->from_name ?: $e->from_address,
                    'subject' => $e->subject ?: '(Tanpa Subjek)',
                    'otp' => $e->otp_code ?: null,
                    'has_attachments' => (bool) $e->attachments_exists,
                    'received_at' => $e->received_at?->toIso8601String(),
                    'received_diff' => $e->received_at?->diffForHumans(),
                    'received_time' => $e->received_at?->translatedFormat('d M Y, H:i:s') . ' WIB',
                ];
            });
        }

        // Jika request via AJAX / API polling
        if ($request->ajax() || $request->wantsJson() || $request->query('json')) {
            return response()->json([
                'success' => true,
                'alias' => $alias,
                'domain' => $domain,
                'total' => $emails->count(),
                'emails' => $emails,
            ]);
        }

        return view('public_check.index', compact('domains', 'alias', 'domain', 'emails'));
    }

    /**
     * Ambil isi detail email lengkap (HTML tersanitasi + Plaintext) via AJAX
     */
    public function showEmail(Request $request, Email $email, EmailHtmlSanitizer $sanitizer)
    {
        $email->load('attachments');

        $otp = $email->otp_code ?: $this->extractOtp($email);
        $showImages = $request->boolean('images', true); // default tampilkan gambar untuk kemudahan user publik
        $htmlClean = $sanitizer->sanitize($email->html_body, $showImages, $email);

        return response()->json([
            'success' => true,
            'email' => [
                'id' => $email->id,
                'to' => $email->to_address,
                'alias' => $email->alias,
                'domain' => $email->domain,
                'from_address' => $email->from_address,
                'from_name' => $email->from_name ?: $email->from_address,
                'subject' => $email->subject ?: '(Tanpa Subjek)',
                'otp' => $otp,
                'text_body' => $email->text_body,
                'html_body' => $htmlClean,
                'has_html' => filled($htmlClean),
                'spf' => $email->spf_result,
                'dkim' => $email->dkim_result,
                'received_at' => $email->received_at?->toIso8601String(),
                'received_time' => $email->received_at?->translatedFormat('d M Y, H:i:s') . ' WIB',
                'received_diff' => $email->received_at?->diffForHumans(),
                'attachments' => $email->attachments->map(fn (Attachment $a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'size' => $this->formatBytes($a->size_bytes),
                    'mime' => $a->mime_type,
                    'download_url' => route('public.check.attachment', $a->id),
                ]),
            ]
        ]);
    }

    /**
     * Unduh lampiran email publik
     */
    public function downloadAttachment(Attachment $attachment)
    {
        if (!Storage::disk('local')->exists($attachment->path)) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    /**
     * Ekstraksi OTP cadangan
     */
    private function extractOtp(Email $email): ?string
    {
        $otp = $this->otpExtractor->extract($email->subject, $email->text_body, $email->html_body);
        if ($otp && blank($email->otp_code)) {
            $email->update(['otp_code' => $otp]);
        }
        return $otp;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
