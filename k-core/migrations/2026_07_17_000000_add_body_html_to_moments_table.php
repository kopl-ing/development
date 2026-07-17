<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `body` itself is repurposed (no schema change needed) to hold the canonical ProseMirror
     * JSON document instead of plain text, going forward -- see the accompanying backfill
     * migration for existing rows. `body_html` is the sanitized rendered HTML `DocumentRenderer`
     * produces from it at write time, nullable until the backfill runs.
     */
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->text('body_html')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }
};
