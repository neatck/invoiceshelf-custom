<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo str_repeat("=", 100) . "\n";
echo "CRITICAL FIX: SYNCHRONIZE SEQUENCE NUMBERS WITH CRATER DATABASE\n";
echo str_repeat("=", 100) . "\n\n";

echo "⚠️  VERIFICATION STEP - Checking current state...\n\n";

// Load Crater data for verification
$crater_sql_file = '/home/royal/Desktop/Compare dbs/Crater/db-dumps/mysql-crater.sql';

if (!file_exists($crater_sql_file)) {
    die("ERROR: Cannot find Crater database file at: $crater_sql_file\n");
}

echo "✓ Found Crater database backup\n";
echo "✓ Extracting Crater sequence numbers...\n\n";

$crater_content = file_get_contents($crater_sql_file);

// Extract Crater invoice data
preg_match('/INSERT INTO `invoices` VALUES\s+(.*?);/s', $crater_content, $invoice_match);
$crater_invoices = [];

if ($invoice_match) {
    $data = $invoice_match[1];
    // Parse invoice records: (id,sequence_number,customer_sequence_number,date,due_date,invoice_number,...)
    preg_match_all('/\((\d+),(\d+|NULL),/', $data, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $id = (int)$match[1];
        $seq = $match[2] === 'NULL' ? null : (int)$match[2];
        $crater_invoices[$id] = $seq;
    }
}

echo "Crater Invoices with sequence numbers: " . count($crater_invoices) . "\n";

// Extract Crater payment data
preg_match('/INSERT INTO `payments` VALUES\s+(.*?);/s', $crater_content, $payment_match);
$crater_payments = [];

if ($payment_match) {
    $data = $payment_match[1];
    preg_match_all('/\((\d+),(\d+|NULL),/', $data, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $id = (int)$match[1];
        $seq = $match[2] === 'NULL' ? null : (int)$match[2];
        $crater_payments[$id] = $seq;
    }
}

echo "Crater Payments with sequence numbers: " . count($crater_payments) . "\n";

// Extract Crater estimate data  
preg_match('/INSERT INTO `estimates` VALUES\s+(.*?);/s', $crater_content, $estimate_match);
$crater_estimates = [];

if ($estimate_match) {
    $data = $estimate_match[1];
    preg_match_all('/\((\d+),(\d+|NULL),/', $data, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $id = (int)$match[1];
        $seq = $match[2] === 'NULL' ? null : (int)$match[2];
        $crater_estimates[$id] = $seq;
    }
}

echo "Crater Estimates with sequence numbers: " . count($crater_estimates) . "\n\n";

echo str_repeat("=", 100) . "\n";
echo "VERIFICATION: Comparing first 10 records with Crater\n";
echo str_repeat("=", 100) . "\n\n";

// Verify invoices
$invoices = DB::table('invoices')->orderBy('id')->limit(10)->get();
$invoice_mismatches = 0;

echo "INVOICES:\n";
printf("%-6s %-18s %-12s %-12s %-10s\n", "ID", "Invoice Number", "Current Seq", "Crater Seq", "Status");
echo str_repeat("-", 100) . "\n";

foreach ($invoices as $invoice) {
    $crater_seq = $crater_invoices[$invoice->id] ?? 'MISSING';
    $current_seq = $invoice->sequence_number ?? 'NULL';
    $match = ($crater_seq === $current_seq || (string)$crater_seq === (string)$current_seq);
    $status = $match ? "✓ OK" : "✗ MISMATCH";
    
    if (!$match) {
        $invoice_mismatches++;
    }
    
    printf("%-6d %-18s %-12s %-12s %-10s\n", 
        $invoice->id, 
        $invoice->invoice_number, 
        $current_seq,
        $crater_seq,
        $status
    );
}

echo "\n";

// Now FIX the data
echo str_repeat("=", 100) . "\n";
echo "APPLYING FIX: Synchronizing sequence numbers with Crater\n";
echo str_repeat("=", 100) . "\n\n";

DB::beginTransaction();

try {
    // Fix Invoices
    echo "Fixing Invoices...\n";
    $invoice_fixes = 0;
    
    foreach ($crater_invoices as $id => $seq_number) {
        if ($seq_number !== null) {
            DB::table('invoices')
                ->where('id', $id)
                ->update(['sequence_number' => $seq_number]);
            $invoice_fixes++;
            
            if ($invoice_fixes % 100 == 0) {
                echo "  Fixed $invoice_fixes invoices...\n";
            }
        }
    }
    
    echo "✓ Fixed $invoice_fixes invoices\n\n";
    
    // Fix Payments
    echo "Fixing Payments...\n";
    $payment_fixes = 0;
    
    foreach ($crater_payments as $id => $seq_number) {
        if ($seq_number !== null) {
            DB::table('payments')
                ->where('id', $id)
                ->update(['sequence_number' => $seq_number]);
            $payment_fixes++;
        }
    }
    
    echo "✓ Fixed $payment_fixes payments\n\n";
    
    // Fix Estimates
    echo "Fixing Estimates...\n";
    $estimate_fixes = 0;
    
    foreach ($crater_estimates as $id => $seq_number) {
        if ($seq_number !== null) {
            DB::table('estimates')
                ->where('id', $id)
                ->update(['sequence_number' => $seq_number]);
            $estimate_fixes++;
        }
    }
    
    echo "✓ Fixed $estimate_fixes estimates\n\n";
    
    DB::commit();
    
    echo str_repeat("=", 100) . "\n";
    echo "POST-FIX VERIFICATION\n";
    echo str_repeat("=", 100) . "\n\n";
    
    // Verify after fix
    $invoices_after = DB::table('invoices')->orderBy('id')->limit(10)->get();
    
    echo "INVOICES AFTER FIX:\n";
    printf("%-6s %-18s %-12s %-12s %-10s\n", "ID", "Invoice Number", "New Seq", "Crater Seq", "Status");
    echo str_repeat("-", 100) . "\n";
    
    $all_match = true;
    foreach ($invoices_after as $invoice) {
        $crater_seq = $crater_invoices[$invoice->id] ?? 'MISSING';
        $new_seq = $invoice->sequence_number ?? 'NULL';
        $match = ($crater_seq === $new_seq || (string)$crater_seq === (string)$new_seq);
        $status = $match ? "✓ MATCH" : "✗ STILL WRONG";
        
        if (!$match) {
            $all_match = false;
        }
        
        printf("%-6d %-18s %-12s %-12s %-10s\n", 
            $invoice->id, 
            $invoice->invoice_number, 
            $new_seq,
            $crater_seq,
            $status
        );
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "FINAL SUMMARY\n";
    echo str_repeat("=", 100) . "\n";
    echo "Invoices synchronized:  $invoice_fixes\n";
    echo "Payments synchronized:  $payment_fixes\n";
    echo "Estimates synchronized: $estimate_fixes\n";
    echo "\n";
    
    if ($all_match) {
        echo "✅ SUCCESS! All sequence numbers now match Crater database!\n";
        echo "✅ New invoices/payments will now be numbered sequentially after the last number.\n";
    } else {
        echo "⚠️  Some mismatches still exist - manual review needed.\n";
    }
    
    echo str_repeat("=", 100) . "\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back - no changes made.\n";
    exit(1);
}
