---
description: Per-run launchd jobs launch through ljob_submit (launchctl submit plus a once-guard) and write no file under ~/Library/LaunchAgents.
last_verified: 2026-10-21
---

# ADR-0209: Per-run launchd jobs write no LaunchAgents plist

**Status:** Accepted
**Date:** 2026-10-21

## Context

Each run of `bin/plan-now` and `bin/post-plan-now` wrote a fresh plist into `~/Library/LaunchAgents`. Each new file there raises the macOS Background Task Management popup. Cleanup was best effort, so killed runs left files behind: the 2026-10-21 backup set held 66 dead plists. Two sources account for them. 24 came from `bin/test-plan-now` dry runs, which ran under the real HOME. The other 42 point at paired `/tmp/plan-now-*.log` runs from 2026-10-10 whose logs are gone, so their caller cannot be pinned down.

## Decision

A per-run launchd job never writes a file under `~/Library/LaunchAgents`. Per-run launchers go through `ljob_submit <label> <log> <script>` in `bin/lib/launchd-job.sh`. It runs `launchctl submit -l <label> -o <log> -e <log>`, which keeps the job in launchd's memory only. launchd restarts a submitted job after it exits, so the job runs inside a wrapper with an atomic `mkdir` once-guard and a tail that runs `launchctl remove` on its own label. A respawn finds the marker, removes the label and exits 0. Labels keep the `com.ibl5.plan-now-` and `com.ibl5.postplan-now-` prefixes and the PID suffix, so `bin/fleet-status` and `bin/watch-run` discover runs as before. post-plan-now's command moves into a mode-700 script beside its log, which the run deletes as its last command. An age-gated startup reap removes scripts orphaned by SIGKILL or reboot. `bin/test-plan-now` sandboxes HOME and fails if the real agents dir changes during the run.

The spike on this Mac (Darwin 25.5) kept `launchctl submit`; the private-dir bootstrap fallback was not needed. Decisive lines from the spike:

```
SPIKE A submit_rc=0
SPIKE A list_row=[22424	0	com.ibl5.submit-smoke-22394-A]
SPIKE A body_runs=3
SPIKE A bootout_rc=0
SPIKE A list_after_bootout_rc=113
SPIKE A log_mode=append
SPIKE A env=[body pid=22424 PATH=/usr/local/bin:...:/usr/bin:/bin:/usr/sbin:/sbin:... HOME=/Users/ajaynicolas PWD=/ SHELL=/bin/zsh]
SPIKE B body_runs=1
SPIKE B guard_hits=0
SPIKE B marker_left=no
SPIKE C body_runs=1
SPIKE C guard_hits=1
SPIKE C marker_left=no
SPIKE agents_dir_unchanged_after=yes
SPIKE leftover_labels=0
```

Case A (no guard) ran its body three times in 25 s, which confirms the restart. Cases B and C (guard, clean exit and SIGKILL) ran it once. The smoke run through the real `ljob_submit`, shaped like both launchers, passed every check:

```
SMOKE ok   label1 row has numeric PID
SMOKE ok   label2 row has numeric PID
SMOKE ok   label1 gone after exit
SMOKE ok   label2 gone after exit
SMOKE ok   body1 ran exactly once
SMOKE ok   body2 ran exactly once
SMOKE ok   cwd is ROOT
SMOKE ok   git resolves on job PATH
SMOKE ok   HOME is the real home
SMOKE ok   stdout reached -o log 1
SMOKE ok   script2 deleted itself
SMOKE ok   no .once marker left
SMOKE ok   agents dir unchanged after
SMOKE result=PASS
```

## Alternatives Considered

- Plist in a private dir plus `launchctl bootstrap`: keeps run-once semantics without a guard. Rejected because it still writes a plist, a killed launcher leaves it behind, and nobody has checked that launchd skips BTM registration outside the agents dir. It stays the documented fallback if `submit` misbehaves.
- `nohup` or `setsid` detach without launchd: rejected because it loses the label and PID column that `bin/fleet-status` and `bin/watch-run` read.
- post-plan-now's command passed as submit argv: rejected because it shows the full prompt in `ps`, can hit argv length limits, and needs a second quoting layer.

## Consequences

- Positive: plan-now and post-plan-now add nothing to `~/Library/LaunchAgents`, in a real run or a dry run.
- Positive: swapping the mechanism later edits only `_ljob_submit_argv` and `ljob_submit_wrapper_text`.
- Negative: a loaded-idle label left by a killed pre-conversion run makes post-plan-now treat the slug as in flight (exit 6) until someone runs `launchctl remove`. `bin/launchd-health-check` reports such labels as `idle-oneshot`.
- Negative: no test can observe the popup itself. The unchanged agents dir is the proxy.
- Follow-up: units 2 and 3 convert the remaining one-shot launchers and append their prefixes to `LEJ_SUBMITTED_PREFIXES`.

## References

- ADR-0142 (launchd health check)
- `bin/lib/launchd-job.sh`
- `bin/lib/launchd-expected-jobs.sh`
- `bin/test-launchd-job`
- `bin/test-plan-now`
