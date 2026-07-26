<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public static function catat(int $status, ?string $reason, ?string $envelopeTo, ?string $from, int $size, ?string $ip): void
    {
        try {
            static::create([
                'status' => $status,
                'reason' => $reason ? mb_substr($reason, 0, 250) : null,
                'envelope_to' => $envelopeTo ? mb_substr($envelopeTo, 0, 250) : null,
                'from_address' => $from ? mb_substr($from, 0, 250) : null,
                'size_bytes' => $size,
                'ip' => $ip,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Log tidak boleh menggagalkan penerimaan email
        }
    }
}
