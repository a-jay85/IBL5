<?php

/**
 * UI - Main entry point for UI components
 *
 * This class delegates to specialized components in the UI namespace.
 * All methods are preserved for backward compatibility.
 *
 * @see UI\DebugOutput
 * @see UI\TopMenu
 * @see UI\PlayerMenu
 * @see UI\TableStyles
 * @see UI\Tables\Contracts
 * @see UI\Tables\Per36Minutes
 * @see UI\Tables\Ratings
 * @see UI\Tables\SeasonAverages
 * @see UI\Tables\SeasonTotals
 * @see UI\Tables\PeriodAverages
 */
class UI
{
    /**
     * Display debug output in a collapsible panel
     *
     * @param string $content The content to display
     * @param string $title The title of the debug panel
     * @return void
     */
    public static function displayDebugOutput($content, $title = 'Debug Output'): void
    {
        UI\DebugOutput::display($content, $title);
    }

    /**
     * Display the top menu with team navigation
     *
     * @param object $db Database connection
     * @param int $teamID Current team ID (defaults to Free Agents)
     * @return void
     */
    public static function displaytopmenu($db, $teamID = League::FREE_AGENTS_TEAMID): void
    {
        UI\TopMenu::display($db, $teamID);
    }

    /**
     * Display the player menu with navigation links
     *
     * @return void
     */
    public static function playerMenu(): void
    {
        UI\PlayerMenu::display();
    }

    /**
     * Generate CSS styles for tables with team-colored separators
     *
     * @param string $tableClass CSS class name for the table
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @return string CSS style block
     */
    public static function tableStyles(string $tableClass, string $teamColor, string $teamColor2): string
    {
        return UI\TableStyles::render($tableClass, $teamColor, $teamColor2);
    }

    /**
     * Render the contracts table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param object $sharedFunctions Shared functions object
     * @return string HTML table
     */
    public static function contracts($db, $result, $team, $sharedFunctions): string
    {
        return UI\Tables\Contracts::render($db, $result, $team, $sharedFunctions);
    }

    /**
     * Render the per-36-minute statistics table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @return string HTML table
     */
    public static function per36Minutes($db, $result, $team, $yr): string
    {
        return UI\Tables\Per36Minutes::render($db, $result, $team, (string)$yr);
    }

    /**
     * Render the ratings table
     *
     * @param object $db Database connection
     * @param iterable $data Player data
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param object $season Season object
     * @param string $moduleName Module name for styling variations
     * @return string HTML table
     */
    public static function ratings($db, $data, $team, $yr, $season, $moduleName = ""): string
    {
        return UI\Tables\Ratings::render($db, $data, $team, (string)$yr, $season, $moduleName);
    }

    /**
     * Render the season averages table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @return string HTML table
     */
    public static function seasonAverages($db, $result, $team, $yr): string
    {
        return UI\Tables\SeasonAverages::render($db, $result, $team, (string)$yr);
    }

    /**
     * Render the season totals table
     *
     * @param object $db Database connection
     * @param iterable $result Player result set
     * @param object $team Team object
     * @param string $yr Year filter (empty for current season)
     * @return string HTML table
     */
    public static function seasonTotals($db, $result, $team, $yr): string
    {
        return UI\Tables\SeasonTotals::render($db, $result, $team, (string)$yr);
    }

    /**
     * Render the period averages table
     *
     * @param object $db Database connection
     * @param object $team Team object
     * @param object $season Season object
     * @param string|null|\DateTime $startDate Start date for the period
     * @param string|null|\DateTime $endDate End date for the period
     * @return string HTML table
     */
    public static function periodAverages($db, $team, $season, $startDate = null, $endDate = null): string
    {
        return UI\Tables\PeriodAverages::render($db, $team, $season, $startDate, $endDate);
    }
}