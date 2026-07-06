---
description: Run the Discord bug/feature pipeline orchestrator as a Mac-local launchd LaunchAgent firing a poll-only bash driver every 180s via StartInterval — not a daemon, tmux, or persistent claude — with single-flight enforced by an atomic DB lease and no prod credentials in its environment.
last_verified: 2026-07-06
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
