<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersViewInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;
use Player\PlayerImageHelper;
use UI\TeamCellHelper;
use Security\HtmlSanitizer;

/**
 * View class for rendering the all-time IBL record holders page.
 *
 * Receives structured data from RecordHoldersService and renders HTML tables.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedPlayerRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamGameRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedFranchiseRecord from RecordHoldersServiceInterface
 *
 * @see RecordHoldersViewInterface
 */
class RecordHoldersView implements RecordHoldersViewInterface
{
    private RecordTableRenderer $tableRenderer;
    private PlayerRecordSectionRenderer $playerSection;

    public function __construct()
    {
        $this->tableRenderer = new RecordTableRenderer();
        $this->playerSection = new PlayerRecordSectionRenderer($this->tableRenderer);
    }

    /**
     * @see RecordHoldersViewInterface::render()
     *
     * @param AllRecordsData $records
     */
    public function render(array $records): string
    {
        $output = '<h1 class="ibl-title">Record Holders</h1>';
        $output .= '<div class="record-section">';
        $output .= $this->playerSection->renderPlayerSingleGameRecords($records);
        $output .= $this->playerSection->renderPlayerFullSeasonRecords($records['playerFullSeason']);
        $output .= $this->playerSection->renderPlayerPlayoffRecords($records);
        $output .= $this->playerSection->renderPlayerHeatRecords($records);
        $output .= $this->renderTeamRecords($records);
        $output .= '</div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 5: Team Records
    // ---------------------------------------------------------------

    /**
     * Render Section 5: Team records.
     *
     * @param AllRecordsData $records
     */
    private function renderTeamRecords(array $records): string
    {
        $output = '<div class="ibl-card">';
        $output .= '<div class="ibl-card__header"><h2 class="ibl-card__title">Team Records</h2></div>';
        $output .= '<div class="ibl-card__body">';

        // Game records subsection
        if ($records['teamGameRecords'] !== []) {
            $output .= '<h3 class="record-section__subheading">Game Records</h3>';
            foreach ($records['teamGameRecords'] as $category => $categoryRecords) {
                $output .= $this->renderTeamGameCategoryBlock($category, $categoryRecords);
            }
        }

        // Season records subsection
        if ($records['teamSeasonRecords'] !== []) {
            $output .= '<h3 class="record-section__subheading">Season Records</h3>';
            foreach ($records['teamSeasonRecords'] as $category => $categoryRecords) {
                $output .= $this->renderTeamSeasonCategoryBlock($category, $categoryRecords);
            }
        }

        // Franchise records subsection
        if ($records['teamFranchise'] !== []) {
            $output .= '<h3 class="record-section__subheading">Franchise Records</h3>';
            foreach ($records['teamFranchise'] as $category => $categoryRecords) {
                $output .= $this->renderFranchiseCategoryBlock($category, $categoryRecords);
            }
        }

        $output .= '</div></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Per-category block renderers
    // ---------------------------------------------------------------

    /**
     * Render a team game category block (heading + mini-table).
     *
     * @param list<FormattedTeamGameRecord> $categoryRecords
     */
    private function renderTeamGameCategoryBlock(string $category, array $categoryRecords): string
    {
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($this->tableRenderer->getStatColumnLabel($category));

        $rows = '';
        foreach ($categoryRecords as $record) {
            $rows .= $this->renderTeamGameRow($record);
        }

        return $this->tableRenderer->renderCategoryTable(
            $category,
            'record-table--4col-team',
            '<col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount">',
            '<th>Team</th><th>Date</th><th>Opponent</th><th>' . $safeStatLabel . '</th>',
            $rows
        );
    }

    /**
     * Render a team season category block (heading + mini-table).
     *
     * @param list<FormattedTeamSeasonRecord> $categoryRecords
     */
    private function renderTeamSeasonCategoryBlock(string $category, array $categoryRecords): string
    {
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($this->tableRenderer->getStatColumnLabel($category));

        $rows = '';
        foreach ($categoryRecords as $record) {
            $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
            $safeSeason = HtmlSanitizer::safeHtmlOutput($record['season']);
            $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
            $teamTid = $record['teamTid'];
            $teamYr = (int) $record['teamYr'];

            $seasonLink = '<a href="' . TeamCellHelper::teamPageUrl($teamTid, $teamYr) . '">' . $safeSeason . '</a>';

            $rows .= '<tr>';
            $teamLabel = $safeTeam !== '' ? $safeTeam : 'Team';
            $rows .= '<td><a href="' . TeamCellHelper::teamPageUrl($teamTid, $teamYr) . '" aria-label="' . $teamLabel . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
            $rows .= '<td>' . $seasonLink . '</td>';
            $rows .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $rows .= '</tr>';
        }

        return $this->tableRenderer->renderCategoryTable(
            $category,
            'record-table--3col-team-season',
            '<col class="col-team"><col class="col-season"><col class="col-amount">',
            '<th>Team</th><th>Season</th><th>' . $safeStatLabel . '</th>',
            $rows
        );
    }

    /**
     * Render a franchise category block (heading + mini-table).
     *
     * @param list<FormattedFranchiseRecord> $categoryRecords
     */
    private function renderFranchiseCategoryBlock(string $category, array $categoryRecords): string
    {
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($this->tableRenderer->getStatColumnLabel($category));

        $rows = '';
        foreach ($categoryRecords as $record) {
            $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
            $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
            $teamTid = $record['teamTid'];

            // Link each year to the team's history page for that season
            $yearsLinked = $this->renderFranchiseYearLinks($record['years'], $teamTid);

            $rows .= '<tr>';
            $rows .= '<td><a href="' . TeamCellHelper::teamPageUrl($teamTid) . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
            $rows .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $rows .= '<td>' . $yearsLinked . '</td>';
            $rows .= '</tr>';
        }

        return $this->tableRenderer->renderCategoryTable(
            $category,
            'record-table--3col-franchise',
            '<col class="col-team"><col class="col-amount"><col class="col-years">',
            '<th>Team</th><th>' . $safeStatLabel . '</th><th>Years</th>',
            $rows
        );
    }

    // ---------------------------------------------------------------
    // Shared rendering helpers
    // ---------------------------------------------------------------

    /**
     * Render franchise years as individual links to team history pages.
     *
     * Splits "1996, 1998, 2001" into linked years separated by ", ".
     */
    private function renderFranchiseYearLinks(string $years, int $teamTid): string
    {
        if ($years === '') {
            return '';
        }

        $yearList = explode(', ', $years);
        $linked = [];
        foreach ($yearList as $year) {
            $safeYear = HtmlSanitizer::safeHtmlOutput(trim($year));
            $yearInt = (int) trim($year);
            if ($yearInt > 0) {
                $linked[] = '<a href="' . TeamCellHelper::teamPageUrl($teamTid, $yearInt) . '">' . $safeYear . '</a>';
            } else {
                $linked[] = $safeYear;
            }
        }
        return implode(', ', $linked);
    }

    /**
     * Render a team game record row.
     *
     * @param FormattedTeamGameRecord $record
     */
    private function renderTeamGameRow(array $record): string
    {
        $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
        $safeDate = HtmlSanitizer::safeHtmlOutput($record['dateDisplay']);
        $safeOppTeam = HtmlSanitizer::safeHtmlOutput($record['oppAbbr']);
        $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);

        $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($record['boxScoreUrl']);
        $dateCell = $record['boxScoreUrl'] !== ''
            ? '<a href="' . $safeBoxScoreUrl . '">' . $safeDate . '</a>'
            : $safeDate;

        $teamTid = $record['teamTid'];
        $teamYr = (int) $record['teamYr'];
        $oppTid = $record['oppTid'];
        $oppYr = (int) $record['oppYr'];

        $output = '<tr>';
        $output .= '<td><a href="' . TeamCellHelper::teamPageUrl($teamTid, $teamYr) . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
        $output .= '<td>' . $dateCell . '</td>';
        $output .= '<td><a href="' . TeamCellHelper::teamPageUrl($oppTid, $oppYr) . '"><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></a></td>';
        $output .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
        $output .= '</tr>';

        return $output;
    }
}
