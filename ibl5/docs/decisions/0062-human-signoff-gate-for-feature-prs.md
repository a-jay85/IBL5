---
description: Feature PRs (conventional-commit `feat:`) cannot merge until a human applies the `human-approved` label — a required CI check, not a convention, blocks auto-merge.
last_verified: 2026-06-20
---

# ADR-0062: Human sign-off gate for feature PRs

**Status:** Accepted
**Date:** 2026-06-14
**Deciders:** a-jay85

## Context

The automouse autonomous pipeline (`bin/automouse-run` → `/post-plan` Phase 6.5) arms `gh pr merge --squash --auto`, and master's branch protection requires only two status checks and **zero reviews** (no `CODEOWNERS`). So whole user-facing features merged unattended overnight (#1073 Trade Block, #1074 Watchlist, #1075 Big Board, #1076 counter-offer; #1067 was rolled back separately by #1070), with no chance for a human to inspect UX before it shipped. The prior attempt, #1072, added `auto_postplan: false` and a manual-UI verification step to the `/plan` docs — but that was inert: the automouse path never reads plan frontmatter, and GitHub had no required check to block on, so `--auto` completed regardless. A convention an LLM is asked to honour is not a gate.

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

The originally-shipped **Layer B** was a runtime strip: `bin/automouse-run`'s `neutralize_feat_signoff()` stripped `--auto` arming and any `human-approved` label from bot-authored `feat:` PRs *after* each post-plan run. Because post-plan arms `--auto` then watches CI to completion before returning to `automouse-run`, that cleanup ran too late to beat a label applied mid-watch — best-effort, not deterministic. PR #1137 **removed `neutralize_feat_signoff()`** and replaced the arm-then-strip pattern with the upstream deterministic refusal now described in the Decision (Phase 6.5 condition (8)): post-plan never arms a `feat:` PR, so nothing needs stripping. Layer A — this ADR's core decision, the required `human-signoff` check — is unchanged. The same refactor moved the general merge-hold lever from the inert `auto_postplan: false` plan-frontmatter key to `auto_merge: false`, read deterministically at Phase 6.5 condition (7).

## Lineage

Relates to (does not supersede) ADR-0017 (`0017-dependabot-full-ci-and-auto-merge.md`), which established auto-merge for dependabot PRs — that path is gated separately on `dependabot[bot]` authorship and is unaffected. This ADR corrects the gap left by #1072, which is retained only as the de-facto first attempt.

## References

- `.github/workflows/human-signoff.yml` — Layer A, the required status check.
- `bin/automouse-run` — formerly held Layer B (`neutralize_feat_signoff`), removed by PR #1137; the `feat:` block now lives upstream in post-plan Phase 6.5 condition (8) (see Update).
- `.claude/skills/post-plan/SKILL.md` — Phase 6.5 arms `gh pr merge --auto` (the behaviour this gate constrains).
- `.claude/rules/automouse-workflow.md` — automouse pipeline overview.
