<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately minimal -- only what Card's current UI actually renders. No `tag`
        // column: tagging is a future extension's own concern, not core's, and doesn't
        // belong here just because the placeholder UI used to show one.
        Schema::create('moments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->string('title');
            // Canonical ProseMirror JSON document. `body_html` is the sanitized rendered HTML
            // `DocumentRenderer` produces from it at write time.
            $table->text('body');
            $table->text('body_html')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moments');
    }
};
