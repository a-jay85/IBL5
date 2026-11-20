---
description: The sql-equivalent token also clears a new read-only Repository query to the second reviewer, when a static scan and a committed equivalence test back it.
last_verified: 2026-11-18
---

# ADR-0265: A new read-only Repository query can reach the second reviewer

**Status:** Accepted
**Date:** 2026-11-18

## Context

PRs [#3815](https://github.com/a-jay85/IBL5/pull/3815), [#3870](https://github.com/a-jay85/IBL5/pull/3870) and [#3985](https://github.com/a-jay85/IBL5/pull/3985) each held only because they added a new SQL construction site. All three merged with zero code change after the last post-plan run. ADR-0248 lets the `sql-equivalent` token clear only a pure rewrite of an existing query, so a speed-up that adds a helper query still waited for the owner.

## Decision

The `sql-equivalent` evidence file gains a `kind` field: `rewrite` keeps the ADR-0248 behavior, and `new-query` covers an added read-only query. A `new-query` file clears the plan trigger to the second reviewer only when all four checks pass:

1. Every changed file is an `ibl5/classes/**/*Repository.php` file or sits under `ibl5/tests/`.
2. The static scan in `tools/postplan-harness/harness/sql_readonly_scan.py` finds no unbound interpolation, no write keyword, and no actor-identity read on the added lines.
3. A committed `ibl5/tests/DatabaseIntegration/<X>Test.php::<method>` equivalence test is in the diff and the method exists.
4. The ADR-0248 checks pass: HASH before/after dumps, a bind test, and blob-SHA freshness.

Any failure or scan error keeps the owner hold, because `arm_input_value` passes only an exact "ok". Both kinds now run the scan over every changed non-test PHP file, so a rewrite that concatenates a user value into SQL also holds. The scan follows a SQL string through later `.=` and `.` lines, checks every added string for a write keyword (class constants included), and treats a local as written when it goes to `extract()`, a `foreach` by reference, an outside function call, or a same-file method's `&$param`. `sql_equivalence.py record` requires `--kind`, and `new-query` also requires `--equivalence-test`.

## Allowlisted constructs

The scan treats these as safe. Each comes from the three target PRs.

- Class constants and constant arithmetic, such as `League::MAX_REAL_TEAMID` and `(self::CANDIDATE_LIMIT - 1)`.
- Constant-array lookups, such as `self::STAT_INDEXES[$expression] ?? null` feeding `FORCE INDEX`.
- Literal ternaries, such as `$config['order'] === 'ASC' ? 'ASC' : 'DESC'`.
- `implode` over an array whose every append is a literal, such as `$tuples[] = '(b.visitor_teamid = ? AND b.home_teamid = ?)'`.
- `sprintf` with a literal format and literal or safe args, such as `sprintf($any, 'b.visitor_teamid', 'b.season_year')`.
- Private-method params whose every in-file call site passes a safe value or relies on a literal default, such as `gamesCte(..., string $indexHint = '')`.
- Names the base file already interpolated into SQL, such as `$expression`, `$dateFilter`, `$safeLabel` and `$extraWhere`. This trust holds only in a method the base file had, and never for a param that method lacked in the base. A `$this->prop` name keeps it in any method. An added assignment to one of these names is still judged on its own value.

## Backtest

The replay test `tools/postplan-harness/tests/test_sql_readonly_replay.py` runs the scan over the five PR fixtures. #3985 returns "ok". #3815 holds on an `implode(...)` at `PlayerRecordRepository.php:214`, because its batch method calls helpers the PR changed, so the unchanged SQL is judged again. #3870 holds on `$safeLabel`, built from `$label` inside a new private method, so the base file's use of `$safeLabel` does not vouch for it, and `$label` comes from a public method's argument. Both stay owner-held. [#3816](https://github.com/a-jay85/IBL5/pull/3816) and [#3820](https://github.com/a-jay85/IBL5/pull/3820) touch `ibl5/api.php`, so the path check holds them.

## Not covered

- Call sites in other files into a public Repository method.
- Reuse of a grandfathered variable name with a new source in a method the diff does not touch.
- A reference assignment (`$a = &$b`) that lets a later write to `$a` change `$b`.
- Whether the equivalence test really compares old rows with new rows. The second reviewer judges that, and CI runs the test.

Auth-guard, UI and engine-golden holds are unchanged.

## Consequences

- Positive: a read-only Repository speed-up with committed evidence can merge without the owner.
- Positive: a rewrite that splices a user value into SQL now holds, where ADR-0248 let it pass.
- Negative: the scan is a hand-written PHP lexer and expression parser. A construct it cannot parse returns an `error:` note and holds, so new PHP syntax can cause false holds.
- The hold-trigger vocabulary is unchanged. This PR itself holds for the owner, because it changes the rule that governs its own merge.

## References

- `ibl5/docs/decisions/0248-sql-equivalent-clearable-hold.md`
- `tools/postplan-harness/harness/sql_equivalence.py`
- `tools/postplan-harness/harness/sql_readonly_scan.py`
- `tools/postplan-harness/tests/test_sql_readonly_scan.py`
- `tools/postplan-harness/tests/test_sql_readonly_replay.py`
