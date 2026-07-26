<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua jenis akun:
 * - owner : pemilik — akses penuh (inbox semua, alamat, setup, pengaturan)
 * - alias : akun per alamat email — hanya bisa melihat email yang masuk
 *           ke alamatnya sendiri (users.email = alias@domain)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('owner')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
