---
description: Cross-PR auto-merge triage via a shared, fail-closed armability predicate sourced by both /post-plan Phase 6.5 and bin/pr-triage.
last_verified: 2026-11-07
---

# ADR-0069: Shared fail-closed armability predicate + cross-PR auto-arm

**Status:** Accepted
**Date:** 2026-06-24

## Context

`/post-plan` Phase 6.5 decided whether to arm `gh pr merge --auto`, but its arming judgment lived only inside that per-branch run, expressed as a mix of inline bash and one prose gate (condition (1), "PR says No manual testing needed", which a human/agent eyeballed). There was no cross-PR view of which open PRs were safe to auto-merge and which were deliberately held, so closing the backlog meant re-deriving "is this armable?" by hand — and that hand re-derivation mis-classified two visual-change PRs (#1163, #1188) as armable when their `## Manual Testing` rows should have held them for a human.

## Decision

Extract the live-readable arming holds into one shared predicate, `bin/lib/pr-armable.sh` — Manual-Testing clearance (1), golden-snapshot touch (5), Depends-on merge-order (6), and the `feat:` floor (8) — sourced by **both** `/post-plan` Phase 6.5 and a new cross-PR `bin/pr-triage --arm`, so the shared judgment has one executable home and cannot drift. Arming is **fail-closed / positive-clearance**: a PR is ARMABLE only when it carries the positive `No manual testing needed` sentinel that `/post-plan` writes after resolving every manual-testing item **and** all required checks are green-and-current — never the mere absence of holds. A PR with no `## Manual Testing` section is UNCLEARED and routed to a human, never auto-armed. The run-only Phase 6.5 conditions (2 review≥80, 3 missing-tests, 4 Phase-5 verify, 7 non-UI `auto_merge: false`, 9 realized-diff verdict) read post-plan-run-local state and stay inline; `bin/pr-triage` cannot positively clear them, so it fails closed. Covered by `ibl5/tests/Cli/PrArmableLibCliTest.php` and `ibl5/tests/Cli/PrTriageCliTest.php`.

## Alternatives Considered

- **Copy the Phase 6.5 idioms into `bin/pr-triage`** — a second implementation of the same buckets. Rejected: a divergent copy is the exact drift surface that mis-armed #1163/#1188.
- **Persist post-plan's full nine-condition verdict onto the PR** (label/body) so all conditions become cross-PR-readable. Rejected for now: a stale persisted "armable" fails OPEN — a PR that grows a SQL/UI surface after post-plan ran still carries the green marker. Deferred as a SHA-stamped follow-up (ignored when marker SHA ≠ head).
- **Absence-based ARMABLE** (arm when no hold matched). Rejected: fails open on a hand-made PR with green checks and no Manual-Testing section — #1188's twin.

## Consequences

- Positive: the live armability judgment has one executable home (`bin/lib/pr-armable.sh`); condition (1) is mechanized rather than eyeballed.
- Positive: `bin/pr-triage` replaces manual PR-closing toil with a fail-closed cross-PR sweep that arms only the deliberate-hold complement.
- Negative: a new autonomous-merge surface that must stay in lockstep with Phase 6.5 — the four shared conditions are the contract, and a Phase 6.5 change that diverges from the predicate breaks the Cli characterization.

## References

- `bin/lib/pr-armable.sh` — the shared live-hold predicate.
- `bin/pr-triage` — the cross-PR classifier + `--arm` gate.
- `.claude/skills/post-plan/SKILL.md` — Phase 6.5 sources the predicate for conditions (1)/(5)/(6)/(8).
- `bin/check-master-ci-green` — the check-runs dedupe idiom `bin/pr-triage` reuses.
- `ibl5/tests/Cli/PrArmableLibCliTest.php`, `ibl5/tests/Cli/PrTriageCliTest.php` — the safety proof.

## Addendum 2026-11-03: --arm obeys the sticky verdict

`bin/pr-triage` now reads the latest `<!-- pr-ready-verdict -->` comment for every PR. It buckets the PR `VERDICT-HELD` unless the banner is READY or READY WITH NOTES, ends `· auto-merge armed`, and carries no `Auto-merge held:` reason. A PR with no sticky, an unreadable sticky, or a failed comment fetch holds. Before this change, a PR that a post-plan run held (for example under condition (7)) was armed by the next triage sweep.

Three policies come with it.

- **Stale verdicts.** The latest sticky wins, whatever head it reviewed. A held, NOT READY, or unreadable sticky stays a hold until a newer sticky replaces it, and only a post-plan run posts one. A READY armed sticky from an older head stays a clear, as before. The banner carries no head SHA, so a SHA-staleness gate would hold every PR. Pushes to the live head are still covered by the clearance and green-checks conditions on the ARMABLE branch.
- **Owner approval for an `auto_merge: false` plan.** The owner arms by hand with `gh pr merge <n> --squash --auto` after the last post-plan run. No label lifts hold (7), so `bin/pr-triage` never arms a PR the verdict holds. A hand arm survives `bin/pr-triage`, which only ever arms. A `feat:` PR keeps its documented path: the `human-approved` label, then a post-plan re-run that posts an armed sticky, then triage may arm it.
- **Disarm path.** A post-plan run that ends held calls `pr_disable_auto_merge` at the arm point, after the Phase 6.5 sticky post. The sticky says so with the line `Any auto-merge already set on this PR is now turned off.` The call runs `gh pr merge <pr> --disable-auto` and swallows errors, so it does nothing on a PR that is not armed. The `auto-mergeable` label resyncs on `auto_merge_disabled`, and `bin/merge-line-select` selects only PRs with an arm set, so a disarm leaves the merge line with no extra step. A run armed at Phase 6.5 whose closer then returns a held decision is disarmed once, just before the terminal state is computed.

`bin/pr-cycle` is unchanged. It keeps one unscoped `bin/pr-triage --arm` per cycle, and `--only` and `--skip` scope work order only.
