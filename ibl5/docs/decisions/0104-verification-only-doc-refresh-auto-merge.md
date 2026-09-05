---
description: A nightly doc-refresh PR may self-ship only when `bin/docfix-check-veronly` proves its realized diff is exclusively date bumps and same-line numeric corrections on tracked files under `ibl5/docs/`; every other docfix PR keeps ADR-0086's human-merge hold.
last_verified: 2026-09-02
---

# ADR-0104: Verification-only doc refreshes may self-ship

**Status:** Accepted
**Date:** 2026-08-13
**Deciders:** A-Jay

## Context

The nightly docfix pipeline (ADR-0079, replaced by ADR-0086's runnerless Mac poll) fires `bin/docfix-poll` → `bin/docfix-run`, which seeds an `auto_merge: false` hold plan and opens a `docs-stale-refresh-*` PR held for a human to merge. `bin/docfix-run`'s dedupe then skips on *any* open `docs-stale-refresh-*` PR, so a single unmerged docfix PR halts the pipeline indefinitely — the docs keep going stale while the machine that fixes them refuses to run.

What makes that tax hard to justify is the shape of the overwhelmingly common docfix diff: the run re-reads each flagged doc, finds it still accurate, and bumps `last_verified:`. The resulting PR is a set of one-line date changes with literally nothing to review. The human-merge hold was designed to bound what an unattended `--dangerously-skip-permissions` run can land; on this diff class it bounds nothing and costs a round-trip that, in practice, has been measured in days.

## Decision

**A docfix PR may be released to auto-merge only when a mechanical predicate proves its realized diff carries no reviewable content. Every other docfix PR keeps ADR-0086's human-merge posture.** Four clauses, all load-bearing:

1. **The grant is predicate-gated, not class-wide.** `bin/docfix-check-veronly` inspects the realized diff (merge-base against the *working tree*, so an uncommitted edit cannot hide from it) and passes only when every changed file is status `M` and under `ibl5/docs/` (no add, delete, rename, untracked file, or path outside the docs tree); at least one file changed; within each hunk the removed and added line counts are equal and paired positionally; each pair is either a `last_verified:` date pair (at most one per file, strictly advancing by ISO-8601 string compare, classified by prefix not full regex) or a pair whose digit-runs are the only difference (both sides mask to the same skeleton under `sed 's/[0-9][0-9]*/__NUM__/g'`, raw texts differ); and any file carrying a non-date numeric pair must also carry that file's one advancing date pair. A single prose line anywhere leaves the hold in place. The predicate is **fail-closed**: any parse ambiguity, unpaired hunk, unexpected shape, or environment error exits non-zero and the plan keeps `auto_merge: false`.

   **Mechanism.** Digit-run masking uses the BRE `[0-9][0-9]*` applied by `sed`; the masked form is called the *skeleton*. Skeleton equality means every non-digit byte is identical at the same position and the digit-runs sit at the same offsets, so the two raw texts differ if and only if at least one digit-run differs — comparing raw texts after a skeleton match *is* the number-vector comparison with no separate extractor. Numbers may move in either direction; `@v2`→`@v3` and `147`→`17` are both acceptable provided the same file also carries a strictly-advancing `last_verified:` date bump.

2. **Release happens at the plan seed, not in the merge gate.** On PASS, `bin/docfix-check-veronly` rewrites the seeded plan's frontmatter `auto_merge: false` → `true` and deletes its `## Automouse Hold Justification` section. `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` is **not** modified. The merge gate stays one generic mechanism with no docfix-shaped special case, and the grant stays auditable in a plan file a human can read.

3. **The flip is necessary, not sufficient.** Frontmatter only releases Phase 6.5 condition (7). Conditions (1)–(6), (8), the (9) realized-diff LLM safety verdict, and the (11) unresolved-scored-finding floor all still gate the arm independently. Nothing in this ADR can make a PR merge that Phase 6.5 otherwise refuses.

4. **The `pipeline-authored` label stays off, deliberately, and the predicate is the substitute floor.** ADR-0086 recorded docfix PRs' evasion of that label as "an unclosed hole rather than a grant." This ADR converts it into a conscious, narrowly-scoped grant — the hole is not being exploited silently. Condition (10)'s unconditional floor remains correct and unmodified for every *other* pipeline-authored PR; we are not relaxing it, we are declining to extend it to a diff class with no reviewable content.

This answers ADR-0086's second reason head-on. ADR-0086 held that "the human review **is** the bound on what an unattended `--dangerously-skip-permissions` run can land." That premise dissolves exactly when the predicate passes: when every changed line is a strictly-advancing ISO date, there is no content for the human read to bound. The predicate is a mechanical substitute for that specific read, and only for it.

## Lineage

This ADR narrows one clause of one prior decision; it supersedes nothing.

- [ADR-0086](0086-runnerless-mac-poll-for-stale-docs-remediation.md) — its decision 2 (doc-refresh PRs are held for human merge; auto-merging them was considered and rejected) **still governs every docfix PR the predicate does not clear**. Only the verification-only subset defined above is carved out. ADR-0086 remains `Accepted` and is not superseded.
- [ADR-0079](0079-stale-docs-auto-remediation.md) — the original self-hosted-runner design ADR-0086 replaced. Referenced for lineage only; its status is unchanged by this ADR.

## Alternatives Considered

- **Arm every docfix PR unconditionally** — drop the hold for the whole class. Rejected because: it discards ADR-0086's bound wholesale and would let a content-changing refresh (a corrected paragraph, a deleted `paths:` glob) land unread.
- **Add a docfix-aware condition (12) to Phase 6.5** — teach the merge gate to recognize verification-only diffs. Rejected because: it puts a pipeline-specific special case into the generic merge gate and hides the grant from the plan file a human would actually read.
- **Apply the `pipeline-authored` label and add a `docfix-veronly` override label to condition (10)** — model the grant as a label exception. Rejected because: it weakens a floor that correctly governs other pipelines, and it needs both a pre-existing repo label and a network write that can fail halfway.
- **Keep the human-merge hold and only fix the dedupe** — narrow `bin/docfix-run`'s skip so a lingering PR stops halting the pipeline, and change nothing else. Rejected as insufficient because: it leaves a permanent human-merge tax on zero-content PRs, which is the friction that produced the lingering PR in the first place.
- **Accept numbers only when they decrease (or only when they increase)** — add a monotonicity check so the direction of a numeric change is constrained. Rejected because `147`→`17` is a downward correction and `@v2`→`@v3` is an upward bump; both are the motivating cases for widening, and any direction rule would either block one of them or encode nothing. The same-file date-bump requirement is the constraint that carries the safety instead of a direction check.

## Consequences

- Positive: the nightly pipeline no longer halts on a zero-content PR — the common case ships itself and the docs stay fresh without a human round-trip.
- Positive: the hold is preserved exactly where review has something to bind. A refresh that changes real content is still held, and the predicate says so explicitly rather than by omission.
- Positive: the release point is a line in a plan file, so "why did this merge unattended?" is answerable from the repo without reconstructing gate logic.
- Negative (accepted): a predicate bug that wrongly passes would land an unreviewed diff. Mitigated by fail-closed defaults on every branch, by conditions (9) and (11) still gating the arm independently, and by per-branch coverage in `bin/test-docfix-run`.
- Negative (accepted): the frontmatter flip is **necessary, not sufficient** — a future reader must not mistake `auto_merge: true` for a merge guarantee. Phase 6.5 can and does still refuse.
- Negative (accepted residual): a doc whose *only* change is a `last_verified:` line inside a fenced YAML **example** rather than its real frontmatter would satisfy the predicate. The exactly-one-pair-per-file rule bounds this to a single-line, date-shaped edit, and the class is not worth frontmatter-position parsing.
- Negative (accepted): a number may move in any direction, so the predicate cannot distinguish a corrected measurement from a rewritten policy figure; the same-file date-bump requirement is the substitute floor. A numeric change with no accompanying date bump always holds.
- Negative (accepted): PR #2059 — the motivating case for widening — shows that the real nightly diff class is dominated by pure additions (three of its six files are `0026`/`0034`/`0077` shape, all held by the hunk-pairing rule). The widening buys less than the original brief predicted while the predicate surface widens all the same; that asymmetry is accepted rather than met by loosening the pairing rule.

## References

- `bin/docfix-check-veronly` — the predicate and the plan-arming rewrite.
- `bin/docfix-run` — seeds the hold plan and invokes the predicate before the PR opener.
- `bin/docfix-poll` — the nightly launchd poll that fires the pipeline.
- `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` — condition (7) reads the plan frontmatter this ADR releases; unmodified.
- `bin/test-docfix-run` — per-branch coverage for the predicate, the dedupe, and the notification path.
