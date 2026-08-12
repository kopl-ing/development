<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('type')->nullable();
            // Both read from a cheap IMAP STATUS call (no message fetch) during folder discovery.
            // uidvalidity changing between sync runs means the server invalidated this folder's
            // UID numbering -- signals a full re-sync of this folder's messages, not incremental.
            // message_count is the progress-bar denominator ("X of Y"), read once per discovery
            // pass rather than computed by counting local rows (which would just show sync
            // progress against itself).
            $table->unsignedInteger('uidvalidity')->nullable();
            $table->unsignedInteger('message_count')->nullable();
            $table->timestamps();

            $table->unique(['mail_account_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_folders');
    }
};
