---
description: Ingests externally-generated sim recap documents and queues them for the sim recap pipeline; RecapPhasePolicy gates generation to HEAT and Regular Season; SimRecapContextRepository precomputes roster context for generation; MentionMapBuilder builds the Discord mention map for the queue script; the Discord publisher classes build and track the drip that IBLbot posts, gated to Regular Season by PublishPhasePolicy.
last_verified: 2026-11-14
---

# SimRecap

Handles the ingest of externally-generated simulation recap documents into the application's pipeline. `SimRecapPayload` parses and validates incoming recap documents at the trust boundary, failing closed on structural errors. `SimSummaryRepository` implements atomic conditional-UPDATE queue logic mirroring the BugPipeline pattern to prevent duplicate processing. It also validates at write time that every game row in a payload joins to an archived box score on date + teams + game_of_that_day, so a payload that could never render is refused before it becomes a row. `SimSummariesView` renders the recap summaries for display. Entry script: `ibl5/scripts/storeSimRecap.php`. The `simRecapContext.php` script precomputes current rosters, active injuries, and in-window trades via `SimRecapContextRepository`, providing roster context for the sim-recap generation pipeline. `RecapPhasePolicy` is the single source of truth for which season phases allow sim-recap generation; it gates both `QueueSimSummaryStep` and the `sim-recap-tick` phase gate.

| Class | Role |
|---|---|
| `RecapPhasePolicy` | Single source of truth for enabled phases (`ENABLED_PHASES = ['HEAT', 'Regular Season']`); `isEnabled(string $phase): bool` |
| `SimRecapPayload` | Parses and validates incoming recap documents |
| `SimSummaryRepository` | Atomic queue operations, plus the write-time box-score join validation |
| `SimSummariesView` | Renders sim recap summaries |
| `RecapDocument` | Assembles the postable document from intro + game rows + outro, shaped like `bin/lib/sim-recap-exemplar.txt`; prefers the stored text when the parts are degraded or incomplete |
| `SimRecapContextRepository` | Precomputes current rosters, active injuries, and in-window trades for a sim. Each game side lists its top 3 scorers plus every player with a double-double or better, and `doubleTag()` tags each line so the writer never counts stats itself |
| `GitHubDispatchClient` | Posts a `repository_dispatch` of type `sim-recap` to GitHub; `fromConfig()` returns null when the config file or token is missing, and `dispatch()` never throws |
| `MentionMapBuilder` | Builds the team name to Discord snowflake map for the `simRecapQueue.php mention-map` verb; ids leave as strings and teams with no id are omitted. State-free: its only collaborator is `TeamIdentityRepositoryInterface` |

## Dispatch and hosts

`QueueSimSummaryStep` calls `GitHubDispatchClient::dispatch()` only when it queues a new sim. The client reads `ibl5/config/github-dispatch.config.php` (template: `ibl5/config/github-dispatch.config.example.php`). A null client means dispatch is off, and the step message stays as it was. A failed dispatch changes the message only. The hourly schedule in `.github/workflows/sim-recap.yml` picks up the queued sim.

`bin/sim-recap-tick` runs on either host. `SIM_RECAP_HOST_MODE=actions` makes the local db-sync a log-only no-op. The default is `launchd`. Setup and rotation steps are in `ibl5/docs/OPERATIONS_RUNBOOK.md` section 8. The design is in ADR-0154.

## Discord publisher

When a sim reaches `done` in a Regular Season, prod hands it to IBLbot, which posts the recap into Discord as a slow drip. PHP builds the whole script and keeps the progress. IBLbot has no database access. It claims a sim, posts each step, and reports each step back. The IBLbot side is in `ibl5/IBLbot/README.sim-recap.md`.

| Class | Role |
|---|---|
| `PublishPhasePolicy` | Phase gate for the publisher. `PUBLISH_PHASES = ['Regular Season']` and `isPublishable(string $phase): bool`. It is separate from `RecapPhasePolicy`, because the recap writer also runs in HEAT and the publisher must not |
| `SimPublishProgressRepository` | Owns the progress table. `enqueue`, `setTitleOverride`, `claimNext`, `advance`, `finish`, `resetForRetry`, `findForSim` |
| `PublishScriptBuilder` | Builds the ordered step list for a sim from the stored summary, the displayable game recaps, the window injuries, and the mention map. `assemble()` is pure and deterministic for unchanged data |
| `PublishScriptUnavailable` | Thrown when the sim has no summary or no displayable game recap |
| `PublishThreadTitle` | Thread name. A stored override wins. Otherwise `2008-09, SIM #3` (season, then the sim's number within the Regular Season) |
| `InjuryLineFormatter` | Formats the `#injuries` lines and the day header |
| `PublishEnqueuer` | Enqueues a sim when the phase is publishable, then pokes IBLbot. A failed poke is logged as one fixed line and never undoes the enqueue |

### Progress table

`ibl_sim_publish_progress` (migration 218) has one row per sim and is the only record of where a drip stands. A restart or a duplicate trigger never starts a second drip.

| Column | Meaning |
|---|---|
| `sim` | Primary key. No foreign key to `ibl_sim_summaries` |
| `title_override` | Manual thread name, `NULL` for the computed title |
| `enqueued_at` | Set by the done notify. `NULL` means the row only holds an override and is never claimed |
| `claim_token` | UUID of the IBLbot process that owns the drip |
| `heartbeat_at` | Refreshed on every claim and every step advance |
| `attempts` | Number of claims. The cap is `MAX_ATTEMPTS = 5` |
| `thread_id` | Discord snowflake of the created thread |
| `last_step` | Index of the last step confirmed posted, `-1` for none |
| `finished_at` | Set when the whole drip is posted |
| `created_at` | Row creation time |

Rules in `SimPublishProgressRepository`. Each one is a conditional `UPDATE` that checks `execute(...) === 1`, and every value is a bound parameter.

- **Claim.** `claimNext` takes the lowest sim that is enqueued, unfinished, under the attempt cap, and either unowned, already owned by this token, or owned by a claim whose heartbeat is older than `STALE_SECONDS = 300`. It adds one to `attempts` and sets the heartbeat.
- **Freshness.** A row that has not started (`last_step` below 0) is claimable only when it was enqueued within `FRESH_SECONDS = 21600` (6 hours). A sim enqueued while the publisher was off never posts when the publisher is switched on later. A drip already started resumes at any age.
- **Heartbeat.** The longest planned gap between steps is 120 seconds, so a 300-second silence means the owner is gone.
- **Advance.** `advance` succeeds only for the claim holder and only when the step is exactly `last_step + 1`. An out-of-order step, a foreign token, or a finished row changes 0 rows. It also stores the thread id once known.
- **Finish.** `finish` succeeds only for the claim holder on an unfinished row.
- **Attempts.** A row at 5 attempts is never claimed again. `resetForRetry` sets `attempts` to 0, clears the token, and sets `enqueued_at` to now, so the row is fresh again. It returns false for a missing or finished row.
- **Enqueue.** A repeat `enqueue` keeps the first `enqueued_at`, so it never resets progress. `setTitleOverride` writes only `title_override`, so an override set before the sim is done does not make the row claimable.

### API routes

All three are POST routes under `/api/v1/sim-recap-publish/`, registered in `ibl5/classes/Api/Router.php`. Each one rejects a standard-tier key with 403 before it reads the body (`ServiceKeyGuard`).

| Route | Body | Reply |
|---|---|---|
| `claim-next` (`SimRecapPublishClaimController`) | `token`, a UUID | 200 with `sim`, `threadId`, `lastStep`, and `script`; 204 when nothing is claimable; 400 for a bad token; 422 `script_unavailable` when the claimed sim has nothing to publish |
| `progress` (`SimRecapPublishProgressController`) | `sim`, `token`, `step`, `threadId` | 200 when recorded; 409 when the step is out of order, the token is foreign, or the row is finished; 400 for bad input |
| `finish` (`SimRecapPublishFinishController`) | `sim`, `token` | 200 when finished; 409 for a foreign token or an already finished row; 400 for bad input |

### Queue script verbs

`ibl5/scripts/simRecapQueue.php` has three publisher verbs. Each one exits 2 for a bad flag, so a wrapper can tell a usage error from a runtime failure.

- `publish-enqueue --sim=N` enqueues the sim and pokes IBLbot. It prints `result` as `enqueued`, or `skipped: phase <phase>` when the phase is not publishable. Add `--retry` to call `resetForRetry` instead. It prints `reset`, or `not found or finished` and exits 1.
- `publish-title --sim=N --title=TEXT` stores a thread name of 1 to 100 characters. `--clear` removes it. Passing both, or neither, is a usage error.
- `publish-script --sim=N` prints the script as pretty JSON. It is read-only and claims nothing. It exits 1 when the sim has no script.

The existing `notify` verb also enqueues a `done` sim through `PublishEnqueuer`. A failure there is written to stderr and never changes the notify result.

### Steps and pacing

The script is a list of steps in this order. `delayBeforeMs` is the wait before each step.

1. The thread title in bold, posted in `#live-sims`. IBLbot then starts a thread on that message. No delay.
2. The `@IBL` role ping with "Let's do the darned thing!" in the thread. No delay.
3. The intro, when there is one. 30 seconds.
4. For each game day, in date order: a `**DAY n**` header (30 seconds for day 1, 120 seconds after), then each game recap in `sort_order` with 30 seconds before each one, then that day's injuries in `#injuries` with no delay.
5. The outro under a rule line and `**WHERE THINGS STAND**`, when there is one. 120 seconds.
6. A closing GIF from the search `game over`. 30 seconds.

Text over 2000 characters is split on blank lines, then newlines, then spaces, with a hard cut for a single token past the limit. Each continuation chunk waits 1 second. Each step lists the user ids it mentions (`mentionUsers`) and the one role id for the ping (`mentionRoles`). IBLbot sends every message with `parse: []`, so only those ids can ping.

An injury belongs to its own date when that is a game day. Otherwise it goes to the latest earlier game day, or the first game day. Each line reads `-B.Portis, <team> - <description> (<games missed>)` and ends with the GM mention when the team has a Discord id. `ibl_team_info` has no abbreviation column, so `<team>` is the franchise name where a sample line might show an abbreviation (`SimRecapContextRepository` fills `teamAbbrev` with the team name).
