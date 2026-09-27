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
        if (empty($domains)) {
            $domains = ['danang.biz.id', 'danangabuhafid.my.id', 'projectdanang.biz.id'];
        }

        // Tentukan domain utama untuk record ini:
        // Jika tidak ditentukan atau "random"/"all", acak dari semua domain yang tersedia
        $isRandomDomain = blank($domain) || in_array(strtolower((string) $domain), ['random', 'any', 'all'], true);
        if ($isRandomDomain) {
            $selectedDomain = $domains[array_rand($domains)];
        } else {
            $selectedDomain = in_array($domain, $domains, true) ? $domain : $domains[0];
        }

        $chosenAlias = null;

        // 1. Jika user meminta custom alias tertentu
        if (filled($customName)) {
            $clean = Str::lower(trim($customName));
            $clean = preg_replace('/[^a-z0-9._-]+/', '', $clean);
            $clean = trim($clean, '._-');
            if ($clean !== '') {
                $existing = static::where('alias', $clean)->where('domain', $selectedDomain)->first();
                if (!$existing) {
                    $chosenAlias = $clean;
                } else {
                    $chosenAlias = $clean . '_' . Str::lower(Str::random(4));
                }
            }
        }

        // 2. Jika belum terpilih, generate acak dengan prefix
        if (!$chosenAlias) {
            $cleanPrefix = '';
            if (filled($prefix)) {
                $cleanPrefix = Str::lower(preg_replace('/[^a-z0-9._-]+/', '', $prefix));
            }
            if ($cleanPrefix === '') {
                $cleanPrefix = 'dev_';
            }

            for ($i = 0; $i < 10; $i++) {
                $candidate = $cleanPrefix . Str::lower(Str::random(6));
                if (!static::where('alias', $candidate)->where('domain', $selectedDomain)->exists()) {
                    $chosenAlias = $candidate;
                    break;
                }
            }
        }

        if (!$chosenAlias) {
            return null;
        }

        // Simpan alias ke domain terpilih
        $primaryRecord = static::create([
            'alias' => $chosenAlias,
            'domain' => $selectedDomain,
            'label' => $label ?: 'Dev API',
            'expires_at' => $expiresAt,
        ]);

        // Daftarkan juga ke domain-domain lainnya agar TTL berlaku merata di seluruh domain
        foreach ($domains as $d) {
            if ($d !== $selectedDomain && !static::where('alias', $chosenAlias)->where('domain', $d)->exists()) {
                static::create([
                    'alias' => $chosenAlias,
                    'domain' => $d,
                    'label' => $label ?: 'Dev API',
                    'expires_at' => $expiresAt,
                ]);
            }
        }

        return $primaryRecord;
    }
}
