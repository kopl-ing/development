<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (moment, person, emoji): a person may pick several different emoji on
        // the same moment, but never the same one twice -- the unique key makes a toggle a
        // simple find-or-create/delete, no counter column to drift out of sync.
        Schema::create('reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moment_id')->constrained(table: 'moments')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->string('emoji');
            $table->timestamps();

            $table->unique(['moment_id', 'person_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
