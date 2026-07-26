<?php

namespace App\Services;

use App\Models\Email;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Kirim ringkasan email baru ke Telegram dan/atau ntfy.sh.
 * Fire-and-forget: timeout pendek, kegagalan tidak mengganggu penerimaan email.
 */
class PushNotifier
{
    public function emailBaru(Email $email): void
    {
        $judul = 'Email baru: ' . Str::limit($email->subject ?: '(tanpa subjek)', 90);
        $baris = 'Dari: ' . ($email->from_name ? $email->from_name . ' ' : '') . '<' . ($email->from_address ?: '?') . '>'
            . "\nKe: " . $email->to_address
            . ($email->otp_code ? "\nKode OTP: " . $email->otp_code : '');

        $this->telegram($judul . "\n" . $baris);
        $this->ntfy($judul, $baris, $email->otp_code);
    }

    // Peringatan operasional (mis. domain rusak/pulih) — prioritas tinggi
    public function peringatan(string $judul, string $isi): void
    {
        $this->telegram("⚠️ {$judul}\n{$isi}");

        $url = config('tempmail.notify_ntfy_url');
        if (blank($url)) {
            return;
        }

        try {
            Http::connectTimeout(2)->timeout(4)
                ->withHeaders(['Title' => $judul, 'Tags' => 'warning', 'Priority' => 'high'])
                ->withBody($isi, 'text/plain')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi peringatan gagal: ' . $e->getMessage());
        }
    }

    private function telegram(string $text): void
    {
        $token = config('tempmail.notify_telegram_token');
        $chatId = config('tempmail.notify_telegram_chat_id');
        if (blank($token) || blank($chatId)) {
            return;
        }

        try {
            Http::connectTimeout(2)->timeout(4)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi Telegram gagal: ' . $e->getMessage());
        }
    }

    private function ntfy(string $title, string $body, ?string $otp): void
    {
        $url = config('tempmail.notify_ntfy_url');
        if (blank($url)) {
            return;
        }

        try {
            Http::connectTimeout(2)->timeout(4)
                ->withHeaders(array_filter([
                    'Title' => $title,
                    'Tags' => 'envelope',
                    // Klik notifikasi ntfy di HP → salin OTP lebih cepat
                    'Priority' => $otp ? 'high' : 'default',
                ]))
                ->withBody($body, 'text/plain')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi ntfy gagal: ' . $e->getMessage());
        }
    }
}
