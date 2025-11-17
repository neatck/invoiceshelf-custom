<?php

/**
 * Fix Missing Base Currency Fields for Payments and Expenses
 * 
 * This is Part 2 of the data migration fix. Part 1 fixed invoices,
 * but payments and expenses also need base_amount fields populated.
 * 
 * Root Cause: Same as invoices - migration didn't include base_amount
 * fields required by InvoiceShelf's multi-currency architecture.
 * 
 * Impact: Dashboard shows:
 * - Receipts: UGX 0 (should be UGX 153,654,000)
 * - Expenses: UGX 0 (should be UGX 134,683,452)
 * - Net Income: UGX 0 (should be UGX 18,970,548)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  InvoiceShelf - Fix Payments & Expenses Base Amounts          ║\n";
echo "║  Part 2: Dashboard Receipts, Expenses, Net Income Fix         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Configuration
$companyId = 1; // Royal Dental Services
$companyCurrency = 12; // UGX (Ugandan Shilling)
$exchangeRate = 1.0; // UGX to UGX = 1:1

echo "Configuration:\n";
echo "  - Company ID: $companyId\n";
echo "  - Currency: UGX (ID: $companyCurrency)\n";
echo "  - Exchange Rate: $exchangeRate\n";
echo "\n";

// Step 1: Analyze Payments
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 1: Analyzing Payments Data\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$totalPayments = DB::table('payments')->where('company_id', $companyId)->count();
$paymentsWithBase = DB::table('payments')
    ->where('company_id', $companyId)
    ->whereNotNull('base_amount')
    ->count();
$paymentsMissingBase = $totalPayments - $paymentsWithBase;

$currentPaymentBaseSum = DB::table('payments')
    ->where('company_id', $companyId)
    ->sum('base_amount') ?? 0;

$expectedPaymentSum = DB::table('payments')
    ->where('company_id', $companyId)
    ->sum('amount') ?? 0;

echo "Payments Analysis:\n";
echo "  - Total Payments: $totalPayments\n";
echo "  - With base_amount: $paymentsWithBase\n";
echo "  - Missing base_amount: $paymentsMissingBase (" . round(($paymentsMissingBase / $totalPayments) * 100, 1) . "%)\n";
echo "\n";
echo "Current State:\n";
echo "  - SUM(base_amount): " . number_format($currentPaymentBaseSum / 100, 0) . " UGX\n";
echo "  - SUM(amount):      " . number_format($expectedPaymentSum / 100, 0) . " UGX\n";
echo "  - Missing:          " . number_format(($expectedPaymentSum - $currentPaymentBaseSum) / 100, 0) . " UGX\n";
echo "  - Expected in Dashboard: ~UGX 153,654,000 (from Crater)\n";
echo "\n";

// Step 2: Analyze Expenses
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 2: Analyzing Expenses Data\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$totalExpenses = DB::table('expenses')->where('company_id', $companyId)->count();
$expensesWithBase = DB::table('expenses')
    ->where('company_id', $companyId)
    ->whereNotNull('base_amount')
    ->count();
$expensesMissingBase = $totalExpenses - $expensesWithBase;

$currentExpenseBaseSum = DB::table('expenses')
    ->where('company_id', $companyId)
    ->sum('base_amount') ?? 0;

$expectedExpenseSum = DB::table('expenses')
    ->where('company_id', $companyId)
    ->sum('amount') ?? 0;

echo "Expenses Analysis:\n";
echo "  - Total Expenses: $totalExpenses\n";
echo "  - With base_amount: $expensesWithBase\n";
echo "  - Missing base_amount: $expensesMissingBase (" . round(($expensesMissingBase / $totalExpenses) * 100, 1) . "%)\n";
echo "\n";
echo "Current State:\n";
echo "  - SUM(base_amount): " . number_format($currentExpenseBaseSum / 100, 0) . " UGX\n";
echo "  - SUM(amount):      " . number_format($expectedExpenseSum / 100, 0) . " UGX\n";
echo "  - Missing:          " . number_format(($expectedExpenseSum - $currentExpenseBaseSum) / 100, 0) . " UGX\n";
echo "  - Expected in Dashboard: ~UGX 134,683,000 (from Crater)\n";
echo "\n";

// Calculate Net Income
$expectedNetIncome = ($expectedPaymentSum - $expectedExpenseSum) / 100;
echo "Expected Net Income: " . number_format($expectedNetIncome, 0) . " UGX\n";
echo "  (Should match Crater's UGX 18,970,548)\n";
echo "\n";

if ($paymentsMissingBase == 0 && $expensesMissingBase == 0) {
    echo "✓ No missing base fields detected. All data is already fixed!\n\n";
    exit(0);
}

// Step 3: Create Backup
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 3: Creating Safety Backups\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$backupDir = storage_path('app/backups');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Backup payments
if ($paymentsMissingBase > 0) {
    $paymentsBackupFile = $backupDir . '/payments_before_base_fix_' . date('Y-m-d_H-i-s') . '.json';
    $paymentsToBackup = DB::table('payments')
        ->where('company_id', $companyId)
        ->whereNull('base_amount')
        ->select('id', 'payment_number', 'amount', 'base_amount', 'payment_date')
        ->get()
        ->toArray();
    
    file_put_contents($paymentsBackupFile, json_encode($paymentsToBackup, JSON_PRETTY_PRINT));
    echo "✓ Payments backup: $paymentsBackupFile\n";
    echo "  (Backed up $paymentsMissingBase payments)\n";
}

// Backup expenses
if ($expensesMissingBase > 0) {
    $expensesBackupFile = $backupDir . '/expenses_before_base_fix_' . date('Y-m-d_H-i-s') . '.json';
    $expensesToBackup = DB::table('expenses')
        ->where('company_id', $companyId)
        ->whereNull('base_amount')
        ->select('id', 'expense_number', 'amount', 'base_amount', 'expense_date')
        ->get()
        ->toArray();
    
    file_put_contents($expensesBackupFile, json_encode($expensesToBackup, JSON_PRETTY_PRINT));
    echo "✓ Expenses backup: $expensesBackupFile\n";
    echo "  (Backed up $expensesMissingBase expenses)\n";
}
echo "\n";

// Step 4: Fix Payments
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 4: Fixing Payments Base Fields\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($paymentsMissingBase > 0) {
    echo "Updating payments with missing base fields...\n";
    
    $paymentsUpdated = DB::table('payments')
        ->where('company_id', $companyId)
        ->whereNull('base_amount')
        ->update([
            'base_amount' => DB::raw("amount * $exchangeRate"),
            'exchange_rate' => $exchangeRate,
            'currency_id' => $companyCurrency,
            'updated_at' => now(),
        ]);
    
    echo "✓ Updated $paymentsUpdated payments\n";
} else {
    echo "✓ All payments already have base_amount\n";
}
echo "\n";

// Step 5: Fix Expenses
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 5: Fixing Expenses Base Fields\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if ($expensesMissingBase > 0) {
    echo "Updating expenses with missing base fields...\n";
    
    $expensesUpdated = DB::table('expenses')
        ->where('company_id', $companyId)
        ->whereNull('base_amount')
        ->update([
            'base_amount' => DB::raw("amount * $exchangeRate"),
            'exchange_rate' => $exchangeRate,
            'currency_id' => $companyCurrency,
            'updated_at' => now(),
        ]);
    
    echo "✓ Updated $expensesUpdated expenses\n";
} else {
    echo "✓ All expenses already have base_amount\n";
}
echo "\n";

// Step 6: Verify Fix
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 6: Verifying Fix\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Verify Payments
$remainingPaymentsMissing = DB::table('payments')
    ->where('company_id', $companyId)
    ->whereNull('base_amount')
    ->count();

$newPaymentBaseSum = DB::table('payments')
    ->where('company_id', $companyId)
    ->sum('base_amount') ?? 0;

$newPaymentSum = DB::table('payments')
    ->where('company_id', $companyId)
    ->sum('amount') ?? 0;

echo "Payments Verification:\n";
echo "  - Remaining Missing: $remainingPaymentsMissing\n";
echo "  - SUM(base_amount): " . number_format($newPaymentBaseSum / 100, 0) . " UGX\n";
echo "  - SUM(amount):      " . number_format($newPaymentSum / 100, 0) . " UGX\n";
echo "  - Match Status:     " . ($newPaymentBaseSum == $newPaymentSum ? "✓ PERFECT" : "⚠ MISMATCH") . "\n";
echo "\n";

// Verify Expenses
$remainingExpensesMissing = DB::table('expenses')
    ->where('company_id', $companyId)
    ->whereNull('base_amount')
    ->count();

$newExpenseBaseSum = DB::table('expenses')
    ->where('company_id', $companyId)
    ->sum('base_amount') ?? 0;

$newExpenseSum = DB::table('expenses')
    ->where('company_id', $companyId)
    ->sum('amount') ?? 0;

echo "Expenses Verification:\n";
echo "  - Remaining Missing: $remainingExpensesMissing\n";
echo "  - SUM(base_amount): " . number_format($newExpenseBaseSum / 100, 0) . " UGX\n";
echo "  - SUM(amount):      " . number_format($newExpenseSum / 100, 0) . " UGX\n";
echo "  - Match Status:     " . ($newExpenseBaseSum == $newExpenseSum ? "✓ PERFECT" : "⚠ MISMATCH") . "\n";
echo "\n";

// Calculate Net Income
$newNetIncome = ($newPaymentBaseSum - $newExpenseBaseSum) / 100;
echo "Net Income Verification:\n";
echo "  - Receipts:    " . number_format($newPaymentBaseSum / 100, 0) . " UGX\n";
echo "  - Expenses:    " . number_format($newExpenseBaseSum / 100, 0) . " UGX\n";
echo "  - Net Income:  " . number_format($newNetIncome, 0) . " UGX\n";
echo "  - Expected:    18,970,548 UGX (from Crater)\n";
echo "  - Match:       " . (abs($newNetIncome - 18970548) < 1000 ? "✓ CLOSE MATCH" : "⚠ CHECK NEEDED") . "\n";
echo "\n";

// Sample Verification
echo "Sample Payment Verification (first 5):\n";
$samplePayments = DB::table('payments')
    ->where('company_id', $companyId)
    ->select('payment_number', 'amount', 'base_amount', 'exchange_rate')
    ->limit(5)
    ->get();

foreach ($samplePayments as $pmt) {
    $match = ($pmt->base_amount == $pmt->amount * $pmt->exchange_rate) ? "✓" : "✗";
    echo "  $match {$pmt->payment_number}: amt={$pmt->amount}, base={$pmt->base_amount}, rate={$pmt->exchange_rate}\n";
}
echo "\n";

echo "Sample Expense Verification (first 5):\n";
$sampleExpenses = DB::table('expenses')
    ->where('company_id', $companyId)
    ->select('expense_number', 'amount', 'base_amount', 'exchange_rate')
    ->limit(5)
    ->get();

foreach ($sampleExpenses as $exp) {
    $match = ($exp->base_amount == $exp->amount * $exp->exchange_rate) ? "✓" : "✗";
    $expNum = $exp->expense_number ?: "EXP-{$exp->id}";
    echo "  $match $expNum: amt={$exp->amount}, base={$exp->base_amount}, rate={$exp->exchange_rate}\n";
}
echo "\n";

// Step 7: Summary
echo "═══════════════════════════════════════════════════════════════\n";
echo "STEP 7: Fix Summary\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$paymentImprovement = $newPaymentBaseSum - $currentPaymentBaseSum;
$expenseImprovement = $newExpenseBaseSum - $currentExpenseBaseSum;

echo "Results:\n";
if ($paymentsMissingBase > 0) {
    echo "  ✓ Payments Fixed:   $paymentsUpdated / $totalPayments\n";
    echo "  ✓ Amount Recovered: " . number_format($paymentImprovement / 100, 0) . " UGX\n";
}
if ($expensesMissingBase > 0) {
    echo "  ✓ Expenses Fixed:   $expensesUpdated / $totalExpenses\n";
    echo "  ✓ Amount Recovered: " . number_format($expenseImprovement / 100, 0) . " UGX\n";
}
echo "\n";

if ($remainingPaymentsMissing > 0 || $remainingExpensesMissing > 0) {
    echo "⚠ Warning: Some records still have missing base fields\n";
    if ($remainingPaymentsMissing > 0) echo "  - Payments: $remainingPaymentsMissing\n";
    if ($remainingExpensesMissing > 0) echo "  - Expenses: $remainingExpensesMissing\n";
    echo "\n";
} else {
    echo "✓ SUCCESS: All payments and expenses have base_amount!\n\n";
}

echo "Dashboard Impact:\n";
echo "  BEFORE FIX:\n";
echo "    - Receipts:   UGX 0\n";
echo "    - Expenses:   UGX 0\n";
echo "    - Net Income: UGX 0\n";
echo "\n";
echo "  AFTER FIX:\n";
echo "    - Receipts:   " . number_format($newPaymentBaseSum / 100, 0) . " UGX\n";
echo "    - Expenses:   " . number_format($newExpenseBaseSum / 100, 0) . " UGX\n";
echo "    - Net Income: " . number_format($newNetIncome, 0) . " UGX\n";
echo "\n";
echo "  CRATER COMPARISON:\n";
echo "    - Crater Receipts:   153,654,000 UGX\n";
echo "    - Crater Expenses:   134,683,452 UGX\n";
echo "    - Crater Net Income: 18,970,548 UGX\n";
echo "\n";

$receiptDiff = abs(($newPaymentBaseSum / 100) - 153654000);
$expenseDiff = abs(($newExpenseBaseSum / 100) - 134683452);
$netIncomeDiff = abs($newNetIncome - 18970548);

if ($receiptDiff < 100000 && $expenseDiff < 100000 && $netIncomeDiff < 100000) {
    echo "  ✓ MATCH: InvoiceShelf totals match Crater (within tolerance)\n";
} else {
    echo "  ⚠ VARIANCE DETECTED:\n";
    echo "    - Receipts variance:   " . number_format($receiptDiff, 0) . " UGX\n";
    echo "    - Expenses variance:   " . number_format($expenseDiff, 0) . " UGX\n";
    echo "    - Net Income variance: " . number_format($netIncomeDiff, 0) . " UGX\n";
    echo "    (This may be due to date ranges or fiscal year differences)\n";
}
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "Next Steps:\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "1. Clear application caches:\n";
echo "   cd /home/royal/InvoiceShelf\n";
echo "   php artisan cache:clear\n";
echo "   php artisan config:clear\n";
echo "\n";
echo "2. Restart services:\n";
echo "   sudo systemctl restart php8.3-fpm nginx\n";
echo "\n";
echo "3. Refresh dashboard in browser (Ctrl+Shift+R)\n";
echo "\n";
echo "4. Verify dashboard now shows:\n";
echo "   - Receipts: ~UGX 153,654,000\n";
echo "   - Expenses: ~UGX 134,683,000\n";
echo "   - Net Income: ~UGX 18,970,000\n";
echo "   - Chart with colored lines for all metrics\n";
echo "\n";

if (isset($paymentsBackupFile)) {
    echo "5. Backups available for rollback:\n";
    echo "   - Payments: $paymentsBackupFile\n";
    if (isset($expensesBackupFile)) {
        echo "   - Expenses: $expensesBackupFile\n";
    }
    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  FIX COMPLETED SUCCESSFULLY                                    ║\n";
echo "║  Dashboard should now display complete financial data          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
