---
description: Run the Discord bug/feature pipeline orchestrator as a Mac-local launchd LaunchAgent firing a poll-only bash driver every 180s via StartInterval — not a daemon, tmux, or persistent claude — with single-flight enforced by an atomic DB lease and no prod credentials in its environment.
last_verified: 2026-09-14
---

# ADR-0080: Mac-local launchd cron topology for the Discord bug/feature pipeline

**Status:** Accepted
**Date:** 2026-07-06

## Context

The Discord bug/feature pipeline (a multi-PR program) needs an autonomous orchestrator that
periodically inspects the DB queue (`ibl_bug_reports`) and drives Claude over untrusted GM thread
text to triage bugs and gather feature requests. The orchestrator must survive Mac sleep/reboot
(resume polling on wake, no lost work); **not burn tokens on idle ticks** (an empty league must
cost a cheap DB round-trip, never a `claude -p` invocation); never run two hunts on the same row
concurrently (single-flight); reach the local Dockerized MySQL (`iblhoops_ibl5`, exposed on
`127.0.0.1`); and use the login session's **ambient `claude` CLI auth** — there is no Anthropic API
key in the repo, and none may be placed in the orchestrator's environment. The repo has no
sub-hour scheduler precedent (`StartCalendarInterval` is calendar-only, used by
`bin/backups-sync-setup` for a nightly 3am job) and no tmux convention (zero tmux usage anywhere).
PR #5a ships the orchestrator **core** — state machine, classifier, feature path — but **no
hunter**: it never transitions a row into `hunting` and has no repo-write / `gh` / push authority
(that arrives with the hunter in PR #5b).

## Decision

Run the orchestrator as a **Mac-local launchd LaunchAgent** (`com.ibl5.bug-pipeline-cron`) in the
user gui domain, firing the poll-only bash driver `bin/bug-pipeline-tick` **every 180 s via
`StartInterval`** — not a daemon, not tmux, not a persistently-running `claude`. **launchd is the
sole persistence mechanism**: a crashed or slept Mac resumes polling on wake. Installed by
`bin/bug-pipeline-cron-setup --install-schedule`, whose plist `Program`/`WorkingDirectory` point at
the **durable main checkout** (derived from `git worktree list`, never a worktree — a worktree gets
torn down and would leave the agent pointing at a dead path). The driver's first act is the
empty-tick cost guard: it reads the actionable set through one CLI wrapper
(`list-active-conversations.php`) and, if nothing is actionable, exits 0 having spawned **zero**
`claude` processes. Single-flight is enforced by the **atomic DB lease** (PR #3), not by launchd —
though launchd also serializes runs (one instance per label; overlapping `StartInterval` fires are
coalesced). In #5a the lease machinery is built + unit-tested but **dormant for bugs** — no driver
step claims a row into `hunting` (the deadlock constraint: a `hunting` row with no hunter would
loop the lease forever). The gui-domain agent inherits the logged-in user's home/keychain, so
`claude -p` uses **ambient CLI auth** — **no `ANTHROPIC_API_KEY`** is ever set; it reaches the
Dockerized MySQL on `127.0.0.1` via `config.php` defaults with only `DB_NAME` overridden through the
plist `EnvironmentVariables`, which also sets an explicit `PATH` (launchd agents start with a
minimal `PATH` that omits Homebrew). The cron mirrors classified bugs/features to a **private**
tracking repo (`BUG_PIPELINE_ISSUE_REPO`, default `a-jay85/ibl5-bugs`) as a best-effort, write-only
projection through one `gh` seam — the DB, not the issue, is authoritative; `gh` runs only in the
cron on the trusted Mac, never in prod PHP.

## Alternatives Considered

- **`StartCalendarInterval` (the existing `bin/backups-sync-setup` idiom)** — rejected: it is
  calendar-only (fixed wall-clock times), wrong for a 3-min poll. `StartInterval` is net-new to the
  repo but is the correct launchd primitive for sub-hour polling; a setup-script comment flags it so
  a reviewer does not "fix" it back.
- **A persistent daemon / tmux session running `claude`** — rejected: it couples liveness to a
  long-lived process, cannot cheaply cover Mac-sleep backfill, and adds a tmux dependency the repo
  has never used. launchd resumes on wake with no supervision.
- **A bot-push trigger (the bot POSTs the cron on each Discord event) instead of polling** —
  rejected: it couples liveness to the bot process and an inbound Mac-local HTTP surface, and cannot
  cheaply cover timed idle reminders or usage-limit backoff wake-ups. A 3-min poll handles new work,
  idle reminders, and blocked-retry uniformly with no new attack surface (deferred as a possible
  later optimization).
- **Placing an `ANTHROPIC_API_KEY` in the agent environment** — rejected: there is no API key in the
  repo; ambient CLI auth via the login keychain is both available and the lower-secret-exposure path.

## Consequences

- Ties the pipeline's liveness to a specific developer Mac being awake — accepted for a
  single-league tool; a slept Mac backfills on wake.
- The 3-min cadence bounds worst-case GM-reply latency and token spend; `StartInterval` becomes the
  repo's precedent for sub-hour launchd polling.
- The orchestrator runs `claude -p` over prompt-injection-exposed GM text and then performs real
  side effects (Discord messages, DB transitions, plan-file creation) — an **intrinsic security
  surface**, so the PR is held for human merge (`auto_merge: false`). No per-tick mechanical check
  can prove an injection-exposed agent behaves safely across arbitrary future input; the defenses
  (tool-capability starvation, hardened prompt, enum-constrained schema, defensive JSON validation,
  fail-safe fallback) reduce but cannot eliminate that risk. #5a's surface is narrower than #5b's —
  no code-ship authority — but is intrinsic on its own.
- `bin/bug-pipeline-cron-setup` and `bin/bug-pipeline-tick` are both new `bin/*` scripts ≥50 lines
  (the `bin/adr-check` trigger); this ADR covers the whole cron topology, so no per-script bypass is
  needed.
- **Liveness heartbeat artifact.** `bin/bug-pipeline-tick` writes the epoch of each tick *start* to
  `$HOME/.claude/logs/bug-pipeline-tick-heartbeat` as the first statement of `main()`, seamed by
  `BUG_PIPELINE_TICK_HEARTBEAT`. `launchctl print` exposes `runs`, `state` and `last exit code` but
  no timestamp, so this file is the only evidence of *when* the driver last started. It is written
  before the `$CLAUDE_BIN` preflight can abort, which deliberately keeps "launchd fired" a distinct
  fact from "the tick succeeded" (the latter is carried by `last exit code`). The value is a bare
  integer epoch from `date '+%s'` — never a formatted timestamp — so the host-local `date -j`
  formatting used elsewhere in the driver cannot leak into a recency comparison. Both the `mkdir`
  and the write are `2>/dev/null || true`: a monitoring line must never be able to fail a tick.
- **Ops health check.** `bin/bug-pipeline-check` is the read-only companion diagnostic for this
  topology. It asserts two independent signals — process liveness (`launchctl` load state, last exit
  code, heartbeat recency against a 600 s threshold ≈ 3.3× the 180 s `StartInterval`) and semantic
  staleness (per-status `SELECT COUNT(*)` probes over `ibl_bug_reports` via `bin/db-query`) — and
  exits 0 healthy / 1 degraded / 2 unprobeable with a greppable `VERDICT:` line. Both signals are
  required: a clean `launchctl` record is compatible with a pipeline that ticks and does nothing, so
  process liveness alone cannot establish health. It performs **no remediation** of any kind — no
  service restart, no lease reset, no re-queue, no `blocked_until` edit — by design: detection and
  alerting only, so a diagnostic run can never itself perturb the pipeline it is measuring.

## Addendum — a third health signal, and local-only tables survive a prod sync (2026-09-14)

Two gaps this ADR's topology left open both fired at once on 2026-09-14, and both are now closed.

**The health check watched the wrong process.** The `## Decision` bullet above records that
`bin/bug-pipeline-check` "asserts two independent signals". As of this addendum it asserts
**three**: process liveness and semantic staleness as described, plus a bounded HTTP probe of the
bug-bot's loopback Express port (`http://127.0.0.1:50001/`, `BOT_BASE_URL`, 5-second `--max-time`),
emitting `bot:reachable` / `bot:unreachable` / `bot:http-error`, and `bot:curl-unavailable` as
UNKNOWN on a host with no `curl`. The original two signals both describe the **cron**; the bug-bot
is a *separate* job. The bot sat dead for roughly four weeks while this script reported
`VERDICT: healthy` every time — the cron ticked correctly over a queue nothing was filling. Both
prior signals were satisfied and the pipeline was fully down.

**`bin/db-sync-prod` destroyed the pipeline's own state.** `bin/dev-up` prod-syncs by default, and
that sync streams `DROP TABLE` / `CREATE TABLE` for every prod table. `ibl_bug_reports`,
`ibl_bug_report_attachments`, `ibl_bug_reporter_profile`, `ibl_bug_pipeline_state` and
`ibl_api_keys` hold state that exists only on this Mac, so every sync wiped the queue, the Discord
cursor, and the locally-minted API key the bot authenticates with — after which every
`/api/v1/bug-pipeline/*` call returned 401 with no visible cause. `bin/db-sync-prod` now carries a
`PRESERVE_TABLES` list covering those five, applied **preserve-if-exists**: a table already present
locally is excluded from both the schema dump (`--ignore-table`) and the data passes; a table that
does not exist yet is left alone so the prod dump still creates it. The condition is load-bearing —
the `schema_migrations` backfill copies prod's applied-migration list, so `bin/db-migrate` treats
migrations 153 and 158 as already done and skips them; an unconditionally-excluded table would
therefore never be created by anything.

Deliberate trade-off: preserving `ibl_api_keys` wholesale means prod key changes no longer
propagate to local. API keys are per-environment credentials, so a local mirror of prod's keys was
never the point.
## Addendum — the bug-bot moves from PM2 to launchd (2026-09-14)

The original ADR scoped only the **orchestrator** (`bin/bug-pipeline-tick`). The other
half of the Mac-local pipeline — the bug-bot Discord process — was supervised by PM2
(`ibl5/IBLbot/ecosystem.bugbot.config.cjs` (example), `max_restarts: 10`, started by hand inside
tmux). That file is deleted; the bot is now the LaunchAgent `com.ibl5.bug-bot`
(`RunAtLoad` + `KeepAlive`, `ThrottleInterval` 10), generated and installed by
`bin/bug-pipeline-cron-setup --install-bot`.

**What changed and why.** On 2026-09-14 the pipeline was found to have been dead for
roughly four weeks. The cron was healthy throughout (`runs = 2414`, last exit code 0); the
*bot* was down. Its last successful start was 2026-08-18 20:33; the PM2 daemon restarted
2026-09-06 12:17:50 and brought back zero apps, because no `pm2 startup` job had ever been
installed. `~/.pm2/dump.pm2` still listed `ibl-bug-bot`, so the loss was silent — nothing
in the topology could resume it, and `bin/bug-pipeline-check` reports only on launchd, so
it read `VERDICT: healthy` for the whole outage.

This is the same argument the original `## Alternatives Considered` already made against
"a persistent daemon / tmux session running `claude`" — *it couples liveness to a
long-lived process*. That reasoning was applied to the orchestrator and not to the bot;
the outage is what that gap costs. launchd was already the sole persistence mechanism for
half this pipeline, and is now the sole persistence mechanism for both halves.

Three properties the swap buys: the job starts at login with no `pm2 startup` sudo step
and no tmux; `KeepAlive` retries **forever** where `max_restarts: 10` gave up; and no
secret enters the plist, because `WorkingDirectory` points at `ibl5/IBLbot` and
`src/bug-bot/config.ts` resolves its own dotenv file against `process.cwd()`.

**Scope limit.** PM2 remains the supervisor for the **test** bot
(`ibl5/IBLbot/ecosystem.bugbot-test.config.cjs`, port 50002) under ADR-0111. That is a
distinct Discord application with its own token and is unaffected.

**Not addressed here.** `bin/bug-pipeline-check` still probes only launchd and would still
have called this outage healthy — a `127.0.0.1:50001` reachability probe is the open gap.
Separately, `bin/db-sync-prod` drops and re-creates `ibl_bug_reports` and
`ibl_bug_pipeline_state` from prod (where they ship via migrations 153/158 but are always
empty, the bot being Mac-only), so every `bin/dev-up` destroys the pipeline's state. Both
are tracked as follow-ups, not closed by this change.
