---
description: Free agency offer submission, market demand calculation, cap validation, and admin management.
last_verified: 2026-09-14
---

# FreeAgency

Manages the complete free agency workflow: GMs submit contract offers, market demand is calculated per player, cap implications are validated, and admins can manage the process. `FreeAgencyController` routes GET/POST requests; `FreeAgencyMarketDemandCalculator` computes demand values for each offer; `FreeAgencyCapCalculator` checks team salary cap impact; and `FreeAgencyProcessor` / `FreeAgencyAdminProcessor` commit accepted offers. See `ibl5/classes/FreeAgency/README_DEMAND_CALCULATOR.md` for demand calculation details. Entry point: `ibl5/modules/FreeAgency/index.php`.

| Class | Role |
|---|---|
| `FreeAgencyController` | Routes GET/POST for the module |
| `FreeAgencyMarketDemandCalculator` | Computes market demand for offer terms |
| `FreeAgencyCapCalculator` | Validates team salary cap implications |
| `FreeAgencyOfferValidator` / `CommonContractValidator` | Offer and contract validation |
| `FreeAgencyProcessor` / `FreeAgency\Admin\FreeAgencyAdminProcessor` | Commits or processes offers |
| `FreeAgencyDiscordDispatcher` | Posts free agency signings summaries to the #free-agency Discord channel |
| `FreeAgencyRepository` / `FreeAgencyDemandRepository` / `FreeAgency\Admin\FreeAgencyAdminRepository` | Database access |

## Admin/User boundary

The admin path is namespaced separately from the user-facing flow. `FreeAgencyAdminProcessor` and `FreeAgencyAdminRepository` live in `FreeAgency\Admin`, with their contracts in `FreeAgency\Admin\Contracts`. Their only production consumer is `ibl5/block.php`, which gates on admin authorization before constructing them. The boundary is enforced by `AdminBoundaryTest`, so adding a second construction site — or a user-facing class that reaches into `FreeAgency\Admin` — fails CI.

### Known residual

`OfferType` is not yet canonical — offer-type values are still represented inconsistently across the free agency path. That half of the original admin/user separation work is deferred and is **not** addressed by this change. The namespace split above does not depend on it, and fixing it later requires no change to the `FreeAgency\Admin` boundary.
