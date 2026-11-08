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
2. **VR gallery.** E2E uploads the gallery JSON as the `vr-gallery-json-<head sha>` artifact. The classifier trusts it only through the Actions run API. The run must be of `.github/workflows/e2e-tests.yml`, from a `pull_request` event for this PR, on the PR's exact head SHA. The artifact name alone proves nothing, and any other artifact holds. A changed or new gating cell holds the PR. Strict-only cells (`strict: true`) and `flakeCells` never hold.

The verdicts are `not-ui`, `ui-paths`, `ui-vr`, `vr-missing`, and `ui-unknown`. Only `not-ui` passes. Every verdict that cannot prove a clean diff holds. The `human-approved` label clears a hold, and the diff-pinned approval fingerprint from ADR-0062 stays as it was.

Five open questions were resolved this way:

- **Late VR signal.** `.github/workflows/human-signoff.yml` waits in-job for the artifact, up to 90 polls 30 seconds apart. It stops early once every trusted `E2E Tests` run for the head SHA has finished without one. A timeout is `vr-missing`. The `labeled human-approved` run skips the wait, since the label clears the hold whatever the gallery shows. It records its fingerprint at once, before a sync run's bounded record wait gives up. The job has no `if:` and no skip arm in its concurrency group. Nothing re-runs the check when the gallery lands after the 45-minute wait. A human re-runs the failed `human-signoff` job by hand. An automatic re-run is tracked in [backlog#1798](https://github.com/a-jay85/IBL5-backlog/issues/1798). A `workflow_run` retrigger was tried and pulled from this PR (owner pick 2026-11-07). A safe one must check the E2E run's conclusion and that its head SHA is still the PR head.
- **Unrenderable diffs.** The classifier reruns `bin/website-affecting`. When the diff is not renderable, the path signal alone decides.
- **Flake vs strict pass.** Strict-only cells sit below VR's own tolerance (ADR-0180), so they stay review-only.
- **`update-baselines` runs.** They publish no artifact. Their new baselines land in `-snapshots/`, so the path signal holds the PR.
- **Trusted gate logic.** Both jobs load the classifier libs and `bin/website-affecting` from a checkout of the base SHA. A PR that edits the classifier cannot change its own verdict, and its change applies only after merge.

Arming condition (8) is now `ui-review-floor`. `tools/postplan-harness/harness/armable.py` `ui_hold` and `bin/lib/pr-armable.sh` `pr_ui_hold` call the classifier's `--paths` mode, and a classifier failure holds. The `feat:` floor is gone.

## Consequences

- Positive: a UI change holds whatever its title says. A `feat:` backend change no longer waits for a human.
- Negative: the signoff job spends runner minutes waiting for VR on renderable PRs, up to its 60-minute timeout.
- Negative: an E2E memo hit uploads no gallery for the new head SHA, so a same-tree re-push (an amend or a squash) holds as `vr-missing`. A lost artifact holds the same way. Either one waits until a human approves or E2E reruns. A slow E2E run that outlasts the wait also holds until someone re-runs the check by hand ([backlog#1798](https://github.com/a-jay85/IBL5-backlog/issues/1798)).
- Residual risk: the gallery is built by the PR's own code on `pull_request`. Pinning it to the PR's own run on its head SHA stops another PR's artifact from clearing this one. It cannot stop a PR from under-reporting its own visual change. A PR whose files are all off the UI path list could change how the gallery is built and pass as `not-ui`. The path list includes every PR-head file the VR job runs before the gallery upload: `.github/workflows/e2e-tests.yml`, `.github/actions/`, the `bin/` scripts it calls, the VR and manual-row specs in any directory, their fixtures, helpers and setup files, `ibl5/package.json` and its lock files, `composer.json` and `composer.lock`, and the Docker files. Editing one of those holds, so a Dependabot bump of the app's own package or lock file holds too. The app under test is what the gallery measures, so it is not on that list. Path-listed UI files always hold, whatever the gallery says.
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
