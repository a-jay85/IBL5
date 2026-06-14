---
description: Feature PRs (conventional-commit `feat:`) cannot merge until a human applies the `human-approved` label — a required CI check, not a convention, blocks auto-merge.
last_verified: 2026-06-14
---

# ADR-0062: Human sign-off gate for feature PRs

**Status:** Accepted
**Date:** 2026-06-14
**Deciders:** a-jay85

## Context

The nightly autonomous pipeline (`bin/nightly-run` → `/post-plan` Phase 6.5) arms `gh pr merge --squash --auto`, and master's branch protection requires only two status checks and **zero reviews** (no `CODEOWNERS`). So whole user-facing features merged unattended overnight (#1073 Trade Block, #1074 Watchlist, #1075 Big Board, #1076 counter-offer; #1067 was rolled back separately by #1070), with no chance for a human to inspect UX before it shipped. The prior attempt, #1072, added `auto_postplan: false` and a manual-UI verification step to the `/plan` docs — but that was inert: the nightly path never reads plan frontmatter, and GitHub had no required check to block on, so `--auto` completed regardless. A convention an LLM is asked to honour is not a gate.

## Decision

Feature PRs require explicit human sign-off, enforced mechanically by a **required status check**, not by convention. The workflow `.github/workflows/human-signoff.yml` classifies a PR as a feature by its conventional-commit title (`^feat(scope)?!?:`) and fails (red) until a human applies the `human-approved` label in the GitHub UI; non-feature work (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) passes automatically so the nightly pipeline can still auto-merge maintenance. The check is added to master's required contexts, so a queued `--auto` merge never completes while it is red. Because the nightly bot runs as the repo owner with the owner's `gh` credentials — GitHub cannot tell a bot merge from a human one — a second deterministic layer lives in `bin/nightly-run` (`neutralize_feat_signoff`): after every post-plan run it strips any `--auto` arming and any `human-approved` label from `feat:` PRs the bot authored, so the gate is satisfiable only by a human.

## Alternatives Considered

- **`CODEOWNERS` + required reviews** — require a human approving review. Rejected because: single-maintainer repo; the bot authors PRs as the owner and GitHub forbids self-approval, so it would permanently block *all* automerge (including maintenance), not just features.
- **Keep the `/plan` doc convention (#1072), maybe extend it** — frontmatter `auto_postplan: false` + manual-UI prose. Rejected because: proven inert — the nightly path never reads it and no required check enforces it; it is the exact failure this ADR corrects.
- **Path-based classifier (gate only UI-touching PRs)** — fire on `*.tpl`/`design/`/`*.css`/View paths. Rejected because: path globs are more fragile than titles and need maintenance as the tree moves; "all features need sign-off" is the fail-closed default the owner chose.
- **`enforce_admins: true` to close the `--admin` bypass** — make even admins satisfy required checks. Rejected because: `bin/merge-master-to-prod` and CI baseline auto-commits push directly to master and would break; the residual `gh pr merge --admin` path is not used by the nightly flow and is documented instead.

## Consequences

- Positive: deterministic, GitHub-enforced block — a feature PR cannot merge unattended; the six PRs that motivated this (#1067/#1069/#1073/#1074/#1075/#1076 are all `feat:`-titled) would all have been held.
- Positive: maintenance work (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) still auto-merges, so the nightly pipeline keeps its throughput on low-risk changes.
- Negative: the nightly pipeline can no longer ship *any* new feature without a human applying the label the next morning — a deliberate throughput cost for UX-bearing changes. The residual `--admin` bypass remains open at the GitHub layer (mitigated only by Layer B + the fact the nightly flow never uses it), the price of keeping `enforce_admins: false` for the direct-push paths.

## Lineage

Relates to (does not supersede) ADR-0017 (`0017-dependabot-full-ci-and-auto-merge.md`), which established auto-merge for dependabot PRs — that path is gated separately on `dependabot[bot]` authorship and is unaffected. This ADR corrects the gap left by #1072, which is retained only as the de-facto first attempt.

## References

- `.github/workflows/human-signoff.yml` — Layer A, the required status check.
- `bin/nightly-run` — Layer B, `neutralize_feat_signoff` strips auto-merge/label from `feat:` PRs.
- `.claude/skills/post-plan/SKILL.md` — Phase 6.5 arms `gh pr merge --auto` (the behaviour this gate constrains).
- `.claude/rules/nightly-workflow.md` — nightly pipeline overview.
