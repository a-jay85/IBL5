---
description: Why the six-slot salary, demand, and offer columns stay wide instead of moving to child tables; records the engine coupling, the measured cost, the rejected options, and the thresholds that would reopen the question.
last_verified: 2026-10-06
---

# ADR-0176: Contract-year columns stay wide

**Status:** Accepted
**Date:** 2026-10-06
**Deciders:** ajaynicolas

## Context

Backlog issue a-jay85/IBL5-backlog#215 asked to replace the six-column year arrays with child tables keyed by `year_number`. Its premise is that a repeated group of columns breaks first normal form. Its stated risk is that "a 7th contract year requires DDL".

Migration 119 (PR #641) already renamed `cy1`..`cy6` to `salary_yr1`..`salary_yr6`, so the opaque-name half of the complaint is resolved. The six-slot shape remains on these tables:

- `salary_yr1`..`salary_yr6` on `ibl_plr`, `ibl_olympics_plr`, `ibl_plr_snapshots`, `ibl_hist`, `ibl_cash_considerations`, and `ibl_trade_cash` (all six are altered in `ibl5/migrations/119_rename_cy1_cy6_to_salary_yr1_yr6.sql`).
- `dem1`..`dem6` on `ibl_demands`.
- `offer1`..`offer6` on `ibl_fa_offers`.

Measurements taken on 2026-10-06 from the worktree root. `git grep` reads tracked files only, so the counts do not depend on vendor or cache directories.

| Fact | Command | Value |
|------|---------|-------|
| PHP files naming a `salary_yr` column | `git grep -lE 'salary_yr[1-6]' -- 'ibl5/*.php' \| wc -l` | 141 |
| PHP lines naming a `salary_yr` column | `git grep -hE 'salary_yr[1-6]' -- 'ibl5/*.php' \| wc -l` | 1337 |
| Test files among those PHP files | `git grep -lE 'salary_yr[1-6]' -- 'ibl5/*.php' \| grep -c '/tests/'` | 91 |
| Class files naming a `salary_yr` column | `git grep -lE 'salary_yr[1-6]' -- 'ibl5/classes/*.php' \| wc -l` | 47 |
| Class lines naming a `salary_yr` column | `git grep -hE 'salary_yr[1-6]' -- 'ibl5/classes/*.php' \| wc -l` | 253 |
| Non-test PHP files naming a `dem` or `offer` slot | `git grep -lP '\b(dem\|offer)[1-6]\b' -- 'ibl5/*.php' \| grep -vc '/tests/'` | 28 |
| Test PHP files naming a `dem` or `offer` slot | `git grep -lP '\b(dem\|offer)[1-6]\b' -- 'ibl5/*.php' \| grep -c '/tests/'` | 31 |

The player-table slots mirror the JSB engine's `.plr` record, which is fixed-width and owned by the external engine. `PlrLineParser` reads six 4-byte salary slots at offsets 298 through 318 (`ibl5/classes/PlrParser/PlrLineParser.php:132` is the sixth). `PlrFileWriter` declares `OFFSET_SALARY_YR1` through `OFFSET_SALARY_YR6` at the same offsets and writes the slots back on export (`ibl5/classes/PlrParser/PlrFileWriter.php:66` is the sixth). `PlrExportService::exportPlrFile` in `ibl5/classes/PlrParser/PlrExportService.php` diffs the database against the `.plr` file, and `ibl5/scripts/jsbExport.php` calls it.

The app caps demands and offers at six years in several places:

- The `FreeAgencyOfferValidator` raise-and-gap loop runs years 2 through 6 (`ibl5/classes/FreeAgency/FreeAgencyOfferValidator.php:282`).
- `OfferType::calculateYears` in `ibl5/classes/FreeAgency/OfferType.php` counts down from `offer6`.
- The demand CSV import expects the header `name,dem1..dem6` (`ibl5/import-demands.php:92`).

Several read paths would change shape under normalization. The public API reads `SELECT * FROM vw_player_current` in `ibl5/classes/Api/Repository/ApiPlayerRepository.php`, so the six columns are part of its response rows. `ibl5/classes/Repositories/SalaryCapRepository.php` reads `vw_current_salary`, and `vw_free_agency_offers` selects `fa.offer1` through `fa.offer6`.

Some sites build column names at runtime, so a grep-driven sweep misses them:

- String concatenation of the year number: `TeamCapCalculator`, `ContractListService`, and `FutureSalaryCalculator`.
- A chosen column literal: `TradeExecutionService` picks `salary_yr1` or `salary_yr2`. `RookieOptionRepository` concatenates `salary_yr3` or `salary_yr4` into an SQL identifier.
- Positional `CASE ... WHEN 1 THEN salary_yr1` expressions in `League.php`, `TradeFormRepository`, and `RefreshIblHistStep`.

## Decision

The six-slot salary, demand, and offer columns stay wide. Backlog #215 closes by this record.

The player-table slots mirror the `.plr` record the app writes back to the engine. A seventh year cannot exist without an engine format change. Child tables therefore buy no capability, and the issue's stated risk does not apply.

Normalizing only the app-owned tables (`ibl_demands` and `ibl_fa_offers`) would leave the schema inconsistent. A seven-year offer would have no contract slot to land in.

The cost is high. The sweep covers the 141 PHP files measured above, plus the dynamic sites that grep cannot find. The public API row shape changes through `vw_player_current`. Every read and every `.plr` export gains a pivot between rows and slots.

Migration 119 already resolved the naming complaint, which was the cheap half of the issue.

The per-column CHECK constraints repeat once per slot (`chk_plr_salary_yr1` through `chk_plr_salary_yr6`, ending at `ibl5/migrations/119_rename_cy1_cy6_to_salary_yr1_yr6.sql:87`). That repetition is accepted as the cost of the wide shape. `ibl5/config/schema-assertions.php` asserts the six columns exist.

### Reopen thresholds

Reopen this decision when any one of these holds:

- The JSB engine `.plr` format gains a seventh salary slot.
- A league rule allows contracts longer than six years.
- The salary slot count in `PlrFileWriter` changes from six.

## Alternatives Considered

**Full child tables as the issue proposed.** This means `ibl_contract_years`, `ibl_demand_years`, and `ibl_offer_years`, each keyed by `year_number`. Rejected. It pays the full sweep and API cost for a seventh year the engine record cannot carry.

**Normalize only `ibl_demands` and `ibl_fa_offers`.** These two tables have no engine coupling. Rejected because a seven-year demand or offer would still need a six-slot contract to land in. The validator loop and the demand CSV header would stay at six, and the schema would carry two shapes for one concept.

**Child tables behind compatibility views.** Views could expose the wide shape so readers stay unchanged. Rejected. Every writer, including the `.plr` export and the demand CSV import, still writes six fixed slots. The views would add a pivot to every read and give no new capability.

**Generated or virtual columns.** Rejected. They restate the same six slots and leave the repeated group in place.

## Consequences

- Positive: no code, API, or export change. Readers and writers keep matching the engine record one slot to one column.
- Positive: the reopen thresholds are concrete and checkable against `PlrFileWriter`.
- Negative: the schema keeps six columns and six CHECK constraints per table for one concept, and new code keeps writing `salary_yr1`..`salary_yr6` by name.
- A future seventh-year change must touch the `.plr` offsets in `PlrFileWriter` and `PlrLineParser`, every table listed in Context, the views, the validator loop, `OfferType::calculateYears`, the demand CSV header, and the dynamic-name sites. Re-run the Context table commands to size it.
- This decision changes no code, config, migration, or test. The ADR is the only file in the change.

## Supersedes

None.

## References

- a-jay85/IBL5-backlog#215 (slug `contract-years-1nf-215`)
- PR #641 (migration 119, `cy1`..`cy6` renamed to `salary_yr1`..`salary_yr6`)
- ADR-0170 (format model for a measured stay-as-is decision)
- `ibl5/classes/PlrParser/PlrFileWriter.php:66`
- `ibl5/classes/PlrParser/PlrLineParser.php:132`
- `ibl5/classes/FreeAgency/FreeAgencyOfferValidator.php:282`
- `ibl5/import-demands.php:92`
- `ibl5/migrations/119_rename_cy1_cy6_to_salary_yr1_yr6.sql:87`
- `ibl5/classes/PlrParser/PlrExportService.php`
- `ibl5/classes/Api/Repository/ApiPlayerRepository.php`
- `ibl5/config/schema-assertions.php`
