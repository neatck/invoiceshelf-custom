<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This adds a composite unique index on (email, company_id) to prevent
     * race conditions where two users might create customers with the same 
     * email simultaneously (both passing validation before either saves).
     */
    public function up(): void
    {
        // First, check for any existing duplicate email/company combinations
        // and handle them before adding the constraint
        $duplicates = DB::table('customers')
            ->select('email', 'company_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->groupBy('email', 'company_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            // Log duplicates for manual review
            foreach ($duplicates as $dup) {
                \Log::warning("Duplicate customer email found: {$dup->email} in company {$dup->company_id} ({$dup->count} occurrences)");
            }
        }

        // Add unique composite index (email + company_id)
        // This allows same email in different companies but not within same company
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['email', 'company_id'], 'customers_email_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_email_company_unique');
        });
    }
};
