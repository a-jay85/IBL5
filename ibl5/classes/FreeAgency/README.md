---
description: Free agency offer submission, market demand calculation, cap validation, and admin management.
last_verified: 2026-07-24
---

# FreeAgency

Manages the complete free agency workflow: GMs submit contract offers, market demand is calculated per player, cap implications are validated, and admins can manage the process. `FreeAgencyController` routes GET/POST requests; `FreeAgencyMarketDemandCalculator` computes demand values for each offer; `FreeAgencyCapCalculator` checks team salary cap impact; and `FreeAgencyProcessor` / `FreeAgencyAdminProcessor` commit accepted offers. See `ibl5/classes/FreeAgency/README_DEMAND_CALCULATOR.md` for demand calculation details. Entry point: `ibl5/modules/FreeAgency/index.php`.

| Class | Role |
|---|---|
| `FreeAgencyController` | Routes GET/POST for the module |
| `FreeAgencyMarketDemandCalculator` | Computes market demand for offer terms |
| `FreeAgencyCapCalculator` | Validates team salary cap implications |
| `FreeAgencyOfferValidator` / `CommonContractValidator` | Offer and contract validation |
| `FreeAgencyProcessor` / `FreeAgencyAdminProcessor` | Commits or processes offers |
| `FreeAgencyRepository` / `FreeAgencyDemandRepository` / `FreeAgencyAdminRepository` | Database access |
