---
description: Retires bin/retro-mine and its weekly launchd job; supersedes ADR-0166 and ADR-0175.
last_verified: 2026-10-06
---

# ADR-0178: Retire the weekly retro-miner

**Status:** Accepted
**Date:** 2026-10-06
**Deciders:** ajaynicolas

## Context

ADR-0166 added a weekly job that grouped retrospective registry rows by keyword and drafted rule PRs. ADR-0175 added a tune-up stage that mined the week's transcripts. A dry run on 2026-10-06, before the job's first scheduled run, showed the selection step was too blunt to be useful. It counted 1270 hook denials and 1 user correction across 186 interactive threads, and it dropped 753 threads over its cap. The models only ever saw 200-character excerpts of the threads the keyword pass picked, so a problem the signals missed never reached a model. Fixing that meant replacing the selection and the prompt inputs, which is most of the code.

## Decision

Delete `bin/retro-mine` (example), its cron setup script, its test harness, and its two lib files. Unload the `com.ibl5.retro-mine` LaunchAgent and drop it from `bin/lib/launchd-expected-jobs.sh`. The registry stays and `/post-plan` keeps writing rows to it. A replacement, if any, starts as a one-off trial that has a model read the user's own messages across a week, and becomes a scheduled job only if that trial finds problems worth acting on.

## Alternatives Considered

- **Tune the signals and the scoring.** Rejected because: the models would still see only short excerpts chosen by keyword, so the blind spot stays.
- **Keep the registry stage only.** Rejected because: one model call can read all 38 rows and group them by meaning, so keyword facets add upkeep without adding signal.

## Consequences

- Positive: about 35 KB of shell, jq, and test harness leaves the meta-tooling surface.
- Negative: nothing scans the registry on a schedule until a replacement proves itself.

## Supersedes

ADR-0166 (weekly registry miner) and ADR-0175 (weekly tune-up stage). Both described `bin/retro-mine` (example), which this ADR removes.

## References

- `ibl5/docs/retrospective-class-registry.md`
- `bin/lib/launchd-expected-jobs.sh`
- `ibl5/docs/decisions/0166-retro-miner-weekly-draft-pr.md`
- `ibl5/docs/decisions/0175-retro-mine-weekly-tune-up.md`
