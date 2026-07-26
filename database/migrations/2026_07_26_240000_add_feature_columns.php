<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom & tabel pendukung fitur: OTP, raw .eml, gambar inline (CID),
 * blokir pengirim, log webhook, 2FA, dan wajib ganti password akun alias.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            // Kode verifikasi/OTP yang terdeteksi otomatis saat ingest
            $table->string('otp_code', 16)->nullable()->after('subject');
            // Lokasi file raw MIME (.eml) di disk privat
            $table->string('raw_path')->nullable()->after('headers_raw');
        });

        Schema::table('attachments', function (Blueprint $table) {
            // Content-ID untuk lampiran inline (dirujuk src="cid:..." di HTML)
            $table->string('content_id')->nullable()->after('path')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('totp_secret', 64)->nullable();
            $table->text('totp_recovery_codes')->nullable();
            $table->boolean('must_change_password')->default(false);
        });

        // Pengirim yang diblokir: alamat penuh (spam@contoh.com) atau domain (contoh.com)
        Schema::create('blocked_senders', function (Blueprint $table) {
            $table->id();
            $table->string('pattern')->unique();
            $table->timestamps();
        });

        // Jejak setiap request webhook — termasuk yang gagal, untuk diagnosis email "hilang"
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status');
            $table->string('reason')->nullable();
            $table->string('envelope_to')->nullable();
            $table->string('from_address')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('blocked_senders');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_recovery_codes', 'must_change_password']);
        });
        Schema::table('attachments', function (Blueprint $table) {
            // Index harus dilepas dulu — SQLite gagal drop kolom yang masih ter-index
            $table->dropIndex(['content_id']);
            $table->dropColumn('content_id');
        });
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'raw_path']);
        });
    }
};
