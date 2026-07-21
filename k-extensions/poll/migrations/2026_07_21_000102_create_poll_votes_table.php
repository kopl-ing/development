<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Denormalized alongside poll_option_id -- lets a poll's own vote count/closing
            // logic query by poll_id directly, no join through poll_options needed.
            $table->foreignUuid('poll_id')->constrained(table: 'polls')->cascadeOnDelete();
            $table->foreignUuid('poll_option_id')->constrained(table: 'poll_options')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_option_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
