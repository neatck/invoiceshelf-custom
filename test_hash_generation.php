<?php

/**
 * Hash Generation Testing Script
 * Tests for collisions, cross-model conflicts, and validates configuration
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Estimate;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\EmailLog;
use App\Models\Transaction;
use Vinkla\Hashids\Facades\Hashids;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║         HASH COLLISION TESTING - COMPREHENSIVE SUITE          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$results = [
    'tests_run' => 0,
    'tests_passed' => 0,
    'tests_failed' => 0,
    'warnings' => [],
    'errors' => [],
];

// Test 1: Basic Hash Generation for Each Model
echo "TEST 1: Basic Hash Generation\n";
echo str_repeat("-", 64) . "\n";

$models = [
    'Invoice' => Invoice::class,
    'Payment' => Payment::class,
    'Estimate' => Estimate::class,
    'Appointment' => Appointment::class,
    'Company' => Company::class,
    'EmailLog' => EmailLog::class,
    'Transaction' => Transaction::class,
];

foreach ($models as $name => $class) {
    $results['tests_run']++;
    try {
        $hash = Hashids::connection($class)->encode(12345);
        $length = strlen($hash);
        
        echo sprintf("  %-15s : %s (length: %d)", $name, $hash, $length);
        
        if ($length == 30) {
            echo " ✓\n";
            $results['tests_passed']++;
        } else {
            echo " ✗ WARNING: Expected 30 chars!\n";
            $results['tests_failed']++;
            $results['warnings'][] = "$name hash is $length chars, expected 30";
        }
    } catch (Exception $e) {
        echo " ✗ ERROR: " . $e->getMessage() . "\n";
        $results['tests_failed']++;
        $results['errors'][] = "$name: " . $e->getMessage();
    }
}

echo "\n";

// Test 2: Generate 10,000 hashes per model and check for duplicates
echo "TEST 2: Collision Testing (10,000 hashes per model)\n";
echo str_repeat("-", 64) . "\n";

foreach ($models as $name => $class) {
    $results['tests_run']++;
    echo "  Testing $name... ";
    
    $hashes = [];
    $collisions = 0;
    $startTime = microtime(true);
    
    for ($i = 1; $i <= 10000; $i++) {
        $hash = Hashids::connection($class)->encode($i);
        
        if (isset($hashes[$hash])) {
            $collisions++;
            $results['errors'][] = "$name collision: ID $i and ID {$hashes[$hash]} both hash to $hash";
        } else {
            $hashes[$hash] = $i;
        }
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($collisions === 0) {
        echo "✓ No collisions ({$duration}ms)\n";
        $results['tests_passed']++;
    } else {
        echo "✗ CRITICAL: $collisions collisions found!\n";
        $results['tests_failed']++;
    }
}

echo "\n";

// Test 3: Cross-Model Collision Testing
echo "TEST 3: Cross-Model Collision Testing\n";
echo str_repeat("-", 64) . "\n";

$results['tests_run']++;
$crossCollisions = [];
$allHashes = [];

foreach ($models as $name => $class) {
    $hash = Hashids::connection($class)->encode(999);
    
    if (isset($allHashes[$hash])) {
        $crossCollisions[] = "$name hash matches {$allHashes[$hash]} for ID 999: $hash";
    }
    
    $allHashes[$hash] = $name;
    echo "  ID 999 → $name: $hash\n";
}

if (count($crossCollisions) === 0) {
    echo "\n  ✓ No cross-model collisions detected\n";
    $results['tests_passed']++;
} else {
    echo "\n  ✗ CRITICAL: Cross-model collisions found:\n";
    foreach ($crossCollisions as $collision) {
        echo "    - $collision\n";
        $results['errors'][] = $collision;
    }
    $results['tests_failed']++;
}

echo "\n";

// Test 4: Hash Reversibility
echo "TEST 4: Hash Reversibility (Decode Test)\n";
echo str_repeat("-", 64) . "\n";

foreach ($models as $name => $class) {
    $results['tests_run']++;
    $testId = 54321;
    
    try {
        $hash = Hashids::connection($class)->encode($testId);
        $decoded = Hashids::connection($class)->decode($hash);
        
        if (count($decoded) === 1 && $decoded[0] === $testId) {
            echo "  $name: $testId → $hash → {$decoded[0]} ✓\n";
            $results['tests_passed']++;
        } else {
            echo "  $name: DECODE FAILED ✗\n";
            $results['tests_failed']++;
            $results['errors'][] = "$name: Could not decode hash $hash back to $testId";
        }
    } catch (Exception $e) {
        echo "  $name: ERROR - {$e->getMessage()} ✗\n";
        $results['tests_failed']++;
        $results['errors'][] = "$name decode error: " . $e->getMessage();
    }
}

echo "\n";

// Test 5: Stress Test - Large IDs
echo "TEST 5: Large ID Stress Test\n";
echo str_repeat("-", 64) . "\n";

$largeIds = [1000000, 5000000, 10000000, 99999999];

foreach ($largeIds as $id) {
    $results['tests_run']++;
    $hash = Hashids::connection(Invoice::class)->encode($id);
    $decoded = Hashids::connection(Invoice::class)->decode($hash);
    
    if (count($decoded) === 1 && $decoded[0] === $id) {
        echo "  ID $id → " . substr($hash, 0, 20) . "... ✓\n";
        $results['tests_passed']++;
    } else {
        echo "  ID $id → FAILED ✗\n";
        $results['tests_failed']++;
        $results['errors'][] = "Large ID $id failed to encode/decode properly";
    }
}

echo "\n";

// Test 6: Alphabet Uniqueness
echo "TEST 6: Alphabet Configuration Validation\n";
echo str_repeat("-", 64) . "\n";

$config = config('hashids.connections');

foreach ($models as $name => $class) {
    $results['tests_run']++;
    
    if (!isset($config[$class]['alphabet'])) {
        echo "  $name: No alphabet configured ✗\n";
        $results['tests_failed']++;
        continue;
    }
    
    $alphabet = $config[$class]['alphabet'];
    $unique = count(array_unique(str_split($alphabet)));
    $total = strlen($alphabet);
    
    if ($unique === $total) {
        echo "  $name: $unique unique chars ✓\n";
        $results['tests_passed']++;
    } else {
        $duplicates = $total - $unique;
        echo "  $name: $duplicates duplicate chars found ✗\n";
        $results['tests_failed']++;
        $results['warnings'][] = "$name alphabet has $duplicates duplicate characters";
    }
}

echo "\n";

// Test 7: Salt Prefix Validation
echo "TEST 7: Model Prefix Validation\n";
echo str_repeat("-", 64) . "\n";

$expectedPrefixes = [
    Invoice::class => 'INV_',
    Payment::class => 'PAY_',
    Estimate::class => 'EST_',
    Appointment::class => 'APT_',
    Company::class => 'COM_',
    EmailLog::class => 'EML_',
    Transaction::class => 'TRX_',
];

foreach ($expectedPrefixes as $class => $prefix) {
    $results['tests_run']++;
    $salt = $config[$class]['salt'] ?? '';
    
    if (strpos($salt, $prefix) === 0) {
        echo "  " . class_basename($class) . ": Has '$prefix' prefix ✓\n";
        $results['tests_passed']++;
    } else {
        echo "  " . class_basename($class) . ": Missing '$prefix' prefix ✗\n";
        $results['tests_failed']++;
        $results['warnings'][] = class_basename($class) . " salt missing '$prefix' prefix";
    }
}

echo "\n";

// Test 8: Sequential ID Pattern Test
echo "TEST 8: Sequential ID Pattern Analysis\n";
echo str_repeat("-", 64) . "\n";

$results['tests_run']++;
echo "  Generating hashes for IDs 1-100...\n";

$hashes = [];
for ($i = 1; $i <= 100; $i++) {
    $hashes[$i] = Hashids::connection(Invoice::class)->encode($i);
}

// Check for obvious patterns
$patterns = 0;
for ($i = 1; $i <= 99; $i++) {
    similar_text($hashes[$i], $hashes[$i+1], $percent);
    if ($percent > 50) {
        $patterns++;
    }
}

if ($patterns < 5) {
    echo "  Low pattern similarity detected ✓\n";
    echo "  (Sequential hashes are sufficiently random)\n";
    $results['tests_passed']++;
} else {
    echo "  High pattern similarity detected ✗\n";
    echo "  ($patterns out of 99 pairs showed >50% similarity)\n";
    $results['tests_failed']++;
    $results['warnings'][] = "Sequential hashes may be too predictable";
}

echo "\n";

// FINAL SUMMARY
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST SUMMARY                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "  Total Tests:    {$results['tests_run']}\n";
echo "  Passed:         {$results['tests_passed']} ✓\n";
echo "  Failed:         {$results['tests_failed']} " . ($results['tests_failed'] > 0 ? "✗" : "") . "\n";
echo "\n";

$passRate = round(($results['tests_passed'] / $results['tests_run']) * 100, 1);
echo "  Pass Rate:      $passRate%\n";
echo "\n";

if ($passRate >= 95) {
    echo "  OVERALL STATUS: ✅ EXCELLENT - Production Ready\n";
} elseif ($passRate >= 80) {
    echo "  OVERALL STATUS: ⚠️  GOOD - Minor issues to address\n";
} elseif ($passRate >= 60) {
    echo "  OVERALL STATUS: ⚠️  FAIR - Several issues need fixing\n";
} else {
    echo "  OVERALL STATUS: 🔴 CRITICAL - Do NOT deploy!\n";
}

echo "\n";

// Show warnings
if (count($results['warnings']) > 0) {
    echo "WARNINGS:\n";
    foreach ($results['warnings'] as $i => $warning) {
        echo "  " . ($i + 1) . ". $warning\n";
    }
    echo "\n";
}

// Show errors
if (count($results['errors']) > 0) {
    echo "ERRORS:\n";
    foreach ($results['errors'] as $i => $error) {
        echo "  " . ($i + 1) . ". $error\n";
    }
    echo "\n";
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      TEST COMPLETE                             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Exit code based on results
exit($results['tests_failed'] > 0 ? 1 : 0);
