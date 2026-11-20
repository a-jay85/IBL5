---
description: ADR-0270. A whole-tree CI gate, bin/check-relative-path-used-after-cd, flags a path argument or a dirname of $0 used after a repo-root cd in bin/ shell scripts
last_verified: 2026-11-20
---

# ADR-0270: Gate relative paths used after a repo-root cd

**Status:** Accepted
**Date:** 2026-11-20

## Context

A shell script that runs `cd` to the repo root changes what every later relative path means. Two shapes cause bugs. A path argument from the caller, such as `"$1"`, now resolves against the repo root instead of the caller's directory. A `dirname` of `$0` or `BASH_SOURCE` evaluated after the `cd` resolves a relative script path against the wrong directory.

Review caught the first two cases. PR #3702 hit it in `bin/lib/prefire-verify`. PR #3857 hit it in `bin/lib/plan-exec-mutations`. The third case was live on master in `bin/post-plan-now`. The same PR that adds this gate fixes it with `PPN_SELF_DIR`, which holds the script directory resolved before the `cd`.

ADR-0118 chose a rule with no gate for the wrapper case. It expected benign `cd` calls in `bin/` to raise false positives. It kept a mechanical rung open for a second instance. This is the third.

## Decision

`bin/check-relative-path-used-after-cd` scans every shell file under `bin/` and fails on both shapes. CI runs it, with its harness `bin/test-check-relative-path-used-after-cd`, in the `static-guards` job of `.github/workflows/tests.yml`. The harness runs first in the same step.

The scan is narrow so it stays quiet on benign code:

- It arms only on a `cd` to a repo-root-named target at the top level of a script.
- It taints only names that hold paths.
- It keys self references on `dirname` and the `%/*` expansion.

A first scan that ignored variable names flagged 21 non-path variables. The narrowing above leaves 0 hits on the tree after the `bin/post-plan-now` fix. ADR-0118's false-positive worry does not hold for this version.

A line that is correct on purpose ends with `# relpath-ok: <reason>`. The scan skips comments, heredoc bodies, and function bodies. A bug inside a function body or a heredoc is therefore not caught.

## Alternatives Considered

- **Rule only.** This was ADR-0118's choice. Rejected because the rule did not stop the second or third instance.
- **A `--since` diff mode in `.github/workflows/pr-meta-checks.yml`.** Rejected because the scan is fast on the whole tree and needs no base ref.
- **Extend `bin/check-shell-portability`.** Rejected because that gate checks stat order with its own statement parser, and the two checks share no code.

## Consequences

- Positive: a path used after a repo-root `cd` fails CI before review has to catch it.
- Positive: the gate is a whole-tree scan, so it needs no base ref.
- Negative: skipping comments, heredoc bodies, and function bodies means a bug in one of them passes.
- Negative: one more `bin/check-*` gate and one more `bin/test-*` harness to maintain.

## References

- `bin/check-relative-path-used-after-cd`
- `bin/test-check-relative-path-used-after-cd`
- `bin/post-plan-now`
- `bin/lib/prefire-verify`
- `bin/lib/plan-exec-mutations`
- `.claude/rules/shell-wrapper-path-resolution.md`
- `ibl5/docs/decisions/0118-shell-wrapper-path-resolution-via-pythonpath.md`
