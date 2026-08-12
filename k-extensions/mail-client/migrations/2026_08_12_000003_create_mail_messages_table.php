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
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();
            $table->text('snippet')->nullable();
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
