<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alamat (alias) yang dibuat/dikelola user. Berkat catch-all, alamat apa pun
 * sebenarnya langsung berfungsi — tabel ini untuk mencatat, memberi label,
 * dan menyematkan alamat yang sengaja dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias');
            $table->string('domain');
            $table->string('label')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->unique(['alias', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliases');
    }
};
