---
description: Truly-manual matrix rows tagged post-merge render under a separate ## After merge PR-body section outside the Phase 6.5 condition (1) window, and a merge workflow job posts them as a checklist comment.
last_verified: 2026-10-21
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
