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
| `run` | Outer loop. Drains the queue, fires two `claude -p` invocations per plan (implementation, then post-plan review), manages logs/heartbeat, and schedules one-shot launchd runs (`run schedule "…"`) or temporary disarms with automatic re-enabling (`run disarm-tonight`, `run disarm-until "YYYY-MM-DD HH:MM [TZ]"`). Holds the `SELF` absolute-path pin used to generate one-shot plists (ADR-0092). Validates each plan's `impl_model` **before** incrementing the attempt counter, disposing an unusable one to `skipped/` with a report so a typo never burns a retry. |
| `queue` | Add/remove/list/requeue/reorder plans in the nightly queue. Enforces the `impl_model` ↔ Verification-Matrix consistency backstop via `../lib/plan-model-consistency`. The listing's MODEL column is wide enough to render full model ids (`claude-sonnet-4-6`) unclipped. |
| `queue-reorder-ui` | Local browser UI to drag-reorder the queue; shells out to `queue reorder` and `../lib/automouse-reorder-router.php`. |
| `self-heal` | Top-of-run recovery. Requeues plans skipped by the staleness gate that now pass `../check-plan-staleness` (only those carrying a `.md.staleness` sidecar marker). |
| `prompt-impl` | The implementation-phase prompt text fed to `claude -p`. |
| `prompt-postplan` | The post-plan-phase prompt text fed to `claude -p`. |

`bin/test-automouse-*` (still in `bin/`, NOT here) are the test harnesses for these
scripts — they are tests, not operational pipeline members, and CI references them
by their `bin/test-automouse-*` paths.

## Loop-side terminal disposition

`bin/automouse/run` no longer depends on the post-plan agent's prose to learn that a
plan is finished. At two points it asks git-forge directly whether the plan's branch
already carries an **OPEN or MERGED** pull request, and if so it performs the
disposition itself: move the plan symlink `queue/` → `done/`, delete the handoff JSON,
clear the `.attempts` / `.failure` / `.cap-refunds` sidecars, and write
`reports/YYYY-MM-DD-done-<slug>.md` if the agent did not write one.

**A held-open PR counts as done.** `/post-plan` always opens the PR; its conditions
decide only whether auto-merge arms, so a PR held for a human (a `feat:` title, an
unmet autonomy contract, any unmet Phase 6.5 condition) is the pipeline's *normal*
terminal state — not a failure. Agents that read "held" as "not success" used to leave
the plan in `queue/`, where it was re-claimed and re-post-planned every iteration,
forever: creating the handoff resets the attempt counter, so `MAX_ATTEMPTS` never
retired it. The loop-side check ends that cycle.

The two check points:

| When | Condition | Effect |
|------|-----------|--------|
| **Claim time**, before the attempt counter increments | no handoff to resume **and** branch has an OPEN/MERGED PR | dispose to `done/`, release the lock, `continue` — **zero `claude -p` spend**, zero attempts burned |
| **After post-plan exits**, only if the environmental breaker did not trip | plan still in `queue/` **and** branch has an OPEN/MERGED PR | dispose to `done/`, then fall through to the normal lock release and between-plans canary |

**Fail-closed.** Only a positive OPEN/MERGED answer triggers a disposition. A forge
error (auth, network, rate limit), no PR for the branch, or a CLOSED-only match all
fall through to the existing behaviour: the plan stays in `queue/` and retries as
before. The branch is taken from the handoff JSON's `branch` field when present and
derived from the plan slug otherwise; a wrong guess finds no PR and is therefore safe.

**The environmental breaker keeps precedence.** A usage-limit or auth exit still
refunds the attempt, writes an `env-stop` report, and stops the run — it is never
re-read as "done". The disposition check runs strictly after the breaker's `break`,
and `bin/test-automouse-postplan-disposition` asserts that ordering statically so the
guarantee cannot be refactored away.

Locked by `bin/test-automouse-postplan-disposition`; the agent-facing statement of the
same three outcomes lives in `bin/automouse/prompt-postplan` Step 4.

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
