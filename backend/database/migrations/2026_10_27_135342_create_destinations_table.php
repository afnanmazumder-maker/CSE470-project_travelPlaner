<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country');
            $table->string('region');
            $table->text('overview');
            $table->text('cuisine')->nullable();
            $table->string('budget_level');
            $table->unsignedTinyInteger('cost_index');
            $table->string('climate');
            $table->jsonb('best_months');
            $table->jsonb('themes');
            $table->unsignedTinyInteger('safety_rating');
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('image_url')->nullable();
            $table->timestamps();

            $table->unique(['name', 'country']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
