<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('property_type');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('rating', 2, 1);
            $table->unsignedInteger('review_count')->default(0);
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->string('address');
            $table->jsonb('amenities');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['destination_id', 'price_per_night']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodations');
    }
};
