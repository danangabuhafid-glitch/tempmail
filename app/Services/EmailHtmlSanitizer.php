<?php

namespace App\Services;

use App\Models\Email;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitasi HTML email sebelum dirender.
 * Email adalah input tak tepercaya dari orang asing — vektor XSS utama.
 */
class EmailHtmlSanitizer
{
    // $allowRemoteImages=false: <img> remote diganti placeholder (anti tracking-pixel).
    // Gambar inline (lampiran cid:) selalu boleh — file lokal, bukan pelacak.
    public function sanitize(?string $html, bool $allowRemoteImages = false, ?Email $email = null): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        if ($email) {
            $html = $this->rewriteCid($html, $email);
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->withMaxInputLength(2_000_000)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            // Elemen media lain bisa memuat URL remote (pelacakan) tanpa lewat <img>
            ->blockElement('video')
            ->blockElement('audio')
            ->blockElement('source')
            ->blockElement('track')
            ->blockElement('picture')
            ->blockElement('object')
            ->blockElement('embed')
            ->dropAttribute('srcset', '*')
            ->dropAttribute('imagesrcset', '*')
            ->dropAttribute('poster', '*')
            ->dropAttribute('background', '*')
            ->dropAttribute('lowsrc', '*')
            // style bisa memuat url(...) remote sebagai pelacak
            ->dropAttribute('style', '*');

        $clean = (new HtmlSanitizer($config))->sanitize($html);

        $clean = $this->rewriteLinks($clean);

        if (!$allowRemoteImages) {
            $inlinePrefix = url('/attachment/inline/');
            $clean = preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($inlinePrefix) {
                if (preg_match('/\bsrc="([^"]*)"/i', $m[0], $src)
                    && str_starts_with(html_entity_decode($src[1], ENT_QUOTES | ENT_HTML5), $inlinePrefix)) {
                    return $m[0];
                }

                return '<span style="display:inline-block;padding:2px 8px;background:#eee;color:#888;border-radius:4px;font-size:12px;">[gambar diblokir]</span>';
            }, $clean);
        }

        return $clean;
    }

    // src="cid:xyz" → URL lampiran inline milik email ini (dirender via route ber-auth)
    private function rewriteCid(string $html, Email $email): string
    {
        $byCid = $email->attachments->filter(fn ($a) => filled($a->content_id))->keyBy('content_id');
        if ($byCid->isEmpty()) {
            return $html;
        }

        return preg_replace_callback('/\b(src)\s*=\s*(["\'])cid:([^"\']+)\2/i', function ($m) use ($byCid) {
            $att = $byCid->get(trim($m[3]));
            if (!$att) {
                return $m[0];
            }

            return 'src="' . e($att->inlineUrl()) . '"';
        }, $html);
    }

    // Semua link http(s) diarahkan ke halaman perantara anti-phishing:
    // URL asli ditampilkan dulu (plus dibersihkan dari parameter pelacak)
    private function rewriteLinks(string $html): string
    {
        return preg_replace_callback('/\bhref="(https?:\/\/[^"]+)"/i', function ($m) {
            $target = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            $encoded = rtrim(strtr(base64_encode($target), '+/', '-_'), '=');

            return 'href="' . route('link.keluar', ['u' => $encoded]) . '"';
        }, $html);
    }
}
