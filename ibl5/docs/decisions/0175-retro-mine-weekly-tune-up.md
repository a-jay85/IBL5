---
description: bin/retro-mine gains a weekly transcript tune-up stage; records its privacy boundary, the spend ledger, and the rule for proposing a weekly cap.
last_verified: 2026-10-06
---

# ADR-0175: Weekly tune-up stage in bin/retro-mine

**Status:** Superseded by ADR-0178 (2026-10-06)
**Date:** 2026-10-06
**Deciders:** ajaynicolas

## Context

ADR-0166 made `bin/retro-mine` (example) a weekly job that drafts PRs from the retrospective class registry. The registry only sees failures someone wrote down. The week's Claude Code transcripts and headless run logs hold the rest: user corrections, hook denies, Stop-hook blocks, compactions, heavy turns, and failed plan-now, post-plan-now and automouse runs. Nobody reads them by hand.

Two existing tools came close. `bin/digest-dm-build` parses only a merge-digest block. `bin/backlog new` caps filing at 3 issues per origin PR. Neither carries a weekly ranked list.

## Decision

`bin/retro-mine` (example) runs a second, isolated stage every Sunday at 09:15. The registry stage and the tune-up stage each run in their own subshell, so a failure in one never stops the other. Either failure makes the job exit nonzero.

1. A deterministic jq and awk pre-pass reads root-level transcripts of the IBL5 project dirs and the failing run logs from the last 7 days. It extracts scored, redacted excerpts and a normalized signature per event.
2. Sonnet 5.5 digests each kept thread, 40 at most. Findings must cite a signature the pre-pass produced.
3. One Opus call ranks the week.
4. Items that match an open issue from last week's report are dropped and listed as still open. The top 5 survivors are filed in `a-jay85/IBL5-backlog` with `gh issue create`. A title key makes the filing idempotent.
5. A ranked digest goes out through `bin/discord-dm --chunk -` after the prose gate passes.
6. The report lands in `~/claude-plans/_reports/` last, so a failed run retries from its stage cache.

Only the DM and the issue bodies leave the machine. Both pass the redaction filter twice: once at extraction and once before send. The stage never edits code, rules, hooks or memory.

Every LLM call appends a row to a spend ledger. From the fourth delivered run, the DM proposes a weekly cap at the p75 of past run totals plus 25 percent. Enforcing a cap is a later human decision.

## Consequences

- The scoring weights, thread and excerpt caps, and model names are named constants. Tuning them is a PR.
- The report's two tables are a machine-read contract between weeks. Changing their headers breaks next week's dedupe.
- The schedule moves from Monday to Sunday. It needs a one-time `bin/retro-mine-cron-setup --install-schedule` (example) from the main checkout after merge.
- ADR-0166 stays as written. This ADR extends it.
