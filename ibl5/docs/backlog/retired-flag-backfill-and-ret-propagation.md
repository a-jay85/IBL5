---
description: Three defects in the retired flag — the `.plr` import zeroes `ibl_plr.retired`, no import path ever writes `retired = 1`, and the `.ret` importer never reconciles REMOVALS so an un-retired player stays in `ibl_jsb_retired_players` forever; PR #1926 stops the wipe, migration 163 backfills the 8 corrupted 2007 retirees, migration 164 removes the one bogus row, and the write path is still unbuilt.
last_verified: 2026-08-20
---

# Retired Flag: Backfill + `.ret` → `ibl_plr` Propagation

## Problem

The career leaderboard's retired asterisk is driven solely by `ibl_plr.retired`
(`ibl5/classes/CareerLeaderboards/CareerLeaderboardsService.php:27-28` — `$isRetired = $retired !== 0`).
Three independent defects leave that column wrong:

1. **The `.plr` import zeroes it.** `PlrParserRepository::upsertPlayer()` includes
   `` `retired` = VALUES(`retired`) `` in its `ON DUPLICATE KEY UPDATE` list
   (`ibl5/classes/PlrParser/PlrParserRepository.php:193`), and the bound value is the
   hardcoded literal `0` (`:343`). So every player present in a `.plr` file is reset to
   `retired = 0` on each import, regardless of prior state.
   **PR #1926 removes line 193** and stops the ongoing damage — but it is a pure
   `+0/-1` diff with no backfill, so already-corrupted rows stay corrupted.

2. **Nothing ever writes `retired = 1`.** The `.ret` import path
   (`ibl5/classes/JsbParser/Importers/RetImporter.php` →
   `ibl5/classes/JsbParser/Repositories/RetRepository.php`) writes only
   `ibl_jsb_retired_players`; its sole statement is
   `INSERT INTO ibl_jsb_retired_players … ON DUPLICATE KEY UPDATE`. No code anywhere in
   the repo sets `ibl_plr.retired = 1` — the flag is maintained out-of-band by hand.
   Once #1926 stops the wipe, a newly retired player still never gets flagged.

3. **The `.ret` import never reconciles removals.** A `.ret` file is a full **overwrite
   snapshot**, not a cumulative log — each JSB retirement batch replaces the entire name
   list. But `RetRepository` writes `INSERT … ON DUPLICATE KEY UPDATE` and issues **no
   `DELETE`**, so `ibl_jsb_retired_players` is additive-only. A player JSB un-retires — or
   a batch imported from a snapshot that was later superseded — stays in the table
   permanently, with nothing in the codebase able to remove them. See
   *Defect 3: the removal-reconciliation gap* below.

## Evidence (measured 2026-08-19 against the main stack)

Every player present in the most recent `.plr` import carries `retired = 0`; no exceptions:

| in latest `.plr` import (2008 mid-season) | `ibl_plr.retired` | rows |
|---|---|---|
| no | 0 | 2 |
| no | 1 | 840 |
| yes | 0 | 722 |

Cross-tabbing `ibl_jsb_retired_players` (133 rows / 127 distinct `pid`, 17 retirement
years) against the flag isolates the damage to a single class — every year from 1989 through 2006 is fully
flagged, and only 2007 is broken:

| retirement_year | in table | `retired = 1` | `retired = 0` |
|---|---|---|---|
| 1989–2006 (16 years) | 114 | 114 | 0 |
| 2007 | 19 | 11 | **8** |

The 8 mis-flagged rows are exactly the 2007 retirees who were still in the 2008
mid-season `.plr` file. The split is clean in both directions — of the 19 rows, the 8 present
in that import all have `retired = 0` and the 11 absent from it all have `retired = 1`, which
is the `.plr` wipe and nothing else: Arvydas Sabonis (327), Gary Payton (2000), Jamal Murray (1250),
Donta Smith (2750), Frank Johnson II (3301), Anthony Tolliver (3307), Troy Bell (5318),
Danny Granger (5645).

[CORRECTED 2026-08-20] **Danny Granger (5645) does not belong on that list** — he was never
retired by JSB at all. His `ibl_jsb_retired_players` row came from a superseded snapshot and
was removed by migration `164_remove_stale_granger_retirement.sql`, which also reset his
`ibl_plr.retired` to 0. The other seven are genuine 2007 retirees and remain flagged. The
row counts in the tables above are as measured on 2026-08-19 and are left unrevised.

## Defect 3: the removal-reconciliation gap (traced 2026-08-20)

The Danny Granger case is the worked example, and it is the reason defect 3 is not
theoretical:

- The archive `06-07_36_finals.zip` (example) contains an `IBL5.ret` with JSB internal
  timestamp 2026-03-11 20:05 whose **first line** is `Danny Granger 5645`.
- The next archive, `07-08_01_preseason.zip` (example), contains an `IBL5.ret` with JSB
  timestamp 2026-03-11 23:12 — roughly three hours later — that is **byte-identical minus
  that single line**. JSB retired and un-retired him the same night. He appears in no later
  archive, and the live `.ret` on disk today does not name him.
- `BackupArchiveLocator::findLatestArchive()`
  (`ibl5/classes/BulkImport/BackupArchiveLocator.php`) selects the archive with the newest
  mtime. On import day 2026-04-19 the stale finals zip was genuinely the newest file on
  disk — the corrected preseason zip did not land until 2026-04-23. **The locator is not
  the defect**; it read the only file that existed.
- Because `RetRepository` has no `DELETE`, the later corrected seven-name import could not
  undo the eight-name one. Row 137 became permanent, migration 163 propagated it into
  `ibl_plr.retired = 1`, and Granger vanished from the Heat roster page. Reported as bug
  pipeline report id 4.

The load-bearing consequence: **clearing `ibl_plr.retired` alone would not have fixed
this.** The propagation write path from defect 2, once built, would re-flag him off the
stale row on the next import. Any propagation design must handle removals, or it will
resurrect every stale row in the table the day it ships.

### Related, NOT the same defect: Sabonis (327) and Payton (2000)

Both carry `retired = 1` from migration 163 *and* a `teamID`, so migration 163 hid them
from their team roster pages (Sabonis → Raptors, Payton → Kings) exactly as it hid Granger
from the Heat. But **neither is a stale-snapshot case** — both are still named in the
current `.ret` snapshot, so JSB does consider them retired. They are not reverted here and
must not be reverted blind.

Two criteria were floated while hunting report 4 and both are **retired as invalid** —
recorded so they are not re-derived:

- **"has an active multi-year contract (`cy=1`, `cyt>1`)" is not a signal.** 73 players are
  `retired = 1` with a `teamID`, and 59 of those also have `cy > 0` and `salary_yr1 > 0`.
  That is a normal, widespread state.
- **"has 2008 `ibl_hist` activity" is not a signal on its own**, because some 2008 rows are
  import-carryover duplicates. Sabonis's 2008 row is byte-identical to his 2007 row (Raptors,
  76 G / 2818 min); Payton's likewise (82 G / 806 min in both). Four pids league-wide show
  that duplicate pattern (327, 1245, 2000, 3877) out of 354 rows for 2008.

The criterion that actually discriminated Granger is **row distinctness**: his 2008 row
differs genuinely from his 2007 one (2007 Knicks 82 G / 2768 min → 2008 Heat 82 G / 2889 min).
That plus the `.ret` snapshot trace is what made his case provable. Sabonis and Payton have
neither.

**Resolved 2026-08-20 (league ruling): a retired-but-contracted player should NOT appear on a
roster.** Retirement is the stronger fact; an unexpired contract on a retired player is a
salary-book artifact, not roster membership. So the roster-page hiding is **correct behavior**,
`retired = 1` alongside a live `teamID`/`cy` is a **legitimate state**, and the roster query is
not the thing that should change. Sabonis (327) and Payton (2000) are therefore **closed — no
fix, and no triage owed**: both are genuinely retired per the current `.ret` snapshot, so being
hidden from the Raptors and Kings rosters is the correct outcome.

Mechanism confirmed 2026-08-20 — the roster reads filter on `p.retired = 0`
(`ibl5/classes/Team/TeamQueryRepository.php:115,148,181,200,218,238,257,275,293` and
`ibl5/classes/Team/TeamRepository.php:323,342`), so migration 163's flag is indeed what hides
them; the mechanism sentence opening this section is correct, not inherited guesswork.

**This ruling does not reopen migration 164.** Granger was never retired by JSB at all, so his
revert stands on the stale-snapshot trace and is independent of this decision. The ruling is
*retired players stay hidden*; it says nothing about players who were never retired.

## The design fork — why this needs a `/plan`

`ibl_jsb_retired_players` is **not** the source of truth for the flag, and its `pid`
column is not trustworthy as a join key. Two independent reasons:

- **Coverage.** **721 players carry `retired = 1` while absent from that table** (127
  distinct pids in the table, 119 of them flagged 1, vs. 840 flagged players overall). A
  propagation rule of "`retired = 1` iff present in `ibl_jsb_retired_players`" would
  clear the flag for 721 players — strictly worse than the bug being fixed. Any write
  must be additive-only.
- **`pid` integrity.** Six pids appear on two rows each, and four of those are collisions
  between *different players* — pid 59 is both Joe Barry Carroll (1995) and Andre Turner
  (1989); likewise pid 129, 208, and 300. (The other two, pid 614 Grant Hill and pid 632
  Escalade Jackson, are one player with two retirement years.) All four collision pids
  currently resolve in `ibl_plr` to the *first* name (59 Joe Barry Carroll, 129 Sidney Lowe,
  208 Hakeem Olajuwon, 300 T.R. Dunn (I)), and all four are already `retired = 1` — so today
  the failure mode is **under-flagging plus false provenance**, not a wrong flag: the second
  player of each pair never gets flagged from their own row, and the row that does drive the
  flag belongs to someone else. A wrong flag needs only one collision where the resolved
  `ibl_plr` player is still active, which nothing guarantees. The back-link needs auditing
  before any join-driven rule is written.

The 8 backfill targets named above were checked against this and are clean — `player_name`
matches `ibl_plr.name` for all 8, and none is a duplicated pid.

The plan has to settle:

- **Backfill scope.** Flag exactly the 8 identified rows (safe, narrow, verifiable), or
  derive a general rule? Narrow is strongly preferred given the `pid` integrity finding.
- **Propagation direction.** Should `RetImporter` set `ibl_plr.retired = 1` for records it
  ingests (additive, never clearing), or should retirement stay a manual act with the
  import merely reporting drift?
- **Removal reconciliation.** A `.ret` snapshot is authoritative for its batch, so a name
  dropped from a later snapshot is a signal, not noise. Does the importer diff the incoming
  snapshot against the stored set and delete the difference? That collides head-on with the
  never-clear invariant below and with the coverage finding above, because a naive
  "delete everything not in this file" would empty the table on every import. A workable
  shape is probably **per-`retirement_year` reconciliation** — only rows whose
  `retirement_year` matches the batch being imported are candidates for removal — but that
  needs verifying against how `retirement_year` is assigned (it comes from the bulk-import
  entry's year, not from the file). Alternatively the importer reports the drift and a human
  confirms. Until this is settled, stale rows must be removed by hand-written migration, as
  `164_remove_stale_granger_retirement.sql` does.
- **Never-clear invariant.** Whatever writes the flag must be additive-only, so a future
  import cannot repeat the wipe. Worth a regression test pinning `retired` across a
  re-import of an already-retired player.
- **Contracted retirees — settled, not a fork.** Per the 2026-08-20 ruling above, a retired
  player with a live contract must stay off the roster, so propagation does **not** need to
  special-case players who hold a `teamID`, `cy`, or salary. `retired = 1` next to an
  unexpired contract is a valid end state, and the roster query stays as-is. The 73
  `retired = 1` rows that carry a `teamID` need no cleanup.
- **Ordering.** The backfill migration is only durable once #1926 is merged; before that,
  the next `.plr` import re-zeroes it.

## Out of scope

**Brandon Roy (pid 931) is a separate bug.** He is reported as missing his asterisk, but
he carries `retired = 0` *and* has no row in `ibl_jsb_retired_players` at all — so neither
the backfill nor the propagation fix reaches him. His absence from the retirement table is
its own data-entry or `.ret`-ingest gap and needs separate triage.

## Status

◑ **Partial** — migration `163_backfill_2007_retired_flag.sql` backfills the 8
mis-flagged 2007 retirees (shipped 2026-08-19). Migration
`164_remove_stale_granger_retirement.sql` removes the one bogus row that backfill inherited
and resets that player's flag (2026-08-20). Remaining: build the `.ret` →
`ibl_plr.retired` propagation write path, which now must also settle **removal
reconciliation** (defect 3) — separate, stacked plan.

**Status (2026-08-20):** Backfills shipped (163, 164). Write-path propagation plus removal
reconciliation is the remaining open work. Sabonis (327) and Payton (2000) are **closed** —
the league ruling confirms retired-but-contracted players are correctly hidden from rosters.
