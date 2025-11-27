<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds soft delete capability to all financial models.
     * This prevents permanent deletion of financial records,
     * which is essential for audit trails and compliance.
     */
    public function up(): void
    {
        // Add soft deletes to invoices
        if (!Schema::hasColumn('invoices', 'deleted_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to customers
        if (!Schema::hasColumn('customers', 'deleted_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to payments
        if (!Schema::hasColumn('payments', 'deleted_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to estimates
        if (!Schema::hasColumn('estimates', 'deleted_at')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to expenses
        if (!Schema::hasColumn('expenses', 'deleted_at')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to recurring_invoices
        if (!Schema::hasColumn('recurring_invoices', 'deleted_at')) {
            Schema::table('recurring_invoices', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
