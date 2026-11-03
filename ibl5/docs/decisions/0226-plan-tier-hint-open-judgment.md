---
description: bin/plan-tier-hint picks the /plan architect tier with one open Opus 5.5 judgment, no trigger list, fails safe to default, and logs every verdict; supersedes ADR-0222's check-list extraction and xhigh failsafe.
last_verified: 2026-11-02
---

# ADR-0226: Open Opus judgment for the /plan architect tier

**Status:** Accepted
**Date:** 2026-11-02
**Deciders:** ajaynicolas
**Supersedes:** ADR-0222

## Context

ADR-0222 gave `bin/plan-tier-hint` one Opus 5.5 judge call. The judge read /plan Step 3 check 1 from SKILL.md and applied it as a trigger list: a security surface, a trust boundary, a destructive migration, a gate removal or weakening, or a bootstrap hazard meant `xhigh` (Fable). Every judge failure also printed `xhigh`.

On 2026-11-02 a backtest-tool fix that loosened a dev-tooling gate drew `xhigh` because check 1 lists gate weakening. Asked the open question instead, Opus picked `default`. The fail-closed rule was already a hard constraint in the task, and the PR was held for owner sign-off. The owner chose to trust Opus judgment over a trigger list. An earlier attempt to narrow the list with word lists (PR #3594) was closed unmerged.

## Decision

- `bin/plan-tier-hint` stays the single decider, with the same CLI, output contract, stub seam, CI guard, and `bin/plan-now` exit-4 wiring.
- The judge prompt states facts and asks one open question: is Opus likely to get this plan wrong, and would a wrong plan be expensive? Security, authorization, and destructive data changes are things to weigh. The reason line names what is at stake, how reversible it is, and which stated mitigations were counted.
- The script no longer reads SKILL.md. No word or regex list exists anywhere in the tier path, not even as a pre-filter.
- Every failure path prints `default` with a `judge-failsafe:` reason and exits 0.
- Every verdict appends one line (timestamp, verdict, reason, first 120 characters of the task) to `~/.claude/logs/plan-tier-hint.log`, overridable with `PLAN_TIER_HINT_LOG`. A failed write never changes the verdict or the exit code.
- `/plan` Step 3 check 1 keeps the gate-weakening and bootstrap-hazard definitions for the `auto_merge` hold rule (gate 14(e)). They no longer set a tier.

## Consequences

- A broken judge now runs a declared `plan-architect` without friction. A declared `plan-architect-xhigh` exits 4 until the human passes `--tier-ok`, so Fable spend needs a confirmation when the judge cannot weigh in.
- The verdict log is the audit trail. A plan that went wrong on Opus can be traced to the reason the judge gave.
- The hold rule is unchanged. A task that weakens a gate or rewrites its own merge rule still ships `auto_merge: false`.

## Alternatives considered

- **Narrow the trigger list with word lists.** Rejected: PR #3594 showed the lists drift and misfire, and they move judgment into regex.
- **Keep the xhigh failsafe.** Rejected: a broken judge then buys a Fable run at 2.5 times the Opus cost, and blocks every declared-default run in `bin/plan-now`.
- **Delete the hint.** The orchestrator would decide alone. Rejected: headless `bin/plan-now` runs need one place that makes and logs the call.

## References

- `bin/plan-tier-hint`: the judge prompt, the `default` failsafe, and `log_verdict()`.
- `bin/test-plan-tier-hint`: the harness and the drift assertions on the shared phrase `tier is an Opus judgment`.
- `.claude/skills/plan/SKILL.md`: /plan Step 3, where the orchestrator owns the final tier call.
- `.claude/rules/agent-tiering-fable.md`: the Fable test the judge applies.
- `ibl5/docs/decisions/0222-plan-tier-hint-opus-judge.md`: the superseded decision.

## Addendum (2026-11-02): the judge sees the full brief

The first version gave the judge a one-line task statement. A gate-race fix whose brief carried a resolved design, fail-closed hard constraints, and regression tests came back `xhigh`, because none of that reached the judge. The judge answered the input it had.

`bin/plan-tier-hint` now also takes `--brief <file>` and `--signals <list>`. One filter drops every line that matches `MUST route to` or names `plan-architect` from each input, so a caller's declared tier never reaches the judge. Paths the brief names join the file list. The brief is capped at 16000 bytes on a line boundary and carries a truncation marker when cut. Signal lines record a `feat:` title, a stated `auto_merge: false`, and a `bin/plan-now --implement` run. The prompt counts a design the brief already resolves as a mitigation.

`bin/plan-now` passes the whole prompt file. `bin/lib/burndown.sh` passes the backlog issue body. `/plan-prompt` Step 5 runs the hint on the written block. A `--desc`-only caller gets the old task block. The fence note, the fail-safe to `default`, and the log format are unchanged.

Rejected: scanning the repo for existing tests of the touched files. It costs a tree walk per call and measures coverage the brief already states.
