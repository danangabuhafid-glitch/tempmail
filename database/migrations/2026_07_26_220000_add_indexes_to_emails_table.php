<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk skala besar: rekap belum-dibaca per alamat dan daftar
 * alamat penerima terbaru tetap cepat walau email sudah puluhan ribu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->index(['is_read', 'alias', 'domain'], 'emails_unread_box_idx');
            $table->index(['alias', 'domain', 'received_at'], 'emails_box_received_idx');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_unread_box_idx');
            $table->dropIndex('emails_box_received_idx');
        });
    }
};
