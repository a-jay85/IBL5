---
description: The human-signoff hold keys on the diff and the VR gallery instead of the PR title. Amends ADR-0062.
last_verified: 2026-11-07
---

# ADR-0233: Diff-based UI review hold

**Status:** Accepted. Amends `ibl5/docs/decisions/0062-human-signoff-gate-for-feature-prs.md`.
**Date:** 2026-11-07

## Context

The `human-signoff` required check held a PR only when its title matched `feat:`. A PR titled `fix:` or `chore:` merged with no human look even when it changed what a GM sees. Between [#3544](https://github.com/a-jay85/IBL5/pull/3544) and [#3753](https://github.com/a-jay85/IBL5/pull/3753), 12 UI-touching `fix:` and `chore:` PRs merged unapproved this way. The title is a claim the author makes. The diff is what ships.

## Decision

The hold reads two signals from the diff and ignores the title. `bin/lib/ui-change-classifier.sh` decides both.

1. **Paths.** A changed path that matches the UI list holds the PR. The list covers `ibl5/design/`, `ibl5/themes/`, `ibl5/jslib/`, `ibl5/images/`, `ibl5/error-pages/`, `ibl5/blocks/`, `ibl5/includes/`, `ibl5/language/`, `ibl5/modules/`, the UI, PageLayout, and Navigation class folders, any `*View.php`, `*Renderer.php`, or `*Formatter.php` class, the root renderer pages, and any `-snapshots/` path. Markdown files never count.
2. **VR gallery.** E2E uploads the gallery JSON as the `vr-gallery-json-<tree>` artifact. A changed or new gating cell holds the PR. Strict-only cells (`strict: true`) and `flakeCells` never hold.

The verdicts are `not-ui`, `ui-paths`, `ui-vr`, `vr-missing`, and `ui-unknown`. Only `not-ui` passes. Every verdict that cannot prove a clean diff holds. The `human-approved` label clears a hold, and the diff-pinned approval fingerprint from ADR-0062 stays as it was.

Four open questions were resolved this way:

- **Late VR signal.** `.github/workflows/human-signoff.yml` waits in-job for the artifact, up to 90 polls 30 seconds apart. It stops early once every same-SHA `E2E Tests` run has finished without one. A timeout is `vr-missing`. The job has no `if:` and no skip arm in its concurrency group.
- **Unrenderable diffs.** The classifier reruns `bin/website-affecting`. When the diff is not renderable, the path signal alone decides.
- **Flake vs strict pass.** Strict-only cells sit below VR's own tolerance (ADR-0180), so they stay review-only.
- **`update-baselines` runs.** They publish no artifact. Their new baselines land in `-snapshots/`, so the path signal holds the PR.

Arming condition (8) is now `ui-review-floor`. `tools/postplan-harness/harness/armable.py` `ui_hold` and `bin/lib/pr-armable.sh` `pr_ui_hold` call the classifier's `--paths` mode, and a classifier failure holds. The `feat:` floor is gone.

## Consequences

- Positive: a UI change holds whatever its title says. A `feat:` backend change no longer waits for a human.
- Negative: the signoff job spends runner minutes waiting for VR on renderable PRs, up to its 60-minute timeout.
- Negative: an E2E memo hit that reused an earlier run with no artifact, or a lost artifact, holds as `vr-missing` until a human approves or E2E reruns.
- Negative: `ibl5/admin/` (example) and `ibl5/depthcharts/` (example) are not on the path list. VR is their only backstop.
- Negative: `.github/workflows/auto-mergeable-label.yml` cannot wait for the gallery. Its condition 2 now needs the `human-approved` label, so an armed `not-ui` PR without the label never gets the advisory `auto-mergeable` label. It still merges.
- Negative: a backend change whose render drift stays under VR tolerance passes. VR missed that case before as well.

## References

- `bin/lib/ui-change-classifier.sh`
- `bin/lib/human-signoff-classifier.sh`
- `.github/workflows/human-signoff.yml`
- `.github/workflows/e2e-tests.yml`
- `.github/workflows/auto-mergeable-label.yml`
- `bin/test-human-signoff-classifier`
- `tools/postplan-harness/harness/armable.py`
- `bin/lib/pr-armable.sh`
- `.claude/rules/commit-conventions.md`
