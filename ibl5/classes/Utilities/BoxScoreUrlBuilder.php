<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Builds URLs to the internal GameBoxscore module page, with legacy fallback.
 *
 * Internal pattern: modules.php?name=GameBoxscore&date={YYYY-MM-DD}&game={gameOfThatDay}
 * Legacy fallback: ./ibl/IBL/box{BoxID}.htm
 */
class BoxScoreUrlBuilder
{
    /**
     * Build a box score URL, preferring the internal module page with legacy BoxID fallback.
     *
     * Returns empty string only if neither gameOfThatDay nor boxId is available
     * (unplayed games).
     */
    public static function buildUrl(string $date, int $gameOfThatDay, int $boxId = 0): string
    {
        if ($gameOfThatDay > 0 && $date !== '' && $date !== '0000-00-00') {
            return 'modules.php?name=GameBoxscore&date=' . rawurlencode($date) . '&game=' . $gameOfThatDay;
        }

        if ($boxId > 0) {
            return './ibl/IBL/box' . $boxId . '.htm';
        }

        return '';
    }
}
