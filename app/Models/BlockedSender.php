<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlockedSender extends Model
{
    protected $guarded = [];

    // Cocok bila pattern = alamat penuh, atau = domain pengirim (termasuk subdomain)
    public static function isBlocked(?string $fromAddress): bool
    {
        $from = Str::lower(trim((string) $fromAddress));
        if ($from === '' || !str_contains($from, '@')) {
            return false;
        }

        $domain = substr($from, strrpos($from, '@') + 1);

        return static::query()
            ->where('pattern', $from)
            ->orWhere('pattern', $domain)
            ->orWhereRaw("? like concat('%.', pattern)", [$domain])
            ->exists();
    }
}
