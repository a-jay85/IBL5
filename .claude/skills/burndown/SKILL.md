---
name: burndown
description: Run one automatic backlog burn-down batch: rank new issues, pick 5 units, route each item to a plan or an ad-hoc worktree, and start it.
last_verified: 2026-09-29
---

# /burndown

Run one automatic backlog burn-down batch. Picks up to 5 units of work from the
highest-priority delta issues, routes each item, and starts implementation.

## Exit-code contract (from `bin/lib/burndown.sh`)

| Code | Meaning |
|------|---------|
| 0 | success |
| 1 | completed but at least one item's live state was unknown (reported, never guessed) <!-- slop-ok --> |
| 2 | usage error (bad subcommand, flag, or argument) |
| 3 | fail-closed abort (missing report, gh/git/jq failure, HOME unset, missing or malformed ledger) |

## Steps

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

Otherwise read five lines from each `## P<N>` section of the current triage report as
calibration context, then rank every delta issue into a ranks file at
`$W/ranks.md` using `## P<N>` headings and lines shaped like the report's own
(e.g. `- [#42](url) Title — reason`). <!-- slop-ok -->

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
bin/backlog burndown [--pair A,B]... | tee "$W/select.txt"
```

Parse only the final `LEDGER:` line. `LEDGER: none` means all delta issues are in
flight or over budget. Show the SKIP rows and stop.

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

Freed units are not backfilled; one batch per call.

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

**Plan route.** Pick a kebab slug. Compose the prompt exactly as
`.claude/skills/plan-prompt/SKILL.md` does, adding this fixed constraints block:

> Verify the issue's premise with a real scan or run before designing. Resolve
> migration numbers at implement time. Base on master. Do not touch these files held
> by other batch items: `<paths from every other ledger item>`. Emit `## Backlog
> issues` with `closes a-jay85/IBL5-backlog#<n>` (plus each `also_closes`). A parser
> or gate change carries a corpus diff in its verification.

An item with empty `.paths` took the batch's solo slot and is the only item in it, so the
`Do not touch` list is empty.

Fire `bin/plan-now` in its default queue mode through the plan-prompt skill's fire
step. Never start automouse early. Then:

```bash
bin/backlog burndown-record <ledger> <n> route=plan slug=<slug> status=queued
```

**Ad-hoc route.** `bin/wt-new <slug>` (base master). Spawn one
`Agent(subagent_type: "sonnet-5-5")` per ad-hoc item with `model` omitted. Items hold
disjoint paths, so spawn them in one message. The helper prompt carries: the absolute
worktree path, the issue body, the liveness evidence, the paths it may touch, and these
rules. Edit only inside the worktree. Run the relevant tests. Leave the tree dirty. Do
not commit. Never run `bin/post-plan-now`. Reply in one line.

A solo item (empty `.paths`) carries no path list, so the helper prompt says to keep the
change to the files the issue body names.

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

**6. Report.** Show the full status:

```bash
bin/backlog burndown-status <ledger>
```

Show this verbatim, plus one line per closed-fixed or skipped item. Show `$W/sweep.txt`
above the status table. Tell the user to run `/burndown` again for the next batch.
Ad-hoc PRs carry no `Closes` line, so their issues close when the next run's sweep sees
the merge.

`burndown-status` is read-only and safe to re-run anywhere. `burndown-sweep` (every
ledger, run by step 1) and `burndown-close-merged` (one ledger) are the only commands
that close issues after merge.

```bash
rm -rf "$W"
```
