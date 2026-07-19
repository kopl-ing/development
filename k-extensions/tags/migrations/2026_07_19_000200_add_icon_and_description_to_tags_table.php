<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `icon` is a bare Font Awesome solid-style icon id (e.g. "palette"), chosen via
        // Ux\Form\IconPicker -- not one of core's own HasIcons-declared semantic ids, since this
        // is a free admin choice per tag, not a fixed small set an extension declares up front.
        // `description` gives newcomers something to read on the related-tags rail widget
        // (see k-extensions/widgets and the moment-detail rail) beyond just a name.
        Schema::table('tags', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('color');
            $table->text('description')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['icon', 'description']);
        });
    }
};
