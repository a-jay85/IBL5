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
     * BoxID that ScheduleUpdater writes for games not played yet (it keeps its own
     * copy as ScheduleUpdater::UNPLAYED_BOX_ID). No box100000.htm file exists, so
     * this id must never become a legacy link.
     */
    private const UNPLAYED_BOX_ID = 100000;

    /**
     * Build a box score URL, preferring the internal module page with legacy BoxID fallback.
     *
     * Returns empty string if neither gameOfThatDay nor a real boxId is available
     * (unplayed games, including the UNPLAYED_BOX_ID placeholder).
     *
     * $teamId (optional) is appended as `&team=` on the internal module URL only, so the
     * box score page opens on that team's tab. Ignored when 0 or for the legacy URL.
     */
    public static function buildUrl(string $date, int $gameOfThatDay, int $boxId = 0, int $teamId = 0): string
    {
        if ($gameOfThatDay > 0 && $date !== '' && $date !== '0000-00-00') {
            $url = 'modules.php?name=GameBoxscore&date=' . rawurlencode($date) . '&game=' . $gameOfThatDay;
            if ($teamId > 0) {
                $url .= '&team=' . $teamId;
            }
            return $url;
        }

        if ($boxId > 0 && $boxId !== self::UNPLAYED_BOX_ID) {
            return './ibl/IBL/box' . $boxId . '.htm';
        }

        return '';
    }
}
