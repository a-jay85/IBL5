---
description: Read-on-demand only (no auto-attach trigger). The inline-edit trial to 2026-11-23, its routing change for chunks under 5 files, how `bin/lib/opus-trial-cost.jq` measures it, and the measurement limits. Parent work-triage.md carries the resident rule and review command; work-triage-detail.md links here.
last_verified: 2026-10-26
paths: ".claude/rules/work-triage-inline-trial.md"
---

# Work Triage: Inline-Edit Trial

Read-on-demand companion to `work-triage.md` § Execution routing and `work-triage-detail.md` § Inline vs. delegated.

## The trial

**Inline-edit trial to 2026-11-23.** A design-resolved, machine-verifiable chunk under 5 distinct files is edited inline by Opus (`work-triage.md` § Execution routing). The recipe writer makes the edits, can revise mid-turn, and skips the 17-23K token spawn and the lossy handoff summary. The cost is review independence, partly restored because the Sonnet review agents are a different model from the editor.

For a chunk of 5 or more files, the entangled and trivial criteria in `work-triage-detail.md` § Inline vs. delegated still decide whether Opus stays inline.

## Measurement

`bin/lib/opus-trial-cost.jq` sums the `cost` field of `bin/lib/automouse-pricer --index` per worktree transcript dir and reports `n`, median, mean, and p90 for a half-open window `[from, to)`. Under 20 tasks it sets `note: "insufficient-sample"`, the floor in `bin/measure-delegate-cost`. `cost` is recomputed from transcripts and includes subagents, unlike `logged_cost` (`bin/automouse/backfill-costs`). Transcripts age out after 30 days, so the before-window stats were saved to `~/claude-plans/_reports/2026-10-26-opus-inline-trial-baseline.json`. The review compares that file with a fresh run. The command is in `work-triage.md` § Execution routing.

Known limits:

- **Headless dilution.** Automouse, `/post-plan`, and `bin/plan-now` sessions share the worktree dirs and no index field separates them. They dilute both windows about equally.
- **The headless ledgers cannot see the trial.** `work-triage.md` § Calibration makes this rule a no-op under headless/automouse, so the sidecar `*.costs.jsonl` files price only sessions the trial never changes.
- **Boundary tasks.** A worktree that spans `from` is priced only for sessions on or after it.
- **Observational evidence.** Task mix differs between windows. Treat a median shift as one signal next to redo rate and review findings.
