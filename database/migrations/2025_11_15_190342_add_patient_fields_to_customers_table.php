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
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('age')->nullable()->after('company_id');
            $table->string('next_of_kin')->nullable()->after('age');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin');
            $table->text('diagnosis')->nullable()->after('next_of_kin_phone');
            $table->text('treatment')->nullable()->after('diagnosis');
            $table->string('attended_to_by')->nullable()->after('treatment');
            $table->date('review_date')->nullable()->after('attended_to_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'age',
                'next_of_kin',
                'next_of_kin_phone',
                'diagnosis',
                'treatment',
                'attended_to_by',
                'review_date'
            ]);
        });
    }
};
