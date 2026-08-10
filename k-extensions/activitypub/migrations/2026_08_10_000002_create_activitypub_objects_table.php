<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic, one row per federated non-Person object (Moment, Reply, ...) -- covers
        // every model registered against Extend\Federation uniformly, so a third federatable
        // model needs no new table. moments/replies themselves carry only origin; every
        // AP-protocol-shaped fact (remote_id, federated_at) lives here instead -- see
        // decisions.md, 2026-08-10.
        Schema::create('activitypub_objects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('federatable');
            $table->string('remote_id')->nullable()->unique();
            $table->timestamp('federated_at')->nullable();
            $table->timestamps();

            $table->unique(['federatable_type', 'federatable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitypub_objects');
    }
};
