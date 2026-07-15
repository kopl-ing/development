<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // moment_id unique -- one active pin per moment, enforced at the DB level, not just app
        // logic: re-pinning updates this same row (see Pin::REASONS/PinController::store's
        // updateOrCreate), no history table.
        Schema::create('pins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('moment_id')->unique()->constrained(table: 'moments')->cascadeOnDelete();
            $table->string('reason');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pins');
    }
};
