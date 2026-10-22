---
description: ADR-0188. Ban env.* and string-typed inputs.* expressions inside workflow run bodies, enforced by bin/check-workflow-run-interpolation in Static guards.
last_verified: 2026-10-08
---

# ADR-0188: Ban env and string-input expressions inside workflow run: bodies

**Status:** Accepted
**Date:** 2026-10-08

## Context

GitHub Actions expands `${{ }}` into a `run:` script's text before the shell parses it, so any value with shell metacharacters becomes code. Backlog#1387 flagged one such site in an open PR. Master carried the same pattern at 7 sites in `.github/workflows/e2e-tests.yml`, all `${{ env.VRCTX_* }}` values derived from `workflow_call` inputs. The repo already follows the safe form in `.github/workflows/vr-refresh.yml` ("Select PRs to refresh": `env: INPUT_PR: ${{ inputs.pr }}` then `"$INPUT_PR"`). Nothing enforced it. Actionlint has no rule for `env.` or `inputs.` interpolation, so a regression would merge silently.

## Decision

`bin/check-workflow-run-interpolation` runs in the unconditional `Static guards` job of `.github/workflows/tests.yml`. It fails any `${{ }}` inside a `run:` body that references `env.<NAME>` or an `inputs.<NAME>` not declared `type: boolean` or `type: number` in the same file. Values reach the shell through a step- or job-level `env:` entry and a quoted `"$NAME"` reference. `bin/test-check-workflow-run-interpolation` pins the rule's boundaries.

## Alternatives Considered

- **Extend `bin/lint-workflows`.** Rejected because it needs Go-built actionlint plus shellcheck and runs only behind the `workflows:` path filter.
- **Fold into `bin/check-workflow-checkout`.** Rejected because that script owns checkout ordering and a second rule would need a second awk program anyway.
- **A name allowlist for boolean inputs.** Rejected in favor of reading the declared `type:`, which needs no upkeep when inputs are added.

## Consequences

- Positive: a workflow author who interpolates an env or string input into a script gets a red `Static guards` check naming the file and line.
- Positive: boolean and number inputs stay allowed because their values cannot carry shell syntax.
- Negative: open PRs that add such a line (PR #2950's `--head-sha` argument) must adopt the env form on rebase.
- Negative: `github.*`, `steps.*`, `needs.*`, and `matrix.*` expressions are outside the rule.

## References

- `bin/check-workflow-run-interpolation`
- `bin/test-check-workflow-run-interpolation`
- `.github/workflows/vr-refresh.yml`
- `.github/workflows/tests.yml`
