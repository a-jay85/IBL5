<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for encoding JSB .sco (Box Score) binary records.
 *
 * Produces fixed-width records matching the format parsed by ScoFileParser /
 * Boxscore::fillGameInfo / PlayerStats::fillBoxscoreStats.
 *
 * All numeric fields are right-justified and space-padded; name/position fields
 * are left-justified and space-padded. Encoding validates field widths and rejects
 * out-of-range or negative values with RuntimeException.
 *
 * Background: the two ASG records (bytes 0..3999) in 06-07_36_finals.zip's
 * IBL5.sco are entirely blank. This interface encodes the known-good reconstructed
 * stats (sourced from ibl5/scripts/reconstruct_2007_asg_boxscores.php) so a future
 * archive-only reimport can natively reproduce the 48 DB rows without special-casing.
 *
 * @see \JsbParser\ScoFileParser
 * @see \Boxscore\Boxscore::fillGameInfo()
 * @see \Player\Stats\PlayerStats::fillBoxscoreStats()
 * @see /docs/decisions/0051-reconstructed-2007-asg-boxscores-in-finals-sco.md
 */
interface ScoFileWriterInterface
{
    /**
     * Build a 58-byte game-info header.
     *
     * Stored month/day/gameOfDay/teamid values are the raw JSB-encoded integers
     * (before the +10/+1 parse-time transforms applied by Boxscore::fillGameInfo).
     * They are overridden at import time by BoxscoreProcessor::overrideGameContext,
     * so their exact values only need to be non-blank and within field width.
     * Attendance, capacity, and quarter scores are read verbatim.
     *
     * @param int $monthStored      Raw stored month (JSB format, before +10)
     * @param int $dayStored        Raw stored day (before +1)
     * @param int $gameOfDayStored  Raw stored game-of-day (before +1)
     * @param int $visitorTidStored Raw stored visitor team ID (before +1)
     * @param int $homeTidStored    Raw stored home team ID (before +1)
     * @param int $attendance       Actual attendance figure
     * @param int $capacity         Venue capacity
     * @param int $visitorWins      Visitor team wins before game
     * @param int $visitorLosses    Visitor team losses before game
     * @param int $homeWins         Home team wins before game
     * @param int $homeLosses       Home team losses before game
     * @param list<int> $visitorQ   [q1, q2, q3, q4, ot] points for visitor
     * @param list<int> $homeQ      [q1, q2, q3, q4, ot] points for home
     * @return string Exactly 58 bytes
     */
    public static function buildGameInfo(
        int $monthStored,
        int $dayStored,
        int $gameOfDayStored,
        int $visitorTidStored,
        int $homeTidStored,
        int $attendance,
        int $capacity,
        int $visitorWins,
        int $visitorLosses,
        int $homeWins,
        int $homeLosses,
        array $visitorQ,
        array $homeQ,
    ): string;

    /**
     * Build a 53-byte player slot.
     *
     * Stores two-pointer makes/attempts (game_2gm, game_2ga) — NOT total FGM/FGA.
     * Stores defensive rebounds (game_drb) — NOT total rebounds.
     * Caller must apply: twoGM = fgm − tpm, twoGA = fga − tpa, drb = reb − orb.
     *
     * @param string $name     Player name, max 16 bytes (left-justified, space-padded)
     * @param string $pos      Position code, max 2 chars (left-justified)
     * @param int    $playerID Player ID (0 for team-total slots)
     * @param int    $min      Minutes played
     * @param int    $twoGM    Two-pointer makes (fgm − tpm)
     * @param int    $twoGA    Two-pointer attempts (fga − tpa)
     * @param int    $ftm      Free throw makes
     * @param int    $fta      Free throw attempts
     * @param int    $threeGM  Three-pointer makes
     * @param int    $threeGA  Three-pointer attempts
     * @param int    $orb      Offensive rebounds
     * @param int    $drb      Defensive rebounds (reb − orb)
     * @param int    $ast      Assists
     * @param int    $stl      Steals
     * @param int    $tov      Turnovers
     * @param int    $blk      Blocks
     * @param int    $pf       Personal fouls
     * @return string Exactly 53 bytes
     */
    public static function buildPlayerSlot(
        string $name,
        string $pos,
        int $playerID,
        int $min,
        int $twoGM,
        int $twoGA,
        int $ftm,
        int $fta,
        int $threeGM,
        int $threeGA,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): string;

    /**
     * Build a 53-byte team-total slot (playerID forced to 0, min forced to 0).
     *
     * @param string $name   Team name, max 16 bytes (left-justified, space-padded)
     * @param int    $twoGM  Two-pointer makes (fgm − tpm)
     * @param int    $twoGA  Two-pointer attempts (fga − tpa)
     * @param int    $ftm    Free throw makes
     * @param int    $fta    Free throw attempts
     * @param int    $threeGM Three-pointer makes
     * @param int    $threeGA Three-pointer attempts
     * @param int    $orb    Offensive rebounds
     * @param int    $drb    Defensive rebounds (reb − orb)
     * @param int    $ast    Assists
     * @param int    $stl    Steals
     * @param int    $tov    Turnovers
     * @param int    $blk    Blocks
     * @param int    $pf     Personal fouls
     * @return string Exactly 53 bytes
     */
    public static function buildTeamTotalSlot(
        string $name,
        int $twoGM,
        int $twoGA,
        int $ftm,
        int $fta,
        int $threeGM,
        int $threeGA,
        int $orb,
        int $drb,
        int $ast,
        int $stl,
        int $tov,
        int $blk,
        int $pf,
    ): string;

    /**
     * Assemble a 2000-byte record from a 58-byte game-info and up to 30 player slots.
     *
     * Slots are placed at byte 58 + (i * 53). Any unfilled slot positions and the
     * trailing bytes 1648..1999 are space-padded.
     *
     * @param string       $gameInfo    58-byte game-info header
     * @param list<string> $playerSlots Up to 30 53-byte slot strings
     * @return string Exactly 2000 bytes
     */
    public static function buildRecord(string $gameInfo, array $playerSlots): string;

    /**
     * Build the 4000-byte All-Star Weekend header block (record 0 ‖ record 1).
     *
     * Encodes the Rising Stars Game (record 0) and All-Star Game (record 1).
     * Each game array carries resolved player names and pre-derived stat values
     * (twoGM = fgm − tpm, twoGA = fga − tpa, drb = reb − orb).
     *
     * Layout per record:
     * - Visitor player slots 0..N-1 (N = count of visitor_players)
     * - Blank filler slots N..13
     * - Visitor team-total at slot 14
     * - Home player slots 15..15+M-1 (M = count of home_players)
     * - Blank filler slots 15+M..28
     * - Home team-total at slot 29
     *
     * @param array{
     *     visitor_name: string,
     *     home_name: string,
     *     visitor_q: list<int>,
     *     home_q: list<int>,
     *     visitor_teamid: int,
     *     home_teamid: int,
     *     attendance: int,
     *     capacity: int,
     *     visitor_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     home_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     visitor_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     *     home_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     * } $risingStars
     * @param array{
     *     visitor_name: string,
     *     home_name: string,
     *     visitor_q: list<int>,
     *     home_q: list<int>,
     *     visitor_teamid: int,
     *     home_teamid: int,
     *     attendance: int,
     *     capacity: int,
     *     visitor_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     home_team: array{twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int},
     *     visitor_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     *     home_players: list<array{name: string, pos: string, pid: int, min: int, twoGM: int, twoGA: int, ftm: int, fta: int, threeGM: int, threeGA: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, pf: int}>,
     * } $allStar
     * @return string Exactly 4000 bytes
     */
    public static function buildAllStarHeaderBlock(array $risingStars, array $allStar): string;

    /**
     * Splice the 4000-byte All-Star block into a .sco file, replacing bytes 0..3999.
     *
     * Guards (throws RuntimeException on violation):
     * - $block must be exactly 4000 bytes
     * - $sco must be exactly 12,781,648 bytes
     * - Bytes 0..3999 of $sco must be entirely spaces (blank precondition)
     * - After splice: total length unchanged AND bytes 1,000,000..EOF byte-identical to original
     *
     * @param string $sco   Full .sco file contents (12,781,648 bytes)
     * @param string $block Encoded 4000-byte block
     * @return string Patched .sco contents (12,781,648 bytes)
     * @throws \RuntimeException on any guard violation
     */
    public static function spliceAllStarBlock(string $sco, string $block): string;
}
