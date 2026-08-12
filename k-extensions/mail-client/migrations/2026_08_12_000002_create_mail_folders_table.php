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
            $table->timestamps();

            $table->unique(['mail_account_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_folders');
    }
};
