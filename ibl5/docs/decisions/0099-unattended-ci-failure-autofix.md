---
description: Adds unattended CI-failure autofix to bug-pipeline-tick — detects settled red PRs, dispatches a sandboxed Claude agent to fix and commit, then pushes if the agent reports a fix.
last_verified: 2026-08-09
---

# ADR-0099: Unattended CI-Failure Autofix via bug-pipeline-tick

**Status:** Accepted
**Date:** 2026-07-30
**Deciders:** A-Jay

## Context

After the bug-pipeline hunter (ADR-0081) was shipping fixes to PRs, the remaining friction was CI failures on those PRs — transient phpunit regressions, mutation tests, golden-file drift — that required a manual push to fix. Each such failure blocked the PR from auto-merging and required manual triage. The hunter itself only ran on newly-assigned bugs; there was no mechanism to retry CI failures on already-open PRs.

## Decision

`bin/bug-pipeline-tick` gains a new `ci_autofix_main` phase that runs after `maybe_hunt`. On each tick it calls `bpgh_pr_failing_checks` to find open, non-draft PRs with settled red checks (no PENDING, excludes human-signoff). For each eligible PR, two pre-dispatch gates run: `ci_wt_is_idle` confirms no worktree file was modified within `CI_AUTOFIX_IDLE_SECS` (converted to whole minutes for `find -mmin`, clamped to a minimum of 1 — integer division of a sub-minute window would produce `-mmin -0`, which matches nothing and would report every worktree idle); `ci_wt_is_clean` then refuses dispatch if `git status --porcelain` is non-empty, since an abandoned dirty tree passes the idle gate and the agent must never commit work it did not author (the prompt's step 3 aborts on a dirty tree correspondingly). Passing both gates, the tick dispatches a sandboxed `claude -p` agent in the same trust split as the hunter (ADR-0081): the agent runs under `run_under_starved_env`, which zeroes GH_TOKEN, GITHUB_TOKEN, SSH_AUTH_SOCK, ANTHROPIC_API_KEY, and DB credentials. The trusted tick fetches the real failing-job log (via `gh run list --commit <sha>` + `gh run view <run-id> --log-failed`, tail-capped) and hands it to the agent as a file path. The agent reads a rendered prompt from `bin/bug-pipeline-ci-autofix-prompt`, writes a result JSON to `$wt_root/.bug-pipeline/ci-autofix-result.json`, and exits. If the agent reports `"result":"fixed"` with a valid commit SHA, the driver pushes `HEAD:branch` and posts a PR comment. `no_change` and `abort` verdicts are logged and commented but do not push. An attempt ledger (`CI_AUTOFIX_LEDGER`, default `~/.claude/logs/ci-autofix-ledger.json`) gates the attempt cap (default 3) and per-SHA retry semantics. After the cap, a `⛔ ceiling` comment is posted once. The feature is gated by `BUG_PIPELINE_CI_AUTOFIX_ENABLED` (default `1`; set to `0` to disable). The feature ships dry-run-by-default (`BUG_PIPELINE_CI_AUTOFIX_DRY_RUN` defaults to `1`: detect + gate + log only; no claude spawn, no comment, no push); going live requires setting `BUG_PIPELINE_CI_AUTOFIX_DRY_RUN=0` in the tick's environment.

## Alternatives Considered

- **GitHub Actions bot** — trigger a re-run workflow on red checks. Rejected because: requires write access to GH Actions, and we can't sandbox what that workflow does.
- **Retry-only (no code change)** — just trigger `gh pr checks --rerun` on transient failures. Rejected because: can't distinguish transient from real failures without reading logs; also misses fixes needed for real test failures.
- **Separate launchd daemon** — run the autofix as its own plist/daemon. Rejected because: `bug-pipeline-tick` already has the gh seam, trust-split infrastructure, and lock mechanism; duplicating them in a second daemon violates the extend-before-add bar.

## Consequences

- Positive: CI failures on open PRs can be fixed and pushed automatically without manual triage.
- Positive: Sandboxed via `run_under_starved_env` — the agent cannot push credentials, call gh, or access the DB directly.
- Positive: Kill switch and dry-run allow safe observability before full enablement. The feature ships dry-run-by-default; going live requires setting `BUG_PIPELINE_CI_AUTOFIX_DRY_RUN=0` in the tick's environment.
- Negative: A bad agent fix gets pushed (though the PR still requires human review before merge, since `auto_merge` is off by default and guarded by the human-signoff check).
- Negative: One dispatch per tick (not parallel) — ensures the ledger lock is simple and single-flight; means high-PR-count repos could be slow to cycle.

## References

- `bin/bug-pipeline-tick` — driver; `ci_autofix_main`, `ci_apply_result`, `ci_autofix_giveup`
- `bin/bug-pipeline-ci-autofix-prompt` — rendered prompt template
- `bin/lib/bug-pipeline-gh.sh` — `bpgh_pr_failing_checks`, `bpgh_pr_comment`
- `bin/test-bug-pipeline-ci-autofix` — integration test suite (23 rows)
- `.github/workflows/tests.yml` — CI wiring for the test suite
- `ibl5/docs/decisions/0081-hunter-trust-split-starved-env-sandbox.md` — trust-split foundation this builds on
