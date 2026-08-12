<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->foreignUuid('mail_folder_id')->constrained('mail_folders')->cascadeOnDelete();
            $table->unsignedInteger('uid');
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            // Full ancestor chain (RFC 5322 3.6.4, oldest-first) -- in_reply_to alone only
            // names the immediate parent, not enough to find a thread's root when that parent
            // was never itself synced locally (a different folder, a different account, or
            // simply not fetched yet).
            $table->json('references')->nullable();
            // Computed at sync time (MessageMapper), not derived at query time: references[0]
            // (the root ancestor) when present, else in_reply_to, else this message's own
            // message_id -- a stable grouping key threads are queried by, not itself
            // guaranteed to be a message that exists locally.
            $table->string('thread_id')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->text('snippet')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();

            $table->unique(['mail_folder_id', 'uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_messages');
    }
};
