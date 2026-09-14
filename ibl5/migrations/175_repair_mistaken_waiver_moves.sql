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
-- Why the target state is Free Agent, and what the sign destroyed
-- ---------------------------------------------------------------------------
-- The signing branch above only fires when hasExistingContract is false. That
-- predicate is computed in WaiversProcessor::determineContractData() and, in the
-- offseason branch, reduces to:
--     hasExistingContract = salary_yr[cy + 1] > 0
-- So it proves only that the NEXT contract year was empty -- that the contract had
-- already expired. It says nothing about salary_yr1, salary_yr2 or bird.
--
-- The salaries the sign stamped are the offseason veteran minimum (exp + 1) from
-- ibl5/classes/ContractRules.php :: VETERAN_MINIMUM_SALARIES:
--   pid 4163  exp  7 -> 8 rung  -> 89   (news feed said 89)
--   pid 1998  exp 14 -> 10+ rung -> 103 (news feed said 103)
--   pid 2001  exp 14 -> 10+ rung -> 103 (news feed said 103)
-- All three match exactly. The exp + 1 rung is reached ONLY inside the
-- isOffseasonPhase() branch of determineContractData() -- in-season, pid 4163
-- would have drawn the raw exp 7 rung of 82, not 89. So the exact match is
-- independent confirmation the league had already rolled over into the offseason
-- and these three were already free agents when the mistake happened.
--
-- What is NOT recoverable: signPlayerFromWaivers() overwrites bird, cy, cyt and
-- salary_yr1..salary_yr6 unconditionally, so every pre-mistake value in those
-- columns is gone. A production copy taken 2026-09-05, before the mistake, shows
-- all three still under contract -- pid 4163 in the final year of a two-year deal
-- at bird = 2, cy = 2, cyt = 2, salary_yr1 = 740, salary_yr2 = 814; pid 1998 at
-- bird = 1, salary_yr1 = 480; pid 2001 at bird = 14, salary_yr1 = 800. Those
-- numbers are NOT restored below and cannot be: they belong to contracts that
-- expired at the rollover, and no code path records what the rollover wrote.
--
-- The target state is therefore justified by the free-agent population, not by
-- reconstructing history. On that same 2026-09-05 copy, all 281 rows matching
--     teamid = 0 AND retired = 0 AND cyt = 0
-- carry salary_yr1 = 0, salary_yr2 = 0, bird = 0 and cy = 0 -- 281 of 281, no
-- exceptions. The columns this migration writes land exactly on that fingerprint.
--
-- ---------------------------------------------------------------------------
-- Column-by-column decisions (verified against the live free-agent population:
-- all 281 rows with teamid = 0 AND retired = 0 AND cyt = 0)
-- ---------------------------------------------------------------------------
--   teamid      10/10/24 -> 0    THE real damage. 0 = League::FREE_AGENTS_TEAMID.
--   cyt         1        -> 0    All 281 sampled free agents have cyt = 0.
--   salary_yr1  89/103/103 -> 0  Fabricated veteran minimum (see above). 0 is the
--                                free-agent value -- NOT a reconstruction of the
--                                740/480/800 the expired contracts carried.
--   droptime    <today>  -> 0    See note below.
--   bird        0        -> 0    NO WRITE NEEDED -- already at the target value.
--                                The sign zeroed it (pid 4163 was bird = 2), so
--                                this is real damage that happens to coincide with
--                                the correct end state: all 281 sampled free agents
--                                have bird = 0, and setWaiversToFreeAgents()
--                                explicitly writes bird = 0 alongside teamid = 0.
--   cy          0        -> 0    NO WRITE NEEDED -- same situation as bird.
--   salary_yr2  0        -> 0    NO WRITE NEEDED -- the sign zeroed yr2..yr6 too
--   ..yr6                        (pid 4163 was salary_yr2 = 814), and 0 is the
--                                free-agent value. Left out of the SET list so a
--                                partial re-run cannot clobber a real contract
--                                signed in between.
--   ordinal     1000     -> left at 1000. See note below.
--
-- droptime: 18 of the 281 sampled free agents carry a nonzero droptime, so 0
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
-- out to the .plr BEFORE the next import. That export writes teamid, bird, cy,
-- cyt and salary_yr1..6 (all are in PlrFileWriter::FIELD_MAP), so it is what makes
-- the free-agent state above authoritative in the file as well as the DB. It is
-- also the point at which the expired-contract values listed as unrecoverable
-- above stop existing anywhere -- that is intended, not collateral.
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
