<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * R8: `amount` stays the USD amount the R7 summary adds up; these columns
     * keep what was actually paid and the rate used to convert it.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->char('currency', 3)->default('USD')->after('amount');
            $table->decimal('original_amount', 14, 2)->nullable()->after('currency');
            $table->decimal('rate_used', 18, 8)->default(1)->after('original_amount');
            $table->date('rate_date')->nullable()->after('rate_used');
            $table->string('rate_source')->default('identity')->after('rate_date');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['currency', 'original_amount', 'rate_used', 'rate_date', 'rate_source']);
        });
    }
};
