---
description: ADR-0248. A pure SQL rewrite plan writes hold_trigger sql-equivalent. The second-review seat may clear its condition (7) hold only when the harness re-checks a committed equivalence evidence file and returns exactly "ok". Every other evidence state keeps the owner hold.
last_verified: 2026-11-12
---

# ADR-0248: A pure SQL rewrite gets a seat-clearable hold backed by committed evidence

**Status:** Accepted
**Date:** 2026-11-12

## Context

PR [#3815](https://github.com/a-jay85/IBL5/pull/3815) and PR [#3870](https://github.com/a-jay85/IBL5/pull/3870) were pure SQL speed-ups. Their tests already compared the new query against the old one. Gate 14(b) still called each one "a new SQL construction site", so both plans carried no clearable token and the owner had to merge them by hand. The second-review seat from ADR-0231 sees only the diff and may Read files. It has no Bash, so it cannot run an equivalence check itself, and CI's seed (`ibl5/tests/e2e/fixtures/ci-seed.sql`) is too small to show identical output on live-shaped data.

## Decision

A plan for a pure rewrite of an existing query, with the same bound inputs and no new input, route or auth change, writes `hold_trigger: sql-equivalent`. That token sits in `CLEARABLE_PLAN_TRIGGERS` and in `EVIDENCE_GATED_PLAN_TRIGGERS` in `tools/postplan-harness/harness/armable.py`. Condition (7) reaches the seat for it only when `plan_triggers_clearable` sees an evidence verdict of exactly `"ok"`.

The evidence is a committed file, `ibl5/tests/sql-equivalence/<branch>.json`. The plan writes it at plan time with `python3 -m harness.sql_equivalence record`, from two local-DB dumps of one `HASH <case-id> <sha256>` line per input case. It records the case count, both digests, the PHPUnit bind test that pins the SQL string and bind list, and the git blob SHA of each rewritten source file. At arming time the runner calls `sql_equivalence.arm_input_value`, which re-checks the file. A file that is missing, malformed, failed, zero-case, outside the diff, stale against a source blob, names a missing bind test, or crashes the check gives a note other than `"ok"`, and the owner keeps the hold.

The equivalence run is local, because only the local DB holds live-shaped data. The bind test is a PHPUnit row that CI runs. The seat sees the evidence file in the diff and can Read the bind test.

## Alternatives Considered

- Narrow gate 14(b) so a pure rewrite writes `security-surface`. Rejected because `security-surface` is clearable today with no evidence, so a mislabelled real SQL change would reach the seat unchecked.
- A CI equivalence job. Rejected because CI's seed is small, and the seat cannot read CI logs.
- Bind the evidence to a tree or commit SHA. Rejected because a tree hash inside a committed file is circular, and post-plan's commit changes the commit SHA after the recording.

## Consequences

- Positive: the owner-held vocabulary is unchanged. `security-irreducible` still holds every real security change, PR [#3816](https://github.com/a-jay85/IBL5/pull/3816) included.
- Positive: the seat still has to agree. The token only makes the hold clearable.
- Positive: the verdict is an exact string with the default `"unchecked"`, so a typo, crash or missing wiring holds.
- Negative: one small JSON file per rewrite PR accumulates under `ibl5/tests/sql-equivalence/` (example).
- Negative: an edit to a file outside the recorded sources does not stale the evidence. The seat's fresh-tree rule and its read of the diff cover that.

## References

- `tools/postplan-harness/harness/armable.py` (`plan_triggers_clearable`, `EVIDENCE_GATED_PLAN_TRIGGERS`)
- `tools/postplan-harness/harness/sql_equivalence.py`
- `tools/postplan-harness/runner.py` (the `sql_equivalence_evidence=` keyword on `ArmInputs`)
- `tools/postplan-harness/tests/test_sql_equivalence.py`
- `tools/postplan-harness/tests/test_sql_equivalence_replay.py`
- `.claude/skills/plan/_validate-and-write.md` (gate 14(b))
- `.claude/skills/plan/_architect-contract.md` (§ Security-surface mechanization, the fixed row shape)
- ADR-0231, the second-review seat this token feeds
