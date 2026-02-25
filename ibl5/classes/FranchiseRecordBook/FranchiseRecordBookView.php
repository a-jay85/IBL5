<?php

declare(strict_types=1);

namespace FranchiseRecordBook;

use FranchiseRecordBook\Contracts\FranchiseRecordBookRepositoryInterface;
use FranchiseRecordBook\Contracts\FranchiseRecordBookServiceInterface;
use BasketballStats\StatsFormatter;
use Player\PlayerImageHelper;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * View for rendering franchise record book pages.
 *
 * @phpstan-import-type AlltimeRecord from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type TeamInfo from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type RecordsByCategory from FranchiseRecordBookServiceInterface
 * @phpstan-import-type RecordBookData from FranchiseRecordBookServiceInterface
 */
class FranchiseRecordBookView
{
    /**
     * Stats that display as percentages (e.g., 0.523 → ".523").
     *
     * @var list<string>
     */
    private const PERCENTAGE_STATS = ['fg_pct', 'ft_pct', 'three_pct'];

    /**
     * Team info lookup by teamid, built once per render.
     *
     * @var array<int, TeamInfo>
     */
    private array $teamLookup = [];

    /**
     * Whether we're viewing a specific team (vs league-wide).
     */
    private bool $isTeamView = false;

    /**
     * Render the complete record book page.
     *
     * @param RecordBookData $data
     */
    public function render(array $data): string
    {
        // Build team lookup for colored team cells
        $this->teamLookup = [];
        foreach ($data['teams'] as $team) {
            $this->teamLookup[(int) $team['teamid']] = $team;
        }
        $this->isTeamView = $data['team'] !== null;

        $html = $this->renderTitle($data);
        $html .= $this->renderTeamSelector($data['teams'], $data['team']);

        if ($data['singleSeason'] !== []) {
            $html .= '<h3 class="ibl-title record-book-section-title">Single-Season Records</h3>';
            $html .= $this->renderRecordSection($data['singleSeason'], 'single_season');
        }

        if ($data['career'] !== [] && !$this->isTeamView) {
            $html .= '<h3 class="ibl-title record-book-section-title">Career Records</h3>';
            $html .= $this->renderRecordSection($data['career'], 'career');
        }

        return $html;
    }

    /**
     * Render the page title and optional team logo banner.
     *
     * @param RecordBookData $data
     */
    private function renderTitle(array $data): string
    {
        if ($data['team'] !== null) {
            $teamId = (int) $data['team']['teamid'];
            /** @var string $teamName */
            $teamName = HtmlSanitizer::safeHtmlOutput($data['team']['team_name']);
            return '<h2 class="ibl-title">' . $teamName . ' Franchise Record Book</h2>'
                . '<img src="images/logo/' . $teamId . '.jpg" alt="" class="team-logo-banner">';
        }
        return '<h2 class="ibl-title">League-Wide Record Book</h2>';
    }

    /**
     * Render the team selector dropdown.
     *
     * @param list<TeamInfo> $teams
     * @param TeamInfo|null $selectedTeam
     */
    private function renderTeamSelector(array $teams, ?array $selectedTeam): string
    {
        $selectedId = $selectedTeam !== null ? (int) $selectedTeam['teamid'] : 0;

        ob_start();
        ?>
<form method="get" class="record-book-team-selector">
    <input type="hidden" name="name" value="FranchiseRecordBook">
    <label for="record-book-team">Select Team:</label>
    <select name="teamid" id="record-book-team" onchange="this.form.submit()">
        <option value="0"<?= $selectedId === 0 ? ' selected' : '' ?>>League-Wide</option>
        <?php foreach ($teams as $team): ?>
        <?php
            /** @var string $safeTeamName */
            $safeTeamName = HtmlSanitizer::safeHtmlOutput($team['team_name']);
        ?>
        <option value="<?= (int) $team['teamid'] ?>"<?= (int) $team['teamid'] === $selectedId ? ' selected' : '' ?>>
            <?= $safeTeamName ?>
        </option>
        <?php endforeach; ?>
    </select>
</form>
        <?php
        $result = ob_get_clean();
        return $result !== false ? $result : '';
    }

    /**
     * Render a section of records (single-season or career) in a grid.
     *
     * Team single-season tables omit the Team column, so they're narrower
     * and fit 4 across on desktop.
     *
     * @param RecordsByCategory $recordsByCategory
     * @param string $recordType 'single_season' or 'career'
     */
    private function renderRecordSection(array $recordsByCategory, string $recordType): string
    {
        $gridClass = ($this->isTeamView && $recordType === 'single_season')
            ? 'ibl-grid ibl-grid--4col'
            : 'ibl-grid ibl-grid--3col';

        $html = '<div class="' . $gridClass . '">';
        foreach ($recordsByCategory as $category => $records) {
            if ($records === []) {
                continue;
            }
            $html .= $this->renderCategoryTable($category, $records, $recordType);
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Render a table for a single stat category.
     *
     * Uses SeasonHighs-style layout: stat name as spanning header, player thumbnails,
     * colored team cells, and linked season years. When viewing a specific team's
     * single-season records, the Team column is omitted.
     *
     * @param string $category Stat category key
     * @param list<AlltimeRecord> $records
     * @param string $recordType 'single_season' or 'career'
     */
    private function renderCategoryTable(string $category, array $records, string $recordType): string
    {
        $label = FranchiseRecordBookService::STAT_LABELS[$category] ?? $category;
        $isPercentage = in_array($category, self::PERCENTAGE_STATS, true);
        $showTeamColumn = !($this->isTeamView && $recordType === 'single_season');

        // Column count: rank + player + stat = 3
        $colCount = 3;
        if ($recordType === 'single_season') {
            $colCount++; // + season
            if ($showTeamColumn) {
                $colCount++; // + team
            }
        } elseif ($recordType === 'career') {
            if (!$isPercentage) {
                $colCount++; // + total
            }
            $colCount++; // + team (always shown for career)
        }

        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);

        $html = '<div class="stat-table-wrapper">';
        $html .= '<table class="ibl-data-table stat-table">';
        $html .= '<thead><tr><th colspan="' . $colCount . '">' . $safeLabel . '</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($records as $record) {
            $teamOfRecord = (int) ($record['team_of_record'] ?? 0);

            $html .= '<tr>';
            $html .= '<td class="rank-cell">' . (int) $record['ranking'] . '</td>';
            $html .= $this->renderPlayerNameCell($record['player_name'], $record['pid']);
            $html .= '<td class="ibl-stat-highlight">' . $this->formatStatValue($record, $isPercentage) . '</td>';

            if ($recordType === 'single_season') {
                $html .= '<td>' . $this->renderSeasonCell($record) . '</td>';
            }

            if ($recordType === 'career' && !$isPercentage) {
                $html .= '<td>' . ($record['career_total'] !== null ? StatsFormatter::formatTotal($record['career_total']) : '') . '</td>';
            }

            if ($showTeamColumn) {
                $html .= $this->renderTeamOfRecordCell($teamOfRecord, $recordType);
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    /**
     * Render a player name cell with thumbnail and link when pid is available.
     */
    private function renderPlayerNameCell(string $playerName, ?int $pid): string
    {
        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($playerName);

        if ($pid !== null && $pid > 0) {
            $thumbnail = PlayerImageHelper::renderThumbnail($pid);
            return '<td class="name-cell"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $thumbnail . $safeName . '</a></td>';
        }

        return '<td class="name-cell">' . $safeName . '</td>';
    }

    /**
     * Render a colored team cell with logo using TeamCellHelper.
     */
    private function renderTeamOfRecordCell(int $teamId, string $recordType): string
    {
        if ($teamId <= 0 || !isset($this->teamLookup[$teamId])) {
            if ($recordType === 'career') {
                return '<td>Retired</td>';
            }
            return '<td></td>';
        }

        $team = $this->teamLookup[$teamId];
        return TeamCellHelper::renderTeamCell(
            (int) $team['teamid'],
            $team['team_name'],
            $team['color1'],
            $team['color2'],
        );
    }

    /**
     * Render a season year cell, linked to the historical team page when possible.
     *
     * @param AlltimeRecord $record
     */
    private function renderSeasonCell(array $record): string
    {
        $seasonYear = $record['season_year'] !== null ? (int) $record['season_year'] : null;
        $teamOfRecord = (int) ($record['team_of_record'] ?? 0);

        if ($seasonYear === null) {
            return '';
        }

        if ($teamOfRecord > 0) {
            return '<a href="' . TeamCellHelper::teamPageUrl($teamOfRecord, $seasonYear) . '">' . $seasonYear . '</a>';
        }

        return (string) $seasonYear;
    }

    /**
     * Format a stat value for display.
     *
     * @param AlltimeRecord $record
     */
    private function formatStatValue(array $record, bool $isPercentage): string
    {
        /** @var string $statValue */
        $statValue = $record['stat_value'];
        $value = (float) $statValue;

        if ($isPercentage) {
            return StatsFormatter::formatWithDecimals($value, 3);
        }
        return StatsFormatter::formatAverage($value);
    }
}
