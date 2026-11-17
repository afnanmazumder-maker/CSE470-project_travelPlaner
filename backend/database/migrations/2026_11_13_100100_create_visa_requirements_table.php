<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R6: one row per passport country and destination country.
 *
 * Layer 1 rows come from the Passport Index dataset (source = dataset) and only fill
 * requirement and max_stay_days. Layer 2 rows are checked by hand against official
 * sites (source = official) or edited by an admin (source = admin), and fill the details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_requirements', function (Blueprint $table) {
            $table->id();
            $table->char('passport_code', 2);
            $table->char('destination_code', 2);
            $table->string('requirement', 20);
            $table->unsignedSmallInteger('max_stay_days')->nullable();
            $table->text('conditions')->nullable();
            $table->jsonb('required_documents')->nullable();
            $table->unsignedTinyInteger('passport_validity_months')->nullable();
            $table->string('fee_note')->nullable();
            $table->string('processing_note')->nullable();
            $table->text('transit_notes')->nullable();
            $table->string('official_url', 500)->nullable();
            $table->string('source', 20)->default('dataset');
            $table->date('last_verified_at')->nullable();
            $table->timestamps();

            $table->foreign('passport_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->foreign('destination_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->unique(['passport_code', 'destination_code']);
            $table->index('destination_code');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_requirements');
    }
};
