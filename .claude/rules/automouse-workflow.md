---
description: Automouse autonomous workflow (formerly "nightly") — launchd fires claude -p on a recurring schedule, running two context-isolated agents per plan (implementation + post-plan) with time guards and incremental checkpoints.
last_verified: 2026-09-08
paths: "bin/automouse/**"
---

# Automouse Autonomous Workflow

> **"Automouse" is this pipeline — the autonomous plan-execution machinery (`bin/automouse/*`, this rule).** It was **formerly called "nightly"**; the term was renamed because the user runs it outside nighttime too, so "nightly" was a misnomer that sent people hunting through `cron` / `/schedule` / `CronCreate` / launchd-by-hand. When you read "automouse" (or legacy "nightly") referring to autonomous plan execution, it means **`bin/automouse/run` fired by launchd**, draining the queue built by `bin/automouse/queue` — *not* a generic scheduler. (The macOS `launchd` agent is the scheduling substrate, but the concept lives in these scripts.)

A headless `claude -p` process runs on a recurring schedule via macOS `launchd`. It loops through queued plans — two `claude -p` invocations per plan (implementation, then post-plan) — until the queue is empty or the time guard is exceeded. For a single watched run, `bin/automouse/run plan <slug>` executes exactly one named plan (auto-queuing it if absent) with the same guard machinery, then stops — leaving the rest of the queue untouched.

## Quick Reference

| Action | Command |
|--------|---------|
| Queue a plan | `bin/automouse/queue <slug>` |
| Show queue | `bin/automouse/queue` (no args) |
| Remove a plan from queue | `bin/automouse/queue remove <slug>` |
| Check morning results | `ls ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/reports/` |
| Cancel the next run | `rm ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/queue/*.md` |
| Schedule a one-shot run | `bin/automouse/run schedule "2026-05-28 20:00 PDT"` (self-cleaning launchd agent; TZ optional) |
| Run one plan (one-off, foreground) | `bin/automouse/run plan <slug>` (impl + post-plan for exactly one named plan, then stops; auto-queues if absent, leaves the rest of the queue untouched) |
| Pause tonight's run (auto re-enables) | `bin/automouse/run disarm-tonight` (re-arms the existing plist ~1 h after the skipped run; the manual `launchctl unload` row below stays off until re-armed by hand) |
| Pause until a given time | `bin/automouse/run disarm-until "2026-08-20 09:00 PDT"` (re-arms the existing plist at the given time; the manual `launchctl unload` row below stays off until re-armed by hand) |
| Disable the automouse job | `launchctl unload ~/Library/LaunchAgents/com.ibl5.automouse.plist` |
| Re-enable the automouse job | `launchctl load ~/Library/LaunchAgents/com.ibl5.automouse.plist` |
| Force-trigger now | `launchctl start com.ibl5.automouse` |
| Requeue skipped plans | `bin/automouse/queue requeue` |
| Skip the between-plans master canary | `AUTOMOUSE_SKIP_CANARY=1 bin/automouse/run` |
| Self-heal staleness-FP skips | `bin/automouse/self-heal` |
| Preview self-heal (no changes) | `bin/automouse/self-heal --dry-run` |
| Check logs | `cat ~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/logs/$(date +%Y-%m-%d).log` |

### Disarm ordering and safety

`disarm-tonight` and `disarm-until` follow an arm-before-disarm sequence: the one-shot re-arm launchd agent is bootstrapped and verified **before** the recurring job is unloaded, so if arming fails the pipeline keeps running and nothing is interrupted. Both subcommands refuse to act while a run is in flight (booting out the recurring job would SIGTERM the active process). After a successful disarm, a breadcrumb is written to `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/.disarmed-until` (epoch, human-readable re-arm time, re-arm agent label, and requester), so `cat` on that file immediately answers "why is automouse off?". Repeated disarms clear any prior `com.ibl5.automouse-rearm-*` agents first so disarms never stack; one-shot agents from `run schedule` are deliberately left alone.

## Directory Layout

```
~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/
  queue/    symlinks to ~/claude-plans/*.md (oldest mtime runs first; queuing and
            requeuing stamp the new entry to the BACK, so a plan authored weeks ago
            still enters last — only `queue reorder` changes relative order)
  done/     symlinks moved here after successful execution, or when the impl agent
            detects the plan is already merged (its work shipped under a prior PR)
  skipped/  symlinks moved here when skipped (ambiguity/errors/poison-pill);
            a sibling <plan>.md.staleness marker tags a *staleness* skip (read by bin/automouse/self-heal)
  handoff/  JSON files bridging state from implementation to post-plan agent
  reports/  per-run markdown reports (YYYY-MM-DD-{done|skipped|env-stop|no-queue|error}-<slug>.md
            and YYYY-MM-DD-canary-park.md — no -<slug> suffix, written at plan boundaries when the
            master health check fails; there is no current plan at a boundary so no slug applies);
            plus YYYY-MM-DD-costs.md — per-phase token cost roll-up written by bin/automouse/run
  logs/     claude -p output logs + launchd stdout/stderr
  *.archive/  startup archival: logs/reports/done/skipped entries idle >7 days are
              moved here (logs.archive/, reports.archive/, …) at run launch
```

### Cost accounting and the sidecar ledger

Each phase's cost is recorded in two places: the markdown row in `reports/YYYY-MM-DD-costs.md` and a line-delimited JSON file `logs/YYYY-MM-DD.costs.jsonl` (the *sidecar ledger*). One JSON object per priced phase is written next to the log by `bin/automouse/run` going forward, and by `bin/automouse/backfill-costs` for history. The weekly aggregate in the costs report reads the sidecar rather than re-parsing the markdown rows, replacing the old fragile column-count heuristic.

**Recomputed vs. harness cost.** The harness `result` event undercounts: it sums only the top-level `usage` of the main transcript, missing `usage.iterations[]` entries and all subagent transcripts. `bin/lib/automouse-pricer` recomputes from transcripts after the phase exits — subagent transcripts are still flushing when `result` fires.

**Prov column.** Each cost row carries a `Prov` (provenance) value:

| Value | Meaning |
|-------|---------|
| `recomputed` | Transcript recomputation succeeded and agrees with expectations. |
| `recomputed-anomalous` | Recomputation succeeded but diverges from the harness figure in a way the mechanical check flags: recomputed cost falls more than $0.01 below the harness figure, or the joined transcript spans materially longer than the logged phase duration. |
| `unknown` | No transcript could be joined to this row — the harness figure is left as-is (transcripts age out after ~30 days). |

**`peak_ctx` semantics.** Maximum context occupancy of the **main** transcript only, taken over `usage.iterations[]` when present (the top-level `usage` is their sum, not a single occupancy) and excluding `advisor_message` iterations. Sub-agent occupancy is excluded. Rows before 2026-08-26 carry the older summed figure and read high.

**Reported cost is a floor.** Compaction cost is not in any transcript record — carried separately as `low–high` in "Surcharge est ($)" (cache-read of pre-boundary context → full re-read plus summary output). Not folded into the cost column.

### Startup archival

At launch, `bin/automouse/run` sweeps `logs/`, `reports/`, `done/`, and `skipped/` and moves any
entry untouched for more than `NIGHTLY_ARCHIVE_AGE_DAYS` (default **7**) into a sibling
`<dir>.archive/`. This keeps the working dirs small without deleting history. Symlinks
(`done/`, `skipped/`) are judged on their *own* mtime — the disposition date — and their
absolute targets keep resolving after the move. `queue/` (pending work) and `handoff/`
(transient) are never touched. The step is non-fatal: an archival error never aborts the run.

### Self-heal

Before the startup archival block, `bin/automouse/run` freshens the local master checkout (a
`git fetch` + `merge --ff-only`); this same refresh also runs at each plan boundary when plans
remain queued (the between-plans master canary). Then it runs `bin/automouse/self-heal`. The self-heal script
scans `skipped/` for plans carrying a `<plan>.md.staleness` sidecar marker — the signal that
a plan was skipped specifically by the staleness gate, not for ambiguity / poison-pill
(already-merged plans are not skipped at all — they land in `done/`). For each such plan, it re-runs `bin/check-plan-staleness` against the
freshly-pulled master; if the guard now passes, `bin/automouse/queue` is invoked to requeue
the plan (which also evicts the `.staleness` and `.attempts` sidecars). Use
`bin/automouse/self-heal --dry-run` to preview what would be healed without acting. The step
is non-fatal.

## How It Works

1. **Daytime:** Work with Claude in plan mode. After approval, queue the plan: `bin/automouse/queue <slug>`
2. **On schedule:** `launchd` fires `bin/automouse/run`
3. **Loop:** For each queued plan (oldest first), `bin/automouse/run` fires two `claude -p` invocations sequentially:
   - **Implementation agent** (`bin/automouse/prompt-impl`): creates worktree, implements the plan, makes checkpoint commits, writes a handoff file. Its model is selectable per-plan via a line-1 `impl_model:` frontmatter field accepting six values — `sonnet`/`claude-sonnet-4-6` → Sonnet, `haiku`/`claude-haiku-4-5` → Haiku, `opus`/`claude-opus-5` or an absent field → Opus — resolved by `bin/lib/plan-impl-model` against the whitelist in `bin/lib/plan-model-tier`. Any other value (including `fable`) is rejected before the attempt counter moves. Declare `sonnet` only for uniformly-mechanical plans whose every verification row is objectively machine-checkable. The post-plan agent is always Sonnet.
   - **Post-plan agent** (`bin/automouse/prompt-postplan`): reads the handoff file, runs `/post-plan` (code review, security audit, PR, CI monitoring, auto-merge), writes the completion report
4. **Guards:** The loop stops when the queue is empty or ~4h45m have elapsed. Plans that fail 3 times (after genuine, full-length attempts) are moved to `skipped/` as poison pills.
   - **Environmental failures stop the run cleanly instead of skipping.** A usage/rate limit, auth error, or any transient that kills an agent refunds the attempt and breaks the loop, leaving the **entire queue intact** to resume next run — so one dead-budget run cannot grind every queued plan into `skipped/`. Each stop writes a `YYYY-MM-DD-env-stop-<slug>.md` report. The watchdog stall threshold is **30 min, not 10**, because an asynchronous `Agent` delegate emits nothing on the parent's stream while it works — for the delegate's whole runtime a healthy impl is indistinguishable from a wedged one. A deliberate impl disposition (to `done/` or `skipped/`) is an **outcome, not a transient**, so the loop continues. A wall-clock cap-timeout is refunded too, but only a bounded number of times per plan, and does not break the loop. Exact signatures, thresholds and refund limits: `should_impl_env_stop()`, `impl_cap_timeout()`, `should_refund_cap_timeout()` — locked by `bin/test-automouse-env-breaker` and `bin/test-automouse-impl-cap-timeout`.
5. **After a run:** Check `gh pr list` for new PRs, read reports for details

## Headless Mode

`bin/automouse/run` sets `CLAUDE_HEADLESS=1`. This environment variable gates `/post-plan` Phase 10 (Preview Environment), which is skipped since no human is present to verify visually. All other phases run normally.

## Plan frontmatter: the autonomy contract

Two **optional** line-1 fields let a plan declare *when it is done*, so `/post-plan` can hold a PR whose deliverable never landed. They are a **unit** — **both or neither**; exactly one is a `bin/check-plan` `[K]` violation.

**`stop_condition:`** — a closed enum; any other value, **including empty**, is rejected.

- `tests-green` — holds unless Phase 5 status is `pass` (`fail`, `skipped` and *no status recorded* all fail).
- `evidence-present` — no executable track (docs / tooling-only); done when the declared artifacts are in the diff; no run-outcome check.

**`evidence:`** — a **single line** (not a YAML block list), comma-separated, of repo-relative path tokens; surrounding whitespace ignored, **one token minimum**, each must appear in the PR's changed-file list at post-plan time. Name the **deliverable artifacts**, never Verification Matrix rows.

**Neither field grants merge authority** — an unmet contract only **adds** a hold; a satisfied one never arms a PR. `auto_merge:`, plan gate 14 and `feat:` human-signoff remain that surface, untouched.

**Malformed values fail at authoring time**, before a run is spent — `bin/check-plan` `[K]` names the specific defect. Unknown keys stay **silently ignored**: this adds two *recognised* keys, not a reject-unknown-key rule.

```yaml
stop_condition: tests-green
evidence: bin/lib/plan-autonomy-contract, bin/test-check-plan
```

Every parser is **line-1-anchored** (frontmatter only, to the closing `---`), so the example above never self-selects. Enforced by `bin/lib/plan-autonomy-contract`, `bin/check-plan` `[K]`, `/post-plan` Phase 5.0.

## Feature PRs cannot auto-merge

Conventional-commit **`feat:`** PRs are gated by the required `human-signoff` check and will **not** auto-merge unattended — they wait for a human to apply the `human-approved` label after inspection (ADR-0062). `/post-plan` Phase 6.5 condition (8) deterministically **never arms** a `feat:` PR (a literal title grep), so there is no arm-then-strip; the required `human-signoff` check remains the independent floor that blocks the merge regardless. Maintenance PRs (`fix`/`refactor`/`chore`/`ci`/`docs`/`revert`) auto-merge as before — still subject to Phase 6.5's other conditions, including the PR-time safety verdict (9) on the realized diff. Check `gh pr list` afterward for `feat:` PRs awaiting your label.

## `depends_on:` hold gate

A plan can declare prerequisites in its YAML frontmatter. When a plan is picked from the queue, `bin/automouse/run` calls `bin/lib/plan-depends-on` to evaluate the key before touching the attempt counter. If any dependency is unmet, the plan is **held** (not attempted) and a `.depends-hold` sidecar is written to `queue/<plan>.md.depends-hold`.

**Frontmatter syntax:**

```yaml
---
depends_on:
  - 2099          # PR number — held until merged
  - other-plan    # plan slug — held until other-plan.md appears in done/
---
```

Inline scalar form also works: `depends_on: 2099`.

**Three-state verdict** (from `bin/lib/plan-depends-on`):

| Verdict | Meaning |
|---------|---------|
| `met` | All deps satisfied (or key absent). Proceed to impl. |
| `unmet:<dep>` | Dep resolved cleanly but not yet merged/done. Hold. |
| `unresolvable:<dep>:<reason>` | Dep cannot be evaluated. Hold. Reasons: `gh-error`, `bad-value`, `empty-value`, `no-done-dir`. |

**Hold lifecycle:**

- A held plan stays in `queue/` with a `.depends-hold` sidecar and is skipped every pick cycle (zero attempt cost — the counter never increments).
- `bin/automouse/self-heal` scans `queue/*.depends-hold` on every run and removes the sidecar when the dep is now `met`, re-enabling the plan for the next pick.
- An orphan sidecar (plan left `queue/` via manual removal) is reaped by `self-heal`.
- The `.depends-hold` sidecar is NOT touched by `bin/automouse/queue remove` — self-heal's orphan-reap is the cleanup path.

**Run-scoped dedup:** once a plan is held within a run, it is skipped for the rest of that run (space-padded `DEPENDS_HELD` string). When every plan in the queue is held, the run terminates cleanly rather than spinning.
