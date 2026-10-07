---
name: burndown
description: Run an automatic backlog burn-down: rank new issues, pick 5 units (backfilling freed units with further selection rounds), route each item to a plan or an ad-hoc worktree, and start it.
last_verified: 2026-10-06
---

# /burndown

Run an automatic backlog burn-down. Picks up to 5 units of work from the
highest-priority delta issues, routes each item, and starts implementation. When an
item closes as already fixed or is skipped, its unit is freed and a further selection
round fills it. An xhigh item costs 2 units, so a run can end at 4 of 5 when no
candidate fits the last unit.

## Exit-code contract (from `bin/lib/burndown.sh`)

| Code | Meaning |
|------|---------|
| 0 | success |
| 1 | completed but at least one item's live state was unknown (reported as unknown and never guessed) |
| 2 | usage error (bad subcommand, flag, or argument) |
| 3 | fail-closed abort (missing report, gh/git/jq failure, HOME unset, missing or malformed ledger) |

## Steps

**Preflight (model).** This run belongs on Sonnet 5.5. A session on any other model,
Opus included, stops here and tells the user to start the run with
`bin/backlog burndown-launch`, which opens `claude --model claude-sonnet-5-5 "/burndown"`
in the current directory. Continue on another model only when the user says to run it
here anyway. A skill's frontmatter `model:` does not switch the session model, so the
launcher is the pin.

State between bash blocks persists through `${TMPDIR:-/tmp}/burndown-run/`. Create
that directory at the start and remove it at the end.

```bash
W="${TMPDIR:-/tmp}/burndown-run"
mkdir -p "$W"
```

**1. Sweep, then delta.** Sweep every ledger first, then fetch the delta from the last triage report:

```bash
W="${TMPDIR:-/tmp}/burndown-run"
bin/backlog burndown-sweep > "$W/sweep.txt"
```

Exit 0 and exit 1 both continue, and `$W/sweep.txt` goes into the step 6 report. Exit 1
means at least one item's live state was unknown; the sweep closed nothing for that item.
Exit 3 stops the run with stderr shown to the user.

```bash
bin/backlog burndown-delta > "$W/delta.json"
```

Exit 3 means the delta fetch failed (missing report, `gh` failure, or `HOME` unset). Stop
and show the stderr output to the user. If the error names a missing report, tell them to
run a full backlog triage first, then re-run `/burndown`. Never guess ranks.

**2. Rank (judgment).** Read `$W/delta.json`. If `.issues` is empty, skip to step 3.

Otherwise read `.calibration` from `$W/delta.json` as calibration context. It holds the
first five item lines of each `## P<N>` section of the current triage report, and an
empty section shows as `[]`. Do not open the report itself. Then rank every delta issue
into a ranks file at `$W/ranks.md` using `## P<N>` headings and lines shaped like the
report's own (e.g. `- [#42](url) Title — reason`).

```bash
bin/backlog burndown-refresh "$W/ranks.md"
```

On exit 2, fix the named defect (usually an omitted issue) and retry once. A second
failure stops the run.

**3. Pairs (judgment).** Scan the P1 and P2 lines of the refreshed report for two
issues that one fix resolves together. Pass each as `--pair A,B`. With no pairs, pass
nothing.

**4. Select.** Run the selection:

```bash
W="${TMPDIR:-/tmp}/burndown-run"
bin/backlog burndown [--pair A,B]... > "$W/select.txt"; rc=$?
grep -E '^SKIP|^LEDGER:' "$W/select.txt"; echo "exit $rc"
```

The full output stays in `$W/select.txt`. Only `SKIP` rows and the `LEDGER:` line reach
the transcript. The redirect keeps `rc`, so exit 3 stops the run with stderr shown to
the user. A pipe into `tee` or `grep` would report that pipe's status and hide the 3.
Stderr is left unredirected, so `cleared blocked on #N` lines still show.

Parse only the final `LEDGER:` line. `LEDGER: none` on the first round means all
delta issues are in flight or over budget. Show the SKIP rows and stop. On a later
round it means the run is full or out of candidates. Go to step 6.

Under `bin/burndown-loop`, a first-round `LEDGER: none` also writes the batch's empty
sentinel (the path in `IBL5_BURNDOWN_EMPTY_SENTINEL`). The loop calls a batch empty only
when that file exists. A live-unknown round (exit 1) writes no sentinel. Run step 4
exactly as written; the sentinel needs no action from the skill.

A `SKIP ... skip-label: <label>` row is a tagged item. It consumes no unit. A
`cleared blocked on #N` line on stderr means the blocking PR closed and the item is
eligible again.

**5. Per picked item** (read items with `jq .items[]` from the ledger path), in ledger
order:

**Liveness (must run something).** Execute the check the issue implies: the cited
test, the grep over the cited `file:line`, or the failing command. Reading code alone
does not count. Keep the command and an output excerpt in `$W/liveness-<n>.txt`.

If already fixed: `bin/backlog close <n> "<command + excerpt>"`, then:

```bash
bin/backlog burndown-record <ledger> <n> status=closed-fixed
```

**Route.** Apply `.claude/rules/work-triage.md` (cite it; do not restate its bar). An
item you cannot do gets one of three skip forms, then report why:

- Blocked on an open IBL5 PR: `bin/backlog burndown-record <ledger> <n> status=skipped reason=blocked blocked_by=<PR>`.
  This adds the `blocked` label and posts one comment with the PR number. The label
  clears itself on a later run once that PR merges or closes.
- The target files live outside the repo: `bin/backlog burndown-record <ledger> <n> status=skipped reason=out-of-repo`.
  The `out-of-repo` label stays until a human removes it.
- Any other reason you cannot classify: plain `status=skipped`.

A `reason=` record that exits 3 means the ledger kept the skip but the tag failed.
Check `gh auth status`, then rerun `bin/backlog burndown-tag <n> reason=...`.

**Plan route.** Pick a kebab slug. Build the prompt with the script, then fire it:

```bash
W="${TMPDIR:-/tmp}/burndown-run"
f="$W/prompt-<n>.md"
bin/backlog burndown-prompt <ledger> <n> <slug> [--after <L1>]... \
    --evidence "$W/liveness-<n>.txt" > "$f" && bin/plan-now "$f"
```

Pass every earlier ledger of this run as `--after` so the `Do not touch` list covers the
whole batch. The script writes the task line, the branch slug, the Step 3 architect
directive from the item's `.tier`, pointers from `.paths`, the liveness evidence, and
the fixed constraints: verify the premise with a real scan or run, resolve migration
numbers at implement time, base on master, do not touch the paths of every other live
item across the given ledgers, emit `## Backlog issues` with
`closes a-jay85/IBL5-backlog#<n>` plus each `also_closes`, and carry a corpus diff for a
parser or gate change. Do not read `.claude/skills/plan-prompt/SKILL.md`. Exit 2 or 3
from `burndown-prompt` stops this item: record `status=skipped` and report the stderr.

An item with empty `.paths` claims no files and shares the batch like any other item.
Its `Do not touch` list carries the paths of every other live item. No other item's
list names it, so a collision with it surfaces as a merge conflict, fixed when the
later PR rebases.

`bin/plan-now` runs in its default queue mode. Never start automouse early. Then:

```bash
bin/backlog burndown-record <ledger> <n> route=plan slug=<slug> status=queued
```

**Ad-hoc route.** `bin/wt-new <slug>` (base master). Spawn one
`Agent(subagent_type: "sonnet-5-5")` per ad-hoc item with `model` omitted. Items with path refs hold
disjoint paths, so spawn them in one message. The helper prompt carries: the absolute
worktree path, the issue body, the liveness evidence, the paths it may touch, and these
rules. Edit only inside the worktree. Run the relevant tests. Leave the tree dirty. Do
not commit. Never run `bin/post-plan-now`. Reply in one line.

A pathless item (empty `.paths`) carries no path list, so the helper prompt says to keep
the change to the files the issue body names.

When a helper returns, read `git -C <wt> diff --stat` and re-run the helper's named
test. Only when that passes:

```bash
(cd <wt> && bin/post-plan-now --auto)
bin/backlog burndown-record <ledger> <n> route=ad-hoc slug=<slug> status=shipped
```

The record omits `pr_url=` because the PR may not exist yet, and the next sweep records it.

A failed item:

```bash
bin/backlog burndown-record <ledger> <n> route=ad-hoc slug=<slug> status=skipped
```

Its worktree stays dirty and keeps the issue in flight through the Phase 3c worktree check.
A failed ad-hoc item keeps its unit.

**Backfill.** Once every item in the current ledger has a settled status (closed-fixed,
skipped, queued, or shipped), check for freed units. If any item closed-fixed, or was
skipped with no ad-hoc route, run the selection again. Pass every ledger of this run so
far:

```bash
W="${TMPDIR:-/tmp}/burndown-run"
n=$(wc -l < "$W/select.txt")
bin/backlog burndown --after <L1> [--after <L2>]... [--pair A,B]... >> "$W/select.txt"; rc=$?
tail -n +"$((n + 1))" "$W/select.txt" | grep -E '^SKIP|^LEDGER:'; echo "exit $rc"
```

The `tail` prints only this round's rows, so the final `LEDGER:` line shown is this round's. Exit 3 stops the run as in step 4.

Process the new ledger with step 5 again. Repeat until the output ends in
`LEDGER: none`. The loop ends because every round either picks a new issue or returns
none. Issues from earlier ledgers are excluded and never come back.

**6. Report.** Show the full status. Run it for each ledger of the run:

```bash
bin/backlog burndown-status <ledger>
```

Show this verbatim, plus one line per closed-fixed or skipped item. Show `$W/sweep.txt`
above the status table. Tell the user to run `/burndown` again for the next batch.
Ad-hoc PRs carry no `Closes` line, so their issues close when the next run's sweep sees
the merge.

For unattended batches paced against the weekly usage window, a human runs
`bin/burndown-loop start` from a shell (`stop`, `status`, `run --dry-run` are the other
forms). A /burndown session never starts the loop itself.

`burndown-status` is read-only and safe to re-run anywhere. `burndown-sweep` (every
ledger, run by step 1) and `burndown-close-merged` (one ledger) are the only commands
that close issues after merge.

```bash
rm -rf "$W"
```
