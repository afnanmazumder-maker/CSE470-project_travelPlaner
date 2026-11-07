<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('origin', 3);
            $table->char('destination', 3);
            $table->date('depart_date')->nullable();
            $table->string('cabin_class')->default('economy');
            $table->decimal('target_price', 10, 2)->nullable();
            $table->decimal('last_seen_price', 10, 2)->nullable();
            $table->decimal('last_notified_price', 10, 2)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
