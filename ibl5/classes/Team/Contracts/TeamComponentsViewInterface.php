<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamComponentsViewInterface - Contract for team page sub-component rendering
 *
 * Renders individual team page sections: current season info, franchise history,
 * championship banners, draft picks, awards, and accomplishments.
 */
interface TeamComponentsViewInterface
{
    /**
     * Render championship, conference, and division banners
     *
     * @param object $team Team object with name, team_name, color1, color2, teamID
     * @return string HTML banner display
     */
    public function championshipBanners(object $team): string;

    /**
     * Render current season summary (record, standings, arena, etc.)
     *
     * @param object $team Team object with name, formerlyKnownAs, arena, capacity
     * @return string HTML info list
     */
    public function currentSeason(object $team): string;

    /**
     * Render the team's draft picks list
     *
     * @param \Team $team Legacy Team object (backslash-prefixed to avoid namespace collision)
     * @return string HTML draft picks list
     */
    public function draftPicks(\Team $team): string;

    /**
     * Render GM history awards
     *
     * @param object $team Team object with ownerName, name
     * @return string HTML awards list, or empty string if no history
     */
    public function gmHistory(object $team): string;

    /**
     * Render H.E.A.T. tournament history
     *
     * @param object $team Team object with name, teamID
     * @return string HTML history list with win/loss totals
     */
    public function resultsHEAT(object $team): string;

    /**
     * Render playoff history by round with series records
     *
     * @param object $team Team object with name
     * @return string HTML playoff results grouped by round
     */
    public function resultsPlayoffs(object $team): string;

    /**
     * Render regular season win/loss history
     *
     * @param object $team Team object with name, teamID
     * @return string HTML history list with win/loss totals
     */
    public function resultsRegularSeason(object $team): string;

    /**
     * Render team accomplishments (awards, honors)
     *
     * @param object $team Team object with name
     * @return string HTML awards list, or empty string if no accomplishments
     */
    public function teamAccomplishments(object $team): string;
}
