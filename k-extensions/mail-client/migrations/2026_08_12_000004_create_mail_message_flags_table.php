<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_message_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mail_message_id')->unique()->constrained('mail_messages')->cascadeOnDelete();
            $table->boolean('seen')->default(false);
            $table->boolean('flagged')->default(false);
            $table->boolean('answered')->default(false);
            $table->boolean('draft')->default(false);
            $table->boolean('deleted')->default(false);
            $table->boolean('dirty')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_message_flags');
    }
};
