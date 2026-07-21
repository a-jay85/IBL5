---
description: A weekly CI_PAT-credentialed scheduled npm audit fix workflow keeps ibl5's pre-existing transitive vulns fixed so the audit-js gate stops false-failing unrelated dependabot PRs; adds an IBL6 npm dependabot entry.
last_verified: 2026-07-21
---

# ADR-0089: Scheduled npm audit fix to keep the audit-js gate green

**Status:** Accepted
**Date:** 2026-07-21

## Context

The `audit-js` job (`.github/workflows/tests.yml:183-205`, name "JS Dependency Audit")
runs `npm ci` then `npm audit --audit-level=high` with `working-directory: ibl5`. It
feeds the aggregate required "Tests and Analysis" gate (`tests.yml:691-697`), so a
`high`-or-above vuln anywhere in `ibl5/`'s dependency tree reds the whole gate.

`ibl5/` carries pre-existing, unfixed transitive vulnerabilities (body-parser,
brace-expansion, js-yaml) that are pulled in through the dependency graph, not declared
directly. Because `audit-js` audits the whole tree rather than the PR's diff, it
false-fails PRs whose own changes are unrelated to those vulns — most visibly dependabot
PRs (e.g. #1549), which get red-Xed by transitive vulns they neither introduced nor can
fix. The gate is doing exactly what it was written to do; the problem is that nothing
keeps the transitive tree fixed, so the debt sits there red-Xing every unrelated PR.

Manually running `npm audit fix` clears it, but only until the next transitive bump
reintroduces a vuln — a recurring chore no one owns.

## Decision

Add a scheduled GitHub Actions workflow, `.github/workflows/npm-audit-fix.yml`, that runs
weekly (`schedule` cron) plus on demand (`workflow_dispatch`). It:

- resets a fixed branch `chore/npm-audit-fix` to the tip of the default branch each run;
- loops the three lockfile directories (`ibl5`, `IBL6`, `ibl5/IBLbot`), running `npm ci`
  then `npm audit fix` (semver-safe transitive fixes only — **no** `--force`, so never a
  breaking major bump);
- only when a lockfile actually changed, commits and force-pushes the branch and opens a
  PR titled `chore(deps): npm audit fix` — and only if no PR for that branch is already
  open, so an existing PR is updated in place rather than duplicated.

The push uses `secrets.CI_PAT`, not the default `GITHUB_TOKEN`. A push made with
`GITHUB_TOKEN` is silently ignored by GitHub's anti-recursion guard and does **not**
trigger `pull_request` CI on the opened PR — so the generated PR's own `audit-js` gate
would never run. CI_PAT makes the PR's CI fire naturally. Logic lives inline in the
workflow (matching `.github/workflows/rebase-prs.yml`) rather than in a `bin/` script;
the workflow-level permission is `contents: read` because every write goes through CI_PAT.

Separately, add an npm `/IBL6` weekly entry to `.github/dependabot.yml`, mirroring the
plain npm `/ibl5` block, so IBL6's dependencies are kept current alongside ibl5's.

## Consequences

- The recurring transitive-vuln debt is kept fixed automatically, so unrelated PRs stop
  tripping `audit-js` for vulns they did not introduce.
- **Residual limitation:** a newly-published vuln still reds open PRs in the window
  between its disclosure and the next scheduled run. This is mitigated — not eliminated —
  by `workflow_dispatch`, which lets a maintainer run the fix on demand rather than
  waiting for the weekly cron.
- **Trust surface:** the workflow wields `secrets.CI_PAT`, which can force-push the
  `chore/npm-audit-fix` branch and open PRs as the bot. This is the same credential and
  posture already accepted for `rebase-prs.yml`; the scope is bounded to a single fixed
  branch and PR creation, and the first credentialed run is reviewed by a human.
- **First-run verification is post-merge by construction:** a `schedule` /
  `workflow_dispatch` workflow only runs from the default branch, so the audit-fix loop,
  PR-dedup, and CI_PAT force-push cannot be exercised on a PR branch. The first real run
  is dispatched and reviewed after merge (see this PR's Automouse Hold Justification).
