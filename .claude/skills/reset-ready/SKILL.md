---
name: reset-ready
description: "Refill the headless pipeline before a usage reset or when automouse runs dry: scout pipeline logs and stale docs, open PRs, prod logs, slow queries, Lighthouse a11y scores, code health (PHPStan baseline, mutation, orphan CSS), unshipped plans and branches, GM bug reports and #dev alerts on Discord, master CI failures and green-run warnings, deferred PR follow-ups, dangling doc pointers, session tool errors, ungated bug classes and parked picks whose blocker merged in parallel, plus weekly data-integrity, dead-code and slow-test sweeps, prefer chore, fix, refactor, test and docs picks over feat, fire each pick through bin/plan-now, and confirm automouse, the burndown loop and the usage-gate coordinator will restart after each limit reset."
last_verified: 2026-10-22
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
- Prefer work whose honest PR title type is `chore`, `fix`, `refactor`, `test` or
  `docs`. A `feat:` PR waits for the user's sign-off, so it ships last. Classify the type
  with the GM-notice test in `.claude/rules/auto-commit.md`.
- Never relabel a feat as chore to dodge the hold. The type follows what the diff is.

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
fix, a size (S, M or L), its honest PR title type (`feat`, `fix`, `refactor`, `test`,
`chore` or `docs`), and whether it touches a security surface or weakens a gate.

| Area | Sources |
|------|---------|
| `pipeline` | Automouse `reports/`, `skipped/` and `logs/`; `/tmp/plan-now-*.log` and `/tmp/post-plan-now-*.log` RESULT, BLOCKED and PAUSED lines; the usage-gate log. Cluster failure causes since the last round. Stale docs: run `gh issue list -R a-jay85/IBL5 --label stale-docs --state open --json number,title,createdAt` and read the newest `/tmp/docfix-run-*.log`. Report only an open stale-docs issue with no refresh PR in `context.md`, or a docfix run that skipped or failed. The nightly audit and `bin/docfix-run` fix date-stale docs. Never propose a `last_verified` bump on a doc no change touches. Cap: 1 pick. Dedupe: by issue number against `context.md`. |
| `prs` | Open PRs: failing checks with the first real error from `gh run view --log-failed`, conflicts, stalled holds. Group shared failure causes. Run `bin/pr-cycle-tick --dry-run` first: it already rescues conflicting and red PRs every 30 minutes, so only the PRs it skips at its retry cap count as work. |
| `prodlogs` | Start with `bin/log-fetch-prod --days 7` and work from its Severity, Channel and deduplicated WARNING+ sections. Then `ssh -o BatchMode=yes iblhoops.net`, read-only commands only, for the access logs: 5xx and repeated 404s. Add one line with the last smoke-prod result from `gh run list -R a-jay85/IBL5 --workflow smoke-prod.yml --limit 1 --json conclusion,createdAt`. Map each group to a repo file and line. Cap: 3 picks. Dedupe: by exception class or message against `context.md`. |
| `perf` | The "Slow Queries" section (grouped by repository) of `bin/log-fetch-prod --days 7`. Rank repositories by hit count, then by max ms, and trace each to its query method and the page that calls it. Cite the query text and table for every fix. Cap: 2 picks. Dedupe: by repository or class name against `context.md` Recent plans, the open PR branch names, and local branches from `git branch --list`. Branch `franchise-five-season-window-slow` already covers the FranchiseHistoryRepository slow queries. |
| `a11y` | The newest weekly Lighthouse issue: `gh issue list -R a-jay85/IBL5 --label lighthouse-audit --state all --limit 1 --json number,title`, then `gh issue view <number> -R a-jay85/IBL5`. Take the three lowest Accessibility scores under 1.00 and any Performance score under 0.80. Map each URL to its module with `bin/lighthouse-audit-urls --json`. Fixes stay on existing markup per the pick rules. Do not run Lighthouse locally. Cap: 2 picks. Dedupe: by module against `context.md` and by URL against the earlier reset-ready reports. |
| `codehealth` | `ibl5/phpstan-baseline.neon` hotspots by path from `awk '/count:/{c=$2} /path:/{s[$2]+=c} END{for(p in s) print s[p], p}' ibl5/phpstan-baseline.neon`, skipping every path backlog#1463 already lists. Per-module test ratio, long methods with or without characterization tests, duplicate helpers, TODO and FIXME. Mutation: the newest scheduled run from `gh run list -R a-jay85/IBL5 --workflow mutation.yml --event schedule --limit 1 --json databaseId`, then `gh run download <id> -R a-jay85/IBL5 -n mutation-reports -D /tmp/reset-ready/mutation` and its `infection-summary.log`. Report it only when the Escaped, Timed Out or Not Covered count is above zero. Orphan CSS: `bin/check-orphan-css` without `--crawl`. Its output lists candidates only, so grep the PHP and JS for a composed prefix (such as `txn-badge--` or `year-cell`) before calling a class dead. Behavior-preserving work only. Cap: 3 picks, at most 1 from the baseline. Dedupe: by file path against `context.md` and backlog#1463. |
| `orphanplans` | Plans in `~/claude-plans/*.md` (top level, touched in 45 days) whose slug has no PR in any state and no entry in automouse `queue/`, `done/` or `skipped/`. Read each with `bin/plan-index`, never whole, and check its target still has the problem on master. Unit plans (`<slug>-2-*`) whose earlier unit merged are the best finds. Also worktrees under `~/GitHub/IBL5-worktrees/` with commits ahead of `origin/master` and no PR, and automouse `skipped/` items that stopped on a plan defect while the code was green. Check the queue first: a queued slug's worktree is live work. Skip superseded plans, frontend feature plans, and anything an earlier report skipped on purpose. Cap: 2 picks. Dedupe: by slug against `context.md`. |
| `bugreports` | The GM bug-report channel and #dev on Discord, read with plain GET calls. #dev is channel `1554593873890775140`, read with `DISCORD_TOKEN` from the IBLbot `.env`. The bug channel id and its token are `BUG_CHANNEL_ID` and the token in the IBLbot `.env.bugbot`. Never print a token and never POST. Read about 7 days of #dev and the last 100 bug-channel messages. A GM report with no "Fixed" reply and no issue in `a-jay85/ibl5-bugs` is a fix candidate. A #dev alert that repeats is a pipeline candidate. Map each one to a repo file. Cap: 2 picks. Dedupe: by symptom against `context.md`. |
| `cihealth` | Scheduled and push workflow failures on master in the last 14 days (`gh run list -R a-jay85/IBL5 --branch master --status failure --limit 100` plus `--event schedule`), grouped by workflow, job and first real error. Also warnings inside green runs, such as an artifact over its size limit. Save the log of the newest green master push of Tests and Analysis to a file and `grep -c` it for `PHPUnit Deprecations:`, `PHPUnit Notices:`, `::warning` and `PHP Fatal`. For a PHPUnit count above 0, run `vendor/bin/phpunit --exclude-group database --display-phpunit-deprecations` locally to read the message. A test that fails across 3 or more PR runs is a flake candidate. A `markTestSkipped` or Playwright `test.skip` whose reason no longer holds is a test candidate. Never re-run a workflow. Cap: 2 picks. Dedupe: by workflow and job against `context.md`. |
| `deferred` | Work a reviewer or agent pushed out of scope and nobody picked up. Grep the bodies of PRs merged in the last 21 days for `Watch:`, `follow-up`, `deferred` and `out of scope`, and the review and issue comments of the newest 40. Keep an item only when the code still shows it on master and no backlog issue covers it (`gh issue list -R a-jay85/IBL5-backlog --state all --search`). Cap: 2 picks. Dedupe: by file path against `context.md`. |
| `docrefs` | Path pointers that `bin/check-docs` misses: relative markdown links in `ibl5/docs/` that resolve from the wrong directory, and pointers to scripts or skill files that moved. Scan `.claude/` and `ibl5/docs/`. Skip ADRs, `archive/` and `retrospective-class-registry.md`, which are historical or append-only. Pointer fixes only. Cap: 1 pick. Dedupe: by doc path against `context.md` and the open PR file lists. |
| `tooling` | Tool errors in the 30 newest session transcripts under `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/` from the last 3 days. Count `"is_error":true` results by message with `jq` or `grep -c`. Never read a whole transcript. A hook deny or timeout that repeats on the same skill step points at a skill fix. A deny on a harmless pattern is a gate change, so flag it. Cap: 1 pick. Dedupe: by skill or script against `context.md`. |
| `registry` | Bug classes and "remember to" lines that still have no mechanical gate. Run `bin/check-registry-trigger-rows` first, since it covers the anchored rows. Then read the `ibl5/docs/retrospective-class-registry.md` rows whose `prior:` field names an earlier occurrence, the Rung 3 and Rung 4 rows, and the "do X" lines in the memory `MEMORY.md`. Grep `bin/`, `.github/workflows/` and the skill includes for a guard under any name before calling a class ungated, then find sites that still break the pattern. Prefer an in-repo gate (`bin/check-*`, a test or CI) over a hook. Cap: 1 pick. Dedupe: by file path against `context.md`. |
| `unblocked` | Candidates earlier rounds parked behind an open PR. Pull the `## Skipped` bullets that name a `#NNNN` from every `~/claude-plans/_reports/*-reset-ready.md`, then join them against one `gh pr list -R a-jay85/IBL5 --state open --limit 200 --json number` call. Re-check a bullet only when none of its PR numbers is still open, and keep it only when master still shows the problem. Ignore bullets that name only a `backlog#`. Low volume: expect 0 to 2 finds. Cap: 1 pick. Dedupe: by slug against `context.md` and later rounds' fired lists. |
| `dataintegrity` | Read-only SELECTs on the main-stack DB (`docker exec ibl5-mariadb sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" iblhoops_ibl5 -e "..."'`). Check the newest data date first and say how old the snapshot is. Compare derived tables against an independent source, such as `ibl_jsb_history` wins and losses against `ibl_team_season_records`. Also look for box-score triples with `COUNT(*)<>2` in `ibl_box_scores_teams`, duplicate natural keys and orphan ids in summary tables, and NULL quarter-point rows. Never treat two box-score-derived tables as evidence of each other. Map each violation to the code that writes the table. Cap: 1 pick. Dedupe: by table and season against `context.md`. |
| `deadcode` | Public and protected methods in `ibl5/classes/` whose name appears nowhere outside their own definition, an interface and tests. Confirm each with `git grep -nwI <name> -- .`, and check for dynamic calls, string callbacks and dispatch tables before calling it dead. Report unreferenced `bin/` scripts only as a note, because many are run by hand. Behavior-preserving deletions only. Cap: 1 pick, at most 12 methods in one class family. Dedupe: by class path against `context.md`. Skip code behind an open PR, an unreleased feature plan, or an auth surface. |
| `testtime` | The slowest single tests on the CI critical path. Read the per-script timing table in the log of the newest green `Shell harness regression tests` job, the slowest Playwright specs in the newest E2E run that executed, and step times from `gh run view <id> -R a-jay85/IBL5 --json jobs`. A candidate makes one slow test do less work: a fixture in place of a live walk, a cheaper setup, or a dead wait removed. Never propose shards or matrix splits. ADR-0150 and the comment above `matrix:` in `.github/workflows/e2e-tests.yml` reject them, because every leg pays its setup again on the busy free runner pool. Check done/ for an earlier speedup of the same script. Cap: 1 pick. Dedupe: by test file against `context.md`. |

The burndown loop owns the PHPStan baseline cleanup in backlog#1463, so `codehealth`
never picks a baseline path that issue lists.

Add a `frontend` scout (CSS dedupe, dead styles, a11y on existing markup, axe coverage)
only when the earlier rounds left frontend candidates unplanned. That includes `a11y` and
orphan-CSS candidates the newest `~/claude-plans/_reports/*-reset-ready.md` skipped at a
cap. Keep its prompt inside the pick rules above. Cap: 2 picks. Dedupe: by CSS file or
module against `context.md`.

When a round in the last 6 hours already ran `prodlogs`, `perf` and `a11y`, skip them. They read 7-day or weekly sources. The other scouts read sources that change every hour.

Run `dataintegrity`, `deadcode` and `testtime` at most once a week. Their sources change only at a sim, a large refactor or a CI change, and a sweep drains what it finds. Skip one when a report from the last 7 days lists it.

Two sources came back empty on 2026-10-22 and run at most once a week. Open code-scanning, Dependabot and secret-scanning alerts plus `composer audit`: the daily CodeQL auto-fix task already clears most of them. Fix-commit hotspots from `git log --since=45.days`: nearly every fix already ships with a test.

These tools are not scout sources. `bin/events-review` spawns `claude -p`, DMs the owner
and needs the prod credential. `bin/smoke-prod` hits prod, and its workflow result is
already a `prodlogs` line. `bin/mutation-diff` is a per-PR tool that needs pcov and the
main-stack database. `bin/lighthouse-audit-report` only formats a CI manifest. Never run
Lighthouse or `bin/check-orphan-css --crawl` from a scout.

While the scouts run, do Step 6. Do not poll them. Each one notifies when it finishes.

## Step 4: Pick

Read the reports. Drop candidates that break a pick rule, that a report marks as covered,
or that the reports could not confirm. Merge candidates with one shared fix shape into one
plan. Put every non-feat pick first, ordered by throughput unlocked, then by GM-visible
bug, then by code health. Feat picks fill only the slots left after that, in the same
order.

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

An `orphanplans` pick already has a plan, so it skips plan-now. Queue an unqueued plan
with `bin/automouse/queue <slug>`. For a finished branch that automouse skipped on a plan
defect, fix the plan line, run `bin/lib/plan-matrix-assertions` against the branch diff,
then fire `bin/post-plan-now --auto` from that worktree in a subshell. A destructive or
security plan goes to the report for the user instead.

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
