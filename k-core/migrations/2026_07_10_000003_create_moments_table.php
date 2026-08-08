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
            // Canonical ProseMirror JSON document. Nullable -- a Moment can be entirely
            // composed of an extension's own content (e.g. a poll) with no body of its own.
            // `body_html` is the sanitized rendered HTML `DocumentRenderer` produces from it at
            // write time.
            $table->text('body')->nullable();
            $table->text('body_html')->nullable();
            $table->timestamps();
            // Hide = soft delete, Delete = force delete -- see
            // .docs/planning/moderation-extension-plan.md. deleted_by/deleted_reason ride
            // alongside deleted_at so that attribution never outlives the row itself.
            $table->softDeletes();
            $table->foreignUuid('deleted_by')->nullable()->constrained(table: 'people')->nullOnDelete();
            $table->string('deleted_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moments');
    }
};
