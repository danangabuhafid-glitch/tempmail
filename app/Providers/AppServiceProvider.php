<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // key settings DB => key config yang ditimpa
    private const SETTING_MAP = [
        'retention_days' => 'tempmail.retention_days',
        'webhook_token' => 'tempmail.webhook_token',
        'max_email_bytes' => 'tempmail.max_email_bytes',
        'inbound_rpm' => 'tempmail.inbound_rpm',
        'max_storage_mb' => 'tempmail.max_storage_mb',
        'notify_telegram_token' => 'tempmail.notify_telegram_token',
        'notify_telegram_chat_id' => 'tempmail.notify_telegram_chat_id',
        'notify_ntfy_url' => 'tempmail.notify_ntfy_url',
        'api_token' => 'tempmail.api_token',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Template pagination default Laravel memakai Tailwind — app ini Bootstrap 5
        Paginator::useBootstrapFive();

        // Pengaturan dari database menimpa nilai .env — supaya bisa diubah via web.
        // .env tetap jadi nilai awal/fallback.
        try {
            if (Schema::hasTable('settings')) {
                $db = Setting::pluck('value', 'key');

                if (filled($db->get('domains'))) {
                    config(['tempmail.domains' => array_filter(array_map('trim', explode(',', $db['domains'])))]);
                }

                $numeric = ['retention_days', 'max_email_bytes', 'inbound_rpm', 'max_storage_mb'];
                foreach (self::SETTING_MAP as $key => $configKey) {
                    if (filled($db->get($key))) {
                        $value = $db[$key];
                        config([$configKey => in_array($key, $numeric, true) ? (int) $value : $value]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // DB belum siap (composer install / migrate pertama) — pakai nilai .env
        }
    }
}
