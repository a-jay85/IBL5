<?php

declare(strict_types=1);

namespace RecordHolders;

use Player\PlayerImageHelper;
use UI\TeamCellHelper;
use Security\HtmlSanitizer;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * Renders the player-record sections (single-game, full-season, playoffs, H.E.A.T.).
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedPlayerRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedSeasonRecord from RecordHoldersServiceInterface
 */
final class PlayerRecordSectionRenderer
{
    public function __construct(private readonly RecordTableRenderer $tableRenderer)
    {
    }

    // ---------------------------------------------------------------
    // Section 1: Regular Season (Single Game)
    // ---------------------------------------------------------------

    /**
     * Render Section 1: Player Regular Season (Single Game) records.
     *
     * @param AllRecordsData $records
     */
    public function renderPlayerSingleGameRecords(array $records): string
    {
        $output = '<div class="ibl-card">';
        $output .= '<div class="ibl-card__header"><h2 class="ibl-card__title">Player, Regular Season (Single Game)</h2></div>';
        $output .= '<div class="ibl-card__body">';

        foreach ($records['playerSingleGame']['regularSeason'] as $category => $categoryRecords) {
            $output .= $this->renderPlayerCategoryBlock($category, $categoryRecords);
        }

        // Quadruple Doubles
        $output .= $this->renderPlayerCategoryBlock('Quadruple Doubles', $records['quadrupleDoubles'], true);

        // Most All-Star Appearances
        $output .= $this->renderAllStarBlock($records['allStarRecord']);

        $output .= '</div></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 2: Regular Season (Full Season)
    // ---------------------------------------------------------------

    /**
     * Render Section 2: Player Regular Season (Full Season) records.
     *
     * @param array<string, list<FormattedSeasonRecord>> $seasonRecords
     */
    public function renderPlayerFullSeasonRecords(array $seasonRecords): string
    {
        $output = '<div class="ibl-card">';
        $output .= '<div class="ibl-card__header"><h2 class="ibl-card__title">Player, Regular Season (Full Season) [minimum 50 games]</h2></div>';
        $output .= '<div class="ibl-card__body">';

        foreach ($seasonRecords as $category => $categoryRecords) {
            $output .= $this->renderSeasonCategoryBlock($category, $categoryRecords);
        }

        $output .= '</div></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 3: Playoffs
    // ---------------------------------------------------------------

    /**
     * Render Section 3: Player Playoff records.
     *
     * @param AllRecordsData $records
     */
    public function renderPlayerPlayoffRecords(array $records): string
    {
        $output = '<div class="ibl-card">';
        $output .= '<div class="ibl-card__header"><h2 class="ibl-card__title">Player, Playoffs</h2></div>';
        $output .= '<div class="ibl-card__body">';

        foreach ($records['playerSingleGame']['playoffs'] as $category => $categoryRecords) {
            $output .= $this->renderPlayerCategoryBlock($category, $categoryRecords);
        }

        $output .= '</div></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 4: H.E.A.T.
    // ---------------------------------------------------------------

    /**
     * Render Section 4: Player H.E.A.T. records.
     *
     * @param AllRecordsData $records
     */
    public function renderPlayerHeatRecords(array $records): string
    {
        $output = '<div class="ibl-card">';
        $output .= '<div class="ibl-card__header"><h2 class="ibl-card__title">Player, H.E.A.T.</h2></div>';
        $output .= '<div class="ibl-card__body">';

        foreach ($records['playerSingleGame']['heat'] as $category => $categoryRecords) {
            $output .= $this->renderPlayerCategoryBlock($category, $categoryRecords);
        }

        $output .= '</div></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Per-category block renderers
    // ---------------------------------------------------------------

    /**
     * Render a player single-game category block (heading + mini-table).
     *
     * @param list<FormattedPlayerRecord> $categoryRecords
     */
    private function renderPlayerCategoryBlock(string $category, array $categoryRecords, bool $multiLineAmount = false): string
    {
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($this->tableRenderer->getStatColumnLabel($category));

        $rows = '';
        foreach ($categoryRecords as $record) {
            $rows .= $this->renderPlayerRecordRow($record, $multiLineAmount);
        }

        return $this->tableRenderer->renderCategoryTable(
            $category,
            'record-table--5col',
            '<col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount">',
            '<th>Player</th><th>Team</th><th>Date</th><th>Opponent</th><th>' . $safeStatLabel . '</th>',
            $rows
        );
    }

    /**
     * Render a full-season category block (heading + mini-table).
     *
     * @param list<FormattedSeasonRecord> $categoryRecords
     */
    private function renderSeasonCategoryBlock(string $category, array $categoryRecords): string
    {
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($this->tableRenderer->getStatColumnLabel($category));

        $rows = '';
        foreach ($categoryRecords as $record) {
            $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
            $safeSeason = HtmlSanitizer::safeHtmlOutput($record['season']);
            $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
            $pid = $record['pid'];
            $teamTid = $record['teamTid'];
            $teamYr = (int) $record['teamYr'];

            $seasonLink = '<a href="' . TeamCellHelper::teamPageUrl($teamTid, $teamYr) . '">' . $safeSeason . '</a>';

            $rows .= '<tr>';
            $rows .= PlayerImageHelper::renderLargePlayerCell($pid, $record['name']);
            $rows .= '<td><a href="modules.php?name=Team&amp;op=team&amp;teamid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
            $rows .= '<td>' . $seasonLink . '</td>';
            $rows .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $rows .= '</tr>';
        }

        return $this->tableRenderer->renderCategoryTable(
            $category,
            'record-table--4col-season',
            '<col class="col-player"><col class="col-team"><col class="col-season"><col class="col-amount">',
            '<th>Player</th><th>Team</th><th>Season</th><th>' . $safeStatLabel . '</th>',
            $rows
        );
    }

    // ---------------------------------------------------------------
    // Special blocks
    // ---------------------------------------------------------------

    /**
     * Render the All-Star Appearances block.
     *
     * @param array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string} $allStar
     */
    private function renderAllStarBlock(array $allStar): string
    {
        $output = '<div class="record-category">';
        $output .= $this->tableRenderer->renderCategoryHeading('Most All-Star Appearances');
        $output .= '<table class="ibl-data-table record-table ibl-table-subheading record-table--5col" data-no-responsive>';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-amount"><col class="col-date" span="2"></colgroup>';
        $output .= '<thead><tr><th>Player</th><th>Team</th><th>Apps</th><th colspan="2">Years</th></tr></thead>';
        $output .= '<tbody>';

        $pid = $allStar['pid'];
        $amount = (int) $allStar['amount'];

        $teamLogos = '';
        if ($allStar['teams'] !== '') {
            $teams = explode(',', $allStar['teams']);
            $teamTids = explode(',', $allStar['teamTids']);
            foreach ($teams as $i => $team) {
                $safeTid = (int) ($teamTids[$i] ?? 0);
                $safeTeam = HtmlSanitizer::safeHtmlOutput($team);
                $teamLogos .= '<a href="modules.php?name=Team&amp;op=team&amp;teamid=' . $safeTid . '">'
                    . '<img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '">'
                    . '</a> ';
            }
        }

        $safeYears = HtmlSanitizer::safeHtmlOutput($allStar['years']);
        $years = $allStar['years'] !== '' ? str_replace(', ', '<br>', $safeYears) : '';

        $output .= '<tr>';
        if ($pid !== null) {
            $output .= PlayerImageHelper::renderLargePlayerCell($pid, $allStar['name']);
        } else {
            $output .= '<td class="player-cell"></td>';
        }
        $output .= '<td>' . $teamLogos . '</td>';
        $output .= '<td class="ibl-stat-highlight">' . $amount . '</td>';
        $output .= '<td colspan="2">' . $years . '</td>';
        $output .= '</tr>';

        $output .= '</tbody></table></div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Shared rendering helpers
    // ---------------------------------------------------------------

    /**
     * Render a single player record row.
     *
     * @param FormattedPlayerRecord $record
     */
    private function renderPlayerRecordRow(array $record, bool $multiLineAmount = false): string
    {
        $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
        $safeDate = HtmlSanitizer::safeHtmlOutput($record['dateDisplay']);
        $safeDate = str_replace("\n", '<br>', $safeDate);
        $safeOppTeam = HtmlSanitizer::safeHtmlOutput($record['oppAbbr']);
        $pid = $record['pid'];
        $teamTid = $record['teamTid'];
        $teamYr = (int) $record['teamYr'];
        $oppTid = $record['oppTid'];
        $oppYr = (int) $record['oppYr'];

        $safeAmountRaw = HtmlSanitizer::safeHtmlOutput($record['amount']);
        $amount = $multiLineAmount
            ? str_replace("\n", '<br>', $safeAmountRaw)
            : $safeAmountRaw;

        $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($record['boxScoreUrl']);
        $dateCell = $record['boxScoreUrl'] !== ''
            ? '<a href="' . $safeBoxScoreUrl . '">' . $safeDate . '</a>'
            : $safeDate;

        $output = '<tr>';
        $output .= PlayerImageHelper::renderLargePlayerCell($pid, $record['name']);
        $output .= '<td><a href="modules.php?name=Team&amp;op=team&amp;teamid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
        $output .= '<td>' . $dateCell . '</td>';
        $output .= '<td><a href="modules.php?name=Team&amp;op=team&amp;teamid=' . $oppTid . '&amp;yr=' . $oppYr . '"><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></a></td>';
        $output .= '<td class="ibl-stat-highlight">' . $amount . '</td>';
        $output .= '</tr>';

        return $output;
    }
}
