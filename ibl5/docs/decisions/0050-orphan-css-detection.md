---
description: Advisory tool and CI workflow for detecting orphan CSS class candidates in ibl5/design/
last_verified: 2026-05-28
---

# ADR-0050: Orphan CSS Detection

**Status:** Accepted
**Date:** 2026-05-28

## Context

A manual CSS coverage analysis found 11 class selectors in `ibl5/design/` whose
literal strings do not appear in the PHP/JS/Twig/HTML source corpus. However, some
candidates are not actually dead: `txn-badge--14` (and similar) are composed at
runtime from a prefix concatenated with a numeric code, so the full literal is absent
from source but the class renders in the live app. A repeatable automated check
prevents the manual re-analysis from being redone by hand after every CSS change.

## Decision

Add `bin/check-orphan-css`, a Bash advisory tool that extracts every class selector
from `ibl5/design/**/*.css`, filters against the source corpus using class-character
token-boundary matching, and optionally crawls a running app (`--crawl`) to drop
candidates that render in a live page. Residue is reported as candidates for human
review — never as confirmed-dead. Add `.github/workflows/orphan-css.yml` to run
steps 1–2 (no crawl) on pull requests and post a sticky advisory comment.
Non-blocking: `continue-on-error: true`, no failing gate step.

## Why Advisory, Never Blocking

The check cannot prove a negative. Every filter only ever *removes* candidates, so
the safe bias is "matched." Residue can include:

- **Data-gated classes** (`txn-badge--14`): composed at runtime; full literal absent
  from source; resolved only by crawling prod-seeded data.
- **Role-gated classes**: renders only for specific roles.
- **State-gated classes** (`ibl-jump-menu__link--active`): present only in active UI
  state.
- **Coverage gaps**: forms behind auth/POST that the GET crawl cannot reach.

Exit 0 by default. `--strict` exits 1 when candidates remain, for local enforcement
only — not a CI gate.

## CSS-Only Extraction

Extracting from `.md` files produced phantom class names that appear as heading
anchors or example selectors in documentation. CSS sources in `ibl5/design/` are the
authoritative extraction scope.

## Two-Tier Matching

**Source filter**: token-boundary grep against `.php`, `.js`, `.twig`, `.tpl`,
`.html` in `ibl5/`. Boundary pattern `(^|[^A-Za-z0-9_-])CLASS([^A-Za-z0-9_-]|$)`
prevents substring collisions (`alert` not marked present by `alert-error`).

**Crawl filter**: whole space-delimited token equality against rendered class
attributes. Substring matching would falsely declare `alert` live off `alert-error`.

## Why CI Runs Steps 1–2 Only

CI uses a test-seed database that renders fewer data/role/state-gated pages than
prod, producing a smaller live-set and thus more false candidates. The full `--crawl`
is a local invocation against a `bin/db-sync-prod`-seeded worktree. The CI comment
carries the step-4 caveat so the list reads as "source-absent, crawl-pending," not
"dead."

## Alternatives Considered

- **Block PRs on candidates** — rejected; cannot prove a class is dead. False
  positives from dynamic composition would fail correct PRs.
- **Run the crawl in CI** — rejected; test-seed DB yields noisier output than prod.
  Revisiting belongs in a future ADR review, not this decision.
- **Auto-delete candidates** — rejected; human judgment required. The 11-class
  cleanup from the initial analysis is tracked in a separate branch.
- **PHP component** — rejected; a Bash tool is consistent with `bin/check-hot-files`
  and requires no framework dependencies.

## Consequences

- Positive: Repeatable, scripted coverage analysis replaces manual re-runs.
- Positive: PR comments surface CSS orphan candidates without blocking merges.
- Positive: `--self-test` provides an offline fixture-based correctness check.
- Negative: Advisory comments accumulate on PRs that touch unrelated CSS.
- Negative: Dynamic composition (txn-badge--N) is unresolvable without a prod-seeded
  crawl; CI output will always include those classes until the crawl is run locally.

## References

- `bin/check-orphan-css` — the advisory tool (exit-0 pattern from `bin/check-hot-files`)
- `.github/workflows/orphan-css.yml` — non-blocking CI workflow
- `bin/check-hot-files` — advisory-gate precedent
- `ibl5/design/` — CSS source directory (extraction scope)
