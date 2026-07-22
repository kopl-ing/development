<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('driver');
            // Driver-specific config bag (e.g. `root` for local, `bucket`/`key`/`secret` for
            // s3). A string value prefixed `env:NAME` resolves via env() at read time only --
            // see Resolver -- so a secret never has to round-trip through this column.
            $table->json('settings');
            // Plain booleans, not folded into `settings` -- a small, fixed set mirrored from
            // StorageAccess/StorageRetention/StoragePermission, kept queryable across every
            // supported DB engine rather than JSON-querying support that isn't uniform across
            // MySQL/MariaDB/Postgres/SQLite.
            $table->boolean('supports_public')->default(false);
            $table->boolean('supports_signed')->default(false);
            $table->boolean('writable')->default(true);
            // Disable without deleting -- a storage_mappings row pointing at a disabled drive
            // becomes visibly stale instead of orphaned by a cascade delete.
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drives');
    }
};
