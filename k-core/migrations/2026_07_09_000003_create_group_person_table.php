<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_person', function (Blueprint $table) {
            $table->foreignUuid('group_id')->constrained(table: 'groups')->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained(table: 'people')->cascadeOnDelete();
            $table->primary(['group_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_person');
    }
};
