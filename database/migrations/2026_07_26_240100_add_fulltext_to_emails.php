<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FULLTEXT index agar pencarian isi email tidak full-scan.
 * Hanya MySQL/MariaDB — di SQLite (testing) pencarian jatuh ke LIKE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE emails ADD FULLTEXT emails_body_fulltext (subject, text_body)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('emails', function ($table) {
            $table->dropIndex('emails_body_fulltext');
        });
    }
};
