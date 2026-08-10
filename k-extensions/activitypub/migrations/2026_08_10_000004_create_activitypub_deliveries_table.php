<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A persisted record of every outbound delivery attempt -- not just a queued job's own
        // in-memory payload. `QUEUE_CONNECTION=sync` by default (see the federation plan's
        // "Hosting posture") means a failed delivery on a host with no real worker would
        // otherwise vanish the moment the request that triggered it ends; this row is what
        // `federation:deliver-pending` finds and retries on a host that only has cron.
        Schema::create('activitypub_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('inbox_url');
            $table->text('activity');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['delivered_at', 'attempts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activitypub_deliveries');
    }
};
