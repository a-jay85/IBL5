---
description: Scheduled pr-cycle watcher tick, its retry and in-flight guards, the post-plan race fix in both directions, and the gate-timeout rerun.
last_verified: 2026-10-23
---

# ADR-0167: Schedule bin/pr-cycle on a launchd tick

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** A-Jay

## Context

`bin/pr-cycle` readies, rescues and arms open PRs in one pass, but only when a human runs it. A PR that turns DIRTY or settles red overnight waits for the next manual run. A watcher has to run unattended without three failure modes. It must not race a post-plan run already working the same PR. It must not burn Claude budget on a tick with nothing to do. It must not retry the same broken head forever. Exploration also found a race inside the existing pipeline. A worktree-fired `bin/post-plan-now` holds `com.ibl5.postplan-now-<slug>-*`, and a fleet rescue holds `com.ibl5.post-plan-fleet-<PR>`. Neither guard looked at the other family.

## Decision

A launchd LaunchAgent, `com.ibl5.watch-pr-cycle`, runs `bin/pr-cycle-tick` from the main checkout every 1800 seconds. The tick checks, in order, the stop file `$HOME/.ibl5-pr-cycle.stop`, the usage-gate zone (ADR-0143), and a live `bin/pr-cycle` worker. It then builds the qualifying set from `gh` reads alone. A PR qualifies when it is DIRTY or its checks have settled red, and when it has no post-plan run in flight under either label family. Its last post-plan `result.json` must also be at least 30 minutes old, and no rescue may already have been tried on its current head SHA. An empty set exits with no Claude call. Otherwise the tick runs `bin/pr-cycle --go --only <set> --max-ready <n>`, at most three PRs per tick. Retry state lives in `$HOME/.ibl5-pr-cycle-watch/`, outside the repo and safe from `/tmp` cleanup. A second failure on the same head sends one DM and then stays silent until a new push. The predicates live in `bin/lib/pr-cycle-watch.sh`, and `bin/pr-cycle` stays one-pass. Both race directions are closed. `bin/post-plan-fleet` skips a PR whose branch has a live `postplan-now` label. A detaching `bin/post-plan-now` exits 6 when a fleet slot for its PR is loaded.

## Alternatives Considered

- Skip predicates and a scheduled mode inside `bin/pr-cycle`: rejected because that file is already over a thousand lines, its harness pins "never polls, never sleeps", and the worker calls `_auth_probe` before any skip could run. The Claude spend an idle tick must avoid would already be made.
- `/loop` in a Claude session: rejected because every tick re-reads the full conversation context.
- A GitHub Actions schedule: rejected because a public repo has no self-hosted runner (ADR-0086).
- A shared lock file for the two post-plan families: rejected because a lock needs stale-lock reaping, while launchd labels already are the liveness record both guards read. Labels vanish when a job unloads.

## Consequences

- Positive: a PR that goes red or DIRTY gets one rescue per head SHA without a human starting it.
- Positive: an idle tick costs a handful of `gh` reads and no Claude call.
- Positive: the two post-plan families can no longer start on one PR at the same time through the scheduled paths.
- Negative: a tick that launches `bin/pr-cycle` also runs its Stage 2 arming pass over every armable PR, the same as a manual run.
- Negative: a human who runs `bin/post-plan-now --foreground` while a fleet rescue is live is not refused, because `--foreground` is the fleet runner's calling contract.
- Negative: a post-plan run killed before it writes `result.json` gets no 30-minute grace window. The in-flight check covers it only while its launchd label is loaded.

## Install

Run from the main checkout after the PR merges. The plist's Program is the main checkout's copy.

```bash
cd /Users/ajaynicolas/GitHub/IBL5 && git pull --ff-only
bin/pr-cycle-tick-cron-setup --install-schedule
launchctl list com.ibl5.watch-pr-cycle
bin/pr-cycle-tick --dry-run
```

Expected: `Installed com.ibl5.watch-pr-cycle (StartInterval 1800s)`, a `launchctl list` record, and a dry-run report naming each open PR's verdict with no launch.

## References

- `bin/pr-cycle-tick`: the tick with ordered cheapest-first guards
- `bin/pr-cycle-tick-cron-setup`: LaunchAgent installer with `--print-schedule`
- `bin/lib/pr-cycle-watch.sh`: per-PR skip predicates
- `bin/test-pr-cycle-tick`: tick, lib and installer harness
- `bin/pr-cycle`: the one-pass pipeline the tick hands PRs to
- `bin/post-plan-fleet`: fleet-side race fix
- `bin/post-plan-now`: now-side race fix
- `bin/lib/launchd-job.sh`: `ljob_safe_slug` and `ljob_postplan_now_live`
- `bin/wt-sync-cron-setup`: the installer precedent

## Addendum 2026-10-07: attempts are counted after the worker finishes

`bin/pr-cycle --go` detaches a launchd worker and returns at once. A launch therefore says nothing about the outcome. The tick writes a `pending/<id>` entry and passes `PR_CYCLE_RESULT_FILE` to the worker. The worker writes a `# pr-cycle-result v1` TSV (`<pr> <status> <outcome> <reason>`) when it finishes. A later tick with no live worker reconciles each PR from that file. Only a PR whose post-plan run actually started records the one-rescue-per-head attempt. A holdrepeat decline is not an attempt: it is remembered in `declined/` per head, with its own DM (`dm-declined/`). A rescue that never reached post-plan is a strike in `strikes/` (`<sha> <n>`), capped at 2 per head, with one DM (`dm-strikes/`). A usage-gate `paused` outcome burns nothing. A new push changes the SHA and clears all three. `--allow-test-only` lets a test-only rescue reach post-plan; it skips only the exit-5 guard.

## Addendum 2026-10-12: the retry key is the PR's patch-id

The retry cap above was keyed on the head SHA. A rescue rebases onto master and pushes, so the next tick saw a head it had never tried and rescued again. #3022 ran 13 harness runs and #3058 ran 7 before a human stopped them, each on the same change.

The tick now keys every ledger on the PR's patch-id: `attempts/`, `dm-sent/`, `declined/`, `dm-declined/`, `strikes/` and `dm-strikes/`. A clean rebase keeps the patch-id, and a push that changes the diff changes it. The computation is `pcw_patch_id` in `bin/lib/pr-cycle-watch.sh`, shared with `bin/pr-cycle`, which used the same code for its sticky verdicts.

- **Captured at launch.** The qualifying loop computes the key, and the launch writes it as a third column of `pending/<id>`. Reconcile records under that key. Recomputing at settle time would hash the rescue's own push.
- **Fallback.** When the patch-id cannot be computed (fetch error, no merge-base, malformed output) the key is the head SHA, which is the old behaviour. A failure never blocks a PR from a future rescue. The tick logs the fallback and sends one DM per (PR, head) through the `dm-patchid/` ledger.
- **Old state.** Files written before this change hold a bare head SHA. Every reader also matches the current head SHA, so an old record keeps its cap until the next push. That push runs one more rescue, which records the patch-id. Pending files without a key column settle under their SHA.
- **Known limit.** `git patch-id` hashes the changed lines and their 3 context lines. A rebase that changes a line next to one of the PR's hunks changes the patch-id and allows one more rescue. The diff keeps the default context because `bin/pr-cycle`'s sticky verdicts store patch-ids made that way.
- **Dry run.** `--dry-run` still computes the patch-id, so its qualifying set matches a real tick. It writes no state, sends no DM, launches nothing, and deletes the `refs/pr-cycle/<pr>` ref it fetched.

## Addendum 2026-10-18: stale post-plan-running labels are swept by the tick

An armed, green, BEHIND PR stays out of the merge line while it carries `post-plan-running`, because `bin/merge-line-select` skips any PR with that label. A post-plan run that dies before its release step leaves the label behind. PR 3306 and PR 3319 were both stuck this way for hours after their runs had ended.

`bin/pr-cycle-tick` now removes the label when every check evaluates false. The label is at least `PCW_STALE_LABEL_MIN` old (180 minutes, twice `MAX_PP_SECS`). No launchd post-plan job exists for the PR or branch. No process has an argv naming the worktree or the run's slug tokens. No headless `claude -p` has the worktree as its cwd. Nothing was written under the newest `live-<slug>-*` out dir within the same window. A check that cannot be evaluated keeps the label. The sweep runs before the usage gate because it spends no Claude.

The sweep leaves `bin/merge-line-select` and its guard alone. It touches no other label and no merged or closed PR. It does nothing about why the run died.

The original Alternatives entry still holds against a lock file. Launchd labels, the wrapper shell's argv and the harness out dir are records a run already leaves. A new lock file would not exist for the runs that are already stuck.

Every removal logs the PR number, the label age and the evidence string. `bin/pr-cycle-tick --dry-run` prints the would-remove set and changes nothing.

## Addendum 2026-10-19: conflict holds re-try on a new master tip

A DIRTY PR whose rescue ended in a rebase conflict stayed capped forever. The 2026-10-12 patch-id key survives a master advance, so a moving master never reopened the cap. PRs sat skipped on "retry cap" while master moved 10 to 200 commits, and manual rebases then succeeded.

The tick now records `conflict-hold/<pr>` when a rescue settles with outcome `conflict`. The ledger holds the origin/master tip read at that rescue's launch. A 4th pending column carries the tip, because the rescue settles ticks later. Any other settled outcome except `paused` clears the ledger.

A capped DIRTY PR with a conflict hold skips only the attempted-key check, and only when the current tip differs from the held one. Every DIRTY launch bumps `retries/<pr>` for the local calendar day. The bypass stops at `PCW_CONFLICT_RETRY_DAILY_CAP` (2) launches per PR per day. An unreadable tip never opens the bypass. The `PR_CYCLE_MASTER_TIP_CMD` seam replaces the tip read in tests.

Non-conflict holds keep the until-the-diff-changes rule. That covers red CI, failed and held rescues, holdrepeat declines, and strikes. The post-plan harness needs no change: holdrepeat and the held-unfixable marker record only evaluated ship conditions, and a rebase conflict exits before conditions are evaluated.

## Addendum 2026-10-21: gate-timeout reruns

The `All checks green` gate (`.github/workflows/all-checks-green.yml` (example)) fails closed when an anchor check has not reported within its 2400 s wait. On 2026-10-21 runner queueing pushed anchors past that deadline on PRs 3404, 3403, 3399, 3390, 3335, 3353 and 3004. Each gate went red, and its anchors finished green later. The tick read each one as a red PR and launched a Claude rescue, which cannot fix a timeout. A manual `gh run rerun <id> --failed` cleared each one.

The tick now reruns the gate's failed job in place of a rescue when all of these hold for the PR's head SHA:

- The gate check-run is the only failure, apart from `human-signoff`.
- The newest gate run for the SHA is completed with conclusion `failure`.
- Its failed log carries `TIMEOUT: WAIT: anchors not reported/completed: <names>`.
- Every named anchor now has a completed check-run with conclusion `success`.

Reruns per (PR, head SHA) stop at `PCW_GATE_RERUN_CAP` (2), counted in `gate-reruns/<pr>`. That ledger is separate from `strikes/`, so a rerun never spends the no-op rescue cap. A gh read failure skips the PR for the tick. Any other failure shape falls through to the rescue as before. `bin/pr-cycle-tick --dry-run` prints the would-rerun line and writes nothing.

The gate keeps its fail-closed semantics. The rerun sends the same check back through the same wait and timeout. This change leaves the gate workflow, `bin/check-pr-checks-green`, the timeouts and the concurrency groups as they were.

## Addendum 2026-10-22: conflict holds re-fire only when the conflict changed

A conflict hold re-fired on every master-tip move, capped at two launches a day. PR #3367 spent two rescues ($3.70 and $4.16) on commit 5e05c984 against the same five conflicted paths, because master had moved in other files.

The tick now runs `git merge-tree --write-tree --name-only <tip> <head>` when it records a conflict hold. It stores the head SHA and the sorted conflicted path set under the tip line of `conflict-hold/<pr>`. When a later tick finds the hold due, it re-fires only when the head changed, the probe merges clean, the probe's path set differs from the recorded one, or any conflicted file's content differs between the held tip and the new tip (`conflict_files_same` in `bin/pr-cycle-tick`). An identical set of paths with identical content skips with `rebase conflict unchanged (N paths)` and moves the held tip forward, so later ticks wait for a new master tip without probing again. The probe costs no tokens. It writes objects only, and it fetches the PR head once, with no ref, only when the head object is missing.

A probe that cannot decide falls back to the 2026-10-19 rule and re-fires on the new tip. That covers a missing object, unrecognised merge-tree output, a legacy one-line hold, and an `unknown` tip. Each fallback logs a `WARN` line. The first fallback per (PR, head) also sends a Discord DM through the `dm-probe/<pr>` ledger, which `pcw_prune` sweeps for closed PRs. The daily cap of two launches stays as the backstop.

Record time and compare time use the same instrument. The harness audit's plain-rebase path list was rejected as the record source: a rebase replays commits one at a time and can list different paths than one three-way merge, so the two sets would not be comparable.

## Addendum 2026-10-23: gate retired

The `All checks green` gate is deleted, so the 2026-10-21 rerun path above has nothing left to match. `bin/lib/pr-cycle-watch.sh` still sets `PCW_GATE_WORKFLOW` to the old workflow file name. `All checks settled` never waits, so it has no anchor timeout to rerun. Removing the dead rerun code is follow-up work.
