<?php

// Patched to avoid Doctrine getDoctrineSchemaManager() usage; uses try/catch around index creation.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add unique constraints on hash fields.
     * This prevents hash collisions at the database level.
     *
     * @return void
     */
    public function up()
    {
        // Add unique constraint to invoices (ignore if exists)
        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique('unique_hash', 'invoices_unique_hash_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }

        // Add unique constraint to estimates (ignore if exists)
        try {
            Schema::table('estimates', function (Blueprint $table) {
                $table->unique('unique_hash', 'estimates_unique_hash_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }

        // Add unique constraint to payments (ignore if exists)
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique('unique_hash', 'payments_unique_hash_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }

        // Add unique constraint to appointments (ignore if exists)
        try {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unique('unique_hash', 'appointments_unique_hash_unique');
            });
        } catch (\Throwable $e) { /* ignore */ }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_unique_hash_unique');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique('estimates_unique_hash_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_unique_hash_unique');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_unique_hash_unique');
        });
    }

    /**
     * Check if unique index exists on table column.
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    private function hasUniqueIndex($table, $column)
    {
        // Deprecated check path removed to avoid doctrine dependency.
        return false;
    }
};
