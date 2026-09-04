---
description: Contract for demoting a planned-test row to truly-manual when the test command cannot be executed by the harness.
last_verified: 2026-09-04
---

# ADR-0116: Executed-Check Manual Demotion Contract

**Status:** Accepted
**Date:** 2026-09-04

## Context

The post-plan harness (tools/postplan-harness/) runs planned test commands during Phase 5 verification and then renders a `## Manual Testing` checklist in the PR body. Early in the harness's design, all rows from the plan's manual testing matrix were treated identically: the harness forwarded them to the PR body as-is, and the arming gate checked for a human reviewer's tick. This produced two defects:

1. A row tagged "automated" in the matrix — one the plan expected the harness to run itself — was listed in the PR checklist as if it needed human action, misleading reviewers.
2. A row that the harness could execute and that passed was still held for a human tick, adding friction with no safety gain.

The correct contract is: if the harness can execute a row's test command and the command passes, the row is fulfilled and must not appear in the human checklist. If execution fails or the command is not safe to run in CI (e.g. it is a shell pipeline, an interactive script, or a command with no recognisable file path), the row is demoted to "truly-manual" and handed to the human reviewer.

## Decision

A planned test row is **demoted to truly-manual** when any of the following applies:

- The token extracted as the test-command path fails `_is_test_path()` — it contains whitespace, shell metacharacters (`&|;><$\`), a leading flag (`-`), a leading quote, a dollar sign, or no `/` separator.
- The harness probe adapter rejects the command (returns a non-zero exit or raises).
- The command exits non-zero.

A demoted row is appended to `PlanInfo.truly_manual_rows` as a `ManualRow` dataclass (number, text, raw). The runner renders the truly-manual rows via `manual_rows.render_rows()` into the `## Manual Testing` section. Rows that passed execution are silently dropped from the checklist.

The demotion boundary is enforced at two code sites:
- `tools/postplan-harness/harness/planfile.py` — `_is_test_path()` rejects shell-command tokens before any execution attempt.
- `tools/postplan-harness/harness/manual_rows.py` — `render_rows()` is the single source of truth for checkbox formatting; both the in-process path and the `bin/normalize-manual-testing` CLI call it.

## Alternatives Considered

- **Always execute, never demote** — rejected because shell pipelines and interactive commands cannot be safely spawned in the CI environment and would require a subprocess sandbox with arbitrary attack surface.
- **Always treat all rows as truly-manual** — rejected because it defeats the purpose of the harness: planned automated tests would still require a human tick on every PR, adding toil with no safety benefit.
- **Separate "attempted" and "truly-manual" lists in the PR body** — rejected because showing attempted-but-demoted rows alongside truly-manual ones would expose internal harness state to reviewers; the only reviewer-relevant signal is whether a row needs a human tick.

## Consequences

- Positive: the arming gate is only held for rows that genuinely require a human eyeball; automated checks do not block merge.
- Positive: shell-command tokens in plan matrix cells cannot cause arbitrary command execution in the harness.
- Negative: a row demoted due to a transient execution failure (e.g. a flaky test) requires a human tick rather than a re-run, which is conservative but safe.

## References

- `tools/postplan-harness/harness/planfile.py` — `_is_test_path()` predicate
- `tools/postplan-harness/harness/manual_rows.py` — `ManualRow`, `render_rows()`
- `tools/postplan-harness/harness/state.py` — `PlanInfo.truly_manual_rows`
- `bin/normalize-manual-testing` — CLI wrapper that exposes `render_rows()` for SKILL.md Phase 2 step 3
- `tools/postplan-harness/tests/test_planfile.py` — shell-token rejection tests
- `tools/postplan-harness/tests/test_manual_rows.py` — render_rows parity tests
