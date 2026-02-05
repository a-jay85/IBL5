<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersViewInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;
use Utilities\HtmlSanitizer;

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
    /**
     * @see RecordHoldersViewInterface::render()
     *
     * @param AllRecordsData $records
     */
    public function render(array $records): string
    {
        $output = '<h2 class="ibl-title">Record Holders</h2>';
        $output .= '<style>
.record-holders-page td { text-align: center; vertical-align: middle; }
.record-holders-page td img { margin: 0 auto; }
.record-holders-page .ibl-data-table { max-width: 900px; margin-left: auto; margin-right: auto; table-layout: fixed; }
.record-holders-page .cols-5 col.col-player { width: 25%; }
.record-holders-page .cols-5 col.col-team { width: 15%; }
.record-holders-page .cols-5 col.col-date { width: 30%; }
.record-holders-page .cols-5 col.col-opponent { width: 15%; }
.record-holders-page .cols-5 col.col-amount { width: 15%; }
.record-holders-page .cols-4-season col.col-player { width: 30%; }
.record-holders-page .cols-4-season col.col-team { width: 20%; }
.record-holders-page .cols-4-season col.col-season { width: 25%; }
.record-holders-page .cols-4-season col.col-amount { width: 25%; }
.record-holders-page .cols-4-team col.col-team { width: 20%; }
.record-holders-page .cols-4-team col.col-date { width: 35%; }
.record-holders-page .cols-4-team col.col-opponent { width: 20%; }
.record-holders-page .cols-4-team col.col-amount { width: 25%; }
</style>';
        $output .= '<div class="record-holders-page">';
        $output .= $this->renderPlayerSingleGameRecords($records);
        $output .= $this->renderPlayerFullSeasonRecords($records['playerFullSeason']);
        $output .= $this->renderPlayerPlayoffRecords($records);
        $output .= $this->renderPlayerHeatRecords($records);
        $output .= $this->renderTeamRecords($records);
        $output .= '</div>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Section 1: Regular Season (Single Game)
    // ---------------------------------------------------------------

    /**
     * Render Section 1: Player Regular Season (Single Game) records.
     *
     * @param AllRecordsData $records
     */
    private function renderPlayerSingleGameRecords(array $records): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Regular Season (Single Game)</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">Individual Single-Game Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($records['playerSingleGame']['regularSeason'] as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($categoryRecords as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        // Quadruple Doubles
        $output .= $this->renderCategoryHeader('Quadruple Doubles');
        $output .= $this->renderPlayerColumnHeaders();
        foreach ($records['quadrupleDoubles'] as $record) {
            $output .= $this->renderPlayerRecordRow($record, true);
        }

        // Most All-Star Appearances
        $allStar = $records['allStarRecord'];
        $output .= $this->renderCategoryHeader('Most All-Star Appearances');
        $output .= '<tr class="text-center">';
        $output .= '<td><strong style="font-weight: bold;">Player</strong></td>';
        $output .= '<td><strong style="font-weight: bold;">Team</strong></td>';
        $output .= '<td><strong style="font-weight: bold;">Amount</strong></td>';
        $output .= '<td colspan="2"><strong style="font-weight: bold;">Years</strong></td>';
        $output .= '</tr>';

        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($allStar['name']);
        $pid = $allStar['pid'];
        $amount = (int) $allStar['amount'];

        $teamLogos = '';
        if ($allStar['teams'] !== '') {
            $teams = explode(',', $allStar['teams']);
            $teamTids = explode(',', $allStar['teamTids']);
            foreach ($teams as $i => $team) {
                $safeTid = (int) ($teamTids[$i] ?? 0);
                /** @var string $safeTeam */
                $safeTeam = HtmlSanitizer::safeHtmlOutput($team);
                $teamLogos .= '<a href="../online/team.php?tid=' . $safeTid . '">'
                    . '<img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '">'
                    . '</a> ';
            }
        }

        /** @var string $safeYears */
        $safeYears = HtmlSanitizer::safeHtmlOutput($allStar['years']);
        $years = $allStar['years'] !== '' ? str_replace(', ', '<br>', $safeYears) : '';

        $output .= '<tr class="text-center">';
        $output .= '<td>';
        if ($pid !== null) {
            $output .= '<img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
            $output .= '<strong style="font-weight: bold;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a></strong>';
        }
        $output .= '</td>';
        $output .= '<td><strong style="font-weight: bold;">' . $teamLogos . '</strong></td>';
        $output .= '<td><strong style="font-weight: bold;">' . $amount . '</strong></td>';
        $output .= '<td colspan="2"><strong style="font-weight: bold;">' . $years . '</strong></td>';
        $output .= '</tr>';

        $output .= '</tbody></table>';

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
    private function renderPlayerFullSeasonRecords(array $seasonRecords): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Regular Season (Full Season) [minimum 50 games]</h2>';
        $output .= '<table class="ibl-data-table cols-4-season">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-season"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="4">Season Average Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($seasonRecords as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong style="font-weight: bold;">Player</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Team</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Season</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Amount</strong></td>';
            $output .= '</tr>';
            foreach ($categoryRecords as $record) {
                /** @var string $safeName */
                $safeName = HtmlSanitizer::safeHtmlOutput($record['name']);
                /** @var string $safeTeam */
                $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
                /** @var string $safeSeason */
                $safeSeason = HtmlSanitizer::safeHtmlOutput($record['season']);
                /** @var string $safeAmount */
                $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
                $pid = $record['pid'];
                $teamTid = $record['teamTid'];
                $teamYr = (int) $record['teamYr'];

                $output .= '<tr class="text-center">';
                $output .= '<td><img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
                $output .= '<strong style="font-weight: bold;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a></strong></td>';
                $output .= '<td><strong style="font-weight: bold;"><a href="../online/team.php?tid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></strong></td>';
                $output .= '<td><strong style="font-weight: bold;">' . $safeSeason . '</strong></td>';
                $output .= '<td><strong style="font-weight: bold;">' . $safeAmount . '</strong></td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody></table>';

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
    private function renderPlayerPlayoffRecords(array $records): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, Playoffs</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">Playoff Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($records['playerSingleGame']['playoffs'] as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($categoryRecords as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        $output .= '</tbody></table>';

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
    private function renderPlayerHeatRecords(array $records): string
    {
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Player, H.E.A.T.</h2>';
        $output .= '<table class="ibl-data-table cols-5">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="5">H.E.A.T. Tournament Records</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($records['playerSingleGame']['heat'] as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category);
            $output .= $this->renderPlayerColumnHeaders();
            foreach ($categoryRecords as $record) {
                $output .= $this->renderPlayerRecordRow($record);
            }
        }

        $output .= '</tbody></table>';

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
        $output = '<h2 class="ibl-table-title">All-Time IBL Records: Team</h2>';
        $output .= '<table class="ibl-data-table cols-4-team">';
        $output .= '<colgroup><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        $output .= '<thead><tr><th colspan="4">Team Records</th></tr></thead>';
        $output .= '<tbody>';

        // Game records (with box scores and opponents)
        foreach ($records['teamGameRecords'] as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong style="font-weight: bold;">Team</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Date</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Opponent</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Amount</strong></td>';
            $output .= '</tr>';
            foreach ($categoryRecords as $record) {
                $output .= $this->renderTeamGameRow($record);
            }
        }

        // Season records (team, season, record/amount)
        foreach ($records['teamSeasonRecords'] as $category => $categoryRecords) {
            $colLabel = ($category === 'Best Season Record' || $category === 'Worst Season Record') ? 'Record' : 'Amount';
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong style="font-weight: bold;">Team</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Season</strong></td>';
            /** @var string $safeColLabel */
            $safeColLabel = HtmlSanitizer::safeHtmlOutput($colLabel);
            $output .= '<td colspan="2"><strong style="font-weight: bold;">' . $safeColLabel . '</strong></td>';
            $output .= '</tr>';
            foreach ($categoryRecords as $record) {
                /** @var string $safeTeam */
                $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
                /** @var string $safeSeason */
                $safeSeason = HtmlSanitizer::safeHtmlOutput($record['season']);
                /** @var string $safeAmount */
                $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
                $output .= '<tr class="text-center">';
                $output .= '<td><strong style="font-weight: bold;"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></strong></td>';
                $output .= '<td><strong style="font-weight: bold;">' . $safeSeason . '</strong></td>';
                $output .= '<td colspan="2"><strong style="font-weight: bold;">' . $safeAmount . '</strong></td>';
                $output .= '</tr>';
            }
        }

        // Franchise records (team, amount, years)
        foreach ($records['teamFranchise'] as $category => $categoryRecords) {
            $output .= $this->renderCategoryHeader($category, 4);
            $output .= '<tr class="text-center">';
            $output .= '<td><strong style="font-weight: bold;">Team</strong></td>';
            $output .= '<td><strong style="font-weight: bold;">Amount</strong></td>';
            $output .= '<td colspan="2"><strong style="font-weight: bold;">Years</strong></td>';
            $output .= '</tr>';
            foreach ($categoryRecords as $record) {
                /** @var string $safeTeam */
                $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
                /** @var string $safeAmount */
                $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
                /** @var string $safeYears */
                $safeYears = HtmlSanitizer::safeHtmlOutput($record['years']);
                $output .= '<tr class="text-center">';
                $output .= '<td><strong style="font-weight: bold;"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></strong></td>';
                $output .= '<td><strong style="font-weight: bold;">' . $safeAmount . '</strong></td>';
                $output .= '<td colspan="2"><strong style="font-weight: bold;">' . $safeYears . '</strong></td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    // ---------------------------------------------------------------
    // Shared rendering helpers
    // ---------------------------------------------------------------

    /**
     * Render a category sub-header row.
     */
    private function renderCategoryHeader(string $category, int $colspan = 5): string
    {
        /** @var string $safeCategory */
        $safeCategory = HtmlSanitizer::safeHtmlOutput($category);
        return '<tr class="text-center"><td colspan="' . $colspan . '"><strong style="font-weight: bold;"><em style="font-style: italic;">' . $safeCategory . '</em></strong></td></tr>';
    }

    /**
     * Render standard player column headers (Player, Team, Date, Opponent, Amount).
     */
    private function renderPlayerColumnHeaders(): string
    {
        return '<tr class="text-center">'
            . '<td><strong style="font-weight: bold;">Player</strong></td>'
            . '<td><strong style="font-weight: bold;">Team</strong></td>'
            . '<td><strong style="font-weight: bold;">Date</strong></td>'
            . '<td><strong style="font-weight: bold;">Opponent</strong></td>'
            . '<td><strong style="font-weight: bold;">Amount</strong></td>'
            . '</tr>';
    }

    /**
     * Render a single player record row.
     *
     * @param FormattedPlayerRecord $record
     */
    private function renderPlayerRecordRow(array $record, bool $multiLineAmount = false): string
    {
        /** @var string $safeName */
        $safeName = HtmlSanitizer::safeHtmlOutput($record['name']);
        /** @var string $safeTeam */
        $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
        /** @var string $safeDate */
        $safeDate = HtmlSanitizer::safeHtmlOutput($record['dateDisplay']);
        /** @var string $safeOppTeam */
        $safeOppTeam = HtmlSanitizer::safeHtmlOutput($record['oppAbbr']);
        $pid = $record['pid'];
        $teamTid = $record['teamTid'];
        $teamYr = (int) $record['teamYr'];
        $oppTid = $record['oppTid'];
        $oppYr = (int) $record['oppYr'];

        /** @var string $safeAmountRaw */
        $safeAmountRaw = HtmlSanitizer::safeHtmlOutput($record['amount']);
        $amount = $multiLineAmount
            ? '<strong style="font-weight: bold;">' . str_replace("\n", '<br>', $safeAmountRaw) . '</strong>'
            : '<strong style="font-weight: bold;">' . $safeAmountRaw . '</strong>';

        /** @var string $safeBoxScoreUrl */
        $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($record['boxScoreUrl']);
        $dateCell = $record['boxScoreUrl'] !== ''
            ? '<a href="' . $safeBoxScoreUrl . '">' . $safeDate . '</a>'
            : $safeDate;

        $output = '<tr class="text-center">';
        $output .= '<td><img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
        $output .= '<strong style="font-weight: bold;"><a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a></strong></td>';
        $output .= '<td><strong style="font-weight: bold;"><a href="../online/team.php?tid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></strong></td>';
        $output .= '<td><strong style="font-weight: bold;">' . $dateCell . '</strong></td>';
        $output .= '<td><strong style="font-weight: bold;"><a href="../online/team.php?tid=' . $oppTid . '&amp;yr=' . $oppYr . '"><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></a></strong></td>';
        $output .= '<td>' . $amount . '</td>';
        $output .= '</tr>';

        return $output;
    }

    /**
     * Render a team game record row.
     *
     * @param FormattedTeamGameRecord $record
     */
    private function renderTeamGameRow(array $record): string
    {
        /** @var string $safeTeam */
        $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
        /** @var string $safeDate */
        $safeDate = HtmlSanitizer::safeHtmlOutput($record['dateDisplay']);
        /** @var string $safeOppTeam */
        $safeOppTeam = HtmlSanitizer::safeHtmlOutput($record['oppAbbr']);
        /** @var string $safeAmount */
        $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);

        /** @var string $safeBoxScoreUrl */
        $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($record['boxScoreUrl']);
        $dateCell = $record['boxScoreUrl'] !== ''
            ? '<a href="' . $safeBoxScoreUrl . '">' . $safeDate . '</a>'
            : $safeDate;

        $output = '<tr class="text-center">';
        $output .= '<td><strong style="font-weight: bold;"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></strong></td>';
        $output .= '<td><strong style="font-weight: bold;">' . $dateCell . '</strong></td>';
        $output .= '<td><strong style="font-weight: bold;"><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></strong></td>';
        $output .= '<td><strong style="font-weight: bold;">' . $safeAmount . '</strong></td>';
        $output .= '</tr>';

        return $output;
    }
}
