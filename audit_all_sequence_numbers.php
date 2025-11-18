<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo str_repeat("=", 80) . "\n";
echo "COMPREHENSIVE SEQUENCE NUMBER AUDIT\n";
echo str_repeat("=", 80) . "\n\n";

echo "Checking all models that use sequence_number for NULL values...\n\n";

$results = [];

// Check Invoices
echo "[1/4] Checking Invoices...\n";
$invoice_stats = DB::table('invoices')
    ->where('company_id', 1)
    ->select(
        DB::raw('COUNT(*) as total'),
        DB::raw('COUNT(sequence_number) as with_sequence'),
        DB::raw('COUNT(*) - COUNT(sequence_number) as null_sequences')
    )
    ->first();

$results['invoices'] = $invoice_stats;
printf("  Total: %d | With Sequence: %d | NULL: %d %s\n", 
    $invoice_stats->total, 
    $invoice_stats->with_sequence, 
    $invoice_stats->null_sequences,
    $invoice_stats->null_sequences > 0 ? "⚠️" : "✅"
);

if ($invoice_stats->null_sequences > 0) {
    $null_invoices = DB::table('invoices')
        ->where('company_id', 1)
        ->whereNull('sequence_number')
        ->select('id', 'invoice_number', 'created_at')
        ->limit(5)
        ->get();
    
    echo "  Sample NULL invoices:\n";
    foreach ($null_invoices as $inv) {
        echo "    - ID: {$inv->id}, Number: {$inv->invoice_number}, Created: {$inv->created_at}\n";
    }
}

// Check Payments
echo "\n[2/4] Checking Payments...\n";
$payment_stats = DB::table('payments')
    ->where('company_id', 1)
    ->select(
        DB::raw('COUNT(*) as total'),
        DB::raw('COUNT(sequence_number) as with_sequence'),
        DB::raw('COUNT(*) - COUNT(sequence_number) as null_sequences')
    )
    ->first();

$results['payments'] = $payment_stats;
printf("  Total: %d | With Sequence: %d | NULL: %d %s\n", 
    $payment_stats->total, 
    $payment_stats->with_sequence, 
    $payment_stats->null_sequences,
    $payment_stats->null_sequences > 0 ? "⚠️" : "✅"
);

if ($payment_stats->null_sequences > 0) {
    $null_payments = DB::table('payments')
        ->where('company_id', 1)
        ->whereNull('sequence_number')
        ->select('id', 'payment_number', 'created_at')
        ->limit(5)
        ->get();
    
    echo "  Sample NULL payments:\n";
    foreach ($null_payments as $pay) {
        echo "    - ID: {$pay->id}, Number: {$pay->payment_number}, Created: {$pay->created_at}\n";
    }
}

// Check Estimates
echo "\n[3/4] Checking Estimates...\n";
$estimate_stats = DB::table('estimates')
    ->where('company_id', 1)
    ->select(
        DB::raw('COUNT(*) as total'),
        DB::raw('COUNT(sequence_number) as with_sequence'),
        DB::raw('COUNT(*) - COUNT(sequence_number) as null_sequences')
    )
    ->first();

$results['estimates'] = $estimate_stats;
printf("  Total: %d | With Sequence: %d | NULL: %d %s\n", 
    $estimate_stats->total, 
    $estimate_stats->with_sequence, 
    $estimate_stats->null_sequences,
    $estimate_stats->null_sequences > 0 ? "⚠️" : "✅"
);

if ($estimate_stats->null_sequences > 0) {
    $null_estimates = DB::table('estimates')
        ->where('company_id', 1)
        ->whereNull('sequence_number')
        ->select('id', 'estimate_number', 'created_at')
        ->limit(5)
        ->get();
    
    echo "  Sample NULL estimates:\n";
    foreach ($null_estimates as $est) {
        echo "    - ID: {$est->id}, Number: {$est->estimate_number}, Created: {$est->created_at}\n";
    }
}

// Check Recurring Invoices (they don't have sequence_number column)
echo "\n[4/4] Checking Recurring Invoices...\n";
$recurring_count = DB::table('recurring_invoices')
    ->where('company_id', 1)
    ->count();

printf("  Total: %d (Note: Recurring invoices don't use sequence_number)\n", $recurring_count);
echo "  ✅ No sequence_number column - uses generated invoices' sequences\n";

// Summary
echo "\n" . str_repeat("=", 80) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 80) . "\n\n";

$total_issues = 
    $results['invoices']->null_sequences + 
    $results['payments']->null_sequences + 
    $results['estimates']->null_sequences;

if ($total_issues == 0) {
    echo "✅ ALL GOOD! No NULL sequence numbers found.\n";
    echo "✅ All invoices, payments, and estimates have proper sequence tracking.\n";
} else {
    echo "⚠️  ISSUES FOUND: $total_issues records with NULL sequence_number\n\n";
    
    echo "Breakdown:\n";
    if ($results['invoices']->null_sequences > 0) {
        echo "  - Invoices: {$results['invoices']->null_sequences} NULL\n";
    }
    if ($results['payments']->null_sequences > 0) {
        echo "  - Payments: {$results['payments']->null_sequences} NULL\n";
    }
    if ($results['estimates']->null_sequences > 0) {
        echo "  - Estimates: {$results['estimates']->null_sequences} NULL\n";
    }
    
    echo "\nRecommendation: Run fix_sequence_from_numbers.php to repair all NULL sequences.\n";
}

echo "\n" . str_repeat("=", 80) . "\n";

// Test next numbers
echo "NEXT NUMBER PREVIEW\n";
echo str_repeat("=", 80) . "\n\n";

$models = ['invoices', 'payments', 'estimates'];
foreach ($models as $model) {
    $last = DB::table($model)
        ->where('company_id', 1)
        ->whereNotNull('sequence_number')
        ->orderBy('sequence_number', 'desc')
        ->first();
    
    if ($last) {
        $next_seq = $last->sequence_number + 1;
        $model_upper = strtoupper($model);
        $prefix = substr($model_upper, 0, 3);
        $next_number = "$prefix-" . str_pad($next_seq, 6, '0', STR_PAD_LEFT);
        echo sprintf("%-12s Last: %-6d → Next: %s (seq: %d)\n", 
            ucfirst($model) . ':', 
            $last->sequence_number, 
            $next_number, 
            $next_seq
        );
    } else {
        echo sprintf("%-12s No records yet\n", ucfirst($model) . ':');
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
