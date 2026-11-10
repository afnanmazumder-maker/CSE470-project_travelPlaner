<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phrases', function (Blueprint $table) {
            $table->id();
            $table->string('language_code', 5);
            $table->string('language_name');
            $table->string('category');
            $table->string('english');
            $table->string('translation');
            $table->string('romanization')->nullable();
            $table->string('pronunciation');
            $table->timestamps();

            $table->index(['language_code', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrases');
    }
};
