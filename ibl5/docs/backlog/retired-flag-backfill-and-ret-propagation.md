---
description: The `.plr` import zeroes `ibl_plr.retired` for any player still present in the file, and no import path ever writes `retired = 1`; PR #1926 stops the wipe but leaves 8 already-corrupted 2007 retirees unflagged and the write path unbuilt.
last_verified: 2026-08-19
---

# Retired Flag: Backfill + `.ret` → `ibl_plr` Propagation

## Problem

The career leaderboard's retired asterisk is driven solely by `ibl_plr.retired`
(`ibl5/classes/CareerLeaderboards/CareerLeaderboardsService.php:27-28` — `$isRetired = $retired !== 0`).
Two independent defects leave that column wrong:

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
- **Never-clear invariant.** Whatever writes the flag must be additive-only, so a future
  import cannot repeat the wipe. Worth a regression test pinning `retired` across a
  re-import of an already-retired player.
- **Ordering.** The backfill migration is only durable once #1926 is merged; before that,
  the next `.plr` import re-zeroes it.

## Out of scope

**Brandon Roy (pid 931) is a separate bug.** He is reported as missing his asterisk, but
he carries `retired = 0` *and* has no row in `ibl_jsb_retired_players` at all — so neither
the backfill nor the propagation fix reaches him. His absence from the retirement table is
its own data-entry or `.ret`-ingest gap and needs separate triage.

## Status

⬜ **Open** — no plan yet. Needs a `/plan` (destructive-adjacent data migration + a new
write path on an import). (discovered 2026-08-19 during #1926 review)
