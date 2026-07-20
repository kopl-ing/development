<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tag vocabulary itself -- what core deliberately left out of the moments table
        // ("tagging is a future extension's own concern"). `color` is an optional hex the
        // card badge + tag page tint themselves with.
        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color')->nullable();
            // Bare Font Awesome solid-style icon id (e.g. "palette"), chosen via
            // Ux\Form\IconPicker -- not one of core's own HasIcons-declared semantic ids, since
            // this is a free admin choice per tag, not a fixed small set an extension declares
            // up front.
            $table->string('icon')->nullable();
            // Gives newcomers something to read on the related-tags rail widget beyond a name.
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
