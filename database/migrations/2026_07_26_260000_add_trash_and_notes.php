<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keranjang sampah (soft delete) + catatan pribadi per email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Email dihapus masuk sampah dulu — bisa dipulihkan sebelum benar-benar hilang
            $table->softDeletes()->index();
            // Catatan pribadi pemilik ("akun trial, expire 3 Agustus")
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
            $table->dropColumn('note');
        });
    }
};
