---
description: Weekly launchd digest that turns the week's merged PRs into a short architecture-delta brief and flags what landed without a recorded human gate.
last_verified: 2026-10-03
---

# ADR-0169: Weekly comprehension-debt digest

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** ajaynicolas

## Context

Nightly auto-merge lands far more PRs than a person can read. The week of 2026-09-26 to 2026-10-03 merged 237 PRs, about 34 per day. Phase 6.5 of `/post-plan` armed auto-merge on 210 of them, and 17 more merged directly with no recorded gate. Only the 10 `feat:` PRs passed the human-signoff floor. `mergedBy` reads `a-jay85` on every PR because the harness merges with the owner's token, so GitHub's merge metadata cannot show who looked. Automouse reports cover one run each. Nothing summarizes a week, so decisions made by agents can land with no human ever reading them. Review capacity then falls behind output with no signal that it has.

## Decision

Add `bin/automouse/comprehension-digest`, run weekly by the LaunchAgent `com.ibl5.automouse-comprehension-digest` (Sunday 10:00 local) and installed by `bin/automouse/comprehension-digest-cron-setup`. The script fetches the seven days of merged PRs that end at 00:00 UTC on the digest date. It classifies each PR as `human-signoff` (a `feat:` title), `auto-armed` (`autoMergeRequest` set), or `direct-merge` (neither). It counts the last two as landed without a recorded human gate and lists which of them touched ADRs, agent rules and skills, CI workflows, migrations, or `bin/` tooling. One headless Sonnet call with no tools turns those facts and the fenced, untrusted PR text into a brief of at most 250 words under three headings. The brief goes to `~/claude-plans/_reports/<date>-comprehension-digest.md` and to a Discord DM. A usage-gate denial or a failed model call sends a `DEGRADED:` brief that still carries the deterministic facts, and the run exits 2. A failed GitHub fetch sends a `DEGRADED:` line and exits 1. The send runs host-side under launchd for the reason ADR-0124 gives: GitHub-hosted runners cannot reach the Discord bot tunnel.

The fixture harness `bin/test-automouse-comprehension-digest` runs in the `harness-tests:` CI job. The registry line in `bin/lib/launchd-expected-jobs.sh` makes `bin/launchd-health-check` flag the job when it is missing or when its last exit is nonzero.

## Alternatives Considered

- **A flag on `bin/automouse/morning-digest`.** Rejected under `.claude/rules/meta-tooling-bar.md`. The morning digest reads automouse's on-disk nightly dispositions once a day. This digest reads GitHub's merged-PR record once a week and makes a model call. A flag would merge two triggers, two data sources, and two failure modes into one script. The cron-setup split follows the three existing schedule-installer precedents.
- **A scheduled GitHub Actions workflow.** Rejected because Actions runners cannot reach `bin/discord-dm` (ADR-0124 constraint 1).
- **Attribution by `mergedBy`.** Rejected because the corpus scan found it uniform across every merged PR.
- **Deterministic counts only, no model call.** Rejected because counts cannot say what changed conceptually, and that is the gap the backlog item names. The counts still ship on every path, so a model failure loses only the narrative.

## Consequences

- Positive: one Discord DM a week says how much merged unseen and which governance surfaces it touched.
- Positive: a model or gate failure degrades to the facts block and a nonzero exit.
- Negative: one weekly Sonnet call, bounded by a 60000-byte input cap and `--disallowedTools`. The installed `claude` CLI has no `--max-turns` flag, so that bound is absent.
- Negative: the new `standard` label in `bin/lib/launchd-expected-jobs.sh` raises the job count recorded in ADR-0142 by one. That ADR stays as written per `.claude/rules/adr-append-only.md`, and this ADR is the record of the change.
- Negative: the `direct-merge` bucket over-reports unseen work by design, since a human button-click looks the same as a scripted direct merge.
- Neutral: the launchd firing and the live Discord and `claude` credential path are intrinsic pre-prod exceptions. Everything else runs in CI.

## References

- `bin/automouse/comprehension-digest`: the digest script.
- `bin/automouse/comprehension-digest-cron-setup`: the launchd scheduler.
- `bin/test-automouse-comprehension-digest`: the fixture harness.
- `bin/automouse/morning-digest`: the sibling digest this one parallels.
- `bin/lib/launchd-expected-jobs.sh`: the health-check registry.
- `bin/launchd-health-check`: the monitor that watches the job.
- `ibl5/docs/decisions/0124-automouse-morning-digest.md`: the host-side send constraint.
- `ibl5/docs/decisions/0142-launchd-health-check.md`: the registry ADR left unedited.
