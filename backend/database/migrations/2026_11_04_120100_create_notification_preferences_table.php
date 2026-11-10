<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('channels');
            $table->boolean('daily_digest')->default(true);
            $table->string('digest_time', 5)->default('07:00');
            $table->unsignedSmallInteger('checkin_lead_hours')->default(48);
            $table->unsignedSmallInteger('deadline_lead_days')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
