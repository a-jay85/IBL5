<?php

class PlayerPageType
{
    const GAME_LOG = 0;
    const OVERVIEW = null;
    const AWARDS_AND_NEWS = 1;
    const ONE_ON_ONE = 2;
    const REGULAR_SEASON_TOTALS = 3;
    const REGULAR_SEASON_AVERAGES = 4;
    const PLAYOFF_TOTALS = 5;
    const PLAYOFF_AVERAGES = 6;
    const HEAT_TOTALS = 7;
    const HEAT_AVERAGES = 8;
    const RATINGS_AND_SALARY = 9;
    const SIM_STATS = 10;
    const OLYMPIC_TOTALS = 11;
    const OLYMPIC_AVERAGES = 12;

    /**
     * Get a human-readable description of the page type
     */
    public static function getDescription($spec): string
    {
        switch ($spec) {
            case self::GAME_LOG:
                return "Game Log";
            case self::OVERVIEW:
                return "Player Overview";
            case self::AWARDS_AND_NEWS:
                return "Awards and News";
            case self::ONE_ON_ONE:
                return "One-on-One Results";
            case self::REGULAR_SEASON_TOTALS:
                return "Regular Season Totals";
            case self::REGULAR_SEASON_AVERAGES:
                return "Regular Season Averages";
            case self::PLAYOFF_TOTALS:
                return "Playoff Totals";
            case self::PLAYOFF_AVERAGES:
                return "Playoff Averages";
            case self::HEAT_TOTALS:
                return "H.E.A.T. Totals";
            case self::HEAT_AVERAGES:
                return "H.E.A.T. Averages";
            case self::RATINGS_AND_SALARY:
                return "Ratings and Salary History";
            case self::SIM_STATS:
                return "Season Sim Stats";
            case self::OLYMPIC_TOTALS:
                return "Olympic Totals";
            case self::OLYMPIC_AVERAGES:
                return "Olympic Averages";
            default:
                return "Unknown Page Type";
        }
    }

    /**
     * Get the URL for a specific page type
     */
    public static function getUrl($playerID, $spec): string
    {
        return "modules.php?name=Player&pa=showpage&pid=$playerID" . ($spec !== self::OVERVIEW ? "&spec=$spec" : "");
    }
}
