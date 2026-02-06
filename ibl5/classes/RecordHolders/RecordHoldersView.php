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
    /** @var array<string, string> */
    private const STAT_LABELS = [
        'Most Points' => 'Pts',
        'Most Rebounds' => 'Reb',
        'Most Assists' => 'Ast',
        'Most Steals' => 'Stl',
        'Most Blocks' => 'Blk',
        'Most Turnovers' => 'TO',
        'Most 3-Pointers' => '3PM',
        'Most Three-Pointers' => '3PM',
        'Most Minutes' => 'Min',
        'Highest Scoring Average' => 'PPG',
        'Highest Rebounding Average' => 'RPG',
        'Highest Assists Average' => 'APG',
        'Highest Steals Average' => 'SPG',
        'Highest Blocks Average' => 'BPG',
        'Highest 3-Point' => '3P%',
        'Highest Three-Point' => '3P%',
        'Highest Field Goal' => 'FG%',
        'Highest Free Throw' => 'FT%',
        'Best Season Record' => 'Record',
        'Worst Season Record' => 'Record',
        'Most Wins' => 'Wins',
        'Most Losses' => 'Losses',
        'Most Playoff Appearances' => 'Apps',
        'Most Championship' => 'Titles',
        'Longest Winning Streak' => 'Wins',
        'Longest Losing Streak' => 'Losses',
    ];

    /**
     * @see RecordHoldersViewInterface::render()
     *
     * @param AllRecordsData $records
     */
    public function render(array $records): string
    {
        $output = '<h2 class="ibl-title">Record Holders</h2>';
        $output .= '<div class="record-section">';
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
    private function renderPlayerFullSeasonRecords(array $seasonRecords): string
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
    private function renderPlayerPlayoffRecords(array $records): string
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
    private function renderPlayerHeatRecords(array $records): string
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
            $output .= '<h4 class="record-section__subheading">Game Records</h4>';
            foreach ($records['teamGameRecords'] as $category => $categoryRecords) {
                $output .= $this->renderTeamGameCategoryBlock($category, $categoryRecords);
            }
        }

        // Season records subsection
        if ($records['teamSeasonRecords'] !== []) {
            $output .= '<h4 class="record-section__subheading">Season Records</h4>';
            foreach ($records['teamSeasonRecords'] as $category => $categoryRecords) {
                $output .= $this->renderTeamSeasonCategoryBlock($category, $categoryRecords);
            }
        }

        // Franchise records subsection
        if ($records['teamFranchise'] !== []) {
            $output .= '<h4 class="record-section__subheading">Franchise Records</h4>';
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
     * Render a player single-game category block (heading + mini-table).
     *
     * @param list<FormattedPlayerRecord> $categoryRecords
     */
    private function renderPlayerCategoryBlock(string $category, array $categoryRecords, bool $multiLineAmount = false): string
    {
        $statLabel = $this->getStatColumnLabel($category);

        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table record-table--5col">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        /** @var string $safeStatLabel */
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($statLabel);
        $output .= '<thead><tr><th>Player</th><th>Team</th><th>Date</th><th>Opponent</th><th>' . $safeStatLabel . '</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($categoryRecords as $record) {
            $output .= $this->renderPlayerRecordRow($record, $multiLineAmount);
        }

        $output .= '</tbody></table></div>';

        return $output;
    }

    /**
     * Render a full-season category block (heading + mini-table).
     *
     * @param list<FormattedSeasonRecord> $categoryRecords
     */
    private function renderSeasonCategoryBlock(string $category, array $categoryRecords): string
    {
        $statLabel = $this->getStatColumnLabel($category);

        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table record-table--4col-season">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-season"><col class="col-amount"></colgroup>';
        /** @var string $safeStatLabel */
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($statLabel);
        $output .= '<thead><tr><th>Player</th><th>Team</th><th>Season</th><th>' . $safeStatLabel . '</th></tr></thead>';
        $output .= '<tbody>';

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

            $output .= '<tr>';
            $output .= '<td class="player-cell"><img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
            $output .= '<a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a></td>';
            $output .= '<td><a href="../online/team.php?tid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
            $output .= '<td>' . $safeSeason . '</td>';
            $output .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';

        return $output;
    }

    /**
     * Render a team game category block (heading + mini-table).
     *
     * @param list<FormattedTeamGameRecord> $categoryRecords
     */
    private function renderTeamGameCategoryBlock(string $category, array $categoryRecords): string
    {
        $statLabel = $this->getStatColumnLabel($category);

        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table record-table--4col-team">';
        $output .= '<colgroup><col class="col-team"><col class="col-date"><col class="col-opponent"><col class="col-amount"></colgroup>';
        /** @var string $safeStatLabel */
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($statLabel);
        $output .= '<thead><tr><th>Team</th><th>Date</th><th>Opponent</th><th>' . $safeStatLabel . '</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($categoryRecords as $record) {
            $output .= $this->renderTeamGameRow($record);
        }

        $output .= '</tbody></table></div>';

        return $output;
    }

    /**
     * Render a team season category block (heading + mini-table).
     *
     * @param list<FormattedTeamSeasonRecord> $categoryRecords
     */
    private function renderTeamSeasonCategoryBlock(string $category, array $categoryRecords): string
    {
        $statLabel = $this->getStatColumnLabel($category);

        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table record-table--3col-team-season">';
        $output .= '<colgroup><col class="col-team"><col class="col-season"><col class="col-amount"></colgroup>';
        /** @var string $safeStatLabel */
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($statLabel);
        $output .= '<thead><tr><th>Team</th><th>Season</th><th>' . $safeStatLabel . '</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($categoryRecords as $record) {
            /** @var string $safeTeam */
            $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
            /** @var string $safeSeason */
            $safeSeason = HtmlSanitizer::safeHtmlOutput($record['season']);
            /** @var string $safeAmount */
            $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
            $output .= '<tr>';
            $output .= '<td><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></td>';
            $output .= '<td>' . $safeSeason . '</td>';
            $output .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';

        return $output;
    }

    /**
     * Render a franchise category block (heading + mini-table).
     *
     * @param list<FormattedFranchiseRecord> $categoryRecords
     */
    private function renderFranchiseCategoryBlock(string $category, array $categoryRecords): string
    {
        $statLabel = $this->getStatColumnLabel($category);

        $output = '<div class="record-category">';
        $output .= $this->renderCategoryHeading($category);
        $output .= '<table class="ibl-data-table record-table record-table--3col-franchise">';
        $output .= '<colgroup><col class="col-team"><col class="col-amount"><col class="col-years"></colgroup>';
        /** @var string $safeStatLabel */
        $safeStatLabel = HtmlSanitizer::safeHtmlOutput($statLabel);
        $output .= '<thead><tr><th>Team</th><th>' . $safeStatLabel . '</th><th>Years</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($categoryRecords as $record) {
            /** @var string $safeTeam */
            $safeTeam = HtmlSanitizer::safeHtmlOutput($record['teamAbbr']);
            /** @var string $safeAmount */
            $safeAmount = HtmlSanitizer::safeHtmlOutput($record['amount']);
            /** @var string $safeYears */
            $safeYears = HtmlSanitizer::safeHtmlOutput($record['years']);
            $output .= '<tr>';
            $output .= '<td><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></td>';
            $output .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
            $output .= '<td>' . $safeYears . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';

        return $output;
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
        $output .= $this->renderCategoryHeading('Most All-Star Appearances');
        $output .= '<table class="ibl-data-table record-table record-table--5col">';
        $output .= '<colgroup><col class="col-player"><col class="col-team"><col class="col-amount"><col class="col-date" span="2"></colgroup>';
        $output .= '<thead><tr><th>Player</th><th>Team</th><th>Apps</th><th colspan="2">Years</th></tr></thead>';
        $output .= '<tbody>';

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

        $output .= '<tr>';
        $output .= '<td class="player-cell">';
        if ($pid !== null) {
            $output .= '<img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
            $output .= '<a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a>';
        }
        $output .= '</td>';
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
     * Render a category heading with accent left border.
     */
    private function renderCategoryHeading(string $category): string
    {
        /** @var string $safeCategory */
        $safeCategory = HtmlSanitizer::safeHtmlOutput($category);
        return '<h3 class="record-category__title">' . $safeCategory . '</h3>';
    }

    /**
     * Get the stat-specific column label for a category name.
     *
     * Maps category names like "Most Points in a Single Game" to abbreviations like "Pts".
     */
    private function getStatColumnLabel(string $category): string
    {
        foreach (self::STAT_LABELS as $prefix => $label) {
            if (str_starts_with($category, $prefix)) {
                return $label;
            }
        }

        return 'Amount';
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
            ? str_replace("\n", '<br>', $safeAmountRaw)
            : $safeAmountRaw;

        /** @var string $safeBoxScoreUrl */
        $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($record['boxScoreUrl']);
        $dateCell = $record['boxScoreUrl'] !== ''
            ? '<a href="' . $safeBoxScoreUrl . '">' . $safeDate . '</a>'
            : $safeDate;

        $output = '<tr>';
        $output .= '<td class="player-cell"><img src="images/player/' . $pid . '.jpg" alt="' . $safeName . '" width="65" height="90" loading="lazy">';
        $output .= '<a href="modules.php?name=Player&amp;pa=showpage&amp;pid=' . $pid . '">' . $safeName . '</a></td>';
        $output .= '<td><a href="../online/team.php?tid=' . $teamTid . '&amp;yr=' . $teamYr . '"><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></a></td>';
        $output .= '<td>' . $dateCell . '</td>';
        $output .= '<td><a href="../online/team.php?tid=' . $oppTid . '&amp;yr=' . $oppYr . '"><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></a></td>';
        $output .= '<td class="ibl-stat-highlight">' . $amount . '</td>';
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

        $output = '<tr>';
        $output .= '<td><img src="images/topics/' . $safeTeam . '.png" alt="' . strtoupper($safeTeam) . '"></td>';
        $output .= '<td>' . $dateCell . '</td>';
        $output .= '<td><img src="images/topics/' . $safeOppTeam . '.png" alt="' . strtoupper($safeOppTeam) . '"></td>';
        $output .= '<td class="ibl-stat-highlight">' . $safeAmount . '</td>';
        $output .= '</tr>';

        return $output;
    }
}
