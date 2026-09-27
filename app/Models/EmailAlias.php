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

    /**
     * Buat alias baru fleksibel untuk Developer API.
     * Mendukung custom alias/nama, prefix, domain pilihan, TTL, dan label.
     */
    public static function buatBaru(?string $customName = null, ?string $prefix = null, ?string $domain = null, ?Carbon $expiresAt = null, ?string $label = null): ?self
    {
        $domains = config('tempmail.domains', []);
        $domain = $domain ?: ($domains[0] ?? 'danang.biz.id');
        if (!in_array($domain, $domains, true) && !empty($domains)) {
            $domain = $domains[0];
        }

        // 1. Jika user meminta custom alias tertentu
        if (filled($customName)) {
            $clean = Str::lower(trim($customName));
            $clean = preg_replace('/[^a-z0-9._-]+/', '', $clean);
            $clean = trim($clean, '._-');
            if ($clean !== '') {
                $existing = static::where('alias', $clean)->where('domain', $domain)->first();
                if (!$existing) {
                    return static::create([
                        'alias' => $clean,
                        'domain' => $domain,
                        'label' => $label ?: 'Dev API',
                        'expires_at' => $expiresAt,
                    ]);
                }
                // Jika sudah ada, tambahkan random suffix agar tetap sukses
                $candidate = $clean . '_' . Str::lower(Str::random(4));
                return static::create([
                    'alias' => $candidate,
                    'domain' => $domain,
                    'label' => $label ?: 'Dev API',
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        // 2. Jika ada prefix (misal: bot_, test_, reg_)
        $cleanPrefix = '';
        if (filled($prefix)) {
            $cleanPrefix = Str::lower(preg_replace('/[^a-z0-9._-]+/', '', $prefix));
        }
        if ($cleanPrefix === '') {
            $cleanPrefix = 'dev_';
        }

        // 3. Generate random alias
        for ($i = 0; $i < 10; $i++) {
            $randomStr = Str::lower(Str::random(6));
            $alias = $cleanPrefix . $randomStr;
            if (!static::where('alias', $alias)->where('domain', $domain)->exists()) {
                return static::create([
                    'alias' => $alias,
                    'domain' => $domain,
                    'label' => $label ?: 'Dev API',
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        return null;
    }
}
