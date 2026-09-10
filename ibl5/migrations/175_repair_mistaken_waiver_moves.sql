-- Migration: 175_repair_mistaken_waiver_moves.sql
-- Purpose: Reverse six waiver transactions that were performed on PRODUCTION by
--          mistake on 2026-09-10, and restore the three affected players to the
--          unsigned Free Agent state they held before the mistake.
--
-- Reported by the league operator, sourced from the APP news feed:
--   3:00PM  The Sting sign Gheorghe Muresan from waivers for 89
--   3:00PM  The Sting sign Juan Antonio San Epifanio from waivers for 103
--   3:00PM  The Sting cut Gheorghe Muresan to waivers
--   3:00PM  The Sting cut Juan Antonio San Epifanio to waivers
--   3:19PM  The Warriors sign Moses Malone from waivers for 103
--   3:19PM  The Warriors cut Moses Malone to waivers
--
-- Affected pids: 4163 (Gheorghe Muresan), 1998 (Juan Antonio San Epifanio),
--                2001 (Moses Malone).
--
-- ---------------------------------------------------------------------------
-- What the two waiver code paths actually wrote
-- ---------------------------------------------------------------------------
-- ibl5/classes/Waivers/WaiversRepository.php :: signPlayerFromWaivers()
--   In the no-existing-contract branch it writes:
--     ordinal = 800, bird = 0, cy = 0, cyt = 1,
--     salary_yr1 = <vet minimum>, salary_yr2..6 = 0,
--     teamid = <signing team>, droptime = 0
--
-- ibl5/classes/Waivers/WaiversRepository.php :: dropPlayerToWaivers()
--   writes ONLY:
--     ordinal = 1000, droptime = <unix ts>
--
-- The cut deliberately does NOT restore teamid -- in the normal flow the player
-- was on that roster to begin with, and a later league-control-panel sweep
-- (LeagueControlPanelRepository::setWaiversToFreeAgents()) moves unclaimed
-- waiver players back to teamid 0. Here the sign was itself the mistake, so the
-- cut left all three players stranded on a roster they never belonged to, still
-- carrying a fabricated contract. That stranded state is the damage this
-- migration repairs.
--
-- ---------------------------------------------------------------------------
-- Proof the players had NO contract before the mistake
-- ---------------------------------------------------------------------------
-- The signing branch above only fires when hasExistingContract is false. The
-- salaries it stamped are the offseason veteran minimum (exp + 1) from
-- ibl5/classes/ContractRules.php :: VETERAN_MINIMUM_SALARIES:
--   pid 4163  exp  7 -> 8 rung  -> 89   (news feed said 89)
--   pid 1998  exp 14 -> 10+ rung -> 103 (news feed said 103)
--   pid 2001  exp 14 -> 10+ rung -> 103 (news feed said 103)
-- All three match exactly. So salary_yr1 was 0 before the mistake and zeroing it
-- is a restoration, not a guess. salary_yr2..yr6 were already 0 and are already
-- correct -- they are left alone so a partial re-run cannot clobber a real
-- contract signed in between.
--
-- ---------------------------------------------------------------------------
-- Column-by-column decisions (verified against the live free-agent population:
-- 20 sampled rows with teamid = 0 AND retired = 0)
-- ---------------------------------------------------------------------------
--   teamid      10/10/24 -> 0    THE real damage. 0 = League::FREE_AGENTS_TEAMID.
--   cyt         1        -> 0    Every sampled free agent has cyt = 0.
--   salary_yr1  89/103/103 -> 0  Fabricated veteran minimum (see proof above).
--   droptime    <today>  -> 0    See note below.
--   bird        0        -> 0    NO CHANGE NEEDED. The sign zeroed it, but 0 is
--                                already the correct free-agent value: every
--                                sampled free agent has bird = 0, and
--                                setWaiversToFreeAgents() explicitly writes
--                                bird = 0 alongside teamid = 0. Nothing was lost.
--   cy          0        -> 0    NO CHANGE NEEDED; already correct.
--   ordinal     1000     -> left at 1000. See note below.
--
-- droptime: two of the twenty sampled free agents carry a nonzero droptime, so 0
-- is not strictly the free-agent fingerprint. But the value sitting there now was
-- written by TODAY's mistaken cut, so it is wrong either way, and a nonzero
-- droptime starts the 24-hour waiver claim clock. 0 is the safe choice: it clears
-- the fake clock, and the real prior value is not recoverable.
--
-- ordinal: this is NOT a status flag -- it is a .plr file slot address,
-- ordinal = (teamid - 1) * 30 + slotIndex + 1, per
-- ibl5/classes/PlrParser/PlrOrdinalMap.php. It is re-derived from the .plr on
-- every import (ibl5/classes/PlrParser/PlrParserRepository.php writes
-- ordinal = VALUES(ordinal)), so it self-heals. 1000 is already inside the
-- free-agent / waiver-pool band the site reads (JSB::WAIVERS_ORDINAL = 960;
-- the pool listing is ordinal > 959), so leaving it causes no visible wrong
-- state. The DB -> file export preserves the file's existing ordinal
-- (ibl5/classes/PlrParser/PlrFileWriter.php), so 1000 will not be pushed into
-- the .plr either.
--
-- ---------------------------------------------------------------------------
-- Deliberately NOT touched
-- ---------------------------------------------------------------------------
-- ibl_fa_offers: two live Sting offers exist -- primary_key 342 (pid 1998,
-- offer1 = 103) and 343 (pid 4163, offer1 = 82). These are legitimate Free
-- Agency artifacts, NOT side effects of the mistake:
--   * Neither waiver code path writes to ibl_fa_offers at all.
--   * Offer 343 is 82, while the mistaken waiver sign for the same player was 89.
--     82 is the exp-based veteran minimum (exp 7) used by the Free Agency flow;
--     89 is the offseason exp + 1 rung used by the waiver flow. Different amount,
--     different code path -- the offer predates and is independent of the mistake.
-- Restoring "the state during Free Agency" means these offers stay.
--
-- ibl_events: request-log rows only; no player state. Left as the historical record.
--
-- ---------------------------------------------------------------------------
-- REQUIRED POST-DEPLOY STEP
-- ---------------------------------------------------------------------------
-- ibl5/classes/PlrParser/PlrParserRepository.php upserts teamid, cy, cyt and
-- salary_yr1..6 straight from the .plr file. If a .plr IMPORT runs after this
-- migration and the production .plr still holds the mistaken roster/contract
-- values, the import will silently re-apply them and revert this repair.
-- After deploying, run ibl5/scripts/jsbExport.php to push the corrected DB rows
-- out to the .plr BEFORE the next import.
--
-- Idempotency and guard choice: each ibl_plr statement is keyed on droptime, the
-- one damaged column that nothing else rewrites -- no .plr field maps to it and
-- setWaiversToFreeAgents() does not touch it. teamid, ordinal and cyt are
-- deliberately NOT in the WHERE clauses even though they are wrong: teamid and
-- ordinal are both re-derivable (a .plr import rewrites ordinal; the league
-- control panel waiver sweep rewrites teamid), so guarding on them would make
-- this migration silently match zero rows if either runs before deploy -- a green
-- migration that repaired nothing. salary_yr1 stays in the guard as a
-- cross-check because it is the fabricated value proven above. Since pass one
-- zeroes droptime, a second run matches nothing. The story statements are bounded
-- to 2026-09-10 or later (and before 2026-09-12) and the counter rollback is gated
-- on those stories still existing, so it too is self-disarming.

-- ---------------------------------------------------------------------------
-- 1. Restore the three players to unsigned Free Agent state
-- ---------------------------------------------------------------------------
-- Keyed on droptime (unique per cut, and the only damaged column no other code
-- path rewrites) plus the fabricated salary as a cross-check. See the guard-choice
-- note in the header for why teamid/ordinal/cyt are deliberately absent here.

-- Gheorghe Muresan -- signed by, then cut by, the Sting (teamid 10)
UPDATE ibl_plr
   SET teamid = 0,
       cyt = 0,
       salary_yr1 = 0,
       droptime = 0
 WHERE pid = 4163
   AND droptime = 1789077790
   AND salary_yr1 = 89;

-- Juan Antonio San Epifanio -- signed by, then cut by, the Sting (teamid 10)
UPDATE ibl_plr
   SET teamid = 0,
       cyt = 0,
       salary_yr1 = 0,
       droptime = 0
 WHERE pid = 1998
   AND droptime = 1789077795
   AND salary_yr1 = 103;

-- Moses Malone -- signed by, then cut by, the Warriors (teamid 24)
UPDATE ibl_plr
   SET teamid = 0,
       cyt = 0,
       salary_yr1 = 0,
       droptime = 0
 WHERE pid = 2001
   AND droptime = 1789078837
   AND salary_yr1 = 103;

-- ---------------------------------------------------------------------------
-- 2. Roll back the category counter the six mistaken stories incremented
-- ---------------------------------------------------------------------------
-- ibl5/classes/Topics/News/NewsRepository.php :: incrementCategoryCounter() bumped
-- 'Waiver Pool Moves' once per story, so those bumps have to come back off.
--
-- This runs BEFORE the DELETE on purpose. nuke_stories_cat.counter is a running
-- total (~11,160 as of this writing) with no link back to individual stories, so a
-- bare "counter >= 6" would happily fire again on a second run and over-subtract.
-- Gating on the stories still being present makes it self-disarming: once section
-- 3 has removed them the subquery returns 0, the arithmetic is a no-op, and the
-- >= clause prevents any underflow. Subtracting the counted rows rather than a
-- literal 6 also keeps the counter consistent if some stories were already
-- removed by hand.

UPDATE nuke_stories_cat
   SET counter = counter - (
       SELECT COUNT(*)
         FROM nuke_stories
       WHERE topic IN (32, 33)
          AND `time` >= '2026-09-10'
          AND `time` < '2026-09-12'
          AND hometext IN (
              'The Sting sign Gheorghe Muresan from waivers for 89.',
              'The Sting sign Juan Antonio San Epifanio from waivers for 103.',
              'The Sting cut Gheorghe Muresan to waivers.',
              'The Sting cut Juan Antonio San Epifanio to waivers.',
              'The Warriors sign Moses Malone from waivers for 103.',
              'The Warriors cut Moses Malone to waivers.'
          )
   )
 WHERE title = 'Waiver Pool Moves'
   AND counter >= (
       SELECT COUNT(*)
         FROM nuke_stories
       WHERE topic IN (32, 33)
          AND `time` >= '2026-09-10'
          AND `time` < '2026-09-12'
          AND hometext IN (
              'The Sting sign Gheorghe Muresan from waivers for 89.',
              'The Sting sign Juan Antonio San Epifanio from waivers for 103.',
              'The Sting cut Gheorghe Muresan to waivers.',
              'The Sting cut Juan Antonio San Epifanio to waivers.',
              'The Warriors sign Moses Malone from waivers for 103.',
              'The Warriors cut Moses Malone to waivers.'
          )
   );

-- ---------------------------------------------------------------------------
-- 3. Remove the six news stories the mistaken moves generated
-- ---------------------------------------------------------------------------
-- Written by ibl5/classes/Waivers/WaiversProcessor.php :: createWaiverNewsStory()
-- via ibl5/classes/Topics/News/NewsRepository.php :: createNewsStory().
-- Topic 32 = waiver cut, topic 33 = waiver addition.
--
-- The guard is on hometext, NOT title: titles are per-team aggregates
-- ("Sting make waiver additions") that legitimate stories also use, whereas each
-- hometext names one player and (for a signing) one amount. Team names verified
-- against ibl_team_info.team_name -- teamid 10 = 'Sting', teamid 24 = 'Warriors'
-- -- so these literals match exactly what the processor interpolated.
--
-- The `time` >= '2026-09-10' bound is REQUIRED, not cosmetic. This category holds
-- roughly 11,160 stories going back years; if the Sting ever previously signed
-- Muresan from waivers for 89, that older story's hometext is byte-identical and
-- an unbounded DELETE would remove it too. The mistake happened on 2026-09-10
-- (droptime 1789077790 = 2026-09-10 22:03:10 UTC), so a lower bound of that date
-- captures all six under any plausible server timezone while excluding every
-- historical duplicate. The bounds are provably safe rather than merely likely:
-- nuke_stories.time is written by PHP date() in the server's local timezone
-- (NewsRepository.php line 18), and 22:03:10 UTC reads as 2026-09-10 in every
-- timezone from UTC-12 (10:03) to UTC+14 (next day, still after the lower bound).
-- The upper bound of '2026-09-12' closes the forward-facing tail: UTC+14 reads the
-- last event (22:20:37 UTC) as 2026-09-11 at most, still before '2026-09-12'.
-- `time` is backticked because TIME is a reserved word.

DELETE FROM nuke_stories
 WHERE topic IN (32, 33)
   AND `time` >= '2026-09-10'
   AND `time` < '2026-09-12'
   AND hometext IN (
       'The Sting sign Gheorghe Muresan from waivers for 89.',
       'The Sting sign Juan Antonio San Epifanio from waivers for 103.',
       'The Sting cut Gheorghe Muresan to waivers.',
       'The Sting cut Juan Antonio San Epifanio to waivers.',
       'The Warriors sign Moses Malone from waivers for 103.',
       'The Warriors cut Moses Malone to waivers.'
   );
