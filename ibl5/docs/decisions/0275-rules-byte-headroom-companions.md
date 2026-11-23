---
description: Near-cap rules files free byte room by moving detail text into three new path-scoped companions (auto-commit-detail, ci-gotchas-mutation, workflow-continuity-blocked-ship) plus two existing ones; the moved text keeps its meaning.
last_verified: 2026-11-21
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0275: Free Byte Headroom in Near-Cap Rules Files With Path-Scoped Companions

**Status:** Accepted
**Date:** 2026-11-21
**Deciders:** post-plan harness (auto-draft)

## Context

Several `.claude/rules/` files sat close to the byte cap, and four of them load into every session. Each new rule edit had to cut text first or fail the size gate. Much of the text in these files is detail an agent needs only in one situation: the `bin/owner-approve` arming mechanics, the per-mutant DB setup for the Infection workflows, and the exit 3 blocked-ship report from post-plan. `bin/adr-check` flagged this PR because it adds three new `.claude/rules/*.md` files.

## Decision

Move that situational text into companion files that load only when the matching path is touched, and leave a one-line pointer in the parent. The three new companions are `.claude/rules/auto-commit-detail.md` (loads on `bin/owner-approve`), `.claude/rules/ci-gotchas-mutation.md` (loads on `.github/workflows/mutation*.yml`), and `.claude/rules/workflow-continuity-blocked-ship.md` (loads on the post-plan skill). Two existing companions, `.claude/rules/agent-tiering-fable.md` and `.claude/rules/automouse-workflow-detail.md`, take more moved text. The moved text keeps its meaning. Old dates and PR history notes in the touched files are deleted. No gate changes. The existing rules size checks still enforce the cap.

## Alternatives Considered

- **Raise the byte cap.** Rejected because the cap exists to keep always-loaded context small, and raising it moves the cost to every session.
- **Delete the detail text.** Rejected because agents still need it in the one situation it covers, such as arming a PR or debugging a mutation run.
- **Put all moved text in one shared detail file.** Rejected because a single file would load on every matching path and grow back toward the cap.

## Consequences

- Positive: the touched files gain room under the cap, so later rule edits fit without a forced cut.
- Positive: sessions that never touch the companion paths stop loading that detail.
- Negative: an agent that needs the detail outside those paths must follow the pointer and Read the companion.
- Negative: `ci-gotchas-mutation.md` loads only for the mutation workflows, so its per-mutant DB notes no longer appear on other workflow edits.

## References

- `.claude/rules/auto-commit.md`
- `.claude/rules/auto-commit-detail.md`
- `.claude/rules/ci-gotchas.md`
- `.claude/rules/ci-gotchas-mutation.md`
- `.claude/rules/workflow-continuity-detail.md`
- `.claude/rules/workflow-continuity-blocked-ship.md`
- `.claude/rules/agent-tiering-fable.md`
- `.claude/rules/automouse-workflow-detail.md`
- `ibl5/docs/decisions/0157-doc-freshness-detail-split.md`
- `bin/adr-check`
