---
description: Parity-loop fixer PRs (jsb-fix-*) must pass a structural rule guard run from the base ref, and the jsb-native pull waits for that PR to merge.
last_verified: 2026-11-26
---

# ADR-0288: Rule Guard for Parity-Loop Fixer PRs

**Status:** Accepted
**Date:** 2026-11-25

## Context

The JSB parity loop stops when a check refuses a port or a capture. A blocker fixer now wakes an agent to repair the cause of such a stop. The cheapest way for that agent to unblock the loop is to weaken the check that stopped it. A written instruction alone cannot stop that.

## Decision

Every fixer PR (head ref `jsb-fix-*`) must pass `bin/check-parity-rule-guard`. The guard is structural. It refuses edits to a list of protected files, edits to lines holding protected fixed-string tokens, removed test lines, deleted test files and added skip markers. The protected list uses directories and globs, so a new checker file is covered too. It includes test runner config such as `phpunit.xml` and `conftest.py`. Saved JSB captures are add-only. A test file is matched by its directory or by its name, such as `*.test.ts` or `test_*.py`, so a test beside its source counts. An added early exit in a test file also refuses. The `Meta checks` job in `.github/workflows/pr-meta-checks.yml` runs the copy of the guard taken from the base ref, and refuses when the base ref has no copy. The parity loop pulls new jsb-native commits only after the fix PR merges, and runs the same guard over the pulled commit range first. The caps live in the jsb-native file `tools/parity_loop/fixer.py`: 2 tries per blocker and 2 fixes per day. Fixer PRs follow the same merge rules as port PRs.

## Alternatives Considered

- **Prompt rules only.** The fixer prompt lists the rules and nothing checks them. Rejected because: the agent that wants to pass the check is the one reading the rule.
- **Run the guard from the PR head.** The workflow runs the copy in the PR. Rejected because: a fixer PR could weaken the guard and pass it in one commit.
- **A model reviewer that judges intent.** Rejected because: a text-judging reviewer can be argued with, and protected files and tokens can be checked exactly.

## Consequences

- Positive: a fixer PR cannot remove a test line, raise a cap or edit a protected file without a refusal.
- Positive: a refused or unmerged fix never reaches the loop, because the pull waits for the merge.
- Negative: a correct fix that must touch a protected file or token goes to the owner.
- Negative: the token match is a substring match, so some harmless edits are refused.

## References

- `bin/check-parity-rule-guard`
- `bin/test-check-parity-rule-guard`
- `.github/workflows/pr-meta-checks.yml`
- `bin/run-meta-checks-local`
- `ibl5/docs/decisions/0266-trace-pinned-golden-clearance.md`
- `ibl5/docs/decisions/0278-jsb-native-hosted-trace-captures.md`
- `ibl5/docs/decisions/0279-jsb-draw-replay-parity.md`
