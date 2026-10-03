<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropForeign(['issued_by']);
        });

        Schema::table('sanctions', function (Blueprint $table) {
            $table->uuid('issued_by')->nullable()->change();
            $table->foreign('issued_by')->references('id')->on('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropForeign(['issued_by']);
        });

        Schema::table('sanctions', function (Blueprint $table) {
            $table->uuid('issued_by')->nullable(false)->change();
            $table->foreign('issued_by')->references('id')->on('people')->cascadeOnDelete();
        });
    }
};
