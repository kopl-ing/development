<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_section_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Compiled via Blade::render() at display time -- full directive support (@if,
            // @foreach, __()), not a placeholder-substitution mini-language. That means an admin
            // authoring a template is writing server-executing PHP, not markup -- see
            // Extension::permissions(), "manage-page-templates" is deliberately its own
            // permission, separate from "manage-pages".
            $table->text('blade_source');
            // Ordered list of {name, type, label} -- what variables the template above expects
            // ($name in the source), and what input widget to render for each when an admin fills
            // in a section using this template. A closed, small `SlotType` set (string/wysiwyg)
            // for v1, same "resist growing until a real template needs more" stance the old
            // SectionKind enum documented for itself.
            $table->json('slots');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_section_templates');
    }
};
