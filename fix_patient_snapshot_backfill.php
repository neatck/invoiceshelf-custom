<?php

/**
 * Backfill Patient Snapshot Data on Invoices
 * 
 * Issue: Migrated invoices don't have patient information in snapshot fields
 * Solution: Copy patient data from customers table to invoice snapshot fields
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  InvoiceShelf - Backfill Patient Snapshot Data                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Analyze
echo "STEP 1: Analyzing Patient Data Status\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$totalInvoices = DB::table('invoices')->where('company_id', 1)->count();
$invoicesWithPatientData = DB::table('invoices')->where('company_id', 1)->whereNotNull('customer_age')->count();
$invoicesMissing = $totalInvoices - $invoicesWithPatientData;

echo "Invoices: $totalInvoices total, $invoicesWithPatientData have patient data, $invoicesMissing missing\n";

$customersWithDiagnosis = DB::table('customers')->where('company_id', 1)->whereNotNull('diagnosis')->count();
echo "Customers with diagnosis: $customersWithDiagnosis\n\n";

if ($invoicesMissing == 0) {
    echo "✓ All invoices have patient snapshot data!\n\n";
    exit(0);
}

// Step 2: Backfill
echo "STEP 2: Backfilling Patient Data\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Update ALL invoices to snapshot current customer patient data
// This ensures PDFs always reflect the customer's patient info at invoice creation
$updated = DB::statement("
    UPDATE invoices i
    JOIN customers c ON i.customer_id = c.id
    SET 
        i.customer_age = c.age,
        i.customer_next_of_kin = c.next_of_kin,
        i.customer_next_of_kin_phone = c.next_of_kin_phone,
        i.customer_diagnosis = c.diagnosis,
        i.customer_treatment = c.treatment,
        i.customer_attended_to_by = c.attended_to_by,
        i.customer_review_date = c.review_date,
        i.updated_at = NOW()
    WHERE i.company_id = 1
      AND (i.customer_diagnosis IS NULL OR i.customer_diagnosis != c.diagnosis)
");

$nowHaveData = DB::table('invoices')->where('company_id', 1)->whereNotNull('customer_age')->count();
$actuallyUpdated = $nowHaveData - $invoicesWithPatientData;

echo "✓ Backfill complete! Updated $actuallyUpdated invoices\n\n";

// Step 3: Verify
echo "STEP 3: Verification\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "After backfill:\n";
echo "  - Invoices with patient data: $nowHaveData / $totalInvoices\n";
echo "  - Coverage: " . round(($nowHaveData / $totalInvoices) * 100, 1) . "%\n\n";

$sampleInvoices = DB::table('invoices as i')
    ->join('customers as c', 'i.customer_id', '=', 'c.id')
    ->where('i.company_id', 1)
    ->whereNotNull('i.customer_diagnosis')
    ->select('i.invoice_number', 'c.name', 'i.customer_age', 'i.customer_diagnosis')
    ->limit(5)
    ->get();

echo "Sample invoices with patient data:\n";
foreach ($sampleInvoices as $inv) {
    $diag = substr($inv->customer_diagnosis ?? 'N/A', 0, 30);
    echo "  - {$inv->invoice_number} ({$inv->name}): Age={$inv->customer_age}, Diagnosis=$diag\n";
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║  PATIENT DATA BACKFILL COMPLETED                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "Next: Clear caches and test invoice PDFs\n\n";
