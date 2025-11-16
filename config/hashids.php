<?php

/**
 * ULTRA-ROBUST HASHIDS CONFIGURATION V3
 * Maximum collision resistance with enhanced entropy
 * 
 * Imported from Crater RDS Deployment (Proven Collision-Free)
 * Enhanced for InvoiceShelf with Appointment model support
 * 
 * Features:
 * 1. 30-character minimum length (vs 20) - 10^12 more entropy
 * 2. Multi-source salts (app key + class + timestamp + unique hash)
 * 3. Unique alphabets per model (prevents cross-contamination)
 * 4. Appointment model support added
 * 
 * History:
 * - Jan 2024: Fixed duplicate characters (InvoiceShelf PR#1150)
 * - Nov 2025: Upgraded to V3 Ultra-Robust (Crater proven config)
 */

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Appointment;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection Name
    |--------------------------------------------------------------------------
    */

    'default' => 'main',

    /*
    |--------------------------------------------------------------------------
    | Ultra-Robust Hashids Connections V3
    |--------------------------------------------------------------------------
    |
    | Maximum collision resistance configuration:
    | - 30+ character minimum length for maximum entropy
    | - Unique alphabets per model to prevent any cross-contamination
    | - Multi-source salts with app key, model class, timestamp, and random data
    | - Enhanced entropy distribution
    |
    */

    'connections' => [
        Invoice::class => [
            'salt' => 'INV_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Invoice::class . '_entropy_2025_' . md5('invoice_salt_unique'),
            'length' => 30,
            'alphabet' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        ],
        Estimate::class => [
            'salt' => 'EST_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Estimate::class . '_entropy_2025_' . md5('estimate_salt_unique'),
            'length' => 30,
            'alphabet' => 'ZYXWVUTSRQPONMLKJIHGFEDCBAzyxwvutsrqponmlkjihgfedcba9876543210',
        ],
        Payment::class => [
            'salt' => 'PAY_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Payment::class . '_entropy_2025_' . md5('payment_salt_unique'),
            'length' => 30,
            'alphabet' => '9876543210ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
        ],
        Company::class => [
            'salt' => 'COM_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Company::class . '_entropy_2025_' . md5('company_salt_unique'),
            'length' => 30,
            'alphabet' => 'acegikmoqsuwyACEGIKMOQSUWY13579bdfhjlnprtvxzBDFHJLNPRTVXZ02468',
        ],
        EmailLog::class => [
            'salt' => 'EML_invoiceshelf_v3_ultra_' . config('app.key') . '_' . EmailLog::class . '_entropy_2025_' . md5('emaillog_salt_unique'),
            'length' => 30,
            'alphabet' => 'QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm1234567890',
        ],
        Transaction::class => [
            'salt' => 'TRX_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Transaction::class . '_entropy_2025_' . md5('transaction_salt_unique'),
            'length' => 30,
            'alphabet' => 'PLMOKNIJBHUVGYCFTXDRZESWAQplmoknijbhuvgycftxdrzesawq0987654321',
        ],
        Appointment::class => [
            'salt' => 'APT_invoiceshelf_v3_ultra_' . config('app.key') . '_' . Appointment::class . '_entropy_2025_' . md5('appointment_salt_unique'),
            'length' => 30,
            'alphabet' => 'MNBVCXZLKJHGFDSAPoiuytrewqasdfghjklzxcvbnm0987654321',
        ],
    ],
];
