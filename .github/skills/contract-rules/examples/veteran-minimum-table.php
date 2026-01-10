<?php

/**
 * Veteran Minimum Salary Table Examples
 *
 * Demonstrates use of ContractRules for minimum and maximum salary lookups.
 */

use ContractRules;

// ============================================
// Looking Up Veteran Minimums
// ============================================

$rookieMin = ContractRules::getVeteranMinimumSalary(1);
$secondYearMin = ContractRules::getVeteranMinimumSalary(2);
$veteranMin = ContractRules::getVeteranMinimumSalary(10);

echo "Rookie minimum (1 year): \$$rookieMin\n";
// Output: Rookie minimum (1 year): $35

echo "Second year minimum: \$$secondYearMin\n";
// Output: Second year minimum: $51

echo "10+ year veteran minimum: \$$veteranMin\n";
// Output: 10+ year veteran minimum: $103


// ============================================
// Full Veteran Minimum Table
// ============================================

echo "\nVeteran Minimum Salary Table:\n";
echo str_repeat("-", 30) . "\n";

foreach (ContractRules::VETERAN_MINIMUM_SALARIES as $years => $salary) {
    $yearsLabel = $years === 10 ? "10+" : (string) $years;
    echo sprintf("  %3s years: \$%d\n", $yearsLabel, $salary);
}
// Output:
//  10+ years: $103
//    9 years: $100
//    8 years: $89
//  ... etc


// ============================================
// Maximum Contract Salaries
// ============================================

$youngMax = ContractRules::getMaxContractSalary(5);    // 0-6 years
$midMax = ContractRules::getMaxContractSalary(8);      // 7-9 years
$veteranMax = ContractRules::getMaxContractSalary(12); // 10+ years

echo "\nMaximum Contract Salaries (First Year):\n";
echo "  0-6 years experience: \$" . number_format($youngMax) . "\n";
echo "  7-9 years experience: \$" . number_format($midMax) . "\n";
echo "  10+ years experience: \$" . number_format($veteranMax) . "\n";
// Output:
//   0-6 years experience: $1,063
//   7-9 years experience: $1,275
//   10+ years experience: $1,451


// ============================================
// Mid-Level Exception Offers
// ============================================

echo "\nMid-Level Exception (6-year contract):\n";
$mleOffers = ContractRules::getMLEOffers(6);

$total = 0;
foreach ($mleOffers as $year => $amount) {
    $yearNum = $year + 1;
    echo "  Year $yearNum: \$$amount\n";
    $total += $amount;
}
echo "  Total: \$" . number_format($total) . "\n";
// Output:
//   Year 1: $450
//   Year 2: $495
//   Year 3: $540
//   Year 4: $585
//   Year 5: $630
//   Year 6: $675
//   Total: $3,375


// ============================================
// Shorter MLE Contract
// ============================================

echo "\nMid-Level Exception (3-year contract):\n";
$shortMle = ContractRules::getMLEOffers(3);

foreach ($shortMle as $year => $amount) {
    echo "  Year " . ($year + 1) . ": \$$amount\n";
}
// Output:
//   Year 1: $450
//   Year 2: $495
//   Year 3: $540


// ============================================
// Lower-Level Exception
// ============================================

echo "\nLower-Level Exception: \$" . ContractRules::LLE_OFFER . "\n";
// Output: Lower-Level Exception: $145


// ============================================
// Practical Example: Determining Contract Range
// ============================================

function getContractRange(int $experience, int $yearsWithTeam): array
{
    $minimum = ContractRules::getVeteranMinimumSalary($experience);
    $maximum = ContractRules::getMaxContractSalary($experience);
    $raisePercent = ContractRules::getMaxRaisePercentage($yearsWithTeam);
    $hasBird = ContractRules::hasBirdRights($yearsWithTeam);
    
    return [
        'minimum' => $minimum,
        'maximum' => $maximum,
        'raise_percent' => $raisePercent * 100,
        'has_bird_rights' => $hasBird,
    ];
}

$range = getContractRange(8, 4);

echo "\nContract Range for 8-year veteran with 4 years on team:\n";
echo "  Minimum: \$" . $range['minimum'] . "\n";
echo "  Maximum: \$" . number_format($range['maximum']) . "\n";
echo "  Max Raise: " . $range['raise_percent'] . "%\n";
echo "  Bird Rights: " . ($range['has_bird_rights'] ? "Yes" : "No") . "\n";
// Output:
//   Minimum: $89
//   Maximum: $1,275
//   Max Raise: 12.5%
//   Bird Rights: Yes
