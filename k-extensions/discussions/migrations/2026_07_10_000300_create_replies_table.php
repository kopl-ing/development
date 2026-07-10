<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replies to a moment -- the "discussion" core has no model for yet. A flat list for
        // now (no nested threading): the demo's discussion page is a single thread.
        Schema::create('replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moment_id')->constrained(table: 'moments')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replies');
    }
};
