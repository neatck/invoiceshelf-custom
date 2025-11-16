<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Ensure column exists before adding unique index
            if (! Schema::hasColumn('appointments', 'unique_hash')) {
                $table->string('unique_hash', 50)->nullable()->after('creator_id');
            }
            $table->unique('unique_hash', 'appointments_unique_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_unique_hash_unique');
        });
    }
};
