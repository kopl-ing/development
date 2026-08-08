<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Stores a registered Manager::moderationTargets() alias (also a morph-map key),
            // never a raw class name -- see Flag::resolveFlaggable().
            $table->string('flaggable_type');
            $table->uuid('flaggable_id');
            $table->foreignUuid('person_id')->nullable()->constrained(table: 'people')->nullOnDelete();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->string('status')->default('pending');
            $table->foreignUuid('resolved_by')->nullable()->constrained(table: 'people')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One open report per (content, reporter) pair -- re-flagging the same item
            // updateOrCreate()s this same row back to pending, no history, same convention
            // pins.moment_id already established.
            $table->unique(['flaggable_type', 'flaggable_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
