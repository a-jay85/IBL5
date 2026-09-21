---
description: Head-to-head win/loss matrix across franchises, teams, and GMs for any game phase and scope.
last_verified: 2026-09-21
---

# HeadToHeadRecords Module

Displays a head-to-head win/loss matrix showing how every franchise, team era, or GM has fared
against every other across selectable phases (HEAT, Regular Season, Playoffs, All) and scopes
(Current Season, All-Time).

## Architecture

```
HeadToHeadRecords/
├── Contracts/
│   └── HeadToHeadRecordsRepositoryInterface.php  # Data contract (MatrixPayload shape)
├── HeadToHeadRecordsRepository.php               # SQL query builder + matrix assembly
├── CachedHeadToHeadRecordsRepository.php         # 24-hour DatabaseCache decorator
├── LogoResolver.php                              # Logo file resolution with fallback chain
├── HeadToHeadRecordsController.php              # Filter resolution + main() orchestration
└── HeadToHeadRecordsView.php                    # HTML rendering (form + matrix table)
```

## Key design decisions

- **No login gate.** The matrix is public; logged-in users get their own row/column highlighted.
- **GM match via owner_name.** The `gms` dimension matches the logged-in user by looking up
  `owner_name` from `ibl_team_info` rather than by username, avoiding the GM-match bug present
  in earlier drafts.
- **Migration 182.** Migration 182 creates the `ibl_franchise_era_branding` table, which stores
  color and identity overrides for retired franchise eras. The module reads from the shared
  DatabaseCache table, populated on first warm-up or when `RefreshHeadToHeadRecordsStep` runs.
- **CSS contract.** The view emits class names from `ibl5/design/components/head-to-head-records.css`
  and the site-wide shared patterns: `sticky-scroll-wrapper`, `h2h-matrix`, `h2h-row-label`,
  `h2h-winning`, `h2h-losing`, `h2h-tied`, `h2h-self`, `h2h-user-row`,
  `table-empty-message`, `h2h-filter`, `h2h-tip-open`.

## Filter defaults

| Filter    | Default                                          |
|-----------|--------------------------------------------------|
| dimension | `franchises`                                     |
| phase     | Mapped from `Season::$phase` via `SEASON_PHASE_TO_FILTER`; falls back to `all` |
| scope     | `current`                                        |

## Data flow

```
modules/HeadToHeadRecords/index.php
  -> HeadToHeadRecordsController::main()
       -> resolveFilters($_POST)
       -> CachedHeadToHeadRecordsRepository::build{Franchises|Teams|Gms}Matrix()
            -> HeadToHeadRecordsRepository (on cache miss)
       -> HeadToHeadRecordsView::renderFilterForm()
       -> HeadToHeadRecordsView::renderMatrix()
       -> HeadToHeadRecordsView::renderTapTooltipScript()
```
