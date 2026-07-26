<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailAlias extends Model
{
    protected $table = 'aliases';

    protected $guarded = [];

    protected $casts = [
        'is_pinned' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function getFullAddressAttribute(): string
    {
        return $this->alias . '@' . $this->domain;
    }

    // Alias sekali pakai yang masa aktifnya sudah lewat — email baru DITOLAK
    public function sudahKedaluwarsa(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Buat alias cepat bernama situs (netflix-x4k9) — dipakai bookmarklet & API.
     * Mengembalikan null bila 5x generate kebetulan tabrakan terus.
     */
    public static function buatCepat(string $site, ?string $domain = null, ?Carbon $expiresAt = null): ?self
    {
        $domain = $domain ?: (config('tempmail.domains')[0] ?? null);
        if (!$domain) {
            return null;
        }

        // "www.Netflix.com" -> "netflix"
        $situs = Str::lower(preg_replace('/^www\./', '', trim($site)));
        $situs = preg_replace('/[^a-z0-9]+/', '-', explode('.', $situs)[0] ?? '');
        $situs = trim(Str::limit($situs, 20, ''), '-') ?: 'situs';

        for ($i = 0; $i < 5; $i++) {
            $alias = $situs . '-' . Str::lower(Str::random(4));
            if (!static::where('alias', $alias)->where('domain', $domain)->exists()) {
                return static::create([
                    'alias' => $alias,
                    'domain' => $domain,
                    'label' => $site,
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        return null;
    }
}
