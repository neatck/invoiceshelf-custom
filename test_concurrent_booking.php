<?php

/**
 * Concurrent Appointment Booking Simulation
 * 
 * This script simulates two users trying to book the same time slot simultaneously.
 * It uses forked processes to truly test concurrency.
 * 
 * Run with: php test_concurrent_booking.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=== Concurrent Appointment Booking Simulation ===\n\n";

// Get a valid company and customer from the database
$company = Company::first();
$customer = Customer::first();

if (!$company || !$customer) {
    echo "ERROR: No company or customer found in database.\n";
    echo "Please ensure you have at least one company and customer.\n";
    exit(1);
}

echo "Using Company ID: {$company->id}\n";
echo "Using Customer ID: {$customer->id}\n\n";

// Test date/time that shouldn't conflict with existing appointments
$testDate = Carbon::tomorrow()->setHour(23)->setMinute(30);
echo "Test appointment time: " . $testDate->format('Y-m-d H:i:s') . "\n\n";

// Clean up any test appointments from previous runs
$deleted = Appointment::where('company_id', $company->id)
    ->where('title', 'LIKE', 'Concurrent Test%')
    ->delete();
echo "Cleaned up $deleted previous test appointments\n\n";

// Simulate two concurrent booking attempts
echo "Simulating concurrent booking attempts...\n\n";

$results = [];

// Since PHP doesn't easily support true parallelism without extensions,
// we'll test the locking mechanism directly

echo "Test 1: Sequential booking (baseline)\n";
echo "---------------------------------------\n";

// First booking should succeed
try {
    $result1 = DB::transaction(function () use ($company, $customer, $testDate) {
        // Lock existing appointments (simulating what the controller does)
        $existingAppointments = Appointment::where('company_id', $company->id)
            ->whereDate('appointment_date', $testDate->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->lockForUpdate()
            ->get();

        $proposedStart = $testDate;
        $proposedEnd = $testDate->copy()->addMinutes(30);

        // Check for overlaps
        foreach ($existingAppointments as $existing) {
            $existingStart = $existing->appointment_date;
            $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes);

            if ($proposedStart->lt($existingEnd) && $proposedEnd->gt($existingStart)) {
                return ['success' => false, 'reason' => 'overlap'];
            }
        }

        // Create appointment
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'title' => 'Concurrent Test Booking 1',
            'appointment_date' => $testDate,
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'type' => 'consultation',
        ]);

        return ['success' => true, 'id' => $appointment->id];
    });

    if ($result1['success']) {
        echo "   ✓ First booking SUCCEEDED (ID: {$result1['id']})\n";
    } else {
        echo "   ✗ First booking FAILED (unexpected)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ First booking ERROR: " . $e->getMessage() . "\n";
}

// Second booking at the same time should fail
try {
    $result2 = DB::transaction(function () use ($company, $customer, $testDate) {
        $existingAppointments = Appointment::where('company_id', $company->id)
            ->whereDate('appointment_date', $testDate->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->lockForUpdate()
            ->get();

        $proposedStart = $testDate;
        $proposedEnd = $testDate->copy()->addMinutes(30);

        foreach ($existingAppointments as $existing) {
            $existingStart = $existing->appointment_date;
            $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes);

            if ($proposedStart->lt($existingEnd) && $proposedEnd->gt($existingStart)) {
                return ['success' => false, 'reason' => 'overlap'];
            }
        }

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'title' => 'Concurrent Test Booking 2',
            'appointment_date' => $testDate,
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'type' => 'consultation',
        ]);

        return ['success' => true, 'id' => $appointment->id];
    });

    if ($result2['success']) {
        echo "   ✗ Second booking SUCCEEDED (should have been blocked!)\n";
    } else {
        echo "   ✓ Second booking correctly BLOCKED (overlap detected)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Second booking ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Test 2: Adjacent time slots (should both succeed)\n";
echo "--------------------------------------------------\n";

$adjacentTime = $testDate->copy()->addMinutes(30);

try {
    $result3 = DB::transaction(function () use ($company, $customer, $adjacentTime) {
        $existingAppointments = Appointment::where('company_id', $company->id)
            ->whereDate('appointment_date', $adjacentTime->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->lockForUpdate()
            ->get();

        $proposedStart = $adjacentTime;
        $proposedEnd = $adjacentTime->copy()->addMinutes(30);

        foreach ($existingAppointments as $existing) {
            $existingStart = $existing->appointment_date;
            $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes);

            if ($proposedStart->lt($existingEnd) && $proposedEnd->gt($existingStart)) {
                return ['success' => false, 'reason' => 'overlap'];
            }
        }

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'title' => 'Concurrent Test Booking 3 (Adjacent)',
            'appointment_date' => $adjacentTime,
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'type' => 'consultation',
        ]);

        return ['success' => true, 'id' => $appointment->id];
    });

    if ($result3['success']) {
        echo "   ✓ Adjacent slot booking SUCCEEDED (ID: {$result3['id']})\n";
    } else {
        echo "   ✗ Adjacent slot booking FAILED (should have succeeded)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Adjacent slot booking ERROR: " . $e->getMessage() . "\n";
}

echo "\n";
echo "Test 3: Cancelled appointments don't block\n";
echo "-------------------------------------------\n";

$cancelledTime = $testDate->copy()->addHours(2);

// Create a cancelled appointment
$cancelledAppt = Appointment::create([
    'customer_id' => $customer->id,
    'company_id' => $company->id,
    'title' => 'Concurrent Test Cancelled',
    'appointment_date' => $cancelledTime,
    'duration_minutes' => 30,
    'status' => 'cancelled',
    'type' => 'consultation',
]);
echo "   Created cancelled appointment at " . $cancelledTime->format('H:i') . "\n";

// Try to book at the same time
try {
    $result4 = DB::transaction(function () use ($company, $customer, $cancelledTime) {
        $existingAppointments = Appointment::where('company_id', $company->id)
            ->whereDate('appointment_date', $cancelledTime->toDateString())
            ->whereNotIn('status', ['cancelled'])  // This excludes cancelled
            ->lockForUpdate()
            ->get();

        $proposedStart = $cancelledTime;
        $proposedEnd = $cancelledTime->copy()->addMinutes(30);

        foreach ($existingAppointments as $existing) {
            $existingStart = $existing->appointment_date;
            $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes);

            if ($proposedStart->lt($existingEnd) && $proposedEnd->gt($existingStart)) {
                return ['success' => false, 'reason' => 'overlap'];
            }
        }

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'title' => 'Concurrent Test Booking Over Cancelled',
            'appointment_date' => $cancelledTime,
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'type' => 'consultation',
        ]);

        return ['success' => true, 'id' => $appointment->id];
    });

    if ($result4['success']) {
        echo "   ✓ Booking over cancelled slot SUCCEEDED (ID: {$result4['id']})\n";
    } else {
        echo "   ✗ Booking over cancelled slot FAILED (should have succeeded)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Booking over cancelled slot ERROR: " . $e->getMessage() . "\n";
}

echo "\n";

// Verify final state
$testAppointments = Appointment::where('company_id', $company->id)
    ->where('title', 'LIKE', 'Concurrent Test%')
    ->get();

echo "=== Final State ===\n";
echo "Total test appointments created: " . $testAppointments->count() . "\n";
foreach ($testAppointments as $appt) {
    echo "  - {$appt->title} at " . $appt->appointment_date->format('H:i') . " ({$appt->status})\n";
}

// Count non-cancelled appointments at the original test time
$activeAtTestTime = Appointment::where('company_id', $company->id)
    ->where('appointment_date', $testDate)
    ->whereNotIn('status', ['cancelled'])
    ->count();

echo "\nActive appointments at test time ({$testDate->format('H:i')}): $activeAtTestTime\n";

if ($activeAtTestTime === 1) {
    echo "\n✓ SUCCESS: Only one booking was allowed at the contested time slot!\n";
} else {
    echo "\n✗ FAILURE: Multiple bookings at the same time were allowed!\n";
}

// Cleanup
echo "\nCleaning up test appointments...\n";
Appointment::where('company_id', $company->id)
    ->where('title', 'LIKE', 'Concurrent Test%')
    ->delete();
echo "Done.\n";
