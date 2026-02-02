<?php

declare(strict_types=1);

namespace Utilities;

/**
 * ScheduleHighlighter - Shared logic for schedule highlighting
 *
 * Provides utilities to determine which games should be highlighted
 * as part of the next simulation run.
 */
class ScheduleHighlighter
{
    /**
     * Determine if a game should be highlighted for the next sim
     *
     * A game is highlighted when it's unplayed and falls within
     * the projected next simulation date range.
     *
     * @param \DateTimeInterface $gameDate The game's scheduled date
     * @param \DateTimeInterface $projectedNextSimEndDate The projected end date of next sim
     * @return bool True if the game should be highlighted
     */
    public static function isNextSimGame(
        \DateTimeInterface $gameDate,
        \DateTimeInterface $projectedNextSimEndDate
    ): bool {
        return $gameDate <= $projectedNextSimEndDate;
    }

    /**
     * Determine if a game has been played based on scores
     *
     * An unplayed game has equal scores (typically both 0).
     *
     * @param int|string $visitorScore Visitor team score
     * @param int|string $homeScore Home team score
     * @return bool True if the game is unplayed
     */
    public static function isGameUnplayed(int|string $visitorScore, int|string $homeScore): bool
    {
        return (int)$visitorScore === (int)$homeScore;
    }

    /**
     * Determine if an unplayed game should be highlighted for next sim
     *
     * Convenience method that combines unplayed check with next sim check.
     *
     * @param int|string $visitorScore Visitor team score
     * @param int|string $homeScore Home team score
     * @param \DateTimeInterface $gameDate The game's scheduled date
     * @param \DateTimeInterface $projectedNextSimEndDate The projected end date of next sim
     * @return bool True if the game should be highlighted
     */
    public static function shouldHighlight(
        int|string $visitorScore,
        int|string $homeScore,
        \DateTimeInterface $gameDate,
        \DateTimeInterface $projectedNextSimEndDate
    ): bool {
        return self::isGameUnplayed($visitorScore, $homeScore)
            && self::isNextSimGame($gameDate, $projectedNextSimEndDate);
    }
}
