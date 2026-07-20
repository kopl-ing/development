<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `moment_id` becomes a polymorphic `reactable_type`/`reactable_id` pair so a Reply (or any
     * future reactable) can carry reactions the same table already gives a Moment -- see
     * decisions.md for the full "why now, why this shape" writeup. `reactable_type` stores the
     * registered morph-map alias ('moment'/'reply'), never a raw class name -- see
     * `Extension::models()`'s own `->morphAlias()` calls, enforced via
     * `Relation::enforceMorphMap()` (`Manager::models()`'s own side effect). Existing rows
     * backfill as `reactable_type = 'moment'`, `reactable_id` copied straight from the column
     * being dropped.
     *
     * Left nullable at the DB level rather than a follow-up `->change()` to NOT NULL -- every
     * write path (the toggle/vote/word routes, the demo seeder) always goes through
     * `$reactable->reactions()->create()`/`updateOrCreate()`, which always sets both; enforcing
     * it again at the schema level would only guard against a write path that doesn't exist,
     * for the cost of a `->change()` migration step this project's SQLite-first dev/test setup
     * doesn't otherwise need.
     */
    public function up(): void
    {
        Schema::table('reactions', function (Blueprint $table) {
            $table->string('reactable_type')->nullable()->after('id');
            $table->uuid('reactable_id')->nullable()->after('reactable_type');
        });

        DB::table('reactions')->update(['reactable_type' => 'moment']);
        DB::statement('UPDATE reactions SET reactable_id = moment_id');

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique(['moment_id', 'person_id', 'emoji']);
            $table->dropForeign(['moment_id']);
            $table->dropColumn('moment_id');
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->unique(['reactable_type', 'reactable_id', 'person_id', 'emoji']);
        });
    }

    public function down(): void
    {
        // Any reaction on a non-Moment reactable (a Reply's, once that exists) has no `moment_id`
        // to roll back to -- dropped rather than left dangling with a null foreign key, the same
        // lossy-but-honest trade-off any "add a new dimension, then undo it" rollback makes.
        DB::table('reactions')->whereNotIn('reactable_type', ['moment'])->delete();

        Schema::table('reactions', function (Blueprint $table) {
            $table->foreignUuid('moment_id')->nullable()->after('id')->constrained(table: 'moments')->cascadeOnDelete();
        });

        DB::statement('UPDATE reactions SET moment_id = reactable_id');

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique(['reactable_type', 'reactable_id', 'person_id', 'emoji']);
            $table->dropColumn(['reactable_type', 'reactable_id']);
            $table->unique(['moment_id', 'person_id', 'emoji']);
        });
    }
};
