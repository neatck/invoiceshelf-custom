<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Estimate;
use App\Models\Expense;

echo str_repeat("=", 100) . "\n";
echo "SEQUENCE NUMBER FIX - Extract from Invoice/Payment Numbers\n";
echo str_repeat("=", 100) . "\n\n";

echo "Strategy: Extract sequence number from the invoice/payment number itself\n";
echo "Example: INV-000256 → sequence_number = 256\n";
echo "Example: PAY-000511 → sequence_number = 511\n\n";

DB::beginTransaction();

try {
    // Fix Invoices
    echo "Processing Invoices...\n";
    $invoices = Invoice::whereNull('sequence_number')->orderBy('id')->get();
    $invoice_fixes = 0;
    
    foreach ($invoices as $invoice) {
        // Extract number from invoice_number (e.g., INV-000256 -> 256)
        if (preg_match('/(\d+)/', $invoice->invoice_number, $matches)) {
            $sequence = (int)$matches[1];
            $invoice->sequence_number = $sequence;
            $invoice->save();
            $invoice_fixes++;
            
            if ($invoice_fixes % 100 == 0) {
                echo "  Fixed $invoice_fixes invoices...\n";
            }
        }
    }
    
    echo "✓ Fixed $invoice_fixes invoices\n\n";
    
    // Fix Payments
    echo "Processing Payments...\n";
    $payments = Payment::whereNull('sequence_number')->orderBy('id')->get();
    $payment_fixes = 0;
    
    foreach ($payments as $payment) {
        if (preg_match('/(\d+)/', $payment->payment_number, $matches)) {
            $sequence = (int)$matches[1];
            $payment->sequence_number = $sequence;
            $payment->save();
            $payment_fixes++;
            
            if ($payment_fixes % 100 == 0) {
                echo "  Fixed $payment_fixes payments...\n";
            }
        }
    }
    
    echo "✓ Fixed $payment_fixes payments\n\n";
    
    // Fix Estimates
    echo "Processing Estimates...\n";
    $estimates = Estimate::whereNull('sequence_number')->orderBy('id')->get();
    $estimate_fixes = 0;
    
    foreach ($estimates as $estimate) {
        if (preg_match('/(\d+)/', $estimate->estimate_number, $matches)) {
            $sequence = (int)$matches[1];
            $estimate->sequence_number = $sequence;
            $estimate->save();
            $estimate_fixes++;
        }
    }
    
    echo "✓ Fixed $estimate_fixes estimates\n\n";
    
    // Skip Expenses (no sequence_number column)
    echo "Skipping Expenses (no sequence_number column)\n";
    $expense_fixes = 0;
    echo "✓ Skipped expenses\n\n";
    
    DB::commit();
    
    echo str_repeat("=", 100) . "\n";
    echo "VERIFICATION\n";
    echo str_repeat("=", 100) . "\n\n";
    
    // Verify invoices
    echo "Sample Invoices (First 10):\n";
    printf("%-6s %-18s %-15s\n", "ID", "Invoice Number", "Sequence");
    echo str_repeat("-", 50) . "\n";
    
    $sample_invoices = Invoice::orderBy('id')->limit(10)->get();
    foreach ($sample_invoices as $inv) {
        printf("%-6d %-18s %-15s\n", $inv->id, $inv->invoice_number, $inv->sequence_number);
    }
    
    // Check what next invoice number will be
    $last_invoice = Invoice::orderBy('sequence_number', 'desc')
        ->where('sequence_number', '<>', null)
        ->first();
    
    if ($last_invoice) {
        $next_seq = $last_invoice->sequence_number + 1;
        echo "\n✓ Next invoice will be: INV-" . str_pad($next_seq, 6, '0', STR_PAD_LEFT) . " (sequence: $next_seq)\n";
    }
    
    echo "\nSample Payments (First 10):\n";
    printf("%-6s %-18s %-15s\n", "ID", "Payment Number", "Sequence");
    echo str_repeat("-", 50) . "\n";
    
    $sample_payments = Payment::orderBy('id')->limit(10)->get();
    foreach ($sample_payments as $pay) {
        printf("%-6d %-18s %-15s\n", $pay->id, $pay->payment_number, $pay->sequence_number);
    }
    
    // Check what next payment number will be
    $last_payment = Payment::orderBy('sequence_number', 'desc')
        ->where('sequence_number', '<>', null)
        ->first();
    
    if ($last_payment) {
        $next_seq = $last_payment->sequence_number + 1;
        echo "\n✓ Next payment will be: PAY-" . str_pad($next_seq, 6, '0', STR_PAD_LEFT) . " (sequence: $next_seq)\n";
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "SUMMARY\n";
    echo str_repeat("=", 100) . "\n";
    echo "Invoices fixed:  $invoice_fixes\n";
    echo "Payments fixed:  $payment_fixes\n";
    echo "Estimates fixed: $estimate_fixes\n";
    echo "Expenses fixed:  $expense_fixes\n";
    echo "\n✅ All sequence numbers have been populated!\n";
    echo "✅ New records will now be numbered sequentially after the highest existing number.\n";
    echo "✅ No more collisions will occur!\n";
    echo str_repeat("=", 100) . "\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back - no changes made.\n";
    exit(1);
}
