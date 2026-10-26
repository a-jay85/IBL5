---
description: How bin/plan-eval replays past /plan prompts in truncated clones and scores the plans, how to run it, what it costs, and how it stays isolated.
last_verified: 2026-10-26
---

# Plan eval

The plan eval replays past `/plan` prompts whose PRs merged clean and scores the plans the skill writes today. Use it to compare a `/plan` change against `master`. Decision record: `ibl5/docs/decisions/0217-plan-eval-corpus.md`. The reviewer eval in `ibl5/docs/reviewer-eval.md` is its sibling.

## What it measures

The corpus lives in `tools/postplan-harness/fixtures/plan-eval/corpus.json`, with one prompt file per case in `prompts/`. Each case names the PR, its base and merge SHAs, the architect tier the human picked, and `expected_files`: the files the PR changed, minus files the prompt already names. Each run is scored three ways.

- **Mechanical pass.** The plan passes `bin/check-plan` and `bin/check-plan-staleness`, and it has a `## Verification Matrix`, a `## Critical Files` and at least one phase heading.
- **File recall.** The plan's critical files, read through `bin/check-plan --print-critical-files`, are matched against `expected_files`.
- **Tier match.** The first `plan-architect*` sub-agent the run spawned matches the case's tier.

## Score line

`bin/plan-eval run` ends with one line of this shape: `plan-eval v=<12 hex> c=<12 hex> runs=<n> mech=<a>/<n> files=<h>/<e> tier=<t>/<n> no_plan=<x> degraded=<d> leaked=<l> cost_usd=<f>`.

- `v` is the plan-surface hash and `c` is the corpus digest.
- `runs` counts scored runs. A `degraded` or `leaked` run is left out of every denominator. A `no_plan` run stays in and scores zero.
- `mech`, `files` and `tier` are the three measures above. `files` sums hits over the expected-file counts of the scored runs.
- `degraded` counts runs that timed out, died, read the historical plan, or had no result event. `leaked` counts runs that wrote into `~/claude-plans`. Either above 0 makes `run` exit 3.
- `cost_usd` sums the cost the transcripts report.

Per-run lines go to stderr in this shape: `case <id> r<k> <status> mech=<0|1> files=<h>/<t> tier=<chosen>/<expected> cost_usd=<f>`.

## Running it

```bash
bin/plan-eval validate             # schema, history, overlay probe
bin/plan-eval run --dry-run        # what would launch, and the token estimate
bin/plan-eval run                  # repeat until it prints the score line
```

`run` starts each missing case-run as a detached worker, waits up to `--wait` seconds (540 by default), then exits 4 with `pending=<n>` while any are still running. Exit 4 is normal. Run the same command again and it picks up where it left off. It never starts a second worker for a run whose worker is alive. A worker that died becomes a `degraded` record with detail `worker died`. `--retry-degraded` deletes the degraded records for the selected runs so they launch again.

Useful flags: `--cases core|all|ID[,ID...]`, `--runs 1-3`, `--parallel 1-4`, `--model`, `--timeout`. `--base origin/master` scores the plan surface at that ref, the "before" side. Compare two lines with `bin/plan-eval compare --before '<line>' --after '<line>'`. It prints `worse` and exits 1 when any measure drops, and exits 2 when the denominators differ.

`bin/plan-eval smoke` and `bin/plan-eval smoke --detached` exercise the whole path with a fake model and spend no tokens.

Records live in `tools/postplan-harness/out/plan-eval/cache/<v>-<c>/`, which git ignores. Each run also keeps its raw transcript there as `eval-<id>-r<k>.stream.jsonl`.

## Cost

Measured medians per run of past `bin/plan-now` sessions: 73,500 output tokens, 7,960,000 cache-read tokens, 356,000 cache-write tokens and 1,218 seconds of wall time.

| Set | Runs | Output tokens | Serial wall |
|-----|------|---------------|-------------|
| Default (4 core x 1) | 4 | about 294K | about 80 min (40 at `--parallel 2`) |
| Full (`--cases all --runs 2`) | 20 | about 1.5M | about 7 h |

Check usage headroom before a full run. `--dry-run` prints the estimate for the exact selection.

## Isolation

- Each run works in a `git clone --local` at the case's base SHA in system temp. The clone keeps no branches, tags, remote or reflog, so later history is out of reach. The clone is removed when the run ends.
- Today's plan surface is copied over the clone, so the run uses today's skill on the old repo.
- `CLAUDE_HEADLESS=1` is set, and nothing calls `bin/plan-now`, so no plan reaches the automouse queue.
- A stub `gh` that prints nothing and exits 0 sits first on `PATH`.
- The prompt coda points the plans dir at a scratch dir in the clone's temp root and renames the slug to `eval-<caseid>-r<k>`.
- After each run, a sweep deletes regular files named `eval-<caseid>-r<k>` plus `.`, `-` or nothing in `~/claude-plans` and its `.drafts/`. A hit marks the run `leaked`. `run` repeats the sweep for any `eval-` name at the end.
- A tool call that reads the case's historical plan from `~/claude-plans` marks the run `degraded`. A shared-context file the prompt names may still be read.

## Adding a case

Re-run `bin/plan-eval mine --prompts-dir <dir>` against a newer frozen prompt set, check the `--dry-run` output, then write it out. Or add a case to `corpus.json` by hand with its prompt file, then run `bin/plan-eval validate`.

The selection rule picks 5 default, 2 Sonnet and 3 xhigh cases with 2 to 12 expected files each. Two details differ from the first design, and both are in the ADR. A follow-up fix disqualifies a case only when its removed lines blame to the case's merge commit. Files the prompt names are dropped from `expected_files` before the bound applies.

## The plan surface

`PLAN_SURFACE_ENTRIES` in `tools/postplan-harness/harness/plan_eval.py` lists the files and prefixes copied into each clone: the `/plan` skill directory, the three architect agent defs, the shared plan-verification docs in `.claude/review-shared/`, and `bin/check-plan`, `bin/check-plan-staleness` and `bin/plan-tier-hint` with their `bin/lib/` helpers. The same list drives the `v` hash. Add an entry when the skill or `bin/check-plan` starts loading a new file. The `validate` overlay probe runs both gates on a canned plan inside a clone and fails loudly when a needed file is missing.

## Limits

- The corpus is small and runs are noisy. Use `--runs 2` before trusting a one-point move.
- The clone carries base-day repo content beside today's skill, so a rule the skill cites may describe code that did not exist yet.
- The orchestrator model is fixed at Sonnet 5.5, the `bin/plan-now` default.
- The plans-dir override relies on the model following the coda. The sweep catches a miss.

## Baseline

The first live run covered 2 cases on 2026-10-26 with this command:

```bash
bin/plan-eval run --cases recordholders-repository-tests,harness-slash-branch-result-line
```

```text
case harness-slash-branch-result-line r1 ok mech=1 files=2/2 tier=plan-architect/plan-architect cost_usd=3.43
case recordholders-repository-tests r1 ok mech=1 files=3/3 tier=plan-architect/plan-architect-sonnet cost_usd=3.59
plan-eval v=4e5ce364f1dd c=dfd27bafb9fe runs=2 mech=2/2 files=5/5 tier=1/2 no_plan=0 degraded=0 leaked=0 cost_usd=7.02
```

Nothing leaked into `~/claude-plans`. These two records hold token counts from before the counter read the result event's per-model totals, so their token fields are low. Score fields and cost are unaffected. The default 4-case core baseline is still to be run.
