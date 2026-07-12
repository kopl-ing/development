<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Many-to-many: a moment can carry several tags, a tag spans many moments. Composite
        // primary key keeps a (moment, tag) pairing unique with no surrogate id to manage.
        Schema::create('moment_tag', function (Blueprint $table) {
            $table->foreignUuid('moment_id')->constrained(table: 'moments')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained(table: 'tags')->cascadeOnDelete();

            $table->primary(['moment_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moment_tag');
    }
};
