<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The notifications, reminders and timeline feature was removed. Databases
     * that already ran its migrations still have these two tables, so drop them.
     */
    public function up(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('travel_notifications');
    }

    /**
     * Nothing to restore: the feature that used these tables is gone.
     */
    public function down(): void {}
};
