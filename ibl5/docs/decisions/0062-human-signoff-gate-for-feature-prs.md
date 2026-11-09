---
description: Feature PRs (conventional-commit `feat:`) cannot merge until a human applies the `human-approved` label — a required CI check, not a convention, blocks auto-merge.
last_verified: 2026-11-08
---

# ADR-0062: Human sign-off gate for feature PRs

**Status:** Accepted
**Date:** 2026-06-14
**Deciders:** a-jay85

## Context

The automouse autonomous pipeline (`bin/automouse/run` → `/post-plan` Phase 6.5) arms `gh pr merge --squash --auto`, and master's branch protection requires only two status checks and **zero reviews** (no `CODEOWNERS`). So whole user-facing features merged unattended overnight (#1073 Trade Block, #1074 Watchlist, #1075 Big Board, #1076 counter-offer; #1067 was rolled back separately by #1070), with no chance for a human to inspect UX before it shipped. The prior attempt, #1072, added `auto_postplan: false` and a manual-UI verification step to the `/plan` docs — but that was inert: the automouse path never reads plan frontmatter, and GitHub had no required check to block on, so `--auto` completed regardless. A convention an LLM is asked to honour is not a gate.

## Decision

Feature PRs require explicit human sign-off, enforced mechanically by a **required status check**, not by convention. The workflow `.github/workflows/human-signoff.yml` classifies a PR as a feature by its conventional-commit title (`^feat(scope)?!?:`) and fails (red) until a human applies the `human-approved` label in the GitHub UI; non-feature work (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) passes automatically so the automouse pipeline can still auto-merge maintenance. The check is added to master's required contexts, so a queued `--auto` merge never completes while it is red. **This required check is the deterministic block** — it holds regardless of what the autonomous flow does, because the merge is gated by GitHub, not by anyone's discipline.

The automouse bot runs as the repo owner with the owner's `gh` credentials, so GitHub cannot tell a bot merge from a human one. **Defense-in-depth lives in the post-plan layer and is deterministic:** `/post-plan` Phase 6.5 condition (8) is a literal PR-title grep (`^feat(scope)?!?:`) that refuses to *arm* `gh pr merge --auto` on a feature PR in the first place — so the bot never queues a `feat:` merge, and there is no arm-then-strip race to lose. The remaining safeguard against the bot self-applying the `human-approved` label is that the post-plan flow does not apply it (verified by inspection). Layer A (the required check) blocks the merge regardless. (This replaced an earlier runtime-strip mechanism — see **Update** below.)

## Alternatives Considered

- **`CODEOWNERS` + required reviews** — require a human approving review. Rejected because: single-maintainer repo; the bot authors PRs as the owner and GitHub forbids self-approval, so it would permanently block *all* automerge (including maintenance), not just features.
- **Keep the `/plan` doc convention (#1072), maybe extend it** — frontmatter `auto_postplan: false` + manual-UI prose. Rejected because: proven inert — the automouse path never reads it and no required check enforces it; it is the exact failure this ADR corrects.
- **Path-based classifier (gate only UI-touching PRs)** — fire on `*.tpl`/`design/`/`*.css`/View paths. Rejected because: path globs are more fragile than titles and need maintenance as the tree moves; "all features need sign-off" is the fail-closed default the owner chose.
- **`enforce_admins: true` to close the `--admin` bypass** — make even admins satisfy required checks. Rejected because: `bin/merge-master-to-prod` and CI baseline auto-commits push directly to master and would break; the residual `gh pr merge --admin` path is not used by the automouse flow and is documented instead.

## Consequences

- Positive: deterministic, GitHub-enforced block — a feature PR cannot merge unattended; the six PRs that motivated this (#1067/#1069/#1073/#1074/#1075/#1076 are all `feat:`-titled) would all have been held.
- Positive: maintenance work (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) still auto-merges, so the automouse pipeline keeps its throughput on low-risk changes.
- Negative: the automouse pipeline can no longer ship *any* new feature without a human applying the label the next morning — a deliberate throughput cost for UX-bearing changes.
- Negative / residual: `gh pr merge --admin` would bypass the required check (admin override, since `enforce_admins: false` is kept for the direct-push paths). It is closed only by the fact the automouse flow does not use `--admin`, not by GitHub — a known, documented gap, not a guarantee. Likewise the title classifier can be dodged by editing a `feat:` PR's title; titles are the team contract (post-plan enforces conventional commits), so this is accepted, not defended.

## Update (2026-06-20, PR #1137)

The originally-shipped **Layer B** was a runtime strip: `bin/automouse/run`'s `neutralize_feat_signoff()` stripped `--auto` arming and any `human-approved` label from bot-authored `feat:` PRs *after* each post-plan run. Because post-plan arms `--auto` then watches CI to completion before returning to `automouse-run`, that cleanup ran too late to beat a label applied mid-watch — best-effort, not deterministic. PR #1137 **removed `neutralize_feat_signoff()`** and replaced the arm-then-strip pattern with the upstream deterministic refusal now described in the Decision (Phase 6.5 condition (8)): post-plan never arms a `feat:` PR, so nothing needs stripping. Layer A — this ADR's core decision, the required `human-signoff` check — is unchanged. The same refactor moved the general merge-hold lever from the inert `auto_postplan: false` plan-frontmatter key to `auto_merge: false`, read deterministically at Phase 6.5 condition (7).

## Update (2026-10-19): approval is pinned to the PR diff

The `human-approved` label now covers one specific PR diff. Before this change the label survived every later push, so commits a maintainer never saw inherited the approval.

- **Fingerprint.** The gate job checks out the PR head, deepens the history for a `feat:` PR holding the label, and computes `git patch-id --verbatim` over `git diff --binary origin/<base>...HEAD` (the merge-base diff). The function is `hs_diff_fingerprint` in `bin/lib/human-signoff-classifier.sh`. `--verbatim` keeps whitespace-only edits visible, matching `diff_patch_id()` in `tools/postplan-harness/harness/fidelity.py`.
- **Record.** A `labeled` event for `human-approved` is the approval. The gate passes, and the `human-approved-fingerprint` job posts a `github-actions[bot]` comment whose first line is `<!-- hs-approved-diff: <id> -->`.
- **Compare.** Every other event on a `feat:` PR holding the label recomputes the id and compares it with the newest marker written by `github-actions[bot]`. Markers from any other author are ignored. A clean rebase keeps the id, so the pipeline's frequent rebases keep the approval.
- **Drop.** A mismatch, a missing record, or any read or compute error reds the required check in the same run, so auto-merge cannot complete in the gap. The write job then posts a comment with a `revoked` marker and the reason, and removes the label.
- **Missing record drops.** A cancelled or failed `labeled` run leaves no record. Treating that as unchanged would let a later real diff change inherit the approval, so it drops instead. Zero open PRs carried the label when this shipped, so no approval was lost.
- **Conservative edge.** patch-id hashes hunk context. An upstream edit inside the three context lines of a PR hunk changes the id after a clean rebase and drops the label. The maintainer re-applies it.
- **Residual.** A same-repo PR can still edit this workflow or the classifier it sources, as before this change. The bot-author filter stops outside commenters from forging a record.

## Update (2026-11-02): an approving run survives a racing push

On PR [#3685](https://github.com/a-jay85/IBL5/pull/3685) the label was applied at 22:58:17Z. An update-branch merge pushed six seconds later. Its synchronize run shared the PR's concurrency group, so it cancelled the approving `labeled` run before the record was posted. It then found no record and removed the label, although the diff fingerprint was unchanged.

- **Per-run approving group.** A `labeled` event for `human-approved` runs in the group `human-signoff-<PR>-approve-<run_id>`. A later push cannot cancel it, and it cannot cancel a later push. The skip predicate and the per-run skip group (ADR-0145, ADR-0210) are unchanged.
- **Bounded wait before a missing drop.** A run that would drop `missing` reads the PR's issue events. When the newest `human-approved` label event is at or after the newest github-actions[bot] marker, it re-reads comments 20 times, 15 seconds apart. A matching bot record keeps the label. A record with another fingerprint drops `mismatch`. A newer revoke or the end of the wait drops `missing`. An events or comments read error drops `error`.
- **Write-time ordering guard.** The approving run captures its own label event time. Its write job skips the record when the label is gone, when a newer `human-approved` label event exists, or when a bot revoke is at or after that time. A skipped record leaves the next run with nothing to match, so it drops. The head SHA in the record is for audit only, because an update-branch merge moves the head without changing the diff.
- **Job timeouts.** The gate job has `timeout-minutes: 20` and the write job has 5. A run that times out publishes no verdict, so it writes nothing and cannot keep the label.

Keeping the label still requires the current fingerprint to equal a record written by github-actions[bot]. A recent label event never counts as approval on its own. `bin/test-human-signoff-classifier` pins each case, including the PR #3685 sequence.

## Update (2026-11-08): the wait also covers a stale mismatch record

A human who removes `human-approved` leaves no revoked marker, so an older bot record stays the newest marker. When the label is reapplied and a racing push cancels the approving run, the synchronize run used to drop `mismatch` against that stale record at once. The eval step now runs the same bounded wait for a `mismatch` drop whenever the approving label event is at or after the newest bot marker. Only a bot record whose fingerprint matches the current diff keeps the label. A mismatch whose record is newer than the label still drops without waiting. Tracked in a-jay85/IBL5-backlog#1781.

## Lineage

Relates to (does not supersede) ADR-0017 (`0017-dependabot-full-ci-and-auto-merge.md`), which established auto-merge for dependabot PRs — that path is gated separately on `dependabot[bot]` authorship and is unaffected. This ADR corrects the gap left by #1072, which is retained only as the de-facto first attempt.

## References

- `.github/workflows/human-signoff.yml` — Layer A, the required status check.
- `bin/automouse/run` — formerly held Layer B (`neutralize_feat_signoff`), removed by PR #1137; the `feat:` block now lives upstream in post-plan Phase 6.5 condition (8) (see Update).
- `.claude/skills/post-plan/SKILL.md` — Phase 6.5 arms `gh pr merge --auto` (the behaviour this gate constrains).
- `.claude/rules/automouse-workflow.md` — automouse pipeline overview.
- `bin/lib/human-signoff-classifier.sh`: the classifier and the diff-pinned approval functions, sourced by the workflow and by `bin/test-human-signoff-classifier`.
