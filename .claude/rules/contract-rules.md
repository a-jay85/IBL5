---
paths:
  - "ibl5/classes/Extension/**/*"
  - "ibl5/classes/FreeAgency/**/*"
  - "ibl5/classes/FreeAgencyPreview/**/*"
  - "ibl5/classes/Negotiation/**/*"
  - "ibl5/classes/Waivers/**/*"
  - "ibl5/classes/ContractList/**/*"
---

# IBL Contract Rules (CBA)

Salary cap rules from `ContractRules` class. Used by Extension, FreeAgency, Negotiation, and Waivers modules.

## Bird Rights

Player must play **3+ consecutive years** with the same team.

| Status | Max Annual Raise |
|--------|------------------|
| Without Bird Rights | 10% |
| With Bird Rights | 12.5% |

## Veteran Minimum Salaries

| Experience | Min Salary | | Experience | Min Salary |
|------------|-----------|---|------------|-----------|
| 10+ years | $103 | | 5 years | $70 |
| 9 years | $100 | | 4 years | $64 |
| 8 years | $89 | | 3 years | $61 |
| 7 years | $82 | | 2 years | $51 |
| 6 years | $76 | | 1 year (rookie) | $35 |

## Maximum Contract (First Year)

| Experience | Max First-Year |
|------------|---------------|
| 10+ years | $1,451 |
| 7-9 years | $1,275 |
| 0-6 years | $1,063 |

## Salary Exceptions

- **MLE:** 6-year, 10% annual raises. Year 1: $450, Year 6: $675.
- **LLE:** Maximum $145 (`ContractRules::LLE_OFFER`).

## Key Methods

```php
ContractRules::hasBirdRights(int $yearsWithTeam): bool
ContractRules::getMaxRaisePercentage(int $yearsWithTeam): float  // 0.10 or 0.125
ContractRules::getVeteranMinimumSalary(int $experience): int
ContractRules::getMaxContractSalary(int $experience): int
ContractRules::getMLEOffers(int $years): array
```
