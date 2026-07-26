<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pendukung anti-spam (DMARC + folder spam), skrining lampiran,
 * dan alias sekali pakai (TTL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->string('dmarc_result', 20)->nullable()->after('dkim_result');
            // Gagal autentikasi pengirim → masuk folder Spam (tetap tersimpan)
            $table->boolean('is_spam')->default(false)->after('is_read')->index();
        });

        Schema::table('attachments', function (Blueprint $table) {
            // Lampiran berpotensi berbahaya (exe/js/html, ekstensi ganda, dsb.)
            $table->boolean('is_flagged')->default(false);
        });

        Schema::table('aliases', function (Blueprint $table) {
            // Alias sekali pakai: setelah lewat, email ke alamat ini DITOLAK
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aliases', fn (Blueprint $t) => $t->dropColumn('expires_at'));
        Schema::table('attachments', fn (Blueprint $t) => $t->dropColumn('is_flagged'));
        Schema::table('emails', function (Blueprint $t) {
            // Index dilepas dulu agar rollback tidak gagal di SQLite
            $t->dropIndex(['is_spam']);
            $t->dropColumn(['dmarc_result', 'is_spam']);
        });
    }
};
