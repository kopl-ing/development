<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (reactable, person, emoji): a person may pick several different emoji on
        // the same reactable, but never the same one twice -- the unique key makes a toggle a
        // simple find-or-create/delete, no counter column to drift out of sync.
        Schema::create('reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Polymorphic pair so a Moment, a Reply, or any future reactable can carry
            // reactions through the same table. `reactable_type` stores the registered
            // morph-map alias ('moment'/'reply'), never a raw class name -- see
            // `Extension::models()`'s own `->morphAlias()` calls, enforced via
            // `Relation::enforceMorphMap()` (`Manager::models()`'s own side effect). Not
            // nullable: every write path (the toggle/vote/word routes, the demo seeder) always
            // goes through `$reactable->reactions()->create()`/`updateOrCreate()`, which always
            // sets both.
            $table->string('reactable_type');
            $table->uuid('reactable_id');
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            // Binary collation on MySQL/MariaDB: their default (utf8mb4_*_ci) collates distinct
            // emoji as EQUAL (👍 == 😂), which would collapse them in the unique key below and
            // make the toggle's `where('emoji', …)` match the wrong reaction -- reacting with a
            // second emoji would silently delete the first. SQLite already compares text
            // byte-exact and doesn't know this collation name, so pin it only on MySQL.
            $table->string('emoji')->collation(
                DB::connection()->getDriverName() === 'mysql' ? 'utf8mb4_bin' : null
            );
            // Optional short word that turns a plain emoji reaction into a "worded" one --
            // the demo's "Latest reactions" strip. Null for a plain rail toggle; a reaction
            // is the same row whether or not it carries a word (one per reactable+person+emoji).
            $table->string('word', 40)->nullable();
            $table->timestamps();

            $table->unique(['reactable_type', 'reactable_id', 'person_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
