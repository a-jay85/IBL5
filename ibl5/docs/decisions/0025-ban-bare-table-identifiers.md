---
description: ADR for BanBareTableIdentifierRule PHPStan custom rule enforcing backtick-quoted table names
last_verified: 2026-05-15
---

# ADR-0025: Ban Bare Table Identifiers

**Status:** Accepted
**Date:** 2026-05-15

## Context

PHPStan's column-rename ban rules (`BanReservedWordColumnsRule`, `BanInconsistentColumnNamesRule`) only match backtick-quoted identifiers. Bare SQL table references like `FROM ibl_plr` escape detection entirely. Per `feedback_column_rename_sweep_scripts.md`, this means a future `RENAME TABLE` migration could silently produce zero-row reads against the old name, with no static-analysis signal.

An audit found ~330 bare `FROM/JOIN/UPDATE/INTO/DELETE FROM ibl_*` occurrences across `ibl5/classes/`.

## Decision

Add `BanBareTableIdentifierRule` (`ibl5/phpstan-rules/BanBareTableIdentifierRule.php`) that flags any string literal containing `FROM/JOIN/UPDATE/INTO/DELETE FROM ibl_*` without backticks. The rule only fires on files under `classes/` (excludes tests, migrations, scripts).

The sweep is split into two PRs:

- **PR A (this PR):** Rule + test + sweep of 31 low-touch modules (1-4 violations each). Baseline freezes the remaining ~264 violations in 17 high-touch modules.
- **PR B (future):** Sweep Team, Player, Trading, Updater, Api, League, FreeAgency, and other high-touch modules. Remove their baseline entries.

## Alternatives Considered

- **Query builder abstraction** — would eliminate bare identifiers structurally but requires a massive rewrite. Rejected as disproportionate to the immediate rename-safety goal.
- **Sweep all 330 sites in one PR** — high rebase risk and test-assertion churn. Rejected in favor of the two-PR split.

## Consequences

- Positive: New code cannot introduce bare table identifiers — the rule fires immediately on any new occurrence outside the baseline.
- Positive: Future table renames get full static-analysis coverage once PR B lands.
- Negative: 133 baseline entries added (temporary — PR B will remove them).
- Negative: Test assertions that match SQL strings must include backticks, adding minor friction.
