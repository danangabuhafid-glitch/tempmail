<?php

namespace App\Services;

/**
 * Deteksi kode verifikasi/OTP dari subjek + isi email.
 * Heuristik: angka 4-8 digit di dekat kata kunci, atau pola khas "kode: 123456".
 */
class OtpExtractor
{
    private const KEYWORDS = 'kode|code|otp|pin|verifikasi|verification|verify|sandi|token|passcode|autentikasi|authentication|konfirmasi|confirmation|login';

    public function extract(?string $subject, ?string $textBody, ?string $htmlBody = null): ?string
    {
        $sources = [$subject, mb_substr((string) $textBody, 0, 4000)];

        $plainFromHtml = null;
        if (filled($htmlBody)) {
            $clean = preg_replace('#<style.*?</style>#is', '', $htmlBody);
            $clean = preg_replace('#<script.*?</script>#is', '', $clean);
            $plainFromHtml = trim(preg_replace('#\s+#', ' ', strip_tags($clean)));
            if (blank($textBody) || strlen((string) $textBody) < 20) {
                $sources[] = mb_substr($plainFromHtml, 0, 4000);
            }
        }

        foreach ($sources as $source) {
            if (blank($source)) {
                continue;
            }

            // "kode ... 482913" — kata kunci diikuti angka dalam jarak dekat
            if (preg_match('/(?:' . self::KEYWORDS . ')[^\d\r\n]{0,40}(\d{4,8})\b/iu', $source, $m)) {
                return $m[1];
            }

            // "482913 adalah kode ..." — angka mendahului kata kunci
            if (preg_match('/\b(\d{4,8})\b[^\d\r\n]{0,40}(?:' . self::KEYWORDS . ')/iu', $source, $m)) {
                return $m[1];
            }

            // Format berpisah "123 456" khas Google/WhatsApp di dekat kata kunci
            if (preg_match('/(?:' . self::KEYWORDS . ')[^\d\r\n]{0,40}(\d{3})[ -](\d{3})\b/iu', $source, $m)) {
                return $m[1] . $m[2];
            }
        }

        // Fallback 1: baris yang HANYA berisi 4-8 digit (pola umum email OTP)
        if (filled($textBody) && preg_match('/^\s*(\d{4,8})\s*$/m', $textBody, $m)) {
            return $m[1];
        }

        // Fallback 2: angka 6 digit di plain text dari HTML jika dekat kata kunci
        if ($plainFromHtml && preg_match('/(?:' . self::KEYWORDS . ')[^\d\r\n]{0,60}(\d{4,8})\b/iu', $plainFromHtml, $m)) {
            return $m[1];
        }

        return null;
    }
}
