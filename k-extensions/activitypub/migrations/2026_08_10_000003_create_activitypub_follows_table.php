<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This extension's own bookkeeping for the AP follow handshake -- a remote actor
        // following a local Person, so outbound delivery knows who to reach. Not a general
        // social graph; see .docs/planning/activitypub-federation-plan.md, "Scope for v1".
        Schema::create('activitypub_follows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('follower_uri');
            $table->foreignUuid('following_person_id')->constrained('people')->cascadeOnDelete();
            $table->string('state')->default('pending');
            $table->timestamps();

            $table->unique(['follower_uri', 'following_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitypub_follows');
    }
};
