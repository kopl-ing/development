<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path')->unique();
            $table->string('title');
            $table->text('meta_description')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('show_in_nav')->default(false);
            $table->integer('nav_order')->default(0);
            // Which page renders at the Pages Portal's own root when no slug is given -- a
            // separate concept from the Portal-level homepage override (Manager::portals()'s
            // core.portal_path.* Settings key): that decides which Portal owns "/" at all, this
            // decides which page renders at *this* Portal's own root once it does.
            $table->boolean('is_index')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
