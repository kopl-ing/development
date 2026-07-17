<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same repurposing as moments' own `2026_07_17_000000_add_body_html_to_moments_table` --
     * `body` becomes canonical ProseMirror JSON, `body_html` is the rendered HTML.
     */
    public function up(): void
    {
        Schema::table('replies', function (Blueprint $table) {
            $table->text('body_html')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }
};
