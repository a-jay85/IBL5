---
description: >
  Why and how the 2006-07 All-Star Weekend box-score records were reconstructed
  and patched into 06-07_36_finals.zip's IBL5.sco binary.
last_verified: 2026-06-08
---

# 0051 — Reconstructed 2006-07 All-Star Weekend Box Scores in Finals SCO

## Status

Accepted

## Context

The 2006-07 season archive (`backups/06-07/06-07_36_finals.zip`) contains an
`IBL5.sco` binary file (12,781,648 bytes). A `.sco` file stores game records as
a 1,000,000-byte header followed by 2,000-byte per-game records. Records 0 and 1
correspond to the Rising Stars Game (2007-02-02) and All-Star Game (2007-02-03).

Both records were entirely blank (all `0x20` / space bytes). The JSB bulk importer
skips any record where `trim(gameInfo) === ''`, so these two games were silently
omitted from every archive-import run. The 48 player box-score rows and 4 team rows
that should have existed in the database were never produced by the importer.

The original blank state was discovered when auditing why the ASG break-season gate
(which depends on `Season::getLastBoxScoreDate()`) returned stale dates. The root
cause is that the sim software failed to write the All-Star Weekend records into the
file at export time; this is unrecoverable from the archive alone.

### What was reconstructed

Stats were transcribed from the HTML box-score pages preserved on the IBL5 website
and stored in `ibl5/scripts/reconstruct_2007_asg_boxscores.php` (the stat source of
truth). Reconstructed fields per player:

| Reconstructable | Not reconstructable |
|-----------------|---------------------|
| All counting stats (pts, reb, ast, stl, blk, tov, pf, min) | W-L record (stored as 0-0) |
| Quarter scores | Exact attendance/capacity (approximated) |
| Player positions | |
| Team totals | |

The W-L records stored in the `.sco` header are treated as don't-care: they are
overridden at import time by `BoxscoreProcessor::overrideGameContext`, which derives
the correct team context from the season gate and game-date sentinel rows.

## Decision

Apply two complementary fixes:

### Fix A — Direct DB write (`reconstruct_2007_asg_boxscores.php`)

`ibl5/scripts/reconstruct_2007_asg_boxscores.php` inserts the reconstructed rows
directly into the live database. This is the fast path for the currently-deployed
environment. It must be run once against production.

### Fix B — Archive patch (`patch_2007_asg_sco.php` + `ScoFileWriter`)

`ibl5/scripts/patch_2007_asg_sco.php` encodes the same stats into bytes 0..3999 of
`IBL5.sco` inside `06-07_36_finals.zip`, making the archive self-sufficient. A future
full reimport (without Fix A) will now produce the correct rows natively, without any
special-case logic in the importer.

The binary patch is not committed to git (the archive is gitignored at 158 MB); only
the encoding code and this ADR are in the repository.

### Encoding implementation (`ScoFileWriter`)

`classes/JsbParser/ScoFileWriter` mirrors the `TrnFileWriter` pattern: a pure
stateless encoder behind `ScoFileWriterInterface`, with no I/O. Key encoding rules
derived from reading `Boxscore::fillGameInfo` and `PlayerStats::fillBoxscoreStats`:

- Names: left-justified, space-padded to field width
- Numerics: right-justified, space-padded to field width
- The `.sco` stores **two-pointer** makes/attempts (`game_2gm`, `game_2ga`), not total
  FGM/FGA. Caller must derive: `twoGM = fgm − tpm`, `twoGA = fga − tpa`
- The `.sco` stores **defensive** rebounds (`game_drb`), not total rebounds. Caller
  must derive: `drb = reb − orb`
- Team-total slot is at position 14 (visitor) and 29 (home); player ID = 0
- Blank filler slots occupy positions visitor_count..13 and 15+home_count..28
- Stored month/day/gameOfDay/teamid values undergo `+10`/`+1`/`+1`/`+1` transforms at
  parse time; the patch uses sentinel values (1, 1, 0, teamid-1) that decode correctly

### Integrity guards in `spliceAllStarBlock`

Before any write:
1. `IBL5.sco` must be exactly 12,781,648 bytes
2. Bytes 0..3999 must be entirely spaces (blank precondition — abort if already patched)
3. SHA-256 hashes of all 30 archive members recorded before write
4. After write: `IBL5.sco` tail bytes (1,000,000..EOF) hash-identical to pre-write
5. After write: all other members hash-identical to pre-write

## Consequences

- Future full reimports of the 2006-07 archive will produce the correct 48 player
  rows and 4 team rows for All-Star Weekend without any special-case importer logic.
- The `ASG break-season gate` (`IBL_ALL_STAR_BREAK_END_DAY`) is no longer affected
  by missing box-score rows for this season.
- `ScoFileWriter` + `ScoFileWriterInterface` are available for encoding other blank
  or corrupt `.sco` records if the same issue is discovered in other season archives.
- W-L records remain 0-0 in the patched binary; this is acceptable because the
  importer overrides them from context and they are not surfaced in the UI.

## Related

- `ibl5/scripts/reconstruct_2007_asg_boxscores.php` — stat source of truth (Fix A)
- `ibl5/scripts/patch_2007_asg_sco.php` — archive patch CLI (Fix B)
- `classes/JsbParser/ScoFileWriter.php` — pure encoder
- `classes/JsbParser/Contracts/ScoFileWriterInterface.php` — interface
- `tests/JsbParser/ScoFileWriterTest.php` — unit tests
- `tests/DatabaseIntegration/AllStarScoReconstructionTest.php` — DB acceptance tests
- `classes/JsbParser/ScoFileParser.php` — parser constants and slot extraction
- `classes/Boxscore/BoxscoreProcessor.php` — importer (overrideGameContext, season gate)
