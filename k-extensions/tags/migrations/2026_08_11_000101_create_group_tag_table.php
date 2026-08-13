<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors `group_pin`'s shape exactly (see its own migration): plain pivot, composite
        // PK, cascading FKs, no extra columns. Only consulted when the owning tag's own
        // `restricted` is true (see Tag::isPostableBy()) -- empty for an unrestricted tag, and
        // ignored entirely even if non-empty for one, same as `group_pin` is ignored for a Pin
        // with no `starts_at`/`ends_at` restriction of its own kind.
        Schema::create('group_tag', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained(table: 'groups')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained(table: 'tags')->cascadeOnDelete();
            $table->primary(['group_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_tag');
    }
};
