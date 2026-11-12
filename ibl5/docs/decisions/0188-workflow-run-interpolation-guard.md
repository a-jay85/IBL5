---
description: ADR-0188. Ban env.* and string-typed inputs.* expressions inside workflow run bodies, and every expression in a pull_request_target workflow's run bodies, enforced by bin/check-workflow-run-interpolation in Static guards.
last_verified: 2026-11-12
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

## Addendum: pull_request_target strict mode

**Date:** 2026-11-08

The Consequences above leave `github.*`, `steps.*`, `needs.*`, and `matrix.*` outside the rule. That holds for workflows that run on trusted events. A workflow triggered by `pull_request_target` runs with a write token on event data the PR author controls, such as the title, body, head ref, and labels. In such a file, `${{ github.event.pull_request.title }}` pasted into a run body is shell injection with a write token. PR #3652 planned a check for that shape against `.github/workflows/hotfix-dispatch.yml` (its matrix row 29), and the mutation passed this gate.

The checker now has a strict mode. When a workflow's top-level `on:` block names `pull_request_target`, any `${{ }}` expression inside a `run:` body fails, whatever its context. The trigger is read only from the `on:` block before `jobs:`, in scalar, flow-list, and block form. Comments are stripped first, so a file that mentions `pull_request_target` only in a comment stays on the default rule. Values reach the shell through `env:` and a quoted `"$NAME"`, as before.

A deny-list of untrusted event fields was considered and left for a later change. It would still pass `steps.*.outputs.*` and `needs.*.outputs.*`, which can carry attacker data forward from an earlier step. Every other workflow keeps the original env and string-input rule unchanged. `bin/test-check-workflow-run-interpolation` covers the trigger forms and the comment cases, and case W7b in `bin/test-merge-line-select` runs the checker on the real dispatcher and on a copy carrying the row-29 mutation.

## Addendum: strict mode unreachable on the real tree (2026-11-09)

The dispatcher moved to `pull_request` (ADR-0223 addendum of 2026-11-09). The checker now fails any workflow whose `on:` block names `pull_request_target`, so no file in the tree can reach strict mode. Strict mode stays as defence in depth, and its fixtures in `bin/test-check-workflow-run-interpolation` still run. Case W7b in `bin/test-merge-line-select` no longer runs the row-29 mutation. It now proves the trigger deny on a copy of the dispatcher with its trigger reverted. Case W7 still pins that no run line carries an expression.
