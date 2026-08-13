---
description: Validate every workflow and local composite action with actionlint (plus its shellcheck pass over run: blocks) via bin/lint-workflows, wired into the gate aggregator as a path-filtered actionlint job; the shell policy is single-sourced from the ShellCheck job.
last_verified: 2026-08-13
---

# ADR-0100: actionlint as the workflow-validation gate

**Status:** Accepted
**Date:** 2026-08-13

## Context

CI configuration was the one large surface in this repo with no static validation: a bad `needs:` edge, an unknown `runs-on` label, a type error inside a `${{ }}` expression, or a `with:` key that a local composite under `.github/actions/**` no longer accepts all parsed as valid YAML and failed only when the workflow actually ran — often on an unrelated PR, minutes into a job. The existing guards are narrow by design: `bin/check-workflow-checkout` models step ordering, `bin/check-composite-contracts` models composite behavior contracts, and `pinact --check` (ADR-0079) models pin freshness. None of them type-check expressions or parse `run:` blocks. Separately, `run:` shell had zero coverage — the repo's ShellCheck job lints `bin/**`, never the inline shell embedded in workflows, which is where most of the fragile scripting lives.

## Decision

Every workflow and local composite action is validated by **actionlint**, run through **`bin/lint-workflows`** and enforced by the path-filtered **`actionlint` job** in `.github/workflows/tests.yml`, wired into the `gate` aggregator's `needs:` array. actionlint is version-pinned (`@v1.7.12`), not `@latest`, so a new upstream rule lands as a deliberate bump with its findings in the same commit rather than as a surprise red on an unrelated PR. actionlint's shellcheck pass over `run:` blocks is **mandatory**: the script hard-fails when shellcheck is absent, because actionlint silently skips that pass and still exits 0 — a false green. The shell policy for that pass (`--severity=warning` plus the `SC2034,SC1090,SC2207` burn-down excludes) is mirrored verbatim from the ShellCheck job so the repo has one shell standard, not two; SC2170 is deliberately excluded from that list and suppressed per-site instead, since actionlint substitutes a placeholder literal for each `${{ }}` before invoking shellcheck and makes a correct string-vs-int comparison read as a defect. The job's path filter includes `.github/actions/**` — a composite-only diff can break a caller's `with:` keys, so it must still run.

## Alternatives Considered

- **`rhysd/actionlint` as a marketplace action** — rejected: this repo installs its Go tooling via `go install` with an explicit `setup-go` (actionlint v1.7.12 needs Go ≥ 1.25, newer than the runner default), and a script keeps the invocation identical locally and in CI.
- **Run actionlint inline in the job's `run:` step, no script** — rejected: the severity/exclude policy would then live in YAML only, so a local run and the gate would drift the first time either changed.
- **Let actionlint's shellcheck pass be best-effort** — rejected: a missing shellcheck binary makes actionlint skip every `run:` block and still exit 0, so half the gate would go green while checking nothing.
- **Track `actionlint@latest`** — rejected: new upstream rules would red PRs that touched no workflow logic; a pin makes each rule addition a reviewed change.
- **Extend the existing bespoke guards instead** — rejected: expression type-checking and shell parsing are a large amount of well-solved upstream work; re-implementing them in `bin/` scripts is strictly worse.

## Consequences

- Positive: Workflow defects that previously surfaced only at run time (bad `needs:` graphs, unknown labels, expression type errors, stale composite `with:` keys) now fail at PR time.
- Positive: Inline `run:` shell gets the same ShellCheck coverage as `bin/**`, under the same single-sourced policy.
- Positive: The gate cannot go falsely green on a missing shellcheck binary.
- Negative: An actionlint bump is now a deliberate chore — the pin must be moved and any new findings fixed in the same commit.
- Negative: Local runs need `actionlint` and `shellcheck` installed; the script prints the install command for macOS and Ubuntu rather than failing opaquely.
- Negative: A handful of correct `${{ }}`-in-`[ ]` comparisons carry an inline `# shellcheck disable=SC2170`, which is noise at those sites — accepted to keep SC2170 live everywhere else.

## Lineage

Follows the **ADR-0079** pattern: install a pinned upstream checker, wrap the invocation so local and CI cannot drift, and wire the `--check`-style guard into the `gate` aggregator rather than adding a new branch-protection context.

## References

- `bin/lint-workflows` — the wrapper: binary resolution, mandatory shellcheck, single-sourced shell policy
- `.github/workflows/tests.yml` — the `actionlint` job, its `workflows` path filter, and the `gate` `needs:` wiring
- `bin/check-workflow-checkout` — complementary guard (step ordering), not replaced by this
- `bin/check-composite-contracts` — complementary guard (composite behavior contracts), not replaced by this
- `ibl5/docs/decisions/0079-sha-pin-github-actions.md` — the pinned-upstream-checker precedent
