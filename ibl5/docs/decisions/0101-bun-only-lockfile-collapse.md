---
description: Collapse ibl5 to a single bun.lock; replace npm audit gate with bun audit + weekly tracking issue
last_verified: 2026-08-13
---

# ADR-0101: Bun-only lockfile for ibl5

**Status:** Accepted  
**Date:** 2026-08-13

## Context

`ibl5` carried two lockfiles describing two different dependency trees. `bun.lock` is what every CI job and every developer installs from; the npm lockfile (`package-lock.json`) was installed by nothing except the `audit-js` gate. The trees disagreed: `npm audit --audit-level=high` on `package-lock.json` reported **0** vulnerabilities while `bun audit` on `bun.lock` reported **27 advisories, 17 high**. The gate was false-green — it audited a tree nobody ran. The remediation loop was equally closed: every `npm audit fix` commit landing in `ibl5` was **lockfile-only**, mutating a file no install consumed, while every fix that actually shipped was a hand-added `overrides` entry in `package.json`.

## Decision

Collapse `ibl5` to `bun.lock` as the single lockfile. Delete `ibl5/package-lock.json` (example) (intentionally removed by this ADR) and its `.bak`. Flip the Dependabot `/ibl5` entry from the `npm` ecosystem to `bun`. Convert the `audit-js` job to `bun install --frozen-lockfile` + `bun audit --audit-level=high`, adding the frozen-lockfile step as a **drift gate** so a `package.json` bump without a regenerated `bun.lock` fails fast. Drop `ibl5` from `npm-audit-fix.yml`'s loop (IBLbot keeps npm and keeps that workflow). Add `.github/workflows/bun-audit-issue.yml`, a weekly `bun audit` run that opens or updates a single labelled tracking issue.

## Alternatives Considered

1. *Keep both lockfiles in sync* — rejected: nothing consumes `package-lock.json`, so "in sync" has no mechanical definition and no consumer would notice divergence; this is the state that produced the 0-vs-27 gap.
2. *Move `ibl5` back to npm as the install tool* — rejected: `bun` is the established toolchain for `ibl5-ts-unit` and local development, and reverting would be a far larger change with no security benefit.
3. *Auto-fix the bun advisories on a schedule* — rejected: `bun audit` has no `--fix`, and the historical `npm audit fix` runs never fixed anything that shipped; an issue a human triages is a truthful control where an auto-PR would be theatre.
4. *Add `bun audit` alongside the npm gate* — rejected: it leaves the misleading npm gate in place and doubles maintenance for the same signal.

## Consequences

Accepted loss: the Dependabot **`bun` ecosystem supports version updates only and does NOT raise security-update PRs**, so `ibl5` no longer receives automatic security PRs. Accepted because every `ibl5` JS dependency is a **devDependency** — CI/tooling supply chain, never a production runtime path — and because retaining the `npm` entry would require retaining the false-green lockfile that is the actual defect. Compensating control: the weekly `bun-audit-issue.yml` tracking issue. New failure mode introduced deliberately: PRs that bump `ibl5/package.json` without running `bun install` now fail `audit-js` on the drift gate. `ibl5/IBLbot` is unaffected and keeps npm, its lockfile, its Dependabot npm entry, and its `npm-audit-fix.yml` coverage.

## Supersedes

ADR-0089 (`0089-scheduled-npm-audit-fix.md`) is superseded in its `ibl5` scope only. ADR-0089 continues to govern `ibl5/IBLbot`.

## References

- `bin/bun-audit-triage` — the audit-JSON triage seam (implementation detail of this decision; no separate ADR required)
- `.github/workflows/bun-audit-issue.yml` — the weekly tracking-issue workflow (compensating control for the Dependabot security-update loss)
- <!-- no-adr: covered by ADR-0101 (lockfile collapse implementation detail) -->
