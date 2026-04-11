<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for parsing JSB .sco (Box Score) binary files.
 *
 * The .sco format is a fixed-width binary file with a 1MB metadata header
 * followed by 2000-byte per-game records. Each game record contains a
 * 58-byte game-info header, then 30 contiguous 53-byte player slots
 * (slots 0-14 are visitors, slots 15-29 are home). Slots whose pid is 0
 * are team-total rows that map to ibl_box_scores_teams; non-zero-pid
 * slots map to ibl_box_scores per-player rows.
 */
interface ScoFileParserInterface
{
    /**
     * Extract the 58-byte game-info prefix from a 2000-byte game line.
     * The returned substring is ready to pass to Boxscore::withGameInfoLine().
     */
    public static function extractGameInfo(string $line): string;

    /**
     * Extract a single 53-byte player/team-total slot from a 2000-byte game line.
     * The returned substring is ready to pass to PlayerStats::withBoxscoreInfoLine().
     *
     * @param int $slotIndex 0..29 inclusive (slots 0-14 are visitors, 15-29 are home)
     */
    public static function extractPlayerSlot(string $line, int $slotIndex): string;

    /**
     * Whether a slot index belongs to the home team (true) or the visiting team (false).
     */
    public static function isHomeTeamSlot(int $slotIndex): bool;
}
