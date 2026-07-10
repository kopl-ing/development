<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No separate "permissions" table: a permission's id/label/description/callback is
        // defined in code (core or an extension's HasPermissions), computed fresh on every
        // request via Manager::permissions() -- never synced into the database as its own
        // row. Only the grant itself (which group has which permission string) persists.
        Schema::create('group_permission', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained(table: 'groups')->cascadeOnDelete();
            $table->string('permission');
            $table->primary(['group_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_permission');
    }
};
