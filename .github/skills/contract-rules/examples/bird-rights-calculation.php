<?php

/**
 * Bird Rights Calculation Examples
 *
 * Demonstrates use of ContractRules for Bird Rights and raise calculations.
 */

use ContractRules;

// ============================================
// Checking Bird Rights Status
// ============================================

$yearsWithTeam = 4;

if (ContractRules::hasBirdRights($yearsWithTeam)) {
    echo "Player has Bird Rights (4 years with team)\n";
    // Player qualifies for higher raises and can be re-signed over the cap
} else {
    echo "Player does NOT have Bird Rights\n";
}
// Output: Player has Bird Rights (4 years with team)


// ============================================
// Getting Max Raise Percentage
// ============================================

$noBirdYears = 2;
$hasBirdYears = 5;

$noBirdRaise = ContractRules::getMaxRaisePercentage($noBirdYears);
$hasBirdRaise = ContractRules::getMaxRaisePercentage($hasBirdYears);

echo "No Bird Rights max raise: " . ($noBirdRaise * 100) . "%\n";
// Output: No Bird Rights max raise: 10%

echo "With Bird Rights max raise: " . ($hasBirdRaise * 100) . "%\n";
// Output: With Bird Rights max raise: 12.5%


// ============================================
// Calculating Multi-Year Contract Offers
// ============================================

$firstYearSalary = 500;
$years = 4;
$yearsWithTeam = 3;

$raisePercentage = ContractRules::getMaxRaisePercentage($yearsWithTeam);

$contract = [];
$currentSalary = $firstYearSalary;

for ($year = 1; $year <= $years; $year++) {
    $contract[$year] = (int) round($currentSalary);
    $currentSalary = $currentSalary * (1 + $raisePercentage);
}

echo "Contract with Bird Rights (12.5% raises):\n";
foreach ($contract as $year => $salary) {
    echo "  Year $year: \$$salary\n";
}
// Output:
// Year 1: $500
// Year 2: $563
// Year 3: $633
// Year 4: $712


// ============================================
// Comparing Bird vs Non-Bird Contract Value
// ============================================

function calculateContractTotal(int $firstYear, int $years, float $raisePercent): int
{
    $total = 0;
    $salary = $firstYear;
    
    for ($i = 0; $i < $years; $i++) {
        $total += (int) round($salary);
        $salary *= (1 + $raisePercent);
    }
    
    return $total;
}

$startingSalary = 800;
$contractYears = 5;

$noBirdTotal = calculateContractTotal(
    $startingSalary,
    $contractYears,
    ContractRules::STANDARD_RAISE_PERCENTAGE
);

$withBirdTotal = calculateContractTotal(
    $startingSalary,
    $contractYears,
    ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE
);

echo "5-year contract starting at \$800:\n";
echo "  Without Bird Rights: \$" . number_format($noBirdTotal) . "\n";
echo "  With Bird Rights: \$" . number_format($withBirdTotal) . "\n";
echo "  Bird Rights advantage: \$" . number_format($withBirdTotal - $noBirdTotal) . "\n";
// Output:
// 5-year contract starting at $800:
//   Without Bird Rights: $4,884
//   With Bird Rights: $5,089
//   Bird Rights advantage: $205


// ============================================
// Edge Cases: Exactly at Threshold
// ============================================

// 2 years = No Bird Rights
echo "2 years: " . (ContractRules::hasBirdRights(2) ? "Has" : "No") . " Bird Rights\n";
// Output: 2 years: No Bird Rights

// 3 years = Bird Rights (threshold)
echo "3 years: " . (ContractRules::hasBirdRights(3) ? "Has" : "No") . " Bird Rights\n";
// Output: 3 years: Has Bird Rights

// 0 years = No Bird Rights (new signing)
echo "0 years: " . (ContractRules::hasBirdRights(0) ? "Has" : "No") . " Bird Rights\n";
// Output: 0 years: No Bird Rights
