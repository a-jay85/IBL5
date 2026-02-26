<?php

declare(strict_types=1);

/**
 * UI - Main entry point for UI components
 *
 * This class delegates to specialized components in the UI namespace.
 * All methods are preserved for backward compatibility.
 *
 * @see UI\DebugOutput
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
     * Generate inline CSS custom property declarations for team-colored tables
     *
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @return string Inline style value for --team-color-primary and --team-color-secondary
     *
     * @see UI\TableStyles::inlineVars()
     */
    public static function teamColorVars(string $teamColor, string $teamColor2): string
    {
        return UI\TableStyles::inlineVars($teamColor, $teamColor2);
    }

    /**
     * Render the contracts table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, array<string, mixed>> $result Player result set
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function contracts($db, $result, $team, \Season $season, array $starterPids = []): string
    {
        return UI\Tables\Contracts::render($db, $result, $team, $season, $starterPids);
    }

    /**
     * Render the per-36-minute statistics table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $result Player result set
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function per36Minutes($db, $result, $team, $yr, array $starterPids = [], string $moduleName = ''): string
    {
        return UI\Tables\Per36Minutes::render($db, $result, $team, (string)$yr, $starterPids, $moduleName);
    }

    /**
     * Render the ratings table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $data Player data
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param \Season $season Season object
     * @param string $moduleName Module name for styling variations
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function ratings($db, $data, $team, $yr, $season, $moduleName = "", array $starterPids = []): string
    {
        return UI\Tables\Ratings::render($db, $data, $team, (string)$yr, $season, $moduleName, $starterPids);
    }

    /**
     * Render the season averages table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $result Player result set
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function seasonAverages($db, $result, $team, $yr, array $starterPids = [], string $moduleName = ''): string
    {
        return UI\Tables\SeasonAverages::render($db, $result, $team, (string)$yr, $starterPids, $moduleName);
    }

    /**
     * Render the season totals table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $result Player result set
     * @param \Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param list<int> $starterPids Starter player IDs
     * @param string $moduleName Module name
     * @return string HTML table
     */
    public static function seasonTotals($db, $result, $team, $yr, array $starterPids = [], string $moduleName = ''): string
    {
        return UI\Tables\SeasonTotals::render($db, $result, $team, (string)$yr, $starterPids, $moduleName);
    }

    /**
     * Render the period averages table
     *
     * @param \mysqli $db Database connection
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param string|null|\DateTime $startDate Start date for the period
     * @param string|null|\DateTime $endDate End date for the period
     * @param list<int> $starterPids Starter player IDs
     * @param list<int> $pidFilter When non-empty, only include these player PIDs
     * @return string HTML table
     */
    public static function periodAverages(\mysqli $db, $team, $season, $startDate = null, $endDate = null, array $starterPids = [], array $pidFilter = []): string
    {
        return UI\Tables\PeriodAverages::render($db, $team, $season, $startDate, $endDate, $starterPids, $pidFilter);
    }
}