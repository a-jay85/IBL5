---
description: The in-run fix round touches a security-audit finding only while a fresh, self-checking reviewer-eval record meets the owner's bar, rechecks such a fix on a blind Opus seat, and bin/review-outcomes counts later fix commits blamed on auto-merged PRs.
last_verified: 2026-11-07
---

# ADR-0232: Gate security-audit fixes on a measured reviewer bar

**Status:** Accepted
**Date:** 2026-11-07
**Deciders:** a-jay85

## Context

The post-plan fix round sent every holding finding to the automated fixer, security-audit findings included, because `collect_items` had no source filter. The owner's policy is that the fixer may touch a security finding only once the reviewer has a measured record of catching real bugs. The reviewer eval from ADR-0201 already scores the reviewer against blame-confirmed cases, but nothing stored a score that a run could trust, and the corpus held only 9 confirmed cases. The fix round's recheck rejected only a new holding finding, so a fix that left the original security finding standing would still pass.

## Decision

The bar is a pure integer verdict in `tools/postplan-harness/harness/reviewer_bar.py`: at least 30 confirmed cases, recall at least 60%, at most 20% of clean cases flagged, and no degraded agent. `bin/reviewer-eval run --write-record` writes one record to `tools/postplan-harness/out/state/reviewer-bar.json` in the main checkout. The record holds the reviewer-surface hash `v`, the corpus digest `c`, the score line, and the per-case cache entries the line came from. The command refuses canned results and exits before any LLM call when the corpus holds fewer than 30 confirmed cases. It runs only on explicit command, after a reviewer or corpus change lands on `master`. `bin/reviewer-eval bar` and the fix round both call `reviewer_bar.check`, which re-scores the stored entries and fails closed on every other state: too few cases, a missing or unreadable record, a stale `v` or `c`, a score line its own entries do not reproduce, or any error.

The fix round asks the bar only when a security-audit item appears. Below the bar the item stays out of the round and keeps holding arming condition (2), so the owner sees it. Above the bar, a round that touched a security item rechecks the post-fix delta on an Opus security seat with the unchanged `security_prompt`. The round rejects when the seat is not Opus, when the security agent degraded, or when any security-audit finding still holds. `bin/review-outcomes` gains a block that counts later `fix:` commits blaming lines from PRs the harness auto-merged, found through executed `pr_merge_auto` records in `actions.jsonl`. `chore:`, `refactor:` and `docs:` subjects never count. `armable.py` is unchanged.

## Alternatives Considered

- **A filter in `armable.holding_findings`.** Rejected because that function also feeds arming condition (2), so any change there weakens a merge hold. A new label has the same problem.
- **Trust a typed score line.** Rejected because nothing would stop a hand-written passing line; the record re-scores its own entries instead.
- **Run the eval on a schedule.** Rejected because it spends tokens on a corpus that cannot pass yet, the same reason ADR-0201 rejected a nightly run.
- **GitHub's merge actor.** Rejected because `--auto` merges under the owner's account, so the actor cannot pick out auto-merged PRs.

## Consequences

- Positive: a PR with no security finding never evaluates the bar, so its `fix-round.json` stays byte-identical.
- Positive: any change to the reviewer surface or the corpus makes an old pass stale until someone re-records it.
- Negative: security findings go to the owner until the corpus reaches 30 confirmed cases and the reviewer meets the bar on them.
- Negative: the record lives in a gitignored state directory, so a fresh main checkout fails the bar until the eval is re-run.

## References

- ADR-0201 (`ibl5/docs/decisions/0201-reviewer-eval-corpus.md`)
- `tools/postplan-harness/harness/reviewer_bar.py`
- `tools/postplan-harness/harness/reviewer_eval.py`
- `tools/postplan-harness/harness/fix_round.py`
- `tools/postplan-harness/harness/review.py`
- `tools/postplan-harness/harness/outcome_log.py`
- `bin/reviewer-eval`
- `ibl5/docs/reviewer-eval.md`
