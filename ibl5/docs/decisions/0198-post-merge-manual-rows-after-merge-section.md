---
description: Truly-manual matrix rows tagged post-merge render under a separate ## After merge PR-body section outside the Phase 6.5 condition (1) window, and a merge workflow job posts them as a checklist comment.
last_verified: 2026-11-20
---

# ADR-0198: Post-merge manual rows move to an After merge section

**Status:** Accepted
**Date:** 2026-10-15
**Deciders:** a-jay85

## Context

Phase 6.5 condition (1) reads the `## Manual Testing` window of the PR body through `pr_manual_testing_clearance` and holds on any unchecked `- [ ]` row. Some Truly-manual rows can only run after merge, such as a launchd job on the live host or a credential only prod holds. PR #3105 carries one such row and is held forever by a condition that cannot be satisfied before merge. The row cannot be dropped either, because the human still owes the check. The clearance parser is shared by two engines and `bin/check-pr-manual-testing`, so teaching it to skip tagged rows would add a new release path inside the gate.

## Decision

A plan row keeps its `Truly-manual` type and adds `post-merge: <reason>` to its location cell. `bin/check-plan` gate `[PM]` requires a reason of at least 15 characters that names an intrinsic slice (scheduling, reachability, credential, live-host state). It rejects the marker on any other row type and on a look-and-feel row.

Both post-plan engines render tagged rows as plain bullets under a separate `## After merge` heading placed after `## Manual Testing`. The heading ends the scan window, so condition (1), its predicate, the sentinel rules and the untagged-row hold are unchanged.

On the merge event, the `after-merge-checklist` job in `.github/workflows/merge-digest-notify.yml` runs `bin/after-merge-checklist` with job-scoped `pull-requests: write`. The script posts one sticky checklist comment (`<!-- after-merge-checklist -->`) and the job sends one #merged Discord message. A re-delivered event posts nothing.

A tagged row still forces `impl_model: opus` and `/plan` gate 14(a), so the human decides the merge. The tag only moves where the row is owed.

## Alternatives Considered

- Teaching `pr_manual_testing_clearance` to skip tagged rows was rejected. It adds a new skip path inside the gate across two engines.
- Carrying the checklist through `bin/post-merge-hook` was rejected. A local git hook has no PR number or body.
- Checkbox bullets in the PR body's `## After merge` section were rejected. The tick path substitutes by `- [ ] **Row N**` id, and a future body scan could count them. The human ticks the boxes in the comment.

## Consequences

- A PR whose only manual rows are post-merge now arms on condition (1) when every other condition clears. The merge hold moves to gate 14(a) and `auto_merge: false`, which the plan author still sets.
- The owed checks arrive as a PR comment plus a DM instead of a stuck PR.
- CI gains one more write-scoped job. Its input is the PR body, which it handles as a file and never interpolates into `run:`.

## Addendum: one #merged message per merge (2026-10-21)

The Decision above says the checklist job "sends one #merged Discord message". ADR-0208 retires that message. The job now only posts the sticky PR comment and emits counts. The single #merged digest carries the directions link and @mentions the owner when a person is needed.

## Update (2026-11-20): plan rows, the prose check, and the auto:mac executor

A plan that leaves an owner step after merge now says so in a `## After merge` section of ADR-0198 rows, numbered after the last matrix row. Gate `[AM]` in `bin/check-plan` fails a plan that holds owner-after-merge prose and no row. The post-plan harness copies the plan's rows into the PR body and rewrites tags fail closed. A tag stays only when it is `manual` or when its `auto:mac` step file exists under `bin/post-merge-steps/` (example). Every other tag, `auto:ci` included, becomes `manual`.

The PR-body check `bin/check-post-merge-recipe` gains a prose check with a waiver, `<!-- after-merge-prose-ok: <15+ chars> -->`. The detector `PMR_PROSE_RE` lives in `bin/lib/post-merge-rows.sh`. Measured on 2026-11-20 with `bin/fixtures/after-merge-prose/measure.sh`: `hits=2 of 250` recent PR bodies and `would-fail=3 of 2746` plans. All five hits are true positives and none is a false positive (`bin/fixtures/after-merge-prose/measurement.txt`).

`bin/post-merge-run` runs `auto:mac` rows on the owner's Mac. The step convention is a tracked regular file at `bin/post-merge-steps/<name>` (example). It receives `--pr <N>`, runs from the main checkout with `CLAUDE_HEADLESS=1`, is idempotent, and exits 0 on success. The PR body supplies only the step name. A name that is not a tracked, non-symlink file under that directory is reported as `unknown-step`.

`bin/pr-cycle-tick` triggers the sweep ahead of its usage gate, because the sweep spends no Claude. The first run records a `since` time under `$HOME/.ibl5-post-merge-run`, so earlier merges are never back-filled. A merge commit that has not reached the checkout stays pending for 24 hours, then sends one `checkout-behind` DM.

The merge digest runs at merge time, before the Mac has run anything, so it cannot know `auto:mac` outcomes. The executor owns them and sends one DM per failing PR. The digest's `--auto-failed-count 0` now covers `auto:ci` failures only.

This update is the decision record for `bin/post-merge-run` and `bin/test-post-merge-run`.
