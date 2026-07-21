<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained(table: 'polls')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('emoji')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
    }
};
