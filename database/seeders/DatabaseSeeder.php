<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Aplikasi single-user: satu akun pemilik, kredensial dari .env
     * (ADMIN_EMAIL / ADMIN_PASSWORD — ganti sebelum deploy!).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@tempmail.local')],
            [
                'name' => 'Pemilik',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'gantipasswordini')),
                'role' => 'owner',
            ]
        );
    }
}
