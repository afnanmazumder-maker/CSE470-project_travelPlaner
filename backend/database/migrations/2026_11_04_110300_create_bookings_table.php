<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The trip this booking belongs to (an R7 budget), if any.
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->foreignId('flight_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accommodation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->string('confirmation_number', 40)->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            $table->string('status')->default('planned');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedSmallInteger('party_size')->default(1);
            $table->dateTime('cancellation_deadline')->nullable();
            $table->dateTime('payment_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
