---
description: ADR-0290. IBL5 will not build an allowlist scan that lets a plan adding a new read-only SQL query skip the owner hold. A premise count found that a strict allowlist would have cleared 0 of the 4 speed-up PRs that added a query, and a looser one only 1. Every new query keeps the owner hold, and the ADR-0248 rewrite lane stays as it is.
last_verified: 2026-11-27
---

# ADR-0290: Decline an allowlist scan for new SQL queries

**Status:** Accepted
**Date:** 2026-11-27

## Context

Gate 14(b) of `/plan` holds a plan that adds a SQL construction site until the owner merges it. ADR-0248 lets the second-review seat clear the hold for a pure rewrite of an existing query when a committed equivalence file backs it. A plan that adds a new query gets no clearable token, so the owner merges it by hand.

PR [#4050](https://github.com/a-jay85/IBL5/pull/4050) tried to let new read-only queries past that hold. It added a deny-list scan, `sql_readonly_scan.py`, of 1782 lines. Over 11 review rounds the reviewers found 38 bypasses in 34 classes. The owner closed the PR unmerged on 2026-11-19, and its ADR-0265 never reached master.

Backlog issue [#1938](https://github.com/a-jay85/IBL5-backlog/issues/1938) asked for the scan again as an allowlist. The allowlist would accept a query only when its SQL is a literal and every value is a bound parameter, with no exemption for the queries a file already holds. The issue asked for a check first: would enough real speed-ups fit that shape to pay for the scanner?

The planning session ran that check on 2026-11-27 over the 17 `perf` PRs merged since 2026-08-01. Seven of them changed a query call line. The check replayed two allowlist shapes:

- Strict shape. The SQL argument is a literal, a concatenation of literals, a constant of the same class, or a local variable built only from those. A `throw` match arm is ignored.
- Loose shape. The strict shape, plus every constant from another class trusted.

Four PRs added a query call line in the `ibl5/classes` files they changed:

| PR | Query call lines, parent to head | Strict shape | Loose shape |
|---|---|---|---|
| [#3815](https://github.com/a-jay85/IBL5/pull/3815) | 4 to 5 | held | held |
| [#3870](https://github.com/a-jay85/IBL5/pull/3870) | 6 to 7 | held | held |
| [#3985](https://github.com/a-jay85/IBL5/pull/3985) | 12 to 13 | held | held |
| [#3944](https://github.com/a-jay85/IBL5/pull/3944) | 10 to 14 | held | cleared |

The strict shape cleared 0 of the 4. The loose shape cleared 1, PR 3944. That PR builds its queries in `OnThisDayMomentBuilder.php`, which is outside any repository file. A clearance limited to repository files, the limit PR 4050 used, would still hold it.

Most of the held SQL is built at run time. The rivalry queries in PR 3985 come from a helper that runs `sprintf` over a string parameter. PRs 3815 and 3870 join query parts with `implode`. PR 3944 fails the strict shape only because it reads a constant from another class.

Three more PRs, [#4186](https://github.com/a-jay85/IBL5/pull/4186), [#3840](https://github.com/a-jay85/IBL5/pull/3840) and [#3143](https://github.com/a-jay85/IBL5/pull/3143), rewrote queries without adding one. That kind of change belongs to the ADR-0248 lane. PRs 3840 and 3143 fit both shapes, and PR 4186 fails both.

The call line counts come from a grep over the changed files, so each may be off by a line or two. The replay read only the first argument of each query call, which can pass a query by mistake. No evidence file for the ADR-0248 lane was committed on master as of 2026-11-27. A scanner pays off only if most query-adding PRs clear it. At most 1 of the 4 did.

## Decision

IBL5 will not build an allowlist scan for new SQL queries, and adds no clearable token for them. A plan that adds a SQL construction site keeps the owner hold under gate 14(b). The ADR-0248 lane for pure rewrites stays as it is, with its token `sql-equivalent` and its evidence check in `tools/postplan-harness/harness/sql_equivalence.py`.

The count script ran once and stays out of the repo. The meta-tooling bar in `.claude/rules/meta-tooling-bar.md` keeps one-time checks out of `bin/`.

Revisit this decision when a new count over the merged PRs finds at least 3 query-adding PRs that the strict shape clears. Rerun the replay with the two shapes above before anyone plans a scanner.

## Alternatives Considered

- Build the strict allowlist. Rejected because it would have cleared 0 of the 4 query-adding PRs, and it would still need the upkeep and bypass review that PR 4050 needed.
- Build the loose allowlist. Rejected because it cleared only 1 of the 4, it cleared 0 once limited to repository files, and trusting every constant from another class widens what a reviewer must check.
- Close the issue with a comment and write no ADR. Rejected because the evidence report sits outside the repo, so the next planner would run the count again.

## Consequences

- Positive: IBL5 maintains no scanner, and review rounds have no new bypass surface to search.
- Positive: the owner-held vocabulary is unchanged. Every new query still reaches the owner.
- Negative: a speed-up PR that adds a query still waits for an owner merge, as the four PRs above did.
- Negative: the counts are approximate, so a reader who doubts them has to rerun the replay.

## References

- `ibl5/docs/decisions/0248-sql-equivalent-clearable-hold.md`
- `tools/postplan-harness/harness/sql_equivalence.py`
- `.claude/rules/meta-tooling-bar.md`
- PR [#4050](https://github.com/a-jay85/IBL5/pull/4050), closed unmerged
- Backlog issue [#1938](https://github.com/a-jay85/IBL5-backlog/issues/1938)
