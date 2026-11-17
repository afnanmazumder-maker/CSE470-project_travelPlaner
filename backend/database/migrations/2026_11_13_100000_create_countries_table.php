<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R6: reference list of countries (ISO 3166-1 alpha-2 code and English name).
 * Used by the visa checker; other features can reuse it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
