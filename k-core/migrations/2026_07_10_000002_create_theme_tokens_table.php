<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad-hoc, per-token admin overrides layered on top of whatever a ChangesTheme
        // extension declares (see Kopling\Core\Ux\Theme) -- a missing row means "use whatever
        // the active theme (or, failing that, the compiled default) already says for this
        // token." No admin editor writes to this yet; the table exists so Theme::css() has
        // somewhere real to read the highest-priority layer from once one does.
        Schema::create('theme_tokens', function (Blueprint $table) {
            $table->string('token')->primary();
            $table->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_tokens');
    }
};
