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

    /**
     * Constructor
     *
     * @param StandingsRepositoryInterface $repository Standings data repository
     */
    public function __construct(StandingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see StandingsViewInterface::render()
     */
    public function render(): string
    {
        $html = '';

        // Conference standings
        $html .= $this->renderRegion('Eastern');
        $html .= $this->renderRegion('Western');

        // Division standings
        $html .= $this->renderRegion('Atlantic');
        $html .= $this->renderRegion('Central');
        $html .= $this->renderRegion('Midwest');
        $html .= $this->renderRegion('Pacific');

        return $html;
    }

    /**
     * @see StandingsViewInterface::renderRegion()
     */
    public function renderRegion(string $region): string
    {
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
        /** @var string $safeRegion */
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
        $streakData = $this->repository->getTeamStreakData($teamId);

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        /** @var string $streakType */
        $streakType = \Utilities\HtmlSanitizer::safeHtmlOutput($streakData['streak_type'] ?? '');
        $streak = $streakData['streak'] ?? 0;
        $rating = $streakData['ranking'] ?? 0;

        // Get Pythagorean win percentage
        $pythagoreanStats = $this->repository->getTeamPythagoreanStats($teamId);
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
            <td><?= $streakType; ?> <?= $streak; ?></td>
            <td><span class="ibl-stat-highlight"><?= $rating; ?></span></td>
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
        /** @var string $teamName */
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
