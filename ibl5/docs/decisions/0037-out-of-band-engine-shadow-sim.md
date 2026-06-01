---
description: Run the native-engine SHADOW sim out-of-band as a detached background process instead of inline in the admin update request.
last_verified: 2026-06-01
---

# ADR-0037: Out-of-Band Engine Shadow Sim

**Status:** Accepted
**Date:** 2026-06-01

## Context

The native-engine SHADOW sim (ADR-0035) runs the full season through the Go engine and writes the result to droppable diagnostic tables (`ibl_box_scores_engine_shadow{,_teams}`) for engine-vs-JSB comparison. It was wired as the last step of the synchronous admin "Update All The Things" web request (`scripts/updateAllTheThings.php`).

Even after PR1 (per-game dedupe + a game cap) and PR2 (NDJSON streaming at constant memory removed the cap), running a full-season sim *inside the web request* keeps three liabilities coupled to the admin's update:

- A long run (hundreds of games) stretches the request and risks the web `set_time_limit`.
- An uncatchable fatal in the engine/loader path (e.g. an OOM `E_ERROR`) could abort the request after canonical work.
- The shadow output is **purely diagnostic and droppable** — it never feeds any canonical table — so blocking or breaking the admin update on its behalf is all downside.

Shadow only READS inputs and writes its own droppable tables, so it is safe to run detached, fire-and-forget, decoupled from the request that triggers it.

## Decision

Move the shadow sim out of the web request into a **detached background process**.

- Extract the orchestration into `EngineShadow\EngineShadowRunService::runForSeason()` (build bundle → stream NDJSON → load each game). It propagates exceptions rather than swallowing them; the CLI is the single catch point.
- A CLI entry point `ibl5/scripts/runEngineShadow.php` resolves the season (`--year=` override, else `Season->endingYear`), takes a non-blocking `flock` so overlapping runs are a benign skip, runs the service, and maps outcomes to exit codes (0 = success or no-work, 1 = failure).
- `EngineShadow\ShadowProcessLauncher` spawns that CLI fire-and-forget. `updateAllTheThings.php` calls the launcher (gated `!$isOlympics && ENGINE_SHADOW_ENABLED`) after the canonical update and returns immediately.

### Detached-spawn mechanism (php:apache, Linux)

`proc_open` with an **explicit argv array** `[setsid, --fork, /usr/local/bin/php, runEngineShadow.php]` — no shell string and no user input, so command injection is structurally impossible (same precedent as `EngineRunner`).

- **`setsid --fork`** is load-bearing. Under mod_php the request worker is not a process-group leader, so plain `setsid` would `exec` php *in place* and `proc_close()` would block the request for the entire season. `--fork` makes setsid fork: the intermediate child exits immediately (so `proc_close` returns in milliseconds), and the grandchild runs php in a **new session/process group**. When Apache reaps the request worker, the SIGHUP/SIGTERM to the worker's group does not reach the shadow run — it **survives request end**.
- **File descriptors, not pipes.** stdout/stderr append to a log file and stdin reads `/dev/null`. A pipe with no reader would deadlock the child once the ~64 KB OS buffer fills on a full-season run.
- The php-cli path is a fixed constructor default (`/usr/local/bin/php`), never `PHP_BINARY` (which under mod_php resolves to the Apache binary).

## Alternatives Considered

- **Keep it inline (status quo).** Rejected: couples a long/heavy/fatal-prone diagnostic run to the admin's canonical update for zero canonical benefit.
- **A real job queue / worker (e.g. a queue table + cron worker).** Rejected as overkill for a single fire-and-forget diagnostic run; no infrastructure exists for it yet and the detached process is sufficient. The CLI is cron-ready if scheduling is wanted later.
- **`exec`/`shell_exec` with a backgrounding `&`.** Rejected: a shell string is injection-prone and harder to reason about; `proc_open` with an explicit argv array and `setsid --fork` is the injection-safe, shell-free equivalent.

## Consequences

- Positive: the admin update can never be blocked, slowed, or aborted by the shadow sim; isolation comes from the **process boundary**, not try/catch.
- Positive: the run is idempotent (PR1 per-game dedupe), so a re-run or an overlap (skipped via `flock`) is harmless.
- Negative / accepted: **fire-and-forget by design — no completion feedback** is surfaced to the admin. Success/failure is observable only in the append-only log file (`sys_get_temp_dir()/ibl5-engine-shadow.log`) and the shadow tables.
- Failure modes: an orphaned process has no parent to reap it, but it either completes or is rendered harmless by the per-game dedupe on the next run; lock contention is a benign skip; the log file grows append-only (rotate externally if needed).
