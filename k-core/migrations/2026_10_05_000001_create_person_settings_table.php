<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_settings', function (Blueprint $table) {
            $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('key');
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->primary(['person_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_settings');
    }
};
