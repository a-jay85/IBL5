---
description: Per-column matching strictness in check-column-rename-sweep to eliminate bareword false positives for common English / SQL-reserved words.
last_verified: 2026-07-22
---

# ADR-0071: Column-rename sweep matching precision (denylist design)

## Status

Accepted

## Context

`bin/check-column-rename-sweep` (the "Check for stale renamed-column references" step in
`.github/workflows/migration-safety.yml`) catches the PR #637 blind spot: a `scripts/` file
that referenced an old column name but was never touched by the rename PR, so PHPStan's
type-checker never saw it.

PR #1191 mechanized the sweep, switching from a diff-scoped check to a whole-tree grep with
word-boundary matching (`\b${old_name}\b`) over `ibl5/scripts/`, `ibl5/bin/`, and `ibl5/api.php`.
The mechanization is correct for normal column names (`legacyZorp`, `r_to`, `cy1`). However,
when a migration renames a column whose **old name is a common English word or SQL reserved
word** — `059_rename_trade_info_from_to.sql` renamed `from`/`to`; subsequent migrations
renamed `key`, `name`, `value`, `line`, etc. — the bareword grep matches every occurrence of
that word in PHP comments, shell loop variables (`while IFS= read -r line`), PHP variable
names (`$key`), and prose string literals (`'run from the command line'`). This produced
206 false-positive hits on PR #1078, failing `Schema Completeness` on every
migration-touching PR and rendering the gate unusable.

The existing false-positive filter (D2) does not help here: the old name is intentionally
absent from the live schema (the rename is complete), so the D2 filter correctly does NOT
skip it — the bareword grep then fires on all prose occurrences.

## Decision

Introduce **per-column matching strictness** keyed on a common-word denylist.

### Denylist

A bash variable `COMMON_WORD_DENYLIST` lists common English words and short SQL reserved
words: `from`, `to`, `key`, `name`, `value`, `line`, `order`, `set`, `group`, `by`,
`select`, `table`, `column`, `index`, `type`, `status`, `date`, `id`, `do`, `old`, `new`,
`home`, `year`.

`do` is a real rename in `ibl5/migrations/113_rename_reserved_word_rating_columns.sql`
(`do` → `r_drive_off`) and also the shell loop keyword, so bareword matching hit every
`; do` in `ibl5/bin/`. `old`/`new`/`home`/`year` are defensive: common English words that
appear in prose far more often than as unquoted column references.

### Matching rule

- **Old name IS in the denylist** — flag only when the token appears in an unambiguous SQL
  column-reference context: backtick-quoted (`` `from` ``) or as a quoted string token
  (`'from'` / `"from"`). This covers `$row['from']` and quoted SQL identifiers. Bare
  occurrences in comments, shell variables, PHP variables, and prose are ignored.

- **Old name is NOT in the denylist** — keep the existing bareword `\b${old_name}\b` match,
  so an unquoted `SELECT legacyZorp FROM …` in a `scripts/` file is still caught (the PR
  #637 blind spot this gate exists to defend).

### D4 — SQL comments are not rename statements

The rename-map parser strips everything from the first `--` or `#` on each migration line
before matching. A column name can never contain either token, so the truncation is
lossless. Without it, prose such as
`` -- is now `RENAME COLUMN IF EXISTS old TO new` followed by a guarded … ``
(`ibl5/migrations/113_rename_reserved_word_rating_columns.sql`) registered a phantom
`old` → `new` rename, which then matched `$change['old']`, `// old path`, and similar.

### D5 — the live-schema filter is case-insensitive

MySQL column names are case-insensitive, so a pure case change such as `Sim` → `sim`
(`ibl5/migrations/120_misc_snake_case_cleanup.sql`) cannot leave a stale reference behind.
The D2 filter therefore compares against `information_schema.COLUMNS` with `grep -qixF`.
Previously the case-sensitive compare made `Sim` look absent from the live schema, so it
fell through to bareword matching and hit English prose ("Sim date update", a Discord
notification label).

### Accepted residual

A literal unquoted reference to a denylisted column (e.g. `SELECT from FROM trade_info`)
is not flagged. This residual is acceptable because SQL's reserved-word quoting requirement
means any real column named `from`, `to`, `order`, etc. **must** be backtick-quoted to be
syntactically valid SQL — so a real reference is necessarily quoted and therefore still
caught by the new regex. The trade eliminates 206 prose false positives that make the gate
unusable, at the cost of a recall gap the SQL grammar itself nearly closes.

The per-PR bypass (`<!-- check-column-rename: … -->`) is preserved as an escape hatch for
genuine edge cases not covered by this rule.

## Consequences

- Migration PRs that rename common-word columns pass `Schema Completeness` without requiring
  per-PR bypass markers.
- Non-denylisted column renames (the typical case) continue to be caught by the bareword
  match — the PR #637 blind spot remains defended.
- `CheckColumnRenameSweepCliTest` covers each false-positive case (exit 0) and its matching
  true-positive counterpart (exit 1): the six denylist cases from the original rule, plus
  the commented-rename (D4), case-only-rename (D5), and shell-keyword `do` cases.
- The implementation is confined to `bin/check-column-rename-sweep`: the denylist constant,
  one conditional before the grep call, a comment strip in the rename-map parser (D4), and
  a `-i` flag on the D2 membership test (D5).
- D4/D5 (added 2026-07-22) close the residual that left `master` red on `Migration Safety`
  after PR #1583 merged: PR runs could bypass via the PR-body marker, but `push` events
  carry no PR body and so had no escape hatch.

## Lineage

Related: ADR-0069 (PR triage / auto-arm), which established the precedent for
mechanical-enforcement-surface decisions being ADR-worthy when they encode a
deliberate recall/precision trade-off.
