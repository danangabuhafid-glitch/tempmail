<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            // sha256 dari raw MIME — tolak email duplikat (worker bisa retry)
            $table->string('dedup_hash', 64)->unique();
            $table->string('message_id')->nullable()->index();

            $table->string('domain')->index();
            $table->string('alias')->index();
            $table->string('to_address');
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->text('subject')->nullable();

            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();
            $table->mediumText('headers_raw')->nullable();

            // Hasil autentikasi pengirim (dibaca dari header Authentication-Results)
            $table->string('spf_result', 20)->nullable();
            $table->string('dkim_result', 20)->nullable();

            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('is_read')->default(false);
            $table->timestamp('received_at')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['domain', 'alias']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('emails')->cascadeOnDelete();
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('emails');
    }
};
