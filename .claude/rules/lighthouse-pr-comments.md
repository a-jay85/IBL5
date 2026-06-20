---
description: Lighthouse CI posts per-URL scores and deltas-vs-master as a sticky PR comment on every source-affecting PR
paths:
  - ".github/workflows/lighthouse*"
  - "ibl5/.lighthouserc.json"
last_verified: 2026-06-20
---

# Lighthouse PR Comments

## What runs

`.github/workflows/lighthouse.yml` audits a **dynamic subset of URLs selected from
the PR diff** by `bin/lighthouse-pr-urls` on every PR that touches PHP/CSS/TS/JS/design
assets. Selection is module-scoped (a changed `ibl5/modules/<Name>/` file audits that
module's page), with a curated representative-set fallback for global or sweeping
changes (CSS/TS/JS, `ibl5/classes/**`, workflow/config self-edits, or more than 8
modules touched). The master baseline runs the **full** site set (via
`.github/workflows/lighthouse-baseline.yml`) so every PR-selectable URL has a baseline
row to diff against.

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

- URL **selection** lives in `bin/lighthouse-pr-urls` plus the shared
  `Cli\LighthouseUrls` class: the module→sub-page map is `LighthouseUrls::SUB_PAGES`,
  and the global/sweeping fallback set is `LighthouseUrls::REPRESENTATIVE_PATHS`.
- The representative fallback set is mirrored in `ibl5/.lighthouserc.json` `collect.url`
  (pinned equal to `REPRESENTATIVE_PATHS` by a `LighthouseUrlsTest` unit test); both
  workflows `jq`-override `.ci.collect.url`, so the static array is only the
  human-readable default for a bare local `autorun`.
- Change thresholds: edit `ibl5/.lighthouserc.json` `assert.assertions`.
- Changing the selection logic or thresholds (mechanical-enforcement surface) requires an ADR.
