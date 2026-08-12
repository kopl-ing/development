<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('email_address');
            $table->string('incoming_host');
            $table->unsignedSmallInteger('incoming_port');
            $table->string('incoming_encryption');
            $table->string('outgoing_host');
            $table->unsignedSmallInteger('outgoing_port');
            $table->string('outgoing_encryption');
            $table->string('auth_type');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('oauth_provider')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            // Set when a sync job starts, cleared when it ends (success or failure) -- lets the
            // cron-fallback command (Console/SyncPendingCommand) skip an account whose sync might
            // still be genuinely in flight on a real queue worker, without a separate lock/cache
            // mechanism. A stuck value (a worker that died mid-job) self-heals once it's old
            // enough to no longer look "in flight" -- see that command's own recency window.
            $table->timestamp('syncing_since')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
