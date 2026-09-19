---
description: Before every post-plan push, `bin/run-meta-checks-local` runs CI's hard-gate checks locally, deriving path filters at runtime from the workflow YAML; a failing run pushes anyway but withholds auto-merge.
last_verified: 2026-09-18
---

# ADR-0131: Pre-push local meta-check gate

**Status:** Accepted
**Date:** 2026-09-18

## Context

`.github/workflows/pr-meta-checks.yml` runs a set of `bin/check-*` hard gates on every pull request, using `dorny/paths-filter` to skip checks whose path filter did not match. Every one of those checks is already a local script, so the failure mode is pure latency: the post-plan harness pushes, CI runs the same scripts minutes later, and by then auto-merge may already be armed. Meta checks is the top real CI failure, with 12 recorded occurrences. Five auto-merge-armed PRs sat red for hours waiting for a human to intervene.

The extend-before-add bar in `.claude/rules/meta-tooling-bar.md` was evaluated. No existing host can absorb the work: `bin/check-docs` and `bin/check-prose` are individual checks without orchestration responsibility; `bin/post-plan-now` dispatches between harness and skill but never runs checks itself; `bin/lib/pr-armable.sh` holds post-PR arming predicates only. The trigger is distinct: "about to push a post-plan branch" is strictly earlier than CI's trigger of "a PR exists." The gate earns its upkeep on the first prevented red arm. No cheaper alternative exists: a rule doc cannot enforce a pre-push run in a headless harness process.

## Decision

(1) A new orchestrator `bin/run-meta-checks-local` runs CI's hard-gate steps locally before every post-plan push, on both the harness and the skill engine paths.

(2) Path gating is derived by parsing the `filters:` block from `.github/workflows/pr-meta-checks.yml` at runtime. The globs are never copied into a second file, because a copy silently drifts and a drifted copy fails open. The parse fails closed on exit 3 when it cannot extract the filters block.

(3) Checks that require a live PR number (`bin/adr-check`, `bin/refactor-flag`) cannot resolve a bypass marker before a PR exists. They run pre-push as advisory warnings, then again as hard gates in a new Phase 6.5 condition after the PR is open and before auto-merge is armed.

(4) A failing local run pushes anyway so the PR exists and CI shows the same red. Auto-merge is not armed. The failing check names are written to a flag file that the Phase 6.5 arming condition reads.

(5) `check-pr-manual-testing` runs in the pre-push stage only when a caller passes `--body-file`. Neither the harness nor the skill engine supplies one. The check runs as a hard gate in the post-pr stage (row 16).

## Alternatives Considered

Shared filter file: extract the globs into a shared filters YAML and have both CI and the local runner read it. Rejected because this edits the one workflow whose failure this change exists to prevent, and a wrong extraction breaks CI for every PR in flight. Runtime parsing leaves CI byte-identical and moves all risk into the new script, where a bidirectional parity assertion in `bin/test-run-meta-checks-local` catches drift.

Fail closed on meta-check failure (no PR opened): pushing anyway lets the PR exist and CI shows the same red. Suppressing the PR hides the diff view the author needs to fix it. Pushing while withholding the arm keeps the PR visible and the unattended merge blocked.

Pure-Python runner: every step the orchestrator runs is an existing shell script. The house pattern for a `bin/` orchestrator plus its `bin/test-*` fixture harness is shell (`bin/test-check-docs`). Python is used only for the indentation-sensitive block-scalar parse and the picomatch glob translation.

## Consequences

- Positive: meta-check failures surface before the push rather than after auto-merge arms, removing the multi-hour human-intervention window.
- Positive: path filter logic has one source of truth (the workflow YAML); local and CI filters cannot diverge silently.
- Negative: one new `bin/` script and one `bin/test-*` script are added to a surface the quarterly cull already watches.
- Negative: a runtime coupling to the workflow YAML's block-scalar structure is introduced, held honest by the parity assertion in `bin/test-run-meta-checks-local` which fails if the step registry diverges from the workflow in either direction.
- Negative: a bounded wall-clock cost is added to every post-plan push for the path-filter computation and any triggered checks.
- Boundary: the gate is strictly additive. No existing gate is loosened. A local pass never substitutes for the CI run.

## References

- `.claude/rules/meta-tooling-bar.md` (extend-before-add bar and the quarterly cull)
- `.claude/rules/adr-append-only.md` (append-only semantics for merged records)
- `bin/run-meta-checks-local` (new orchestrator, created in Phase 2)
- `bin/test-run-meta-checks-local` (new fixture harness, created in Phase 4)
- `.github/workflows/pr-meta-checks.yml` (the workflow this gate mirrors locally)
- `tools/postplan-harness/runner.py` (harness integration, Phase 5)
- `.claude/skills/post-plan/SKILL.md` (skill integration, Phase 6)
- `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` (condition 15, Phase 7)
