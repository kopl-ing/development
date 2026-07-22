<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained(table: 'pages')->cascadeOnDelete();
            // restrictOnDelete(), not cascade -- a template still used by a section shouldn't
            // silently take the section (and its content) down with it; see
            // PageSectionTemplatesController's own docblock.
            $table->foreignUuid('template_id')->constrained(table: 'page_section_templates')->restrictOnDelete();
            $table->integer('order')->default(0);
            // One entry per the template's own declared slots, keyed by slot name. A "wysiwyg"
            // slot's value is `{json, html}` -- json is the raw ProseMirror document (reloaded
            // into the editor), html is rendered server-side at write time through the same
            // Ux\Editor\DocumentRenderer whitelist Moment::$body/$body_html uses, never a second
            // sanitization codepath just because this content is admin-authored. Every other slot
            // type stores its value directly.
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
