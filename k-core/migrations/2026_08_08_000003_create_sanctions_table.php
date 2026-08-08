<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only audit log -- Sanction::issue() writes a row and updates the person's own
        // current-state columns in the same call, never one without the other (see that
        // method's own docblock). Deliberately carries no reference back to a triggering report
        // (a `flags.id` FK) -- `flags` belongs to the optional `kopling/moderation` extension,
        // and this table is a core primitive any caller can use whether or not that extension is
        // even installed; a caller that does have a specific Flag in hand resolves it on its own
        // side instead (see ContentModerator's own resolvePendingFlags()-shaped precedent).
        Schema::create('sanctions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->foreignUuid('issued_by')->constrained(table: 'people')->cascadeOnDelete();
            $table->foreignUuid('lifted_by')->nullable()->constrained(table: 'people')->nullOnDelete();
            $table->boolean('communication_blocked')->default(false);
            $table->string('visibility')->nullable();
            $table->boolean('access_blocked')->default(false);
            $table->timestamp('access_blocked_until')->nullable();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('lifted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
