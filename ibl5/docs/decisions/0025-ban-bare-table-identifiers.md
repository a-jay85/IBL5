---
description: ADR for BanBareTableIdentifierRule PHPStan custom rule enforcing backtick-quoted table names
last_verified: 2026-07-22
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
- Positive: Future table renames get significant static-analysis coverage once PR B lands (see Known Gaps below).
- Negative: 133 baseline entries added (temporary — PR B will remove them).
- Negative: Test assertions that match SQL strings must include backticks, adding minor friction.

**Update 2026-07-22:** `phpstan-baseline.neon` now contains **0** `ibl.bareTableIdentifier` entries (measured 2026-07-22). The PR B high-touch module sweep has been completed and the baseline entries removed. Remaining grep hits for `FROM/JOIN/UPDATE/INTO/DELETE FROM ibl_*` without backticks in `classes/` are in PHPDoc comments, docblocks, and `.md` files — outside the `String_`-node scope the rule scans, so they are not caught (and not in the baseline). The rule and its file-scope restriction (`str_contains($file, '/classes/')`) are unchanged.

## Known Gaps

The rule processes `PhpParser\Node\Scalar\String_` nodes only — plain string literals. Two patterns escape detection:

1. **Interpolated strings** (`Encapsed` AST nodes) — e.g., `"SELECT pid, name FROM ibl_plr WHERE name IN ({$placeholders})"`. PHP-Parser emits an `Encapsed` node for any string containing `{$var}` or `$var`. PHPStan will not fire `ibl.bareTableIdentifier` on these. Workaround: use a parameterized `IN (?, ?, ...)` pattern with a plain string literal, or add `@phpstan-ignore-next-line ibl.bareTableIdentifier -- dynamic placeholder` if interpolation is unavoidable.

2. **Constant concatenation** — e.g., `"SELECT col FROM " . self::TABLE`. The `String_` fragment `"SELECT col FROM "` contains no `ibl_` name; the table name lives in a separate constant. The rule cannot see across the concatenation boundary. Workaround: embed the table name directly in the string literal, or use a backtick-prefixed constant value (`private const TABLE = '`ibl_votes_ASG`'`) — though the latter is unusual.

These gaps mean a rename of a table referenced only via interpolation or constant concatenation will not be caught by `ibl.bareTableIdentifier`. Before any `RENAME TABLE` migration, manually grep for both patterns in addition to relying on PHPStan.
