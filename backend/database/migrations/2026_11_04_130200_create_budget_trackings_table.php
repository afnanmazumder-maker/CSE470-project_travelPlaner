<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R8 settings for one budget: the trip start date (to match expenses to
     * trip days), the alert threshold, and which alerts are currently raised.
     */
    public function up(): void
    {
        Schema::create('budget_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->unsignedSmallInteger('alert_threshold_pct')->default(10);
            $table->jsonb('alerted_categories')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_trackings');
    }
};
