<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mirrors `group_person`'s shape exactly: plain pivot, composite PK, cascading FKs, no
        // extra columns. Named `group_pin` (not `pin_group`) to match Eloquent's own default
        // belongsToMany pivot-naming convention (models joined alphabetically), same reason
        // `group_person` isn't `person_group`. Empty for a given pin -- visible to everyone;
        // non-empty -- visible only to a Person in at least one of these Groups (see
        // Pin::isVisibleTo()).
        Schema::create('group_pin', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained(table: 'groups')->cascadeOnDelete();
            $table->foreignUuid('pin_id')->constrained(table: 'pins')->cascadeOnDelete();
            $table->primary(['group_id', 'pin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_pin');
    }
};
