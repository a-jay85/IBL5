---
name: reset-ready
description: "Refill the headless pipeline before a usage reset or when automouse runs dry: scout pipeline logs, open PRs, prod logs and code health in parallel, pick refactor-and-fix work, fire each pick through bin/plan-now, and confirm automouse, the burndown loop and the usage-gate coordinator will restart after each limit reset."
last_verified: 2026-10-10
---

# /reset-ready

Find work, plan it, queue it, and make sure the runners keep going across usage resets.
Use it when the weekly limit is about to reset with budget left, or when the automouse
queue is empty.

<user_request>
$ARGUMENTS
</user_request>

The request may name a focus area (for example "prod only") or a count cap. With no
request, run every step.

## Pick rules

- Frontend work is refactor, simplification and a11y fixes on existing markup only. Never
  pick a new page, widget, visual state or skip link.
- Skip anything out of the repo (server config, cPanel, jsb-native).
- Skip work an open PR already covers, or that would collide with one.
- Leave open backlog issues to the burndown loop. List backlog issues that look already
  fixed or duplicated in the report, and leave closing them to the user.
- A recurring cause beats a one-off. A pipeline fix that unblocks many runs beats a single
  code cleanup.
- A SQL, auth or destructive-migration item may go in, but its prompt routes to
  `plan-architect-xhigh`.

## Step 1: Read live state

Run these and keep the numbers for the report.

```bash
A=~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse
ls "$A/queue"                          # empty means automouse has nothing to do
ls -t "$A/done" | head -30             # what shipped since the last round
ls -t "$A/skipped" | head -15          # what failed out
bin/burndown-loop status
launchctl list | grep -E 'automouse|usage-gate-coordinator'
```

Read plan usage from the UserPromptSubmit hook line (5-hour and weekly percent, reset
times). When weekly use is at 95% or more and the reset is over 6 hours away, plan fewer
items. Each plan-now run plus its automouse implementation costs real budget.

## Step 2: Build the dedupe context

Every scout reads this file so nobody proposes work that already exists.

```bash
A=~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse
D=/tmp/reset-ready; mkdir -p "$D"
{
  echo "## Open PRs"
  gh pr list --state open --limit 100 --json number,title,headRefName \
    --jq '.[]|"#\(.number) [\(.headRefName)] \(.title)"'
  echo "## Automouse queue"; ls "$A/queue"
  echo "## Done"; ls "$A/done" "$A/done.archive" 2>/dev/null | grep '\.md$' | sort -u
  echo "## Skipped"; ls "$A/skipped" "$A/skipped.archive" | grep '\.md$' | sort -u
  echo "## Open backlog"
  gh issue list -R a-jay85/IBL5-backlog --state open --limit 200 \
    --json number,title,labels --jq '.[]|"backlog#\(.number) \(.title)"'
  echo "## Recent plans"; ls -t ~/claude-plans/*.md | head -60 | xargs -n1 basename
} > "$D/context.md"
```

Also list the slugs planned in earlier rounds from the newest
`~/claude-plans/_reports/*-reset-ready.md`. Scouts skip those.

## Step 3: Fan out the scouts

Spawn these in one message, each as `subagent_type: "sonnet-5-5"` with
`run_in_background: true`. Each one is read-only and stays in the main checkout cwd. A
session pinned to a worktree blocks Bash calls that resolve to the main checkout, so do
not enter a worktree until the scouts finish. Each writes its report to
`/tmp/reset-ready/<area>.md` at about 6 KB, dedupes against `context.md`, and ends with a
"Not confirmed" list. Each candidate carries a title, evidence with paths and counts, the
fix, a size (S, M or L), and whether it touches a security surface or weakens a gate.

| Area | Sources |
|------|---------|
| `pipeline` | Automouse `reports/`, `skipped/` and `logs/`; `/tmp/plan-now-*.log` and `/tmp/post-plan-now-*.log` RESULT, BLOCKED and PAUSED lines; the usage-gate log. Cluster failure causes since the last round. |
| `prs` | Open PRs: failing checks with the first real error from `gh run view --log-failed`, conflicts, stalled holds. Group shared failure causes. Run `bin/pr-cycle-tick --dry-run` first: it already rescues conflicting and red PRs every 30 minutes, so only the PRs it skips at its retry cap count as work. |
| `prodlogs` | `ssh -o BatchMode=yes iblhoops.net`, read-only commands only. The Monolog app logs `~/public_html/ibl5/logs/ibl5-<date>.log` first, then the access logs for 5xx and repeated 404s. Map each group to a repo file and line. |
| `codehealth` | `ibl5/phpstan-baseline.neon` hotspots by path, per-module test ratio, long methods with or without characterization tests, duplicate helpers, TODO and FIXME. Behavior-preserving work only. |

Add a `frontend` scout (CSS dedupe, dead styles, a11y on existing markup, axe coverage)
only when the earlier rounds left frontend candidates unplanned. Keep its prompt inside the
pick rules above.

While the scouts run, do Step 6. Do not poll them. Each one notifies when it finishes.

## Step 4: Pick

Read the reports. Drop candidates that break a pick rule, that a report marks as covered,
or that the reports could not confirm. Merge candidates with one shared fix shape into one
plan. Order the picks by throughput unlocked, then by GM-visible bug, then by code health.

## Step 5: Write and fire the prompts

Each pick gets one prompt file in `$D/prompts/<slug>.md`, in the `/plan-prompt` format
from `.claude/skills/plan-prompt/SKILL.md` Steps 3 and 4. The first line is
`/plan <one sentence>`. The sections follow in this order:

- `## Exploration pointers`, carrying the scout evidence so the planner does not redo the search
- `## Resolved design decisions`
- `## Scope`, with an out-of-scope list
- `## Hard constraints`
- `## Blocking questions to resolve inside the plan`
- `## Verification`
- `## Step 3 architect tier`, holding a `Step 3 MUST route to <def>` directive
- `## Sequencing`, naming the slug and base `master`

Pick the tier, then check it against the hint, which reads the task line only:

```bash
D=/tmp/reset-ready; mkdir -p "$D/prompts"
for f in "$D"/prompts/*.md; do
  printf '%s ' "$(basename "$f" .md)"
  bin/plan-tier-hint --desc "$(head -1 "$f" | sed 's#^/plan ##')"
done
```

When the hint says `xhigh`, use `plan-architect-xhigh`. When you pick xhigh and the hint
says `default` (a gate narrowing or a SQL rewrite the hint cannot see), plan-now exits 4
unless you pass `--tier-ok "<reason>"`. Then fire each prompt:

```bash
D=/tmp/reset-ready; mkdir -p "$D/prompts"
for f in "$D"/prompts/*.md; do bin/plan-now "$f"; echo "$(basename "$f") rc=$?"; done
```

Exit 3 means the slug already has a plan, so drop it. Exit 4 means a tier mismatch, so fix
the directive and fire again. Each clean run queues itself for automouse and DMs the user.

## Step 6: Keep the runners alive across resets

- **Automouse.** The daily 3 AM launchd job runs it once. For the hours between now and
  the weekly reset, schedule one-shots about 5 hours apart, the first a few minutes after
  the next 5-hour reset: `bin/automouse/run schedule "YYYY-MM-DD HH:MM"`. Check
  `launchctl list | grep automouse-oneshot` first and skip times already covered.
- **Burndown loop.** When `bin/burndown-loop status` shows it stopped, or stuck on a
  paused marker, run `bin/burndown-loop stop` then `bin/burndown-loop start`.
- **Usage-gate coordinator.** `com.ibl5.usage-gate-coordinator` must be loaded. It resumes
  paused sessions after each reset and after a login switch. When it is missing, run
  `bin/usage-gate-cron-setup`.

## Step 7: Report

Write `~/claude-plans/_reports/<date>-reset-ready.md` with the fired slugs by area, the
skipped candidates with a reason each, the runner state from Step 6, and the backlog
issues the user can close. Then tell the user in short plain sentences what got queued and
what needs their hands. End the turn there. Do not arm a Monitor or ScheduleWakeup on the
plan-now runs.
