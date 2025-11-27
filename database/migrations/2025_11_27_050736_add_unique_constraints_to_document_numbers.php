<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds unique constraints on document numbers scoped by company_id
     * to prevent duplicate invoice/payment/estimate numbers within a company.
     * This ensures database-level integrity even if application-level checks fail.
     */
    public function up(): void
    {
        // Add unique constraint to invoices (company_id + invoice_number)
        try {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(['company_id', 'invoice_number'], 'invoices_company_invoice_number_unique');
            });
        } catch (\Throwable $e) {
            // Index may already exist, ignore
        }

        // Add unique constraint to payments (company_id + payment_number)
        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['company_id', 'payment_number'], 'payments_company_payment_number_unique');
            });
        } catch (\Throwable $e) {
            // Index may already exist, ignore
        }

        // Add unique constraint to estimates (company_id + estimate_number)
        try {
            Schema::table('estimates', function (Blueprint $table) {
                $table->unique(['company_id', 'estimate_number'], 'estimates_company_estimate_number_unique');
            });
        } catch (\Throwable $e) {
            // Index may already exist, ignore
        }

        // Add unique constraint to expenses (company_id + expense_number) if column exists
        try {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'expense_number')) {
                    $table->unique(['company_id', 'expense_number'], 'expenses_company_expense_number_unique');
                }
            });
        } catch (\Throwable $e) {
            // Index may already exist or column doesn't exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_company_invoice_number_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_company_payment_number_unique');
        });

        Schema::table('estimates', function (Blueprint $table) {
            $table->dropUnique('estimates_company_estimate_number_unique');
        });

        try {
            Schema::table('expenses', function (Blueprint $table) {
                if (Schema::hasColumn('expenses', 'expense_number')) {
                    $table->dropUnique('expenses_company_expense_number_unique');
                }
            });
        } catch (\Throwable $e) {
            // Ignore if doesn't exist
        }
    }
};
