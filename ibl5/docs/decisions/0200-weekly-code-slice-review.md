---
description: A weekly launchd job reviews one ibl5/classes module with the PR-review agent prompts, scores the findings with a second model call, and files the survivors in the backlog. It never fixes anything.
last_verified: 2026-10-19
---

# ADR-0200: Weekly rotating review of existing code

**Status:** Accepted
**Date:** 2026-10-19

## Context

Every review agent in the pipeline reads a diff. Code that merged before an agent existed, or that no recent diff touched, is never reviewed. A weekly filer named retro-mine was removed in [#2922](https://github.com/a-jay85/IBL5/pull/2922) because its keyword selection was too blunt to be useful. A new filer has to favor precision over volume.

## Decision

`bin/code-slice-review` runs weekly from launchd on the owner's Mac, the same mechanism as `bin/events-review` (ADR-0168). The job calls Claude, only the Mac has a subscription login, and the repo holds no API key. Each run reviews one `ibl5/classes/<Module>` directory in two calls: a Sonnet reviewer limited to Read, Grep and Glob, then a Haiku scorer. The prompts come from `.claude/review-shared/` and are extracted at runtime by heading prefix. A missing or ambiguous anchor stops the run before any strike is counted, and the shared files are never copied or edited.

Rotation is a reviewed-set kept under `$HOME/.local/state/ibl5-code-slice-review`, outside the repo and written through temp+`mv`. Findings below the rubric floors (80 code, 75 security) or outside the slice are dropped. The rest are de-duplicated by a path, category and symbol fingerprint against open issues and the last 300 closed issues, then filed with `gh issue create` up to `--max-findings`. A whole-run budget cap splits 0.25 USD to the scorer. Every outcome is a backlog issue. No fix, branch or PR is created.

Enforcement lives in `bin/test-code-slice-review`, which CI runs, and in `bin/test-code-slice-review --live`, which the owner runs once before arming the schedule with `bin/code-slice-review --install-schedule`.

## Alternatives Considered

- **A GitHub Actions cron like `.github/workflows/log-review.yml`.** No new host on the Mac. Rejected because Actions has no Claude credential and the repo holds no API key.
- **A mode on `bin/events-review`.** One fewer script. Rejected because that job reads production rows and DMs a digest, while this one reads repo files and files issues. A shared script would mix two trust boundaries.
- **Filing through `bin/backlog new`.** Reuses the existing filer. Rejected because its origin-PR-URL rule is right for PR follow-ups, and a weekly scan has no origin PR. Weakening it would widen a tool that other flows depend on.

## Consequences

- Positive: old code gets reviewed on a schedule, and repeat findings in PR review should fall as old bugs are filed.
- Positive: de-duplication and the finding cap keep the backlog from flooding.
- Negative: at about 100 modules and one module a week, a full cycle takes about two years.
- Negative: a module that hits the cost cap twice is skipped for that cycle, and the owner reviews it with `--slice=` by hand.
- Negative: one Sonnet and one Haiku call per week on the owner's subscription.
- Negative: the process runs under the owner's account and inherits the ambient `gh` login. The reviewer has no Bash, Edit or Write, which limits what an injected comment in a reviewed file can do.

## References

- `bin/code-slice-review`
- `bin/test-code-slice-review`
- `bin/events-review`
- `ibl5/docs/decisions/0168-weekly-events-review.md`
- `.claude/review-shared/_review-agents.md`
- `.claude/review-shared/_review-rubric.md`
