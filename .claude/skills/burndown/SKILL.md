---
name: burndown
description: Run one automatic backlog burn-down batch: rank new issues, pick 5 units, route each item to a plan or an ad-hoc worktree, and start it.
last_verified: 2026-09-28
---

# /burndown

Run one automatic backlog burn-down batch. Picks up to 5 units of work from the
highest-priority delta issues, routes each item, and starts implementation.

## Exit-code contract (from `bin/lib/burndown.sh`)

| Code | Meaning |
|------|---------|
| 0 | success |
| 1 | completed but at least one item's live state was unknown |
| 2 | usage error (bad subcommand, flag, or argument) |
| 3 | fail-closed abort (missing report, gh/git/jq failure, HOME unset, missing or malformed ledger) |

## Steps

State between bash blocks persists through `${TMPDIR:-/tmp}/burndown-run/`. Create
that directory at the start and remove it at the end.

```bash
W="${TMPDIR:-/tmp}/burndown-run"
mkdir -p "$W"
```

**1. Delta.** Fetch the delta from the last triage report:

```bash
bin/backlog burndown-delta > "$W/delta.json"
```

Exit 3 means no triage report exists. Tell the user to run a full backlog triage first,
then re-run `/burndown`. Never guess ranks.

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
item you cannot classify: `bin/backlog burndown-record <ledger> <n> status=skipped`
and report why.

**Plan route.** Pick a kebab slug. Compose the prompt exactly as
`.claude/skills/plan-prompt/SKILL.md` does, adding this fixed constraints block:

> Verify the issue's premise with a real scan or run before designing. Resolve
> migration numbers at implement time. Base on master. Do not touch these files held
> by other batch items: `<paths from every other ledger item>`. Emit `## Backlog
> issues` with `closes a-jay85/IBL5-backlog#<n>` (plus each `also_closes`). A parser
> or gate change carries a corpus diff in its verification.

Fire `bin/plan-now` in its default queue mode through the plan-prompt skill's fire
step. Never start automouse early. Then:

```bash
bin/backlog burndown-record <ledger> <n> route=plan slug=<slug> status=queued
```

**Ad-hoc route.** `bin/wt-new <slug>` (base master). Spawn one
`Agent(subagent_type: "sonnet-4-6")` per ad-hoc item with `model` omitted. Items hold
disjoint paths, so spawn them in one message. The helper prompt carries: the absolute
worktree path, the issue body, the liveness evidence, the paths it may touch, and these
rules. Edit only inside the worktree. Run the relevant tests. Leave the tree dirty. Do
not commit. Never run `bin/post-plan-now`. Reply in one line.

When a helper returns, read `git -C <wt> diff --stat` and re-run the helper's named
test. Only when that passes:

```bash
(cd <wt> && bin/post-plan-now --auto)
bin/backlog burndown-record <ledger> <n> route=ad-hoc slug=<slug> status=shipped
```

A failed item gets `status=skipped`. Its worktree stays dirty and keeps the issue in
flight through the Phase 3c worktree check.

**6. Report.** Show the full status:

```bash
bin/backlog burndown-status <ledger>
```

Show this verbatim, plus one line per closed-fixed or skipped item. Tell the user two
things: run `/burndown` again for the next batch, and run
`bin/backlog burndown-close-merged` after ad-hoc PRs merge. Ad-hoc PRs carry no
`Closes` line, so issues stay open until that command runs.

`burndown-status` is read-only and safe to re-run anywhere. `burndown-close-merged`
is the only command that closes issues after merge.

```bash
rm -rf "$W"
```
