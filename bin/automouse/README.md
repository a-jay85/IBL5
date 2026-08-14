# bin/automouse/ — nightly autonomous implementation pipeline

Operational scripts for the **automouse** unattended workflow (ADR-0007). These
were moved out of a flat `bin/automouse-*` prefix into this directory; the
`automouse-` prefix is stripped because the directory name already provides the
context (same convention as `bin/lib/db-helpers.sh`).

## Data flow

```
queue/ ──► run ──► (per plan) claude -p impl  ──► handoff/<plan>.json
  ▲          │                 claude -p postplan ──► PR
  │          └─► self-heal (top of run: requeue plans that now pass staleness)
queue ◄──── queue-reorder-ui (browser drag-reorder UI, writes queue order)
```

## Scripts

| Script | Role |
|--------|------|
| `run` | Outer loop. Drains the queue, fires two `claude -p` invocations per plan (implementation, then post-plan review), manages logs/heartbeat, and schedules one-shot launchd runs (`run schedule "…"`) or temporary disarms with automatic re-enabling (`run disarm-tonight`, `run disarm-until "YYYY-MM-DD HH:MM [TZ]"`). Holds the `SELF` absolute-path pin used to generate one-shot plists (ADR-0092). |
| `queue` | Add/remove/list/requeue/reorder plans in the nightly queue. Enforces the `impl_model` ↔ Verification-Matrix consistency backstop via `../lib/plan-model-consistency`. |
| `queue-reorder-ui` | Local browser UI to drag-reorder the queue; shells out to `queue reorder` and `../lib/automouse-reorder-router.php`. |
| `self-heal` | Top-of-run recovery. Requeues plans skipped by the staleness gate that now pass `../check-plan-staleness` (only those carrying a `.md.staleness` sidecar marker). |
| `prompt-impl` | The implementation-phase prompt text fed to `claude -p`. |
| `prompt-postplan` | The post-plan-phase prompt text fed to `claude -p`. |

`bin/test-automouse-*` (still in `bin/`, NOT here) are the test harnesses for these
scripts — they are tests, not operational pipeline members, and CI references them
by their `bin/test-automouse-*` paths.

## Host-state runbook — REQUIRED after this rename merges

The rename from `bin/automouse-run` to `bin/automouse/run` touches **live host
state that lives outside the repo** and that the *running* pipeline reads. CI
cannot reach it, and the implementation run **deliberately did NOT touch it** —
repointing it before master carries the new path would brick the next nightly run
(and unloading the launchd job would SIGTERM the very run doing the work). Do these
steps **by hand on the Mac host, after this PR is merged to master and pulled**:

1. **Repoint the launchd Program path** in
   `~/Library/LaunchAgents/com.ibl5.automouse.plist`:
   `…/bin/automouse-run` → `…/bin/automouse/run` (both `<Program>` and the
   `ProgramArguments` string).
2. **Reload the daemon** so it reads the new path:
   ```bash
   launchctl unload ~/Library/LaunchAgents/com.ibl5.automouse.plist
   launchctl load   ~/Library/LaunchAgents/com.ibl5.automouse.plist
   launchctl list com.ibl5.automouse    # confirm it resolves & is loaded
   ```
3. **Purge any live one-shot plists** still embedding the old path, then reschedule
   if desired:
   ```bash
   grep -l "bin/automouse-run" ~/Library/LaunchAgents/*.plist 2>/dev/null \
     | while read -r p; do launchctl unload "$p"; rm "$p"; done
   ```
4. ~~**Sweep `~/.claude/hooks/`**~~ — **already done (2026-08-09), before merge.**
   The three stale mentions (`bash-guard.sh` and `plans-write-guard.sh` deny-message
   text, `subagent-persist-gate.py` comment) were pure prose, so correcting them early
   could not break a running pipeline. These files live outside the repo, so the fix is
   **not** in this PR's diff and is **not** covered by CI or by a revert of this PR.
   Re-check (expect zero output):
   ```bash
   grep -rl "bin/automouse-run\|bin/automouse-queue\|bin/automouse-self-heal" \
     ~/.claude/hooks/
   ```

Until step 2 is done and verified, the nightly pipeline still runs from the old
main-checkout path — which is why this PR is `auto_merge: false` and held for human
merge + host verification.
