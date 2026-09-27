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

        return response()
            ->view('public_check.index', compact('domains', 'alias', 'domain', 'emails'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Ambil isi detail email lengkap (HTML tersanitasi + Plaintext) via AJAX
     */
    public function showEmail(Request $request, Email $email, EmailHtmlSanitizer $sanitizer)
    {
        $email->load('attachments');

        $otp = $email->otp_code ?: $this->extractOtp($email);
        $showImages = $request->boolean('images', true); // default tampilkan gambar untuk kemudahan user publik
        $isV2 = $request->query('v') === '2';

        if (!$isV2) {
            // Jika dipanggil oleh tab browser HP lama yang belum di-refresh (masih tersimpan di memori),
            // otomatis paksa reload window agar membuka antarmuka in-page yang baru
            $wrappedHtml = '<div style="padding:24px;text-align:center;font-family:sans-serif;">'
                . '<h4 style="color:#4f46e5;margin-bottom:12px;">Pembaruan Sistem</h4>'
                . '<p style="color:#64748b;margin-bottom:16px;">Tampilan telah diperbarui ke versi langsung tanpa popup. Memuat ulang...</p>'
                . '<button onclick="window.top.location.reload(true)" style="padding:10px 20px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;">Muat Ulang Halaman</button>'
                . '<script>try { window.top.location.reload(true); } catch(e) {}</script>'
                . '</div>';
        } else {
            $htmlClean = $sanitizer->sanitize($email->html_body, $showImages, $email);
            $wrappedHtml = '';
            if (filled($htmlClean)) {
                $wrappedHtml = '<!DOCTYPE html><html lang="id"><head>'
                    . '<meta charset="utf-8">'
                    . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                    . '<style>'
                    . '*, *::before, *::after { box-sizing: border-box; } '
                    . 'html, body { margin: 0; padding: 0; background: #ffffff; -webkit-text-size-adjust: 100%; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.5; overflow-x: hidden; } '
                    . '#tm-email-scaler { display: inline-block; min-width: 100%; transform-origin: 0 0; padding: 8px; } '
                    . 'img { max-width: 100%; height: auto; display: inline-block; } '
                    . 'a { color: #4f46e5; } '
                    . '</style>'
                    . '</head><body>'
                    . '<div id="tm-email-scaler">'
                    . $htmlClean
                    . '</div>'
                    . '<script>'
                    . 'var isFitMode = true;'
                    . 'function fitEmail() {'
                    . '  var scaler = document.getElementById("tm-email-scaler");'
                    . '  if (!scaler) return;'
                    . '  if (!isFitMode) { scaler.style.transform = "none"; scaler.style.width = "auto"; notify(document.body.scrollHeight + 30); return; }'
                    . '  scaler.style.transform = "none";'
                    . '  scaler.style.width = "auto";'
                    . '  var viewW = window.innerWidth || document.documentElement.clientWidth;'
                    . '  var contentW = Math.max(scaler.scrollWidth, scaler.offsetWidth, document.body.scrollWidth);'
                    . '  if (contentW > viewW) {'
                    . '    var scale = viewW / contentW;'
                    . '    scaler.style.width = contentW + "px";'
                    . '    scaler.style.transform = "scale(" + scale + ")";'
                    . '    scaler.style.transformOrigin = "top left";'
                    . '    var scaledH = Math.ceil(scaler.offsetHeight * scale);'
                    . '    notify(scaledH + 30);'
                    . '  } else {'
                    . '    scaler.style.width = "100%";'
                    . '    scaler.style.transform = "none";'
                    . '    notify(document.body.scrollHeight + 30);'
                    . '  }'
                    . '}'
                    . 'function setFitMode(fit) { isFitMode = fit; fitEmail(); }'
                    . 'function notify(h) {'
                    . '  try {'
                    . '    if (window.parent && window.parent.postMessage) {'
                    . '      window.parent.postMessage({ type: "tm-iframe-height", height: h }, "*");'
                    . '    }'
                    . '  } catch(e) {}'
                    . '}'
                    . 'window.addEventListener("load", function() { fitEmail(); setTimeout(fitEmail, 200); setTimeout(fitEmail, 600); setTimeout(fitEmail, 1500); });'
                    . 'window.addEventListener("resize", fitEmail);'
                    . '</script>'
                    . '</body></html>';
            }
        }

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
                'html_body' => $wrappedHtml ?: $htmlClean,
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
