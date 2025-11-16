<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('customer_age')->nullable()->after('customer_id');
            $table->string('customer_next_of_kin')->nullable()->after('customer_age');
            $table->string('customer_next_of_kin_phone')->nullable()->after('customer_next_of_kin');
            $table->text('customer_diagnosis')->nullable()->after('customer_next_of_kin_phone');
            $table->text('customer_treatment')->nullable()->after('customer_diagnosis');
            $table->string('customer_attended_to_by')->nullable()->after('customer_treatment');
            $table->date('customer_review_date')->nullable()->after('customer_attended_to_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'customer_age',
                'customer_next_of_kin',
                'customer_next_of_kin_phone',
                'customer_diagnosis',
                'customer_treatment',
                'customer_attended_to_by',
                'customer_review_date'
            ]);
        });
    }
};
