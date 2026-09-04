---
description: Shell wrappers grant module access via PYTHONPATH instead of cd-ing to the module root, so caller-supplied relative path arguments keep resolving against the caller's cwd.
last_verified: 2026-09-04
---

# ADR-0118: Shell wrapper path resolution via `PYTHONPATH`, not `cd`

**Status:** Accepted
**Date:** 2026-09-04

## Context

`bin/normalize-manual-testing` ran `cd "$ROOT/tools/postplan-harness"` before forwarding caller arguments to `python3 -m`. Any caller passing a repo-relative path — the natural form, e.g. `bin/normalize-manual-testing tools/postplan-harness/tests/fixtures/manual-matrix-plan.md` — had that path silently reinterpreted inside the module root. The failure surfaced as a module-level `FileNotFoundError`, not a "wrong directory" message, so it masked the real defect it was invoked to diagnose (PR #2087). The wrapper pattern itself is the hazard: `cd` is a cheap-looking way to satisfy Python's module-root requirement, and the argument-resolution damage is invisible at authoring time because the author tests with an absolute path or from the module root.

## Decision

A shell wrapper that invokes a Python module must grant module access by exporting `PYTHONPATH` and keep the caller's cwd; it must not `cd` to the module root before forwarding caller arguments. Where a subcommand genuinely requires a specific cwd, every positional argument is resolved to an absolute path *before* the `cd`. Enforcement is a decision-point norm for plan and script authors, carried by the path-conditional rule `.claude/rules/shell-wrapper-path-resolution.md` (scoped to `bin/**`) — not a mechanical gate; see Alternatives.

## Alternatives Considered

- **PHPStan / static analysis (Rung 1)** — let a static tool flag the pattern. Rejected because: the shell script is valid syntax and no static analyzer decides that a given `cd` invalidates subsequent argument resolution; the surface is shell, not PHP.
- **Extend an existing `bin/check-*` gate (Rung 2)** — add a checker for shell wrapper path-resolution contracts. Rejected because: no existing gate models wrapper argument semantics, and a new one would have to distinguish a harmful `cd`-before-`"$@"` from the many benign `cd`s in `bin/`, producing false positives on a surface with few instances.
- **Forced-trigger verification row (Rung 3)** — require a relative-path CLI test row in the Verification Matrix. Rejected because: the matrix already carried exactly that row for PR #2087. Coverage existed and the implementation was still wrong, so the trigger would have caught a *future* wrong implementation but not the one that shipped.
- **Fix the one wrapper and stop** — treat it as a one-off bug. Rejected because: the defect class is the authoring pattern, and the next wrapper reintroduces it at zero cost.

## Consequences

- Positive: caller-supplied relative paths keep working from any cwd, so wrappers behave like ordinary CLI tools and their failures are legible.
- Positive: the norm is stated at the decision point (authoring a new wrapper), where it is cheapest to apply, and rides a `paths: bin/**` rule so it costs no always-loaded context.
- Negative: enforcement is convention plus code review, not a gate — a wrapper authored without reading the rule can still ship the `cd` pattern. Escalating to a mechanical rung stays available if a second instance appears.

## References

- `.claude/rules/shell-wrapper-path-resolution.md` — the rule this ADR justifies.
- `bin/normalize-manual-testing` — the wrapper whose `cd` produced the origin defect.
- `tools/postplan-harness/` — the Python module root the wrapper grants access to.
- `.claude/rules/meta-tooling-bar.md` — the extend-before-add bar the rung ladder above applies.
