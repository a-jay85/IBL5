---
name: contract-rules
description: IBL CBA salary cap rules including Bird Rights, veteran minimums, and maximum contracts. Use when working on extensions, free agency, negotiations, or salary calculations.
---

# IBL Contract Rules (CBA)

Salary cap rules from `ContractRules` class. Used by Extension, FreeAgency, Negotiation, and Waivers modules.

## Bird Rights

### Threshold
Player must play **3+ consecutive years** with the same team.

### Raise Percentages
| Status | Max Annual Raise |
|--------|------------------|
| Without Bird Rights | 10% |
| With Bird Rights | 12.5% |

### Helper Methods
```php
use ContractRules;

$hasBird = ContractRules::hasBirdRights($yearsWithTeam); // bool
$raisePercent = ContractRules::getMaxRaisePercentage($yearsWithTeam); // 0.10 or 0.125
```

## Veteran Minimum Salaries

| Experience | Minimum Salary |
|------------|---------------|
| 10+ years | $103 |
| 9 years | $100 |
| 8 years | $89 |
| 7 years | $82 |
| 6 years | $76 |
| 5 years | $70 |
| 4 years | $64 |
| 3 years | $61 |
| 2 years | $51 |
| 1 year (rookie) | $35 |

```php
$minSalary = ContractRules::getVeteranMinimumSalary($experience);
```

## Maximum Contract Salaries (First Year)

| Experience | Max First-Year Salary |
|------------|----------------------|
| 10+ years | $1,451 |
| 7-9 years | $1,275 |
| 0-6 years | $1,063 |

```php
$maxSalary = ContractRules::getMaxContractSalary($experience);
```

## Salary Exceptions

### Mid-Level Exception (MLE)
6-year contract with 10% annual raises:
| Year | Amount |
|------|--------|
| 1 | $450 |
| 2 | $495 |
| 3 | $540 |
| 4 | $585 |
| 5 | $630 |
| 6 | $675 |

```php
$mleAmounts = ContractRules::getMLEOffers($years); // array of amounts
```

### Lower-Level Exception (LLE)
Maximum: **$145**

```php
$lleAmount = ContractRules::LLE_OFFER; // 145
```

## Usage Example

```php
use ContractRules;

// Calculate max offer for a player
$experience = 8;
$yearsWithTeam = 4;

if (ContractRules::hasBirdRights($yearsWithTeam)) {
    $maxRaise = ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE; // 0.125
    $maxFirstYear = ContractRules::getMaxContractSalary($experience); // 1275
} else {
    $maxRaise = ContractRules::STANDARD_RAISE_PERCENTAGE; // 0.10
}

// Or get veteran minimum as floor
$minOffer = ContractRules::getVeteranMinimumSalary($experience); // 89
```

## Examples

See [examples/](./examples/) for calculations:
- [bird-rights-calculation.php](./examples/bird-rights-calculation.php)
- [veteran-minimum-table.php](./examples/veteran-minimum-table.php)
