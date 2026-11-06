<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->uuid('client_id')->nullable();
            $table->string('tool', 120);
            $table->uuid('team_id')->nullable();
            $table->json('arguments')->nullable();
            $table->string('outcome', 16);
            $table->timestamp('created_at', 6)->nullable();

            $table->index('created_at');
            $table->index(['team_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
    }
};
