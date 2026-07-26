<?php

use App\Models\Setting;
use App\Models\WebhookLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus email yang melewati masa retensi (lihat TEMPMAIL_RETENTION_DAYS)
Schedule::command('model:prune', ['--model' => [App\Models\Email::class]])->daily();

// Pagar kuota penyimpanan (settings max_storage_mb; 0 = tanpa batas)
Schedule::command('tempmail:trim-storage')->daily();

// Backup DB + lampiran + raw .eml, rotasi 7 arsip terakhir
Schedule::command('tempmail:backup')->dailyAt('03:00');

// Heartbeat: bukti cron `schedule:run` benar-benar jalan. Kalau timestamp ini
// basi, banner peringatan muncul di web (auto-hapus retensi ikut mati diam-diam).
Schedule::call(fn () => Setting::setValue('scheduler_last_run', now()->toIso8601String()))
    ->hourly()
    ->name('tempmail-heartbeat');

// Kesehatan per-domain: cek NS/MX tiap domain tiap jam; saat ada yang rusak/pulih,
// kirim notifikasi Telegram/ntfy + tampilkan banner & status di aplikasi
Schedule::call(fn () => app(App\Services\DomainHealthService::class)->runScheduledCheck())
    ->hourly()
    ->name('tempmail-domain-health');

// Log webhook tidak perlu disimpan selamanya
Schedule::call(fn () => WebhookLog::where('created_at', '<', now()->subDays(30))->delete())
    ->daily()
    ->name('tempmail-prune-webhook-logs');
