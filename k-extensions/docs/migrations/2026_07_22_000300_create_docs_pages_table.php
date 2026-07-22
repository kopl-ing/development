<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docs_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            // Top-level nav grouping, e.g. "Getting Started" -- front matter's own "section"
            // key, not this codebase's Kopling\Core\Portal\Portal.
            $table->string('section');
            $table->integer('order')->default(0);
            $table->string('locale')->default('en');
            // Relative path on the resolved drive (Resolver::resolve('kopling-docs::content')),
            // not a filesystem path on this app server -- the drive is the source of truth, this
            // table is only a queryable/cacheable index over it (see PageRegistry).
            $table->string('storage_path');
            // sha1 of the raw file content -- PageRegistry::sync() skips re-rendering a page
            // whose file hasn't changed since the last sync.
            $table->string('content_hash');
            $table->longText('content_html');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_pages');
    }
};
