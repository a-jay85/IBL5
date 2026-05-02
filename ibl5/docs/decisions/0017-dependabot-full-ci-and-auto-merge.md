---
description: Rationale for forcing all CI checks on Dependabot PRs and enabling squash auto-merge.
last_verified: 2026-05-01
---

# ADR-0017: Dependabot Full CI and Auto-Merge

**Status:** Accepted
**Date:** 2026-05-01

## Context

Dependabot PRs that bumped frontend dependencies (tailwindcss, @tailwindcss/cli) skipped E2E, Lighthouse, ESLint, and CodeQL checks because those workflows used trigger-level `paths:` filters that didn't match lock files or `package.json`. A CSS framework upgrade could silently break visual rendering without any browser-level test catching it. Dependabot PRs also required manual merge clicks despite being low-risk once CI passes.

## Decision

Force all CI workflows to run on Dependabot PRs by adding `github.event.pull_request.user.login == 'dependabot[bot]'` to the changes-job output expressions. Workflows that used trigger-level `paths:` (eslint, lighthouse, codeql) were converted to the dorny/paths-filter `changes` job pattern so the Dependabot override could be applied at job level. A new `dependabot-auto-merge.yml` workflow enables `gh pr merge --auto --squash` on Dependabot PRs, taking effect once all required status checks pass.

## Alternatives Considered

- **Add lock files to every workflow's paths filter** — would run tests on file-match but miss future dependency ecosystems. Rejected because it doesn't generalize.
- **Use `dependabot.yml` groups to batch updates** — reduces PR count but doesn't solve the skipped-tests problem. Orthogonal concern.
- **GitHub auto-merge via repo settings only** — requires manual enable per-PR. Rejected because the workflow approach is zero-touch.

## Consequences

- Positive: every Dependabot PR gets full CI coverage including E2E and Lighthouse.
- Positive: passing Dependabot PRs merge automatically without human intervention.
- Negative: CI minute usage increases for Dependabot PRs that previously skipped most workflows.
- Negative: workflows that previously used trigger-level `paths:` now always start (to run the `changes` job), adding ~10s overhead per non-matching PR.

## References

- `.github/workflows/dependabot-auto-merge.yml`
- `.github/workflows/tests.yml`
- `.github/workflows/e2e-tests.yml`
- `.github/workflows/eslint.yml`
- `.github/workflows/lighthouse.yml`
- `.github/workflows/codeql.yml`
