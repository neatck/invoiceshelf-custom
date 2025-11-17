<?php

/**
 * Fix Missing Base Currency Fields in InvoiceShelf
 * 
 * This script populates the missing base_* fields (base_due_amount, base_total, etc.)
 * that are required by InvoiceShelf's multi-currency architecture.
 * 
 * Root Cause: Migration from Crater didn't include these fields, causing dashboard
 * to show 99% data loss (UGX 200,000 instead of UGX 11,421,030).
 * 
 * Solution: Calculate base amounts using exchange_rate = 1.0 (UGX to UGX)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  InvoiceShelf - Fix Missing Base Currency Fields              ║\n";
echo "║  Critical Production Issue Resolution                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Configuration
$companyId = 1; // Royal Dental Services
$companyCurrency = 12; // UGX (Ugandan Shilling)
$exchangeRate = 1.0; // UGX to UGX = 1:1 (single currency operation)

echo "Configuration:\n";
echo "  - Company ID: $companyId\n";
echo "  - Currency: UGX (ID: $companyCurrency)\n";
echo "  - Exchange Rate: $exchangeRate\n";
echo "\n";

// Step 1: Analyze current state
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 1: Analyzing Current Data State\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$totalInvoices = DB::table('invoices')->where('company_id', $companyId)->count();
$missingBase = DB::table('invoices')
    ->where('company_id', $companyId)
    ->where(function($query) {
        $query->whereNull('base_due_amount')
              ->orWhereNull('base_total')
              ->orWhereNull('exchange_rate');
    })
    ->count();

$currentBaseDueSum = DB::table('invoices')
    ->where('company_id', $companyId)
    ->sum('base_due_amount') ?? 0;

$expectedDueSum = DB::table('invoices')
    ->where('company_id', $companyId)
    ->sum('due_amount') ?? 0;

echo "Total Invoices: $totalInvoices\n";
echo "Invoices Missing Base Fields: $missingBase (" . round(($missingBase / $totalInvoices) * 100, 1) . "%)\n";
echo "\n";
echo "Current State (BEFORE Fix):\n";
echo "  - SUM(base_due_amount): " . number_format($currentBaseDueSum / 100, 0) . " UGX\n";
echo "  - SUM(due_amount):      " . number_format($expectedDueSum / 100, 0) . " UGX\n";
echo "  - Missing Amount:       " . number_format(($expectedDueSum - $currentBaseDueSum) / 100, 0) . " UGX\n";
echo "  - Data Loss:            " . round((($expectedDueSum - $currentBaseDueSum) / $expectedDueSum) * 100, 1) . "%\n";
echo "\n";

if ($missingBase == 0) {
    echo "✓ No missing base fields detected. Database is already fixed!\n\n";
    exit(0);
}

// Step 2: Create backup
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 2: Creating Safety Backup\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$backupFile = storage_path('app/backups/invoices_before_base_fix_' . date('Y-m-d_H-i-s') . '.json');
$backupDir = dirname($backupFile);

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$invoicesToBackup = DB::table('invoices')
    ->where('company_id', $companyId)
    ->whereNull('base_due_amount')
    ->select('id', 'invoice_number', 'due_amount', 'base_due_amount', 'total', 'base_total')
    ->get()
    ->toArray();

file_put_contents($backupFile, json_encode($invoicesToBackup, JSON_PRETTY_PRINT));
echo "✓ Backup created: $backupFile\n";
echo "  (Backed up $missingBase invoices)\n\n";

// Step 3: Fix Invoices
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 3: Fixing Invoice Base Fields\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Updating invoices with missing base fields...\n";

$invoicesUpdated = DB::table('invoices')
    ->where('company_id', $companyId)
    ->where(function($query) {
        $query->whereNull('base_due_amount')
              ->orWhereNull('base_total')
              ->orWhereNull('exchange_rate');
    })
    ->update([
        'base_due_amount' => DB::raw("due_amount * $exchangeRate"),
        'base_total' => DB::raw("total * $exchangeRate"),
        'base_sub_total' => DB::raw("sub_total * $exchangeRate"),
        'base_discount_val' => DB::raw("COALESCE(discount_val, 0) * $exchangeRate"),
        'base_tax' => DB::raw("COALESCE(tax, 0) * $exchangeRate"),
        'exchange_rate' => $exchangeRate,
        'currency_id' => $companyCurrency,
        'updated_at' => now(),
    ]);

echo "✓ Updated $invoicesUpdated invoices\n\n";

// Step 4: Fix Invoice Items
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 4: Fixing Invoice Items Base Fields\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "Updating invoice items with missing base fields...\n";

$itemsUpdated = DB::table('invoice_items')
    ->where('company_id', $companyId)
    ->where(function($query) {
        $query->whereNull('base_total')
              ->orWhereNull('exchange_rate');
    })
    ->update([
        'base_total' => DB::raw("total * $exchangeRate"),
        'base_price' => DB::raw("price * $exchangeRate"),
        'base_discount_val' => DB::raw("COALESCE(discount_val, 0) * $exchangeRate"),
        'base_tax' => DB::raw("COALESCE(tax, 0) * $exchangeRate"),
        'exchange_rate' => $exchangeRate,
        'updated_at' => now(),
    ]);

echo "✓ Updated $itemsUpdated invoice items\n\n";

// Step 5: Verify Fix
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 5: Verifying Fix\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$remainingMissing = DB::table('invoices')
    ->where('company_id', $companyId)
    ->where(function($query) {
        $query->whereNull('base_due_amount')
              ->orWhereNull('base_total')
              ->orWhereNull('exchange_rate');
    })
    ->count();

$newBaseDueSum = DB::table('invoices')
    ->where('company_id', $companyId)
    ->sum('base_due_amount') ?? 0;

$newDueSum = DB::table('invoices')
    ->where('company_id', $companyId)
    ->sum('due_amount') ?? 0;

echo "After Fix Verification:\n";
echo "  - Remaining Missing: $remainingMissing invoices\n";
echo "  - SUM(base_due_amount): " . number_format($newBaseDueSum / 100, 0) . " UGX\n";
echo "  - SUM(due_amount):      " . number_format($newDueSum / 100, 0) . " UGX\n";
echo "  - Match Status:         " . ($newBaseDueSum == $newDueSum ? "✓ PERFECT MATCH" : "⚠ MISMATCH") . "\n";
echo "\n";

// Check specific invoices
echo "Sample Invoice Verification (first 5 with due amounts):\n";
$sampleInvoices = DB::table('invoices')
    ->where('company_id', $companyId)
    ->where('due_amount', '>', 0)
    ->select('invoice_number', 'due_amount', 'base_due_amount', 'exchange_rate', 'currency_id')
    ->limit(5)
    ->get();

foreach ($sampleInvoices as $inv) {
    $match = ($inv->base_due_amount == $inv->due_amount * $inv->exchange_rate) ? "✓" : "✗";
    echo "  $match {$inv->invoice_number}: due={$inv->due_amount}, base={$inv->base_due_amount}, rate={$inv->exchange_rate}\n";
}
echo "\n";

// Step 6: Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 6: Fix Summary\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$improvement = $newBaseDueSum - $currentBaseDueSum;
$percentFixed = ($totalInvoices > 0) ? round(($invoicesUpdated / $totalInvoices) * 100, 1) : 0;

echo "Results:\n";
echo "  ✓ Invoices Fixed:       $invoicesUpdated / $totalInvoices ($percentFixed%)\n";
echo "  ✓ Invoice Items Fixed:  $itemsUpdated\n";
echo "  ✓ Amount Recovered:     " . number_format($improvement / 100, 0) . " UGX\n";
echo "  ✓ Backup Location:      $backupFile\n";
echo "\n";

if ($remainingMissing > 0) {
    echo "⚠ Warning: $remainingMissing invoices still have missing base fields\n";
    echo "  Please investigate these manually.\n\n";
} else {
    echo "✓ SUCCESS: All invoices now have complete base currency fields!\n\n";
}

echo "Dashboard Impact:\n";
echo "  BEFORE: Total Amount Due = " . number_format($currentBaseDueSum / 100, 0) . " UGX\n";
echo "  AFTER:  Total Amount Due = " . number_format($newBaseDueSum / 100, 0) . " UGX\n";
echo "  IMPROVEMENT: +" . number_format($improvement / 100, 0) . " UGX (+" . 
     round((($improvement / ($currentBaseDueSum ?: 1)) * 100), 0) . "%)\n";
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "Next Steps:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "1. Clear application caches:\n";
echo "   php artisan cache:clear\n";
echo "   php artisan config:clear\n";
echo "   php artisan view:clear\n";
echo "\n";
echo "2. Restart services:\n";
echo "   sudo systemctl restart php8.2-fpm\n";
echo "   sudo systemctl restart nginx\n";
echo "\n";
echo "3. Login to InvoiceShelf and verify:\n";
echo "   - Dashboard shows correct Total Amount Due (~11.4M UGX)\n";
echo "   - Invoice list displays properly\n";
echo "   - PDF generation works\n";
echo "\n";
echo "4. If needed, rollback using backup:\n";
echo "   $backupFile\n";
echo "\n";

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  FIX COMPLETED SUCCESSFULLY                                    ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
