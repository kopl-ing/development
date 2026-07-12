<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The disabled set, and only the disabled set: an installed extension is enabled
        // unless a row here names it -- absence means enabled, the same "store only the
        // exception" model group_permission uses (a grant is a row; no row is no grant).
        // `extension` is the plain Composer package name ("kopling/example"), not a foreign
        // key: extensions aren't database rows, they're discovered from installed.json, so
        // there's nothing to reference -- exactly like group_permission.permission is a plain
        // string, not an FK to a permissions table that doesn't exist.
        Schema::create('extension_states', function (Blueprint $table) {
            $table->string('extension')->primary();
            $table->timestamp('disabled_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_states');
    }
};
