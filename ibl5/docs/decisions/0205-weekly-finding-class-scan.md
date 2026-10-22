---
description: Weekly launchd scan that clusters recurring PR review findings and files one backlog issue per class proposing a static check.
last_verified: 2026-10-20
---

# ADR-0205: Weekly scan of recurring PR review finding classes

**Status:** Accepted
**Date:** 2026-10-20

## Context

Review agents and the owner leave findings on PR threads every week. Some findings repeat across PRs: the ADR-0171 factory violations in [#2800](https://github.com/a-jay85/IBL5/pull/2800), and `claude -p` runs with no timeout whose verdict was parsed from `2>&1` in [#3363](https://github.com/a-jay85/IBL5/pull/3363). Each repeat costs a review round and a fix. A class that recurs is worth a static check that rejects it at write time. A one-off finding is not. Nothing in the repo counts recurrence today, so these classes are found only when someone happens to remember them. ADR-0178 retired retro mining on the condition that any replacement first prove itself on a trial. A dry-run backfill over the full thread history is that trial. ADR-0200 already ruled that a job reading GitHub threads and filing issues has a different trust boundary from one reading repo files.

## Decision

Add `bin/finding-class-scan`, a separate script from `bin/code-slice-review` per the ADR-0200 trust-boundary ruling. It runs weekly from launchd (`com.ibl5.finding-class-scan`, Sunday 09:15 local time, installed from the main checkout only). A T3 scheduled task was rejected. It depends on the T3 app, cannot be emitted as a unit, cannot be pinned by a golden fixture, and `bin/launchd-health-check` does not watch it.

The script pages every PR's review threads, keeps threads opened by the owner or a bot, and asks Sonnet to assign each new thread to a defect class. Thread text is untrusted input. It sits in a nonce-fenced data block, the model has no tools, its output is schema-checked item by item, and thread ids it invents are dropped.

A class becomes a candidate at 2 distinct threads (`FCS_MIN_OCCURRENCES`). Both seed classes have exactly 2 threads inside one PR, so a distinct-PR threshold would miss them. The issue reports the PR count instead. The classifier labels each class `phpstan`, `bin-check`, or `none`. A `none` class is reported in the run summary and DM and is never filed, because burndown cannot build a check for it. There is no per-run cap on issues. Every filable class at the threshold is filed.

Class identity lives in a key marker on the backlog issue, read from open and closed issues each run. A known class with new evidence gets a comment and is never filed twice. A closed issue gets a comment and stays closed. A classifier failure exits 1 with nothing filed. A GitHub write failure stops the run and leaves earlier filings in place. A prose-gate rejection skips that class, files the rest, and exits 1. The watermark moves only after a clean run that is not capped and not narrowed by `--since`. Local state is a cache. Losing it triggers a full backfill that files no duplicate for a class whose key marker is already in the backlog.

`--verify-schedule` proves the job fires under launchd by running a one-off dry-run plist. A merge-triggered watcher installs and verifies the weekly job on the main checkout after merge.

## Alternatives Considered

- **A mode on `bin/code-slice-review`.** One fewer script. Rejected because that job reads repo files, while this one reads third-party thread text. A shared script would mix two trust boundaries (ADR-0200).
- **A T3 scheduled task.** No plist to maintain. Rejected for the reasons in the Decision.
- **A distinct-PR threshold.** Stricter, so fewer false classes. Rejected because both seed classes sit inside one PR and would never be filed.
- **A per-run cap on filed issues.** Limits backlog flooding. Rejected because the threshold and the key-marker dedupe already bound volume, and a cap would silently drop real classes.

## Consequences

- Positive: recurring classes reach burndown as executable issues that name a check to build.
- Positive: the key marker and the evidence marker keep a class from being filed twice, even after local state is lost.
- Negative: weekly Sonnet spend, capped by `--max-budget-usd` per batch and by `FCS_MAX_BATCHES` batches per run. The default ceiling is 10 batches times the per-batch budget.
- Negative: a class the model re-keys can still duplicate once.
- Negative: `bin/launchd-health-check` alerts on the new expected job until the post-merge install runs.
- Negative: the process runs under the owner's account and inherits the ambient `gh` login. The classifier has no tools, which limits what an injected comment can do.

## References

- `bin/finding-class-scan`
- `bin/test-finding-class-scan`
- `bin/code-slice-review`
- `ibl5/docs/decisions/0200-weekly-code-slice-review.md`
- `ibl5/docs/decisions/0178-retire-retro-mine.md`
