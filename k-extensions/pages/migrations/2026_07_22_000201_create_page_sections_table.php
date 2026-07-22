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
            // A closed, small set for v1 -- "rich-text" and "hero" -- resist growing this into a
            // generic block-type registry until a real page needs a third kind.
            $table->string('kind');
            $table->integer('order')->default(0);
            // ProseMirror JSON for a "rich-text" section, rendered through the same
            // DocumentRenderer whitelist Moment::$body/$body_html already uses -- never a second
            // sanitization codepath for admin-authored content.
            $table->json('content')->nullable();
            $table->text('content_html')->nullable();
            // Kind-specific fields that aren't rich-text content at all -- a "hero" section's
            // subtitle/CTA label/CTA url.
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
