<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            // Same meaning as people.origin: null = local, otherwise the origin domain.
            // AP-protocol-specific facts (remote_id, federated_at) live in activitypub's own
            // activitypub_objects table instead -- see decisions.md, 2026-08-10.
            $table->string('origin')->nullable()->index()->after('person_id');
        });
    }

    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            // sqlite doesn't drop an indexed column's index for you -- must go first, or the
            // dangling index left behind makes the drop itself fail.
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};
