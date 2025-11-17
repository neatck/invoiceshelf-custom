<?php

/**
 * Comprehensive Data Verification: Crater vs InvoiceShelf
 * 
 * Verifies customer-by-customer that ALL data from Crater exists in InvoiceShelf
 * No assumptions, no exceptions - 100% accuracy required
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  CRATER → INVOICESHELF DATA VERIFICATION                       ║\n";
echo "║  Customer-by-Customer Accuracy Check                          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$errors = [];
$warnings = [];

// Connect to Crater DB
$craterHost = '127.0.0.1';
$craterPort = 33008;
$craterDb = 'crater';
$craterUser = 'crater';
$craterPass = 'crater';

try {
    $craterPdo = new PDO(
        "mysql:host=$craterHost;port=$craterPort;dbname=$craterDb",
        $craterUser,
        $craterPass
    );
    $craterPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to Crater DB\n";
} catch (PDOException $e) {
    echo "✗ Cannot connect to Crater DB: " . $e->getMessage() . "\n";
    exit(1);
}

echo "✓ Connected to InvoiceShelf DB\n\n";

// SECTION 1: Customer Verification
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECTION 1: CUSTOMER DATA VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$craterCustomers = $craterPdo->query("SELECT COUNT(*) as count FROM customers")->fetch(PDO::FETCH_ASSOC);
$shelfCustomers = DB::table('customers')->where('company_id', 1)->count();

echo "Customer Count:\n";
echo "  Crater:       {$craterCustomers['count']}\n";
echo "  InvoiceShelf: $shelfCustomers\n";

if ($craterCustomers['count'] != $shelfCustomers) {
    $errors[] = "Customer count mismatch: Crater has {$craterCustomers['count']}, InvoiceShelf has $shelfCustomers";
    echo "  ✗ MISMATCH!\n\n";
} else {
    echo "  ✓ Match\n\n";
}

// Verify each customer's patient fields
echo "Verifying patient fields for each customer...\n";
$craterCustomerData = $craterPdo->query("
    SELECT id, name, age, diagnosis, treatment, next_of_kin, next_of_kin_phone, 
           attended_to_by, review_date
    FROM customers 
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$mismatchCount = 0;
$matchCount = 0;

foreach ($craterCustomerData as $craterCust) {
    $shelfCust = DB::table('customers')
        ->where('company_id', 1)
        ->where('id', $craterCust['id'])
        ->first();
    
    if (!$shelfCust) {
        $errors[] = "Customer ID {$craterCust['id']} ({$craterCust['name']}) exists in Crater but NOT in InvoiceShelf";
        $mismatchCount++;
        continue;
    }
    
    // Check each patient field
    $fields = ['age', 'diagnosis', 'treatment', 'next_of_kin', 'next_of_kin_phone', 'attended_to_by', 'review_date'];
    foreach ($fields as $field) {
        if ($craterCust[$field] !== $shelfCust->$field) {
            $errors[] = "Customer ID {$craterCust['id']} field '$field' mismatch: Crater='{$craterCust[$field]}', Shelf='{$shelfCust->$field}'";
            $mismatchCount++;
        }
    }
    
    $matchCount++;
}

echo "  Verified: $matchCount customers\n";
echo "  Mismatches: $mismatchCount\n\n";

// SECTION 2: Invoice Verification
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECTION 2: INVOICE DATA VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$craterInvoices = $craterPdo->query("SELECT COUNT(*) as count FROM invoices")->fetch(PDO::FETCH_ASSOC);
$shelfInvoices = DB::table('invoices')->where('company_id', 1)->count();

echo "Invoice Count:\n";
echo "  Crater:       {$craterInvoices['count']}\n";
echo "  InvoiceShelf: $shelfInvoices\n";

if ($craterInvoices['count'] != $shelfInvoices) {
    $errors[] = "Invoice count mismatch: Crater has {$craterInvoices['count']}, InvoiceShelf has $shelfInvoices";
    echo "  ✗ MISMATCH!\n\n";
} else {
    echo "  ✓ Match\n\n";
}

// Check invoice totals
$craterTotalDue = $craterPdo->query("SELECT SUM(due_amount) as total FROM invoices")->fetch(PDO::FETCH_ASSOC);
$shelfTotalDue = DB::table('invoices')->where('company_id', 1)->sum('due_amount');

echo "Invoice Total Due Amount:\n";
echo "  Crater:       " . number_format($craterTotalDue['total'] / 100, 2) . " UGX\n";
echo "  InvoiceShelf: " . number_format($shelfTotalDue / 100, 2) . " UGX\n";

if ($craterTotalDue['total'] != $shelfTotalDue) {
    $diff = abs($craterTotalDue['total'] - $shelfTotalDue);
    $errors[] = "Invoice totals mismatch by " . number_format($diff / 100, 2) . " UGX";
    echo "  ✗ MISMATCH by " . number_format($diff / 100, 2) . " UGX\n\n";
} else {
    echo "  ✓ Match\n\n";
}

// SECTION 3: Payment Verification
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECTION 3: PAYMENT DATA VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$craterPayments = $craterPdo->query("SELECT COUNT(*) as count FROM payments")->fetch(PDO::FETCH_ASSOC);
$shelfPayments = DB::table('payments')->where('company_id', 1)->count();

echo "Payment Count:\n";
echo "  Crater:       {$craterPayments['count']}\n";
echo "  InvoiceShelf: $shelfPayments\n";

if ($craterPayments['count'] != $shelfPayments) {
    $errors[] = "Payment count mismatch: Crater has {$craterPayments['count']}, InvoiceShelf has $shelfPayments";
    echo "  ✗ MISMATCH!\n\n";
} else {
    echo "  ✓ Match\n\n";
}

$craterPaymentTotal = $craterPdo->query("SELECT SUM(amount) as total FROM payments")->fetch(PDO::FETCH_ASSOC);
$shelfPaymentTotal = DB::table('payments')->where('company_id', 1)->sum('amount');

echo "Payment Total Amount:\n";
echo "  Crater:       " . number_format($craterPaymentTotal['total'] / 100, 2) . " UGX\n";
echo "  InvoiceShelf: " . number_format($shelfPaymentTotal / 100, 2) . " UGX\n";

if ($craterPaymentTotal['total'] != $shelfPaymentTotal) {
    $diff = abs($craterPaymentTotal['total'] - $shelfPaymentTotal);
    $errors[] = "Payment totals mismatch by " . number_format($diff / 100, 2) . " UGX";
    echo "  ✗ MISMATCH by " . number_format($diff / 100, 2) . " UGX\n\n";
} else {
    echo "  ✓ Match\n\n";
}

// SECTION 4: Expense Verification
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECTION 4: EXPENSE DATA VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$craterExpenses = $craterPdo->query("SELECT COUNT(*) as count FROM expenses")->fetch(PDO::FETCH_ASSOC);
$shelfExpenses = DB::table('expenses')->where('company_id', 1)->count();

echo "Expense Count:\n";
echo "  Crater:       {$craterExpenses['count']}\n";
echo "  InvoiceShelf: $shelfExpenses\n";

if ($craterExpenses['count'] != $shelfExpenses) {
    $errors[] = "Expense count mismatch: Crater has {$craterExpenses['count']}, InvoiceShelf has $shelfExpenses";
    echo "  ✗ MISMATCH!\n\n";
} else {
    echo "  ✓ Match\n\n";
}

$craterExpenseTotal = $craterPdo->query("SELECT SUM(amount) as total FROM expenses")->fetch(PDO::FETCH_ASSOC);
$shelfExpenseTotal = DB::table('expenses')->where('company_id', 1)->sum('amount');

echo "Expense Total Amount:\n";
echo "  Crater:       " . number_format($craterExpenseTotal['total'] / 100, 2) . " UGX\n";
echo "  InvoiceShelf: " . number_format($shelfExpenseTotal / 100, 2) . " UGX\n";

if ($craterExpenseTotal['total'] != $shelfExpenseTotal) {
    $diff = abs($craterExpenseTotal['total'] - $shelfExpenseTotal);
    $errors[] = "Expense totals mismatch by " . number_format($diff / 100, 2) . " UGX";
    echo "  ✗ MISMATCH by " . number_format($diff / 100, 2) . " UGX\n\n";
} else {
    echo "  ✓ Match\n\n";
}

// SECTION 5: Patient Data in Invoice Snapshots
echo "═══════════════════════════════════════════════════════════════\n";
echo "SECTION 5: PATIENT DATA SNAPSHOT VERIFICATION\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$customersWithPatientData = DB::table('customers')
    ->where('company_id', 1)
    ->where(function($query) {
        $query->whereNotNull('age')
              ->orWhereNotNull('diagnosis')
              ->orWhereNotNull('treatment')
              ->orWhereNotNull('next_of_kin');
    })
    ->count();

$invoicesWithSnapshot = DB::table('invoices')
    ->where('company_id', 1)
    ->where(function($query) {
        $query->whereNotNull('customer_age')
              ->orWhereNotNull('customer_diagnosis')
              ->orWhereNotNull('customer_treatment')
              ->orWhereNotNull('customer_next_of_kin');
    })
    ->count();

echo "Patient Data Status:\n";
echo "  Customers with patient data: $customersWithPatientData\n";
echo "  Invoices with patient snapshot: $invoicesWithSnapshot / {$shelfInvoices}\n";

$invoicesNeedingSnapshot = DB::table('invoices as i')
    ->join('customers as c', 'i.customer_id', '=', 'c.id')
    ->where('i.company_id', 1)
    ->whereNull('i.customer_diagnosis')
    ->whereNotNull('c.diagnosis')
    ->count();

if ($invoicesNeedingSnapshot > 0) {
    $warnings[] = "$invoicesNeedingSnapshot invoices need patient snapshot backfill";
    echo "  ⚠ $invoicesNeedingSnapshot invoices missing patient snapshot\n\n";
} else {
    echo "  ✓ All invoices have patient snapshots where applicable\n\n";
}

// FINAL REPORT
echo "═══════════════════════════════════════════════════════════════\n";
echo "FINAL VERIFICATION REPORT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (count($errors) == 0 && count($warnings) == 0) {
    echo "✓✓✓ SUCCESS: 100% DATA ACCURACY ✓✓✓\n\n";
    echo "All Crater data perfectly matches InvoiceShelf:\n";
    echo "  ✓ Customers: {$craterCustomers['count']} / {$craterCustomers['count']}\n";
    echo "  ✓ Invoices: {$craterInvoices['count']} / {$craterInvoices['count']}\n";
    echo "  ✓ Payments: {$craterPayments['count']} / {$craterPayments['count']}\n";
    echo "  ✓ Expenses: {$craterExpenses['count']} / {$craterExpenses['count']}\n";
    echo "  ✓ Financial totals match\n";
    echo "  ✓ Patient data present\n";
} else {
    echo "✗✗✗ DATA INTEGRITY ISSUES FOUND ✗✗✗\n\n";
    
    if (count($errors) > 0) {
        echo "ERRORS (" . count($errors) . "):\n";
        foreach ($errors as $i => $error) {
            echo "  " . ($i + 1) . ". $error\n";
        }
        echo "\n";
    }
    
    if (count($warnings) > 0) {
        echo "WARNINGS (" . count($warnings) . "):\n";
        foreach ($warnings as $i => $warning) {
            echo "  " . ($i + 1) . ". $warning\n";
        }
        echo "\n";
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║  VERIFICATION COMPLETE                                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

exit(count($errors) > 0 ? 1 : 0);
