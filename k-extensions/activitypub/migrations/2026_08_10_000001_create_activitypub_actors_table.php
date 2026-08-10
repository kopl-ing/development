<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-to-one with people -- a row existing here is what makes a Person an AP actor,
        // regardless of people.origin. Every AP-protocol-shaped fact lives here, never on
        // people itself -- see decisions.md, 2026-08-10.
        Schema::create('activitypub_actors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->unique()->constrained('people')->cascadeOnDelete();
            // A local Person's own chosen fediverse handle (set from their settings page, not
            // derived from people.name -- see decisions.md, 2026-08-10) -- the WebFinger
            // acct:{handle}@domain local-part. Null = this Person has never opted into
            // federation; irrelevant for a remote actor's own row (identified by remote_id
            // instead).
            $table->string('handle')->nullable()->unique();
            // Lets a Person who already set a handle pause federation without losing it --
            // independent of `handle` being null (opted in) vs set (opted out again).
            $table->boolean('federation_enabled')->default(true);
            // The actor's real AP URI -- arbitrary per remote server for a remote actor (e.g.
            // Mastodon's own https://mastodon.social/users/alice), this instance's own minted
            // /ap/people/{id} URI for a local one.
            $table->string('remote_id')->nullable()->unique();
            $table->string('inbox_url')->nullable();
            $table->string('outbox_url')->nullable();
            $table->string('shared_inbox_url')->nullable();
            $table->text('public_key')->nullable();
            // Only ever set for a local Person's own actor row.
            $table->text('private_key')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitypub_actors');
    }
};
