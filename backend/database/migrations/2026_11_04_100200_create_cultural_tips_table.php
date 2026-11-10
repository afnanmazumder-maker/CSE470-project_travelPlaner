<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultural_tips', function (Blueprint $table) {
            $table->id();
            $table->string('country');
            $table->string('language_code', 5);
            $table->string('topic');
            $table->string('title');
            $table->text('body');
            $table->timestamps();

            $table->index(['country', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultural_tips');
    }
};
