<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lives in `reactions`, not `tags` -- voting is entirely reactions' own concept
     * (the roadmap's own wording: "Upvotes, dual-purposed from `reactions`"), just stored on a
     * table `tags` owns. Any extension's migration may alter a table it doesn't own -- `tags`'
     * own `moment_tag` migration already alters the `moments`/`tags` relationship the same way.
     * `tags` itself declares nothing about voting anywhere in its own code; see
     * `ValidatesModels`/the `kopling-tags::admin.tag-form` slot for how these two columns get
     * validated and edited without `tags` ever knowing `reactions` exists.
     *
     * `Schema::hasTable('tags')` guards both directions -- `reactions` is soft-dependent on
     * `tags` everywhere else in its own runtime code (`class_exists` guards in
     * `Reaction::voteConfigFor`/`SortMomentsByVotes`), so its migration can't be the one place
     * that hard-fails when `tags` isn't installed alongside it. Same accepted limitation any
     * soft dependency in this codebase already has: installing `tags` *after* `reactions` won't
     * retroactively add these columns, since a skipped migration still marks itself run.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tags')) {
            return;
        }

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
        if (! Schema::hasTable('tags')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn(['upvote_emoji', 'downvote_emoji']);
        });
    }
};
