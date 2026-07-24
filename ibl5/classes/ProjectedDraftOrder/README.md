---
description: Projects end-of-season draft order by simulating playoff seedings and resolving tiebreakers.
last_verified: 2026-07-24
---

# ProjectedDraftOrder

Calculates and displays the projected draft order based on current standings, accounting for playoff seedings and tiebreaker rules. `PlayoffSeedingCalculator` computes playoff seedings used in draft order projection. `DraftOrderTiebreakerResolver` resolves tiebreaker scenarios between teams, delegating non-head-to-head cases to `NonHeadToHeadTiebreaker`. Entry point: `ibl5/modules/ProjectedDraftOrder/index.php`.

| Class | Role |
|---|---|
| `ProjectedDraftOrderService` | Orchestrates projection logic |
| `ProjectedDraftOrderRepository` | Fetches standings and record data |
| `ProjectedDraftOrderView` | Renders the projected order table |
| `PlayoffSeedingCalculator` | Computes playoff seedings |
| `DraftOrderTiebreakerResolver` | Resolves tied draft positions |
| `NonHeadToHeadTiebreaker` | Handles non-H2H tiebreaker cases |
