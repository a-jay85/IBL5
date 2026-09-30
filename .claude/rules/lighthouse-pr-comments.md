---
description: Lighthouse CI runs after merge only (master-push baseline plus weekly audit); where the audited URL set and thresholds live, and the NO_FCP empty-body failure.
paths:
  - ".github/workflows/lighthouse*"
  - "ibl5/.lighthouserc.json"
last_verified: 2026-09-29
---

# Lighthouse CI (post-merge)

## What runs

Lighthouse runs after merge only. PRs get no Lighthouse check and no sticky comment.

- `.github/workflows/lighthouse-baseline.yml` runs on master push. It collects the **full** site set with `lhci collect`, applies no assertions, and uploads the `lighthouse-baseline-manifest` artifact. An artifact-age gate skips the run when a fresh baseline already exists.
- `.github/workflows/lighthouse-audit.yml` runs weekly (Sunday 03:00 UTC) and on `workflow_dispatch`. It audits the full set against `ibl5/.lighthouserc.json` and opens or closes the `lighthouse-audit` issue.

## Why there is no PR run

The PR workflow was removed on 2026-09-29 to free runner slots for the four required checks. It was never a required check, and no hook, harness, or promotion script read its result. An accessibility regression now surfaces in the weekly `lighthouse-audit` issue. To audit a branch before merge, run a local `lhci autorun` with `ibl5/.lighthouserc.json` against the worktree stack.

## Modifying the audit

- The module to sub-page map is `LighthouseUrls::SUB_PAGES` in the shared
  `Cli\LighthouseUrls` class, listed in full by `bin/lighthouse-audit-urls`.
  `bin/lighthouse-pr-urls` served only the retired PR run and stays until a
  follow-up removes it. `LighthouseUrls::REPRESENTATIVE_PATHS` was the PR fallback
  set and still pins the static `collect.url` default (see below).
- A module that **hard-requires query params** (its bare `?name=<Module>` URL 404s)
  needs BOTH a `SUB_PAGES` entry and a `LighthouseUrls::PARAM_REQUIRED_MODULES` entry —
  the latter suppresses the bare URL, which Lighthouse would otherwise treat as
  `ERRORED_DOCUMENT_REQUEST` and hard-fail the entire audit run on. Enforced by
  `bin/lighthouse-audit-urls --check`, the last step of `.github/actions/lighthouse-setup`
  and so gates both Lighthouse workflows (`lighthouse-baseline.yml`,
  `lighthouse-audit.yml`) before any audit runs. A new param-required module is
  caught the first time either workflow runs.
- The representative fallback set is mirrored in `ibl5/.lighthouserc.json` `collect.url`
  (pinned equal to `REPRESENTATIVE_PATHS` by a `LighthouseUrlsTest` unit test); both
  workflows `jq`-override `.ci.collect.url`, so the static array is only the
  human-readable default for a bare local `autorun`.
- Change thresholds: edit `ibl5/.lighthouserc.json` `assert.assertions`.
- Changing the selection logic or thresholds (mechanical-enforcement surface) requires an ADR.

## NO_FCP — Empty Module Bodies

`NO_FCP ("The page did not paint any content")` means an audited URL returned HTTP 200 with a 0-byte body. An action-dispatch-only module whose `index.php` has no `default` case in its `switch ($pa)` / `switch ($op)` echoes nothing when the action param is absent → blank 200 → NO_FCP. The audit aborts on the first failure; alphabetical order means one blank module masks every module after it.

**Diagnostic sweep** (run against main stack):
```bash
bin/lighthouse-audit-urls | while read -r url; do
  sz=$(curl -s -o /dev/null -w '%{size_download}' "$url")
  [ "$sz" -lt 500 ] && printf 'CHECK %6s  %s\n' "$sz" "$url"
done
```
Re-check flagged URLs with `curl -s -D - -o /dev/null` and read the status line — a `302` redirect also shows 0 bytes but Lighthouse follows it (fine); only a dead `200` with empty body fails.

**Fix:** add a `default:` arm that renders page chrome + a visible notice, or add both a `Cli\LighthouseUrls::SUB_PAGES` entry AND the module name to `Cli\LighthouseUrls::PARAM_REQUIRED_MODULES` in `ibl5/classes/Cli/LighthouseUrls.php`. Enforced by `bin/lighthouse-audit-urls --check`, the last step of `.github/actions/lighthouse-setup`.
