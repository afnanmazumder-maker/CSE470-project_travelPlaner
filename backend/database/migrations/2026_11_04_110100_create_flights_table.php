<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->string('airline');
            $table->string('airline_code', 3);
            $table->string('flight_number', 10);
            $table->char('origin', 3);
            $table->char('destination', 3);
            $table->dateTime('depart_at');
            $table->dateTime('arrive_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedTinyInteger('stops')->default(0);
            $table->string('stopover', 20)->nullable();
            $table->string('cabin_class');
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('seats_available')->default(9);
            $table->timestamps();

            $table->index(['origin', 'destination', 'cabin_class', 'depart_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
