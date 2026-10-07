---
description: bin/retro-mine scans the retrospective class registry weekly and opens a draft PR that proposes one rule edit for each recurring defect family.
last_verified: 2026-10-06
---
# ADR-0166: Weekly retro-miner that drafts rule proposals as draft PRs

**Status:** Superseded by ADR-0178 (2026-10-06)
**Date:** 2026-10-03
**Deciders:** automouse implementation run (backlog#106)

## Context

`/post-plan` Phase 9 appends one row per defect class to `ibl5/docs/retrospective-class-registry.md`. A recurrence must route one rung more mechanical than the earlier row. The registry's own prose said nothing scanned it on a schedule, so a recurring family only surfaced when someone happened to notice. A scan of the 38 rows on master found that token similarity over the Class column surfaces only pairs that `prior:` already links. Keyword facets do surface real families: the shell family spans Rung 3 and Rung 4 and has already produced four separate rule docs. Rule authoring is judgment, so a miner may draft a norm and must never apply one.

## Decision

Add `bin/retro-mine` (example), a weekly launchd job (`com.ibl5.retro-mine`, installed by `bin/retro-mine-cron-setup` (example), registered as `standard` in `bin/lib/launchd-expected-jobs.sh`). A deterministic awk scan tags rows with named keyword facets. It reports a family only at `MIN_ROWS` rows that span `MIN_SPAN` rungs or destinations, and it reports any recurrence that did not escalate. State kept outside the repo stops weekly re-proposals. Each new family gets one deny-all `claude -p` call (`--allowedTools ''`) that returns schema-checked JSON. The trusted script owns every write. It is limited to a new `.claude/rules/<slug>.md` or one row in the forced-trigger table, and it opens a draft PR from a throwaway `git worktree` off `origin/master`. Any drafter, validation, push, or PR failure exits non-zero, so the launchd health check alerts. `bin/test-retro-mine` (example) is the harness and runs in the `tests.yml` shell job.

## Alternatives Considered

- Extend `bin/check-registry-trigger-rows`. It is a PR-time CI gate that checks `anchor:` rows against the trigger table. Rejected because: a weekly proposal generator is a different trigger with a different output, and folding it in breaks that gate's single responsibility.
- Lexical clustering of the Class column. Rejected because: the scan above showed it finds nothing `prior:` has not already linked.
- Let the model edit files with write tools. Rejected because: registry text is untrusted input to a model call, and write tools would turn it into a path-and-content channel.
- A non-draft PR held by a new `bin/lib/pr-armable.sh` predicate. Rejected because: a draft PR cannot merge, so "never auto-applied" needs no gate edit.
- Memory (Rung 5) proposals. Rejected because: memory lives outside the repo and Phase 9 owns it.

## Consequences

- Positive: a recurring defect family reaches a human as a reviewable draft PR instead of waiting to be noticed.
- Positive: the model returns data only, so a prompt injection in a registry row cannot write a file.
- Negative: one Opus call per new family per week, at most.
- Negative: the facet keywords and thresholds are constants that a human tunes by PR.
- Negative: until the job is installed on the host, `launchd-health-check` reports the label missing.

## References

- `bin/retro-mine` (example)
- `bin/retro-mine-cron-setup` (example)
- `bin/test-retro-mine` (example)
- `bin/check-registry-trigger-rows`
- `bin/bug-pipeline-tick`
- `bin/sim-recap-cron-setup`
- `ibl5/docs/retrospective-class-registry.md`
