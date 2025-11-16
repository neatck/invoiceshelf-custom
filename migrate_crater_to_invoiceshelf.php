<?php

/**
 * Crater to InvoiceShelf Data Migration Script
 * 
 * This script migrates data from Crater database to InvoiceShelf database
 * while maintaining referential integrity and handling hash collisions.
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Crater to InvoiceShelf Migration ===\n\n";

// Source database (Crater)
$craterDb = 'crater_temp';

// Target database (InvoiceShelf)
$invoiceShelfDb = env('DB_DATABASE', 'invoiceshelf');

echo "Source DB: $craterDb\n";
echo "Target DB: $invoiceShelfDb\n\n";

// Step 1: Migrate Companies
echo "[1/8] Migrating Companies...\n";
$companies = DB::connection('mysql')->select("SELECT * FROM $craterDb.companies");
foreach ($companies as $company) {
    DB::table('companies')->updateOrInsert(
        ['id' => $company->id],
        [
            'name' => $company->name,
            'logo' => $company->logo ?? null,
            'unique_hash' => $company->unique_hash ?: Str::random(20),
            'slug' => $company->slug ?? null,
            'owner_id' => $company->owner_id ?? null,
            'created_at' => $company->created_at,
            'updated_at' => $company->updated_at,
        ]
    );
}
echo "   Migrated " . count($companies) . " companies\n\n";

// Step 2: Migrate Users
echo "[2/8] Migrating Users...\n";
$users = DB::connection('mysql')->select("SELECT * FROM $craterDb.users");
foreach ($users as $user) {
    DB::table('users')->updateOrInsert(
        ['id' => $user->id],
        [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'phone' => $user->phone ?? null,
            'role' => $user->role ?? 'super admin',
            'remember_token' => $user->remember_token ?? null,
            'facebook_id' => $user->facebook_id ?? null,
            'google_id' => $user->google_id ?? null,
            'github_id' => $user->github_id ?? null,
            'contact_name' => $user->contact_name ?? null,
            'company_name' => $user->company_name ?? null,
            'website' => $user->website ?? null,
            'enable_portal' => $user->enable_portal ?? 0,
            'currency_id' => $user->currency_id ?? null,
            'creator_id' => $user->creator_id ?? null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]
    );
}
echo "   Migrated " . count($users) . " users\n\n";

// Step 2b: Migrate User-Company relationships
echo "[2b/8] Migrating User-Company relationships...\n";
$userCompanies = DB::connection('mysql')->select("SELECT * FROM $craterDb.user_company");
foreach ($userCompanies as $uc) {
    DB::table('user_company')->updateOrInsert(
        ['user_id' => $uc->user_id, 'company_id' => $uc->company_id],
        [
            'created_at' => $uc->created_at,
            'updated_at' => $uc->updated_at,
        ]
    );
}
echo "   Migrated " . count($userCompanies) . " user-company relationships\n\n";

// Step 3: Migrate Customers with Patient Fields
echo "[3/8] Migrating Customers (Patients)...\n";
$customers = DB::connection('mysql')->select("SELECT * FROM $craterDb.customers");
$migratedCount = 0;
$skippedCount = 0;

foreach ($customers as $customer) {
    try {
        DB::table('customers')->updateOrInsert(
            ['id' => $customer->id],
            [
                'company_id' => $customer->company_id,
                'name' => $customer->name,
                'email' => $customer->email ?? null,
                'phone' => $customer->phone ?? null,
                'contact_name' => $customer->contact_name ?? null,
                'website' => $customer->website ?? null,
                'enable_portal' => $customer->enable_portal ?? 0,
                'password' => $customer->password ?? null,
                'currency_id' => $customer->currency_id ?? null,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
                // Patient fields
                'age' => $customer->age ?? null,
                'review_date' => $customer->review_date ?? null,
                'next_of_kin' => $customer->next_of_kin ?? null,
                'next_of_kin_phone' => $customer->next_of_kin_phone ?? null,
                'attended_to_by' => $customer->attended_to_by ?? null,
                'diagnosis' => $customer->diagnosis ?? null,
                'treatment' => $customer->treatment ?? null,
            ]
        );
        $migratedCount++;
    } catch (\Exception $e) {
        echo "   Warning: Could not migrate customer ID {$customer->id}: " . $e->getMessage() . "\n";
        $skippedCount++;
    }
}
echo "   Migrated $migratedCount customers, skipped $skippedCount\n\n";

// Step 4: Migrate Addresses
echo "[4/8] Migrating Addresses...\n";
$addresses = DB::connection('mysql')->select("SELECT * FROM $craterDb.addresses");
foreach ($addresses as $address) {
    DB::table('addresses')->updateOrInsert(
        ['id' => $address->id],
        [
            'company_id' => $address->company_id ?? null,
            'user_id' => $address->user_id ?? null,
            'customer_id' => $address->customer_id ?? null,
            'name' => $address->name ?? null,
            'address_street_1' => $address->address_street_1 ?? null,
            'address_street_2' => $address->address_street_2 ?? null,
            'city' => $address->city ?? null,
            'state' => $address->state ?? null,
            'country_id' => $address->country_id ?? null,
            'zip' => $address->zip ?? null,
            'phone' => $address->phone ?? null,
            'fax' => $address->fax ?? null,
            'type' => $address->type ?? null,
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ]
    );
}
echo "   Migrated " . count($addresses) . " addresses\n\n";

// Step 5: Migrate Items
echo "[5/8] Migrating Items...\n";
$items = DB::connection('mysql')->select("SELECT * FROM $craterDb.items");
foreach ($items as $item) {
    DB::table('items')->updateOrInsert(
        ['id' => $item->id],
        [
            'company_id' => $item->company_id,
            'name' => $item->name,
            'description' => $item->description ?? null,
            'price' => $item->price,
            'unit_id' => $item->unit_id ?? null,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ]
    );
}
echo "   Migrated " . count($items) . " items\n\n";

// Step 6: Migrate Invoices
echo "[6/8] Migrating Invoices...\n";
$invoices = DB::connection('mysql')->select("SELECT * FROM $craterDb.invoices");
foreach ($invoices as $invoice) {
    // Check if unique_hash exists in InvoiceShelf, regenerate if collision
    $uniqueHash = $invoice->unique_hash;
    $hashExists = DB::table('invoices')->where('unique_hash', $uniqueHash)->exists();
    
    if ($hashExists) {
        $uniqueHash = Str::random(20);
        echo "   Hash collision detected for invoice {$invoice->invoice_number}, regenerating...\n";
    }
    
    DB::table('invoices')->updateOrInsert(
        ['id' => $invoice->id],
        [
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'creator_id' => $invoice->creator_id ?? null,
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date,
            'due_date' => $invoice->due_date,
            'status' => $invoice->status,
            'paid_status' => $invoice->paid_status,
            'tax_per_item' => $invoice->tax_per_item ?? 'NO',
            'discount_per_item' => $invoice->discount_per_item ?? 'NO',
            'notes' => $invoice->notes ?? null,
            'discount' => $invoice->discount ?? 0,
            'discount_type' => $invoice->discount_type ?? 'fixed',
            'discount_val' => $invoice->discount_val ?? 0,
            'sub_total' => $invoice->sub_total,
            'total' => $invoice->total,
            'tax' => $invoice->tax ?? 0,
            'due_amount' => $invoice->due_amount ?? 0,
            'sent' => $invoice->sent ?? 0,
            'viewed' => $invoice->viewed ?? 0,
            'unique_hash' => $uniqueHash,
            'template_name' => $invoice->template_name ?? null,
            'created_at' => $invoice->created_at,
            'updated_at' => $invoice->updated_at,
        ]
    );
}
echo "   Migrated " . count($invoices) . " invoices\n\n";

// Step 7: Migrate Invoice Items
echo "[7/8] Migrating Invoice Items...\n";
$invoiceItems = DB::connection('mysql')->select("SELECT * FROM $craterDb.invoice_items");
foreach ($invoiceItems as $item) {
    DB::table('invoice_items')->updateOrInsert(
        ['id' => $item->id],
        [
            'company_id' => $item->company_id,
            'invoice_id' => $item->invoice_id,
            'item_id' => $item->item_id ?? null,
            'name' => $item->name,
            'description' => $item->description ?? null,
            'quantity' => $item->quantity,
            'price' => $item->price,
            'discount' => $item->discount ?? 0,
            'discount_type' => $item->discount_type ?? 'fixed',
            'discount_val' => $item->discount_val ?? 0,
            'tax' => $item->tax ?? 0,
            'total' => $item->total,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ]
    );
}
echo "   Migrated " . count($invoiceItems) . " invoice items\n\n";

// Step 8: Migrate Appointments
echo "[8/8] Migrating Appointments...\n";
$appointments = DB::connection('mysql')->select("SELECT * FROM $craterDb.appointments");
foreach ($appointments as $appointment) {
    DB::table('appointments')->updateOrInsert(
        ['id' => $appointment->id],
        [
            'company_id' => $appointment->company_id,
            'customer_id' => $appointment->customer_id,
            'user_id' => $appointment->user_id ?? null,
            'date' => $appointment->date,
            'time' => $appointment->time ?? null,
            'type' => $appointment->type ?? null,
            'status' => $appointment->status ?? 'scheduled',
            'duration' => $appointment->duration ?? null,
            'notes' => $appointment->notes ?? null,
            'remind_hours_before' => $appointment->remind_hours_before ?? null,
            'created_at' => $appointment->created_at,
            'updated_at' => $appointment->updated_at,
        ]
    );
}
echo "   Migrated " . count($appointments) . " appointments\n\n";

echo "=== Migration Complete! ===\n\n";
echo "Summary:\n";
echo "- Companies: " . count($companies) . "\n";
echo "- Users: " . count($users) . "\n";
echo "- Customers: $migratedCount (skipped: $skippedCount)\n";
echo "- Addresses: " . count($addresses) . "\n";
echo "- Items: " . count($items) . "\n";
echo "- Invoices: " . count($invoices) . "\n";
echo "- Invoice Items: " . count($invoiceItems) . "\n";
echo "- Appointments: " . count($appointments) . "\n";
echo "\nNext steps:\n";
echo "1. Verify data in InvoiceShelf\n";
echo "2. Test invoice PDF generation\n";
echo "3. Test appointment creation\n";
echo "4. Drop crater_temp database when satisfied\n";
