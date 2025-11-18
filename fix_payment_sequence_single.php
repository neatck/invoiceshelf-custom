<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Payment;

echo str_repeat("=", 80) . "\n";
echo "FIX PAYMENT SEQUENCE NUMBER - PAY-001485\n";
echo str_repeat("=", 80) . "\n\n";

echo "Problem: Payment PAY-001485 exists with NULL sequence_number\n";
echo "This causes next payment generation to try reusing PAY-001485\n";
echo "Solution: Extract sequence number from payment_number\n\n";

DB::beginTransaction();

try {
    // Find the problematic payment
    $payment = Payment::where('payment_number', 'PAY-001485')
        ->where('company_id', 1)
        ->whereNull('sequence_number')
        ->first();
    
    if (!$payment) {
        echo "✓ No payment found with NULL sequence_number for PAY-001485\n";
        echo "✓ Problem already fixed or doesn't exist!\n";
        DB::rollBack();
        exit(0);
    }
    
    echo "Found payment:\n";
    echo "  ID: {$payment->id}\n";
    echo "  Payment Number: {$payment->payment_number}\n";
    echo "  Current Sequence: " . ($payment->sequence_number ?? 'NULL') . "\n";
    echo "  Created: {$payment->created_at}\n\n";
    
    // Extract sequence from payment number (PAY-001485 -> 1485)
    if (preg_match('/(\d+)/', $payment->payment_number, $matches)) {
        $sequence = (int)$matches[1];
        
        echo "Extracted sequence: $sequence\n";
        echo "Setting payment sequence_number = $sequence\n\n";
        
        $payment->sequence_number = $sequence;
        $payment->save();
        
        DB::commit();
        
        echo "✅ SUCCESS! Fixed payment PAY-001485\n";
        echo "✅ sequence_number is now: $sequence\n\n";
        
        // Verify next payment number
        $last_payment = Payment::where('company_id', 1)
            ->orderBy('sequence_number', 'desc')
            ->where('sequence_number', '<>', null)
            ->first();
        
        if ($last_payment) {
            $next_seq = $last_payment->sequence_number + 1;
            $next_number = 'PAY-' . str_pad($next_seq, 6, '0', STR_PAD_LEFT);
            echo "✅ Next payment will be: $next_number (sequence: $next_seq)\n";
        }
        
    } else {
        DB::rollBack();
        echo "❌ ERROR: Could not extract sequence number from '{$payment->payment_number}'\n";
        exit(1);
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "FIX COMPLETE\n";
    echo str_repeat("=", 80) . "\n";
    
} catch (Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back - no changes made.\n";
    exit(1);
}
