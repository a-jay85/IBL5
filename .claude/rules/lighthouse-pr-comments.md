---
description: Lighthouse CI posts per-URL scores and deltas-vs-master as a sticky PR comment on every source-affecting PR
paths:
  - ".github/workflows/lighthouse*"
  - "ibl5/.lighthouserc.json"
last_verified: 2026-05-27
---

# Lighthouse PR Comments

## What runs

`.github/workflows/lighthouse.yml` runs Lighthouse on 5 URLs (configured in
`ibl5/.lighthouserc.json`) on every PR that touches PHP/CSS/TS/JS/design assets.
Scores are compared against the master baseline (refreshed on every push to master
via `.github/workflows/lighthouse-baseline.yml`).

## Reading the PR comment

The sticky comment shows per-URL scores in three categories:

| Marker | Meaning |
|--------|---------|
| `0.92 (±0.00)` | Score and delta vs baseline |
| 🟡 `0.58` | Below warn threshold OR regression > 0.03 |
| 🔴 `0.75` | Below error threshold (PR will fail accessibility-error gate) |

A 🔴 score on accessibility blocks merge (LHCI App status check fails).
A 🟡 marker is informational — investigate before merging.

## When the comment is missing

- Path filter excluded the PR (docs-only, infra-only changes) — expected.
- Baseline workflow has not run yet on master after the most recent
  source-affecting push — comment shows scores without deltas plus a footnote
  "No master baseline yet."

## Modifying the audit

- Add/remove URLs: edit `ibl5/.lighthouserc.json` `collect.url`.
- Change thresholds: edit `assert.assertions`.
- Both changes require an ADR (changes mechanical-enforcement surface).
