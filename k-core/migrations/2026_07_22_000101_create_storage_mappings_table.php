<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_mappings', function (Blueprint $table) {
            // The already-prefixed StorageRequest id (e.g. "kopling-docs::content") is the
            // primary key -- one purpose maps to exactly one drive, and this id is already
            // globally unique by construction (Manager::storageDrivers() prefixing). A row's
            // mere absence means "unmapped", deliberately -- no nullable FK + status flag --
            // so "declared but unmapped" and "mapped but no longer declared" are both plain
            // set-diff queries against Manager::storageDrivers()'s live ids.
            $table->string('request_id')->primary();
            // restrictOnDelete(), not cascade -- deleting a drive while a mapping still points
            // at it should fail loudly, never silently vanish the mapping.
            $table->foreignUuid('drive_id')->constrained(table: 'drives')->restrictOnDelete();
            $table->string('prefix')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_mappings');
    }
};
