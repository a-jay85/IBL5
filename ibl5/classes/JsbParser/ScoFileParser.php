<?php

declare(strict_types=1);

namespace JsbParser;

use JsbParser\Contracts\ScoFileParserInterface;

/**
 * Pure parser for JSB .sco (Box Score) binary files.
 *
 * The .sco format starts with a 1,000,000-byte metadata header (skip with fseek),
 * after which each completed game occupies a contiguous 2,000-byte record:
 *
 * - bytes 0..57   — 58-byte game-info header (date, team IDs, attendance, quarter
 *                   scores, records before the game). Decoded by `Boxscore::withGameInfoLine`.
 * - bytes 58..1647 — 30 player/team-total slots × 53 bytes each. Slots 0..14 are
 *                    visitors (5 starters + bench + visitor team total); slots 15..29
 *                    are home (5 starters + bench + home team total). Each slot is
 *                    decoded by `PlayerStats::withBoxscoreInfoLine`. The team-total
 *                    rows have `playerID = 0` and feed `ibl_box_scores_teams`; the
 *                    non-zero rows feed `ibl_box_scores`.
 * - bytes 1648..1999 — Trailing padding / unknown JSB sim data, ignored.
 *
 * @see /docs/JSB_FILE_FORMATS.md
 */
class ScoFileParser implements ScoFileParserInterface
{
    public const RECORD_SIZE = 2000;
    public const GAME_INFO_SIZE = 58;
    public const PLAYER_SLOT_SIZE = 53;
    public const PLAYER_SLOT_COUNT = 30;
    public const VISITOR_SLOT_COUNT = 15;
    public const HEADER_OFFSET_BYTES = 1000000;

    /**
     * @see ScoFileParserInterface::extractGameInfo()
     */
    public static function extractGameInfo(string $line): string
    {
        return substr($line, 0, self::GAME_INFO_SIZE);
    }

    /**
     * @see ScoFileParserInterface::extractPlayerSlot()
     */
    public static function extractPlayerSlot(string $line, int $slotIndex): string
    {
        $offset = self::GAME_INFO_SIZE + ($slotIndex * self::PLAYER_SLOT_SIZE);
        return substr($line, $offset, self::PLAYER_SLOT_SIZE);
    }

    /**
     * @see ScoFileParserInterface::isHomeTeamSlot()
     */
    public static function isHomeTeamSlot(int $slotIndex): bool
    {
        return $slotIndex >= self::VISITOR_SLOT_COUNT;
    }
}
