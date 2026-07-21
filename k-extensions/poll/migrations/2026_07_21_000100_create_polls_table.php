<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moment_id')->unique()->constrained(table: 'moments')->cascadeOnDelete();
            $table->string('question');
            $table->boolean('multiple_choice')->default(false);
            $table->unsignedTinyInteger('max_choices')->nullable();
            $table->string('results_visibility')->default('after_vote');
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
