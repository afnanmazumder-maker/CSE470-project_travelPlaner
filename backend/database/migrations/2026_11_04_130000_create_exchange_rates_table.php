<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frankfurter rates are stored against EUR, the base it publishes; cross rates are derived.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base', 3);
            $table->char('quote', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source')->default('frankfurter');
            $table->timestamps();

            $table->unique(['base', 'quote', 'rate_date']);
            $table->index(['quote', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
