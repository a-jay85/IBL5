---
description: Jump Shot Basketball binary file format specifications.
last_verified: 2026-04-11
---

# Jump Shot Basketball (JSB) Engine File Format Specifications

Reverse-engineered specifications for the binary and text data files produced by the Jump Shot Basketball simulation engine.

---

## Table of Contents

- [IBL5.car — Career Statistics](#ibl5car--career-statistics)
- [IBL5.plr — Player Records](#ibl5plr--player-records)
- [IBL5.his — Historical Results](#ibl5his--historical-results)
- [IBL5.rcb — Record Book](#ibl5rcb--record-book)
- [IBL5.trn — Transactions](#ibl5trn--transactions)
- [IBL5.asw — All-Star Weekend](#ibl5asw--all-star-weekend)
- [Shared Conventions](#shared-conventions)

---

## IBL5.car — Career Statistics

**Size:** 15,212,431 bytes | **Format:** Fixed-width binary (no line delimiters) | **Encoding:** Latin-1

### File Structure

The file is divided into contiguous **2,500-byte blocks**. Block 0 is a header; blocks 1–N contain individual player career data.

- **Total blocks:** ~6,085 (file size / 2500)
- **Populated blocks:** ~4,184 contain actual player data; the rest are space-padded empty slots
- **Block index = Player ID** used by the `.rcb` file (e.g., block 1230 = Michael Jordan)

### Block 0 — File Header

| Offset | Width | Content |
|--------|-------|---------|
| 0–3 | 4 | Player count (e.g., `6226`), right-justified |
| 4–2499 | 2496 | Space padding |

### Blocks 1–N — Player Career Data

#### Player Header (24 bytes)

| Offset | Width | Format | Description |
|--------|-------|--------|-------------|
| 0–2 | 3 | Right-justified int | Number of seasons played |
| 3–7 | 5 | Right-justified int | Internal player ID (e.g., `27242`) |
| 8–23 | 16 | Left-justified string | Player name, space-padded |

#### Season Records (100 bytes each, max 24 per player)

Season records follow immediately after the header. Remaining block space (after `24 + seasons * 100` bytes) is filled with spaces.

| Offset | Width | Format | Description |
|--------|-------|--------|-------------|
| 0–3 | 4 | String | Season year (e.g., `1988`) |
| 4–19 | 16 | Left-justified string | Team name, space-padded |
| 20–35 | 16 | Left-justified string | Player name (repeated) |
| 36–37 | 2 | String | Position: `PG`, `SG`, `SF`, `PF`, ` C` |
| 38 | 1 | Char | Depth chart flag (usually `0`) |
| 39 | 1 | Space | Separator |
| 40–41 | 2 | Right-justified int | GP (Games Played) |
| 42–45 | 4 | Right-justified int | MIN (Minutes) |
| 46–49 | 4 | Right-justified int | 2GM (Two-Pointers Made) |
| 50–53 | 4 | Right-justified int | 2GA (Two-Pointers Attempted) |
| 54–57 | 4 | Right-justified int | FTM (Free Throws Made) |
| 58–61 | 4 | Right-justified int | FTA (Free Throws Attempted) |
| 62–65 | 4 | Right-justified int | 3GM (Three-Pointers Made) |
| 66–69 | 4 | Right-justified int | 3GA (Three-Pointers Attempted) |
| 70–73 | 4 | Right-justified int | ORB (Offensive Rebounds) |
| 74–77 | 4 | Right-justified int | DRB (Defensive Rebounds) |
| 78–81 | 4 | Right-justified int | AST (Assists) |
| 82–85 | 4 | Right-justified int | STL (Steals) |
| 86–89 | 4 | Right-justified int | TO (Turnovers) |
| 90–93 | 4 | Right-justified int | BLK (Blocks) |
| 94–97 | 4 | Right-justified int | PF (Personal Fouls) |
| 98–99 | 2 | Spaces | Trailing padding |

**Important:** 3GM is a separate counting stat from 2GM. They do not overlap. Points = `2GM * 2 + FTM + 3GM * 3`.

**Important:** `.car` does **not** update mid-season. Every mid-season snapshot in a given season has byte-identical `.car` contents (verified via MD5 across the 06-07 sim05 / sim06 / sim07 archives). The `.car` regenerates only at season end — a player's most recent season row is always the *last completed* season, never the in-progress one. For rebuilding mid-season `.plr` snapshots use `ibl_box_scores` instead; see `classes/PlrParser/PlrReconstructionService.php`.

---

## IBL5.plr — Player Records

**Size:** 998,788 bytes | **Format:** Fixed-width text with CRLF line endings | **Encoding:** CP1252 (Windows-1252)

### File Structure

The file is a sequence of **607-byte records** separated by CRLF. Each record is one player *or* team-summary row. There are ~1603 total lines per snapshot, split into:

| Region | Lines | Content |
|--------|-------|---------|
| Player records | 1440 slots | One per active/retired/prospect player; identified by `pid` at offset 38 |
| Team-summary rows | ~60 rows | Per-franchise totals, `pid=0`, team name at offset 4 |
| Padding | ~885 rows | Empty slots for future player growth |

Players are identified by the **`pid` at offset 38** (database primary key), not the `ordinal` at offset 0. The ordinal is a display/roster index that tops out at 1440.

### Player Record Layout (607 bytes)

Field offsets and widths below are the authoritative spec used by `classes/PlrParser/PlrParserService.php` (reader) and `classes/PlrParser/PlrFileWriter.php` (writer). Keep those two in sync with this table.

#### Identification (0-51)

| Offset | Width | Type | Field |
|--------|-------|------|-------|
| 0 | 4 | int | Ordinal (1-1440 for active player slots) |
| 4 | 32 | string | Player name (left-justified, CP1252, space-padded) |
| 36 | 2 | int | Age |
| 38 | 6 | int | `pid` — database primary key |
| 44 | 2 | int | `tid` — team ID |
| 46 | 4 | int | Peak rating |
| 50 | 2 | string | Position (`PG`, `SG`, `SF`, `PF`, ` C`) |

#### Real-Life / Previous Season Stats (52-111)

Backing for the JSB simulation engine's "real-life" tendencies — these are the reference stats used by the engine when simulating games, independent of the in-game season totals.

| Offset | Width | Field |
|--------|-------|-------|
| 52 | 4 | `realLifeGP` |
| 56 | 4 | `realLifeMIN` |
| 60 | 4 | `realLifeFGM` |
| 64 | 4 | `realLifeFGA` |
| 68 | 4 | `realLifeFTM` |
| 72 | 4 | `realLifeFTA` |
| 76 | 4 | `realLife3GM` |
| 80 | 4 | `realLife3GA` |
| 84 | 4 | `realLifeORB` |
| 88 | 4 | `realLifeDRB` |
| 92 | 4 | `realLifeAST` |
| 96 | 4 | `realLifeSTL` |
| 100 | 4 | `realLifeTVR` |
| 104 | 4 | `realLifeBLK` |
| 108 | 4 | `realLifePF` |

#### Unknown region (112-127)

16 bytes of unmapped data. Not parsed or written by any code today.

#### Clutch / Consistency / Depth Chart (128-140)

| Offset | Width | Field |
|--------|-------|-------|
| 128 | 2 | `clutch` |
| 130 | 2 | `consistency` |
| 132 | 1 | `PGDepth` |
| 133 | 1 | `SGDepth` |
| 134 | 1 | `SFDepth` |
| 135 | 1 | `PFDepth` |
| 136 | 1 | `CDepth` |
| 137 | 1 | `canPlayInGame` |
| 138 | 2 | Unknown |
| 140 | 4 | `injuryDaysLeft` |

#### Season Stats (144-207) — reconstructed from `ibl_box_scores`

All width 4. Overwritten by `PlrReconstructionService` using `SUM(...) WHERE game_type = 1`.

| Offset | Field | Source |
|--------|-------|--------|
| 144 | `seasonGamesStarted` | Not reconstructable from box scores; preserved from base snapshot |
| 148 | `seasonGamesPlayed` | `SUM(CASE WHEN gameMIN > 0 THEN 1 ELSE 0 END)` |
| 152 | `seasonMIN` | `SUM(gameMIN)` |
| 156 | `season2GM` | `SUM(game2GM)` |
| 160 | `season2GA` | `SUM(game2GA)` |
| 164 | `seasonFTM` | `SUM(gameFTM)` |
| 168 | `seasonFTA` | `SUM(gameFTA)` |
| 172 | `season3GM` | `SUM(game3GM)` |
| 176 | `season3GA` | `SUM(game3GA)` |
| 180 | `seasonORB` | `SUM(gameORB)` |
| 184 | `seasonDRB` | `SUM(gameDRB)` |
| 188 | `seasonAST` | `SUM(gameAST)` |
| 192 | `seasonSTL` | `SUM(gameSTL)` |
| 196 | `seasonTVR` | `SUM(gameTOV)` — note the rename: box scores use `TOV`, `.plr` uses `TVR` |
| 200 | `seasonBLK` | `SUM(gameBLK)` |
| 204 | `seasonPF` | `SUM(gamePF)` |

**DNP gotcha:** `ibl_box_scores` stores DNP rows with `gameMIN = 0`. Those must be excluded from `seasonGamesPlayed` but still contribute 0 to every counting stat — use `SUM(CASE WHEN gameMIN > 0 THEN 1 ELSE 0 END)` not `COUNT(*)`.

#### Playoff Season Stats (208-267)

Same structure as regular-season stats; reconstructed from `game_type = 2`.

| Offset | Field |
|--------|-------|
| 208 | `playoffSeasonGP` |
| 212 | `playoffSeasonMIN` |
| 216 | `playoffSeason2GM` |
| 220 | `playoffSeason2GA` |
| 224 | `playoffSeasonFTM` |
| 228 | `playoffSeasonFTA` |
| 232 | `playoffSeason3GM` |
| 236 | `playoffSeason3GA` |
| 240 | `playoffSeasonORB` |
| 244 | `playoffSeasonDRB` |
| 248 | `playoffSeasonAST` |
| 252 | `playoffSeasonSTL` |
| 256 | `playoffSeasonTVR` |
| 260 | `playoffSeasonBLK` |
| 264 | `playoffSeasonPF` |

#### Attributes / Morale / Contracts (268-329)

| Offset | Width | Field |
|--------|-------|-------|
| 268 | 2 | `talent` |
| 270 | 2 | `skill` |
| 272 | 2 | `intangibles` |
| 274 | 2 | `coach` |
| 276 | 2 | `loyalty` |
| 278 | 2 | `playingTime` |
| 280 | 2 | `playForWinner` |
| 282 | 2 | `tradition` |
| 284 | 2 | `security` |
| 286 | 2 | `exp` |
| 288 | 2 | `bird` |
| 290 | 2 | `currentContractYear` (`cy`) |
| 292 | 2 | `totalContractYears` (`cyt`) |
| 294 | 4 | Unknown |
| 298 | 4 | Contract salary year 1 (`cy1`) |
| 302 | 4 | `cy2` |
| 306 | 4 | `cy3` |
| 310 | 4 | `cy4` |
| 314 | 4 | `cy5` |
| 318 | 4 | `cy6` |
| 322 | 4 | Unknown |
| 326 | 2 | `draftRound` |
| 328 | 2 | `draftPickNumber` |

#### Derived Team Fields (330-340)

Auto-updated by `PlrFileWriter::applyDerivedTidFields()` whenever `tid` changes.

| Offset | Width | Field |
|--------|-------|-------|
| 330 | 1 | `freeAgentSigningFlag` |
| 331 | 2 | `contractOwnedBy` (equals tid) |
| 333 | 2 | `currentTeamIndex` (tid − 1, or −1 for free agents) |
| 335 | 2 | `previousTeamIndex` (previous tid − 1) |
| 337 | 4 | Unknown |

#### Single-Season Highs (341-363)

Width 2 each. Derivable from `MAX(...)` over `ibl_box_scores` for the season window.

| Offset | Field |
|--------|-------|
| 341 | `seasonHighPTS` |
| 343 | `seasonHighREB` |
| 345 | `seasonHighAST` |
| 347 | `seasonHighSTL` |
| 349 | `seasonHighBLK` |
| 351 | `seasonHighDoubleDoubles` |
| 353 | `seasonHighTripleDoubles` |
| 355 | `seasonPlayoffHighPTS` |
| 357 | `seasonPlayoffHighREB` |
| 359 | `seasonPlayoffHighAST` |
| 361 | `seasonPlayoffHighSTL` |
| 363 | `seasonPlayoffHighBLK` |

#### Career Best Highs (365-435)

Width 6 each. Monotonic: `new = max(base_career_best, current_season_high)`.

| Offset | Field |
|--------|-------|
| 365 | `careerSeasonHighPTS` |
| 371 | `careerSeasonHighREB` |
| 377 | `careerSeasonHighAST` |
| 383 | `careerSeasonHighSTL` |
| 389 | `careerSeasonHighBLK` |
| 395 | `careerSeasonHighDoubleDoubles` |
| 401 | `careerSeasonHighTripleDoubles` |
| 407 | `careerPlayoffHighPTS` |
| 413 | `careerPlayoffHighREB` |
| 419 | `careerPlayoffHighAST` |
| 425 | `careerPlayoffHighSTL` |
| 431 | `careerPlayoffHighBLK` |

#### Career Totals (437-511)

Width 5 each. Monotonic within a season: `career_new[X] = career_base[X] + max(0, season_new[X] - season_base[X])`.

| Offset | Field |
|--------|-------|
| 437 | `careerGP` |
| 442 | `careerMIN` |
| 447 | `career2GM` |
| 452 | `career2GA` |
| 457 | `careerFTM` |
| 462 | `careerFTA` |
| 467 | `career3GM` |
| 472 | `career3GA` |
| 477 | `careerORB` |
| 482 | `careerDRB` |
| 487 | `careerAST` |
| 492 | `careerSTL` |
| 497 | `careerTVR` |
| 502 | `careerBLK` |
| 507 | `careerPF` |

#### Unknown region (512-549)

38 bytes of unmapped data.

#### Height / Weight / Ratings (550-606)

| Offset | Width | Field |
|--------|-------|-------|
| 550 | 2 | `heightInches` |
| 552 | 3 | `weight` |
| 555 | 3 | `rating2GA` |
| 558 | 3 | `rating2GP` |
| 561 | 3 | `ratingFTA` |
| 564 | 3 | `ratingFTP` |
| 567 | 3 | `rating3GA` |
| 570 | 3 | `rating3GP` |
| 573 | 3 | `ratingORB` |
| 576 | 3 | `ratingDRB` |
| 579 | 3 | `ratingAST` |
| 582 | 3 | `ratingSTL` |
| 585 | 3 | `ratingTVR` |
| 588 | 3 | `ratingBLK` |
| 591 | 2 | `ratingOO` (offense-offense) |
| 593 | 2 | `ratingDO` |
| 595 | 2 | `ratingPO` |
| 597 | 2 | `ratingTO` |
| 599 | 2 | `ratingOD` |
| 601 | 2 | `ratingDD` |
| 603 | 2 | `ratingPD` |
| 605 | 2 | `ratingTD` |

### Team-Summary Rows (ordinal ≥ 1441)

Lines with `ordinal ≥ 1441` and `pid = 0` store per-team totals. The first 28 rows (ordinals 1441-1468) are the 28 franchises (**607-608 bytes each** — the trailing tail block contains variable-width fields); a second block (1471 onward) appears to be a secondary team-totals section (possibly opponent-allowed stats).

Franchise rows are indexed by position: ordinal 1441 = team ID 1, ordinal 1468 = team ID 28 (matching `ibl_team_info` row order). The embedded team name is sometimes stale in older snapshots — use ordinal-based identity instead.

**Validated regular-season field map** (see `classes/PlrParser/PlrTeamRowLayout.php` for the authoritative constant):

| Offset | Width | Field | Description |
|--------|-------|-------|-------------|
| 148 | 4 | `gp` | Games played |
| 152 | 4 | `gpAlt` | Games played (duplicate; purpose unconfirmed) |
| 156 | 4 | `twoGM` | 2-point field goals made |
| 160 | 4 | `twoGA` | 2-point field goals attempted |
| 164 | 4 | `ftm` | Free throws made |
| 168 | 4 | `fta` | Free throws attempted |
| 172 | 4 | `threeGM` | 3-point field goals made |
| 176 | 4 | `threeGA` | 3-point field goals attempted |
| 180 | 4 | `orb` | Offensive rebounds |
| 184 | 4 | `drb` | Defensive rebounds |
| 188 | 4 | `ast` | Assists |
| 192 | 4 | `stl` | Steals |
| 196 | 4 | `tov` | Turnovers |
| 200 | 4 | `blk` | Blocks |
| 204 | 4 | `pf` | Personal fouls |

**Validated playoff-season field map** (same 15 fields at offsets 208-267, see `PlrTeamRowLayout::PLAYOFF_SEASON_FIELD_MAP`):

| Offset | Width | Field | Description |
|--------|-------|-------|-------------|
| 208 | 4 | `gp` | Playoff games played |
| 212 | 4 | `gpAlt` | Playoff games played (duplicate) |
| 216-264 | 4 each | (same 13 stat fields as regular-season) | Playoff cumulative totals |

**Still unknown** (preserved byte-for-byte on writeback):
- Bytes 0-147: static team metadata
- Bytes 320-511: suspected streak/record/opponent-allowed totals
- Bytes 540-606/607: trailing tail block (variable-width fields cause 607-or-608-byte rows)

### Encoding Notes

- **Right-justified integers** with space padding (never zero-padded). `4` in a 4-byte field is `"   4"`, not `"0004"`.
- **Left-justified strings** with space padding (player names, team names).
- **CP1252** for non-ASCII characters (accented names). Readers must `iconv('CP1252', 'UTF-8//IGNORE', $raw)` before display.
- **CRLF** line endings between records. Player records are *exactly* 607 bytes; franchise team rows are 607-608 bytes (variable trailing tail). `PlrFileWriter::applyChangesToRecord()` asserts length invariance.

---

## IBL5.his — Historical Results

**Size:** ~72 KB | **Format:** Text (CRLF line endings) | **Encoding:** Latin-1

### File Structure

The file contains team-by-team season results organized in **season blocks** of ~33 lines each. Seasons run from 1988 onward.

- Blocks are separated by a line of spaces
- Each block covers one season
- Lines within a block describe each team's record and playoff outcome
- Empty/padding lines fill out the block to a fixed size

### Line Format

Each line uses arrow delimiters (`→`) to separate fields:

```
TeamName (W-L) [playoff result] (Year)
```

#### Playoff Result Patterns

| Pattern | Meaning |
|---------|---------|
| `defeat the OtherTeam` | Won the championship |
| `lose to OtherTeam in the Finals` | Lost in the finals |
| `lose to OtherTeam in the X round` | Eliminated in a playoff round |
| `make the Playoffs` | Made playoffs but no further detail |
| *(no playoff text)* | Did not make playoffs |

### Example

```
Celtics (52-30) defeat the Lakers (1993)
Lakers (58-24) lose to Celtics in the Finals (1993)
Bulls (47-35) lose to Knicks in the 2nd round (1993)
```

### League Expansion

The league grew over time:
- **1988:** 24 teams
- **Later seasons:** Up to 30+ teams (Pistons, Kings, Bullets, Mavericks, Braves, Aces, Sting, Rockets added)

---

## IBL5.rcb — Record Book

**Size:** 1,823,016 bytes | **Format:** Fixed-width text (CRLF line endings) | **Encoding:** Latin-1

### File Structure

| Section | Lines | Bytes/Line | Purpose |
|---------|-------|------------|---------|
| All-Time Records | 0–49 | 26,928 | Career and single-season records (ranked #1–#50) |
| Current Season Records | 50–82 | 14,432 | Single-game records for in-progress season |
| Trailing Overflow | 83–85 | 110, 56, 22 | Overflow/padding |
| Empty | 86 | 0 | Trailing empty line |

### Section 1: All-Time Records (Lines 0–49)

Each line represents a **ranking position** (#1 through #50). Each line contains **528 entries** of **51 characters** each (528 * 51 = 26,928 bytes).

The 528 entries are organized as **33 groups of 16 entries**:

| Group | Entries | Scope |
|-------|---------|-------|
| 0 | 0–15 | League-wide records |
| 1–28 | 16–463 | Team-specific records (Group N = Team ID N) |
| 29–32 | 464–527 | Reserved/empty (all zeros) |

#### Group 0: League-Wide Records (16 entries)

Entries alternate: **even = single-season record**, **odd = career record**.

| Entry | Type | Stat Category |
|-------|------|---------------|
| 0 | Single-Season | PPG (Points Per Game) |
| 1 | Career | PTS (Total Points) |
| 2 | Single-Season | RPG (Rebounds Per Game) |
| 3 | Career | TRB (Total Rebounds) |
| 4 | Single-Season | APG (Assists Per Game) |
| 5 | Career | AST (Total Assists) |
| 6 | Single-Season | SPG (Steals Per Game) |
| 7 | Career | STL (Total Steals) |
| 8 | Single-Season | BPG (Blocks Per Game) |
| 9 | Career | BLK (Total Blocks) |
| 10 | Single-Season | FG% (Field Goal Percentage) |
| 11 | Career | FG% (Career Field Goal Percentage) |
| 12 | Single-Season | FT% (Free Throw Percentage) |
| 13 | Career | FT% (Career Free Throw Percentage) |
| 14 | Single-Season | 3P% (Three-Point Percentage) |
| 15 | Career | 3P% (Career Three-Point Percentage) |

#### Single-Season Entry Format (51 chars)

| Offset | Width | Description |
|--------|-------|-------------|
| 0–32 | 33 | Player name (right-justified) |
| 33–37 | 5 | Block ID from `.car` file (right-justified) |
| 38–43 | 6 | Stat value (right-justified) |
| 44–45 | 2 | Team ID (right-justified) |
| 46–49 | 4 | Season year |
| 50 | 1 | Trailing space |

**Stat value encoding:**
- Per-game averages: `int(total / GP * 100)` — e.g., 36.11 PPG = `3611`
- Percentages: `int(pct * 10000)` — e.g., 67.08% = `6708`

#### Career Entry Format (51 chars)

| Offset | Width | Description |
|--------|-------|-------------|
| 0–32 | 33 | Player name (right-justified) |
| 33–37 | 5 | Block ID from `.car` file (right-justified) |
| 38–42 | 5 | Career counting total (right-justified) |
| 43–48 | 6 | Per-game average * 100 or percentage * 10000 (right-justified) |
| 49–50 | 2 | Team ID of last active team (right-justified) |

**Career counting totals by entry:**
- Entry 1 (PTS): `2GM * 2 + FTM + 3GM * 3`
- Entry 3 (TRB): `ORB + DRB`
- Entry 5 (AST): Total assists
- Entry 7 (STL): Total steals
- Entry 9 (BLK): Total blocks
- Entry 11 (FG%): `2GM + 3GM` (total makes); average field = percentage * 10000
- Entry 13 (FT%): `FTM` (total makes); average field = percentage * 10000
- Entry 15 (3P%): `3GM` (total makes); average field = percentage * 10000

#### Groups 1–28: Team-Specific Records

Same 16-entry structure as Group 0, but scoped to a single team:
- **Even entries:** Best player single-season performances for that team
- **Odd entries:** League-wide team season records (ranked across all teams for groups 1–2; sentinel value `65535` for groups 3+)

Team season record entry format:
```
[0:33]  Team name (right-justified)
[33:38] Season year (right-justified)
[38:43] Season total (5 chars, for counting stats)
[43:49] Percentage * 10000 (6 chars, for percentage stats)
[49:51] " 0" (trailing)
```

### Section 2: Current Season Records (Lines 50–82)

33 lines of 14,432 bytes each tracking **single-game performance records** for the in-progress season.

- **Line 50:** League-wide current season records
- **Lines 51–82:** Team-specific records (Team ID 1–32)

Each line contains **160 entries of 90 characters** + 32 bytes padding.

#### Entry Format (90 chars = 45-char player record + 45-char team record)

Player record (45 chars):
| Offset | Width | Description |
|--------|-------|-------------|
| 0–32 | 33 | `POS Name` (position code + player name, right-justified) |
| 33–37 | 5 | Block ID from `.car` file (right-justified) |
| 38–40 | 3 | Stat value (single-game count, right-justified) |
| 41–44 | 4 | Season year |

Team record (45 chars): Usually all zeros/spaces.

#### Stat Category Layout (160 entries)

160 entries = 10 ranking positions * 16 stat categories (8 stats * 2 contexts: away/home).

| Entry | Context | Stat | Entry | Context | Stat |
|-------|---------|------|-------|---------|------|
| 0 | Away | PTS | 8 | Home | PTS |
| 1 | Away | REB | 9 | Home | REB |
| 2 | Away | AST | 10 | Home | AST |
| 3 | Away | STL | 11 | Home | STL |
| 4 | Away | BLK | 12 | Home | BLK |
| 5 | Away | 2GM | 13 | Home | 2GM |
| 6 | Away | 3GM | 14 | Home | 3GM |
| 7 | Away | FTM | 15 | Home | FTM |

### Section 3: Trailing Overflow (Lines 83–85)

Three short lines (110, 56, 22 bytes) containing overflow/partial entries from the current season section.

---

## IBL5.trn — Transactions

**Size:** 64,000 bytes (exactly) | **Format:** Fixed-width binary (no line delimiters) | **Encoding:** ASCII

### File Structure

- **Record size:** 128 bytes
- **Total record slots:** 500 (64,000 / 128)
- **Used records:** Stored in header (bytes 0–16 of record 0); e.g., `201`
- **Unused slots:** Space-padded

### Record Layout (128 bytes)

| Offset | Width | Description |
|--------|-------|-------------|
| 0–16 | 17 | Header area (record 0 stores used-count; other records have spaces) |
| 17–18 | 2 | Month (right-justified, 1–12) |
| 19–20 | 2 | Day (right-justified, 1–31) |
| 21–24 | 4 | Season year (e.g., `2006` for the 2006-07 season) |
| 25 | 1 | Space delimiter |
| 26 | 1 | Transaction type: `1`=Injury, `2`=Trade, `3`=Waiver Claim, `4`=Waiver Release |
| 27–127 | 101 | Type-specific data (see below) |

### Type 1 — Injury

| Offset | Width | Description |
|--------|-------|-------------|
| 27–28 | 2 | Padding (spaces) |
| 29–32 | 4 | Player ID (right-justified) |
| 33–34 | 2 | Team ID (right-justified) |
| 35–38 | 4 | Games missed (right-justified) |
| 39–95 | ~57 | Injury description (right-justified text, e.g., `Strained groin`, `Torn ACL in left knee`) |
| 96–127 | 32 | Padding (spaces) |

Duration ranges from 1 game (minor) to 233+ games (torn ACL).

### Type 2 — Trade/Roster Move

Uses a sub-record system with **19-byte items** packed sequentially starting at offset 27. A trade block begins with a subtype-blank separator record, followed by one or more item records.

#### Player Movement Item (subtype `0`)

| Offset | Width | Description |
|--------|-------|-------------|
| 0 | 1 | Marker: `0` = player move |
| 1–6 | 6 | From Team ID (right-justified) |
| 7–12 | 6 | To Team ID (right-justified) |
| 13–18 | 6 | Player ID (right-justified) |

#### Draft Pick Trade Item (subtype `1`)

| Offset | Width | Description |
|--------|-------|-------------|
| 0 | 1 | Marker: `1` = draft pick |
| 1–6 | 6 | Draft year (right-justified) |
| 7–12 | 6 | From Team ID (right-justified) |
| 13–18 | 6 | To Team ID (right-justified) |

Multiple items can be packed into a single 128-byte record (up to 5 items at 19 bytes each).

### Type 3 — Waiver Claim

| Offset | Width | Description |
|--------|-------|-------------|
| 27–28 | 2 | Team ID (right-justified) |
| 29–30 | 2 | Padding |
| 31–34 | 4 | Player ID (right-justified) |

### Type 4 — Waiver Release

Same layout as Type 3. Types 3 and 4 often appear in pairs on the same date for the same team (release one player, claim another).

---

## IBL5.asw — All-Star Weekend

**Size:** 10,000 bytes (exactly, space-padded) | **Format:** ASCII text (CRLF line endings)

### File Structure

- **Data lines:** 0–111 (112 lines)
- **Padding line:** 112 (space-filled to reach 10,000 bytes total)
- **Field width:** 6 chars for Player IDs (right-justified), 4 chars for scores (right-justified)
- **Empty slot marker:** `0`

### Sections (8 total)

| Section | Lines | Content | Max Slots |
|---------|-------|---------|-----------|
| 1 | 0–15 | All-Star Team 1 roster | 15 (line 0 is header flag `0`) |
| 2 | 16–30 | All-Star Team 2 roster | 15 |
| 3 | 31–45 | Rookie Challenge Team 1 | 15 |
| 4 | 46–60 | Rookie Challenge Team 2 | 15 |
| 5 | 61–70 | 3-Point Shootout participants | 10 |
| 6 | 71–80 | Slam Dunk Contest participants | 10 |
| 7 | 81–96 | Slam Dunk Contest scores | 16 (line 81 is header flag `0`) |
| 8 | 97–111 | 3-Point Shootout scores | 15 |

### Section Details

#### Sections 1–4: Team Rosters

Each line contains one Player ID (6 chars, right-justified). `0` = empty slot.

#### Sections 5–6: Contest Participants

Each line contains one Player ID (6 chars, right-justified). `0` = empty slot. Up to 8 participants per contest.

#### Section 7: Slam Dunk Contest Scores

Scores correspond to Section 6 participants. Values are stored as `score * 10` (e.g., 932 = 93.2 points).

| Lines | Content |
|-------|---------|
| 81 | Header flag (`0`) |
| 82–89 | Round 1 scores (8 contestants) |
| 90–92 | Finals scores (top 3 advance) |
| 93–96 | Empty slots |

Scoring is on a **100-point scale** with one decimal place (stored as integer * 10). Example Round 1 values: 932 (93.2), 890 (89.0), 878 (87.8). A value of 1000 represents a perfect 100.0.

#### Section 8: 3-Point Shootout Scores

Scores correspond to Section 5 participants. Values are the **raw count of three-pointers made** per round (typically 16–25 out of 25 attempts).

| Lines | Content |
|-------|---------|
| 97–104 | Round 1 scores (8 contestants) |
| 105–108 | Semifinal scores (top 4 advance) |
| 109–110 | Finals scores (top 2) |
| 111 | Empty slot |

Later rounds (semifinals, finals) appear to be stored in descending score order rather than participant order.

---

## Shared Conventions

### Numeric Fields
- **Right-justified** with space padding (never zero-padded)
- Integer values only (no decimal points in files)
- Per-game averages stored as `int(value * 100)` — e.g., 25.38 PPG = `2538`
- Percentages stored as `int(value * 10000)` — e.g., 52.15% = `5215`

### String Fields
- **Left-justified** with space padding (player names, team names in `.car`)
- **Right-justified** with space padding (player names in `.rcb`)
- Player names: 16 chars in `.car`, 33 chars in `.rcb`
- Team names: 16 chars in `.car`, variable in other files

### File Sizes
All files use **exact round sizes** padded with spaces:
- `.car`: 2,500 bytes per block
- `.trn`: 64,000 bytes total (500 slots * 128 bytes)
- `.asw`: 10,000 bytes total

### Player ID Systems
Two distinct ID systems exist:
- **Internal Player ID** (stored in `.car` header bytes 3–7): Large numbers in the 27,000+ range
- **Block Index** (position in `.car` file): Used by `.rcb` as the player reference
- **Player ID** (used by `.trn` and `.asw`): Matches the `pid` column in the database's `ibl_plr` table

### Team ID Mapping

| ID | Team | ID | Team |
|----|------|----|------|
| 0 | Free Agents | 15 | Nuggets |
| 1 | Celtics | 16 | Thunder |
| 2 | Heat | 17 | Spurs |
| 3 | Knicks | 18 | Trailblazers |
| 4 | Nets | 19 | Clippers |
| 5 | Magic | 20 | Grizzlies |
| 6 | Bucks | 21 | Lakers |
| 7 | Bulls | 22 | Supersonics |
| 8 | Pelicans | 23 | Suns |
| 9 | Hawks | 24 | Warriors |
| 10 | Hornets | 25 | Pistons |
| 11 | Pacers | 26 | Kings |
| 12 | Raptors | 27 | Bullets |
| 13 | Jazz | 28 | Mavericks |
| 14 | Timberwolves | | |

### Points Formula

In the `.car` file, **3GM is a separate counting stat from 2GM** — they do not overlap. Points are calculated as:

```
PTS = 2GM * 2 + FTM + 3GM * 3
```

This differs from standard basketball box scores where FGM includes both two-pointers and three-pointers (making the formula `FGM * 2 + FTM + 3GM`). In JSB, 2GM tracks only two-point field goals.

### Line Endings
- `.his`, `.rcb`, `.asw`: CRLF (`\r\n`)
- `.car`, `.trn`: No line delimiters (pure binary/fixed-width)
