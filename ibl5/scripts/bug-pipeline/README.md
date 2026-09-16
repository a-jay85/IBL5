---
description: Bug-pipeline PHP CLI layer — how to exercise it end-to-end against a scratch DB, and the behavioural gotchas (UTC skew, claim-loser semantics, tick sequencing) the shell harnesses cannot prove.
last_verified: 2026-09-16
---

# Bug Pipeline CLI

These scripts are the PHP CLI layer of the Discord bug pipeline. The bash driver
`bin/bug-pipeline-tick` is fully env-seamed, so a whole tick can run against throwaway
targets — scratch DB, stub `claude`/`gh` binaries, and a fake bot HTTP responder.

## Running it end-to-end

Use the provisioned harness, not a hand-rolled one:

```bash
bin/bug-pipeline-test-env                       # scratch DB + stubs + test bot
source /tmp/bug-pipeline-test-env.sh
BUG_PIPELINE_E2E=1 bin/bug-pipeline-e2e         # real DB writes + a real tick
bin/bug-pipeline-test-env --teardown
```

`bin/bug-pipeline-test-env` loads **both** `ibl5/migrations/153_create_bug_pipeline_tables.sql`
and `ibl5/migrations/158_create_bug_report_attachments.sql` — without 158 the claim's
attachment join fatals. It exports `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`, `BOT_BASE_URL`,
`CLAUDE_BIN`, and `GH_BIN`; `ibl5/config.php` reads the DB vars from the environment, so the
real CLI scripts in this directory write into the scratch DB with no code change. The
three-barrier isolation design is
`ibl5/docs/decisions/0111-bug-pipeline-test-isolation.md`.

This is the layer worth running before merging a bug-pipeline PR. The shell harnesses
(`bin/test-bug-pipeline-classify` and siblings) stub the PHP layer, so they **cannot** prove
that a transition option actually round-trips into a DB column — the E2E run can. That is how
`--thread-id` on the `queued --class=bug` transition was proved to persist, and how
reconcile's PR post was proved idempotent across ticks.

## Poking the scratch DB by hand

The main stack's MariaDB container `ibl5-mariadb` publishes host `127.0.0.1:3306`, creds
`root`/`root`. The Homebrew `mysql` client defaults to requiring TLS, which that server does
not offer, so **`--skip-ssl` is mandatory** — without it you get `ERROR 2026 TLS/SSL error`:

```bash
mysql --skip-ssl -h 127.0.0.1 -P 3306 -u root -proot "$DB_NAME" -e "SELECT id,status FROM ibl_bug_reports"
```

## There is no enqueue CLI script

This directory has claim-next, transition, the `list-*` scripts, and the reporter-tech-level
pair. Ingestion is HTTP-only, and that path is not drivable from a worktree: the worktree dev
DB holds only the bug-pipeline tables, so there is no `ibl_api_keys` and no `ibl_team_info`,
and both the API-key check and `isKnownDiscordID()` fail. To seed a row outside the E2E
harness, call the repository directly — host PHP 8.5 is installed, so no container is needed:

```bash
DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root DB_NAME="$DB_NAME" php -r '
require __DIR__ . "/scripts/bug-pipeline/_bootstrap.php";
$r = new \BugPipeline\BugReportRepository($mysqli_db);
echo $r->enqueueAuthorizedAndAdvance("<author_snowflake>","<channel>","<message>","bug text"), "\n";'
```

Then walk the row through:
`transition.php <id> queued --class=bug --thread-id=<snowflake>` →
`claim-next.php --owner=<token>` →
`transition.php <id> pr_open --pr=<n> --release-lease` → `fixed`.

The option is `--pr=`, **not** `--pr-number=`; `transition.php` rejects unknown options and
prints the full valid list.

## Gotchas

**The bash driver is host-local, the DB is UTC.** `bin/bug-pipeline-tick`'s `epoch_of` parses
DB timestamps with host-local `date -j`, and `future_ts` writes host-local values back, while
MariaDB stores UTC. On a PDT host that is a 7-hour skew: `now - ref_epoch` goes **negative**,
so an idle row seeded `NOW() - INTERVAL 3 HOUR` never fires its reminder, and `future_ts 1800`
returned `00:49:05` against a UTC `NOW()` of `07:19:05` — a `blocked_until` backoff that
expires the moment it is written. Seed idle rows at `NOW() - INTERVAL 30 HOUR` so they stay
idle through the skew. This is a real pre-existing bug in the bash driver, not a harness
artifact; the PHP↔DB path is unaffected because both are UTC.

**A claim loser returns nothing — it does not fall through.**
`BugReportClaimRepository::claimNextHuntable()` picks the oldest huntable row, then
`claimQueued()` re-asserts `status='queued'`; on a lost race it returns null rather than
trying the next row. So "N racers get N distinct ids" is a **wrong** assertion. The
discriminating one: fire 8 concurrent `claim-next` processes at 4 huntable rows → 3 are
claimed, each by exactly one `lease_owner`, no id twice, and the losers exit 0 silently.

**Tick sequencing.** `main()` runs `reconcile_pr_open_rows` → `maybe_hunt` → classify. A row
classified on tick N is claimed by the hunter on tick N+1, which tries `bin/wt-new` against
the fake repo and fails — harmless, it logs, releases the lease, and re-queues. To exercise
only classify + reconcile, run **one** tick and read the DB.

**`CLAUDE_BIN` must emit the full envelope**, not the bare schema object. `run_structured_call`
reads `.result` off a `{"is_error":false,"result":{…}}` wrapper; a bare object logs
"envelope is_error/unparseable — safe fallback, row unchanged". The `GH_BIN` stub echoes an
issue URL on `issue create` and a bare PR number on `pr list` — the driver's `--jq` is already
applied, so it emits the final value.
