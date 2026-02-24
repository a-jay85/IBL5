<?php

declare(strict_types=1);

namespace Standings;

use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;
use UI\TeamCellHelper;

/**
 * StandingsView - HTML rendering for team standings
 *
 * Generates sortable HTML tables for conference and division standings.
 * Handles clinched indicators (X/Y/Z) and team streak display.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 *
 * @see StandingsViewInterface For the interface contract
 * @see StandingsRepository For data access
 */
class StandingsView implements StandingsViewInterface
{
    private StandingsRepositoryInterface $repository;
    private int $seasonYear;

    /** @var array<int, StreakRow>|null Pre-loaded streak data keyed by team ID */
    private ?array $allStreakData = null;

    /** @var array<int, PythagoreanStats>|null Pre-loaded Pythagorean stats keyed by team ID */
    private ?array $allPythagoreanStats = null;

    /**
     * Constructor
     *
     * @param StandingsRepositoryInterface $repository Standings data repository
     * @param int $seasonYear Season ending year (e.g. 2025 for the 2024-25 season)
     */
    public function __construct(StandingsRepositoryInterface $repository, int $seasonYear)
    {
        $this->repository = $repository;
        $this->seasonYear = $seasonYear;
    }

    /**
     * @see StandingsViewInterface::render()
     */
    public function render(): string
    {
        // Pre-load all streak and Pythagorean data in 2 queries instead of per-team
        $this->allStreakData = $this->repository->getAllStreakData();
        $this->allPythagoreanStats = $this->repository->getAllPythagoreanStats($this->seasonYear);

        $html = '';

        // Conference standings
        $html .= $this->renderRegion('Eastern');
        $html .= $this->renderRegion('Western');

        // Division standings
        $html .= $this->renderRegion('Atlantic');
        $html .= $this->renderRegion('Central');
        $html .= $this->renderRegion('Midwest');
        $html .= $this->renderRegion('Pacific');

        // Clear pre-loaded data
        $this->allStreakData = null;
        $this->allPythagoreanStats = null;

        return $html;
    }

    /**
     * @see StandingsViewInterface::renderRegion()
     */
    public function renderRegion(string $region): string
    {
        // If called standalone (not via render()), load data on demand
        if ($this->allStreakData === null) {
            $this->allStreakData = $this->repository->getAllStreakData();
        }
        if ($this->allPythagoreanStats === null) {
            $this->allPythagoreanStats = $this->repository->getAllPythagoreanStats($this->seasonYear);
        }

        $groupingType = $this->getGroupingType($region);
        $standings = $this->repository->getStandingsByRegion($region);

        $html = $this->renderHeader($region, $groupingType);
        $html .= $this->renderRows($standings);
        $html .= '</tbody></table></div></div>'; // Close table, scroll container, and wrapper

        return $html;
    }

    /**
     * Get the grouping type (Conference or Division) for a region
     *
     * @param string $region Region name
     * @return string 'Conference' or 'Division'
     */
    private function getGroupingType(string $region): string
    {
        if (in_array($region, \League::CONFERENCE_NAMES, true)) {
            return 'Conference';
        }

        return 'Division';
    }

    /**
     * Render the table header for a standings section
     *
     * @param string $region Region name
     * @param string $groupingType 'Conference' or 'Division'
     * @return string HTML for table header
     */
    private function renderHeader(string $region, string $groupingType): string
    {
        $safeRegion = \Utilities\HtmlSanitizer::safeHtmlOutput($region);
        $title = $safeRegion . ' ' . $groupingType;

        ob_start();
        ?>
        <h2 class="ibl-title"><?= $title; ?></h2>
        <div class="table-scroll-wrapper">
        <div class="table-scroll-container">
        <table class="sortable ibl-data-table">
            <thead>
                <tr>
                    <th class="sticky-col">Team</th>
                    <th>W-L</th>
                    <th>Win%</th>
                    <th>Pyth<br>W-L%</th>
                    <th>GB</th>
                    <th>Magic<br>#</th>
                    <th>Games<br>Left</th>
                    <th>Conf.</th>
                    <th>Div.</th>
                    <th>Home</th>
                    <th>Away</th>
                    <th>Home<br>Played</th>
                    <th>Away<br>Played</th>
                    <th>Last 10<br>W-L</th>
                    <th>Streak</th>
                    <th>Power<br>Rank</th>
                    <th>SOS</th>
                    <th>Rem.<br>SOS</th>
                </tr>
            </thead>
            <tbody>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render all team rows for a standings table
     *
     * @param list<StandingsRow> $standings Array of team standings data
     * @return string HTML for all team rows
     */
    private function renderRows(array $standings): string
    {
        $html = '';

        foreach ($standings as $team) {
            $html .= $this->renderTeamRow($team);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param StandingsRow $team Team standings data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = $team['tid'];
        $teamName = $this->formatTeamName($team);
        $streakData = $this->allStreakData[$teamId] ?? null;

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        $streakType = \Utilities\HtmlSanitizer::safeHtmlOutput($streakData['streak_type'] ?? '');
        $streak = $streakData['streak'] ?? 0;
        $streakSortKey = ($streakData['streak_type'] ?? '') === 'W' ? $streak : -$streak;
        $rating = $streakData['ranking'] ?? 0;
        $sos = $streakData['sos'] ?? 0;
        $remainingSos = $streakData['remaining_sos'] ?? 0;

        // Get Pythagorean win percentage from pre-loaded data
        $pythagoreanStats = $this->allPythagoreanStats[$teamId] ?? null;
        $pythagoreanPct = '0.000';
        if ($pythagoreanStats !== null) {
            $pythagoreanPct = \BasketballStats\StatsFormatter::calculatePythagoreanWinPercentage(
                $pythagoreanStats['pointsScored'],
                $pythagoreanStats['pointsAllowed']
            );
        }

        $leagueRecord = $team['leagueRecord'];
        $pct = $team['pct'];
        $gamesBack = $team['gamesBack'];
        $magicNumber = $team['magicNumber'];
        $gamesUnplayed = $team['gamesUnplayed'];
        $confRecord = $team['confRecord'];
        $divRecord = $team['divRecord'];
        $homeRecord = $team['homeRecord'];
        $awayRecord = $team['awayRecord'];
        $homeGames = $team['homeGames'];
        $awayGames = $team['awayGames'];

        ob_start();
        ?>
        <tr data-team-id="<?= $teamId; ?>">
            <?= TeamCellHelper::renderTeamCell($teamId, $team['team_name'], $team['color1'], $team['color2'], 'sticky-col', '', $teamName) ?>
            <td><?= $leagueRecord; ?></td>
            <td><?= $pct; ?></td>
            <td><?= $pythagoreanPct; ?></td>
            <td><?= $gamesBack; ?></td>
            <td><?= $magicNumber; ?></td>
            <td><?= $gamesUnplayed; ?></td>
            <td><?= $confRecord; ?></td>
            <td><?= $divRecord; ?></td>
            <td><?= $homeRecord; ?></td>
            <td><?= $awayRecord; ?></td>
            <td><?= $homeGames; ?></td>
            <td><?= $awayGames; ?></td>
            <td><?= $lastWin; ?>-<?= $lastLoss; ?></td>
            <td sorttable_customkey="<?= $streakSortKey; ?>"><?= $streakType; ?> <?= $streak; ?></td>
            <td><span class="ibl-stat-highlight"><?= $rating; ?></span></td>
            <td><?= number_format((float)$sos, 3); ?></td>
            <td><?= number_format((float)$remainingSos, 3); ?></td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Format team name with clinched indicator
     *
     * @param StandingsRow $team Team standings data
     * @return string Formatted team name with clinched prefix if applicable
     */
    private function formatTeamName(array $team): string
    {
        $teamName = \Utilities\HtmlSanitizer::safeHtmlOutput($team['team_name']);

        if ($team['clinchedConference'] === 1) {
            return '<span class="ibl-clinched-indicator">Z</span>-' . $teamName;
        }

        if ($team['clinchedDivision'] === 1) {
            return '<span class="ibl-clinched-indicator">Y</span>-' . $teamName;
        }

        if ($team['clinchedPlayoffs'] === 1) {
            return '<span class="ibl-clinched-indicator">X</span>-' . $teamName;
        }

        return $teamName;
    }
}
