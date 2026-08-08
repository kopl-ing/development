<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            // Null = local (this instance is canonical); otherwise the origin domain,
            // e.g. federated actor's home server. Paired with `id` (UUIDv7) this reconstructs
            // the federated URI per charter D6 -- never a separate remote-id column.
            $table->string('origin')->nullable()->index();
            // Three independent sanction axes, not one status enum -- see
            // .docs/planning/moderation-extension-plan.md, "The three axes, not a status enum".
            $table->timestamp('communication_blocked_at')->nullable();
            $table->string('visibility')->default('normal');
            $table->timestamp('access_blocked_at')->nullable();
            $table->timestamp('access_blocked_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
