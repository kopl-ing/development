<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Voting is configured per tag, not via a global reactions-extension setting -- whoever
        // creates/edits a tag decides whether it carries voting and which emoji represent up/down.
        // Both nullable: most tags never carry voting at all.
        Schema::table('tags', function (Blueprint $table) {
            $table->string('upvote_emoji')->nullable()->after('color');
            $table->string('downvote_emoji')->nullable()->after('upvote_emoji');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['upvote_emoji', 'downvote_emoji']);
        });
    }
};
