<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'totp_secret', 'totp_recovery_codes', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'totp_secret', 'totp_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return filled($this->totp_secret);
    }

    // role 'owner' = pemilik (akses penuh); 'alias' = akun per alamat email
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    // Untuk akun alias: [alias, domain] diambil dari email login (alias@domain)
    public function kotakSaya(): array
    {
        $pos = strrpos($this->email, '@');
        if ($pos === false) {
            return [$this->email, ''];
        }

        return [substr($this->email, 0, $pos), substr($this->email, $pos + 1)];
    }
}
