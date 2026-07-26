<?php

return [

    // Domain yang diterima aplikasi (catch-all). Email ke domain lain ditolak.
    'domains' => array_filter(array_map('trim', explode(',', env('TEMPMAIL_DOMAINS', '')))),

    // Umur email sebelum dihapus otomatis oleh scheduler (model:prune)
    'retention_days' => (int) env('TEMPMAIL_RETENTION_DAYS', 30),

    // Shared secret antara Cloudflare Email Worker dan endpoint /inbound/email
    'webhook_token' => env('INBOUND_WEBHOOK_TOKEN'),

    // Batas ukuran raw email yang diterima webhook (byte)
    'max_email_bytes' => (int) env('TEMPMAIL_MAX_EMAIL_BYTES', 15 * 1024 * 1024),

    // Batas jumlah email masuk per menit di webhook (anti banjir catch-all)
    'inbound_rpm' => (int) env('TEMPMAIL_INBOUND_RPM', 60),

    // Kuota total penyimpanan email dalam MB; 0 = tanpa batas.
    // Saat terlampaui, email tertua dipangkas oleh tempmail:trim-storage.
    'max_storage_mb' => (int) env('TEMPMAIL_MAX_STORAGE_MB', 0),

    // Notifikasi push email baru (opsional; biasanya diisi lewat halaman Pengaturan)
    'notify_telegram_token' => env('TEMPMAIL_TELEGRAM_TOKEN'),
    'notify_telegram_chat_id' => env('TEMPMAIL_TELEGRAM_CHAT_ID'),
    'notify_ntfy_url' => env('TEMPMAIL_NTFY_URL'),

    // Token API baca (Bearer) untuk skrip/bot — kosong = API mati.
    // Biasanya dibuat lewat halaman Pengaturan (tersimpan di settings).
    'api_token' => env('TEMPMAIL_API_TOKEN'),

];
