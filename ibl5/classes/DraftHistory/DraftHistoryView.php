<?php

declare(strict_types=1);

namespace DraftHistory;

use DraftHistory\Contracts\DraftHistoryViewInterface;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering draft history page.
 *
 * @see DraftHistoryViewInterface
 *
 * @phpstan-import-type DraftPickByYearRow from Contracts\DraftHistoryRepositoryInterface
 * @phpstan-import-type DraftPickByTeamRow from Contracts\DraftHistoryRepositoryInterface
 */
class DraftHistoryView implements DraftHistoryViewInterface
{
    /**
     * @see DraftHistoryViewInterface::render()
     *
     * @param list<DraftPickByYearRow> $draftPicks
     */
    public function render(int $selectedYear, int $startYear, int $endYear, array $draftPicks): string
    {
        $output = $this->renderTitleWithYearSelect($startYear, $endYear, $selectedYear);

        if ($draftPicks === []) {
            $output .= $this->renderNoDataMessage();
        } else {
            $output .= $this->renderTableStart();
            $output .= $this->renderTableRows($draftPicks);
            $output .= $this->renderTableEnd();
        }

        return $output;
    }

    /**
     * @see DraftHistoryViewInterface::renderTeamHistory()
     *
     * @param list<DraftPickByTeamRow> $draftPicks
     */
    public function renderTeamHistory(\Team $team, array $draftPicks): string
    {
        /** @var string $teamName */
        $teamName = HtmlSanitizer::safeHtmlOutput($team->name);
        $teamId = $team->teamID;

        $output = "<h2 class=\"ibl-title\">{$teamName} Draft History</h2>";
        $output .= "<img src=\"images/logo/{$teamId}.jpg\" alt=\"\" class=\"team-logo-banner\">";

        if ($draftPicks === []) {
            $output .= '<p class="draft-no-data">No draft history found.</p>';
        } else {
            $output .= $this->renderTeamTableStart();
            $output .= $this->renderTeamTableRows($draftPicks);
            $output .= $this->renderTableEnd();
        }

        return $output;
    }

    /**
     * Render the page title with integrated year select dropdown.
     *
     * @param int $startYear First draft year
     * @param int $endYear Last draft year
     * @param int $selectedYear Currently selected year
     * @return string HTML title with dropdown
     */
    private function renderTitleWithYearSelect(int $startYear, int $endYear, int $selectedYear): string
    {
        $output = '<h2 class="ibl-title">';
        $output .= '<select id="draft-year-select" class="draft-year-select" onchange="window.location.href=\'./modules.php?name=DraftHistory&amp;year=\' + this.value">';

        for ($year = $endYear; $year >= $startYear; $year--) {
            $selected = ($year === $selectedYear) ? ' selected' : '';
            $output .= '<option value="' . $year . '"' . $selected . '>' . $year . '</option>';
        }

        $output .= '</select> Draft</h2>';
        return $output;
    }

    /**
     * Render the no data message.
     *
     * @return string HTML message
     */
    private function renderNoDataMessage(): string
    {
        return '<p class="draft-no-data">Please select a draft year.</p>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table draft-history-table responsive-table">
            <thead>
                <tr>
                    <th class="sticky-col-1">Rd</th>
                    <th class="sticky-col-2">Pick</th>
                    <th>Player</th>
                    <th>Pos</th>
                    <th class="ibl-team-cell--colored">Team</th>
                    <th>College</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param list<DraftPickByYearRow> $draftPicks Array of draft pick data
     * @return string HTML table rows
     */
    private function renderTableRows(array $draftPicks): string
    {
        $output = '';

        foreach ($draftPicks as $pick) {
            $pid = $pick['pid'];
            /** @var string $name */
            $name = HtmlSanitizer::safeHtmlOutput($pick['name']);
            /** @var string $pos */
            $pos = HtmlSanitizer::safeHtmlOutput($pick['pos']);
            $round = $pick['draftround'];
            $pickNo = $pick['draftpickno'];
            /** @var string $college */
            $college = HtmlSanitizer::safeHtmlOutput($pick['college']);

            // Team cell styling
            $teamId = $pick['teamid'] ?? 0;
            /** @var string $teamName */
            $teamName = HtmlSanitizer::safeHtmlOutput($pick['draftedby']);
            /** @var string $color1 */
            $color1 = HtmlSanitizer::safeHtmlOutput($pick['color1'] ?? 'FFFFFF');
            /** @var string $color2 */
            $color2 = HtmlSanitizer::safeHtmlOutput($pick['color2'] ?? '000000');

            // Handle unknown teams (no match found) gracefully
            if ($teamId === 0) {
                $teamCell = "<td>{$teamName}</td>";
            } else {
                $teamCell = "<td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$teamId}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$teamId}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamName}</span>
        </a>
    </td>";
            }

            $playerThumbnail = PlayerImageHelper::renderThumbnail($pid);

            $output .= "<tr data-team-id=\"{$teamId}\">
    <td class=\"sticky-col-1\">{$round}</td>
    <td class=\"sticky-col-2\">{$pickNo}</td>
    <td class=\"name-cell\"><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$playerThumbnail}{$name}</a></td>
    <td>{$pos}</td>
    {$teamCell}
    <td>{$college}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render the start of the team history table.
     *
     * @return string HTML table start
     */
    private function renderTeamTableStart(): string
    {
        return '<table class="sortable ibl-data-table draft-history-table">
            <thead>
                <tr>
                    <th>Rd</th>
                    <th>Pick</th>
                    <th>Player</th>
                    <th>Pos</th>
                    <th>College</th>
                    <th>Year</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render team history table rows.
     *
     * @param list<DraftPickByTeamRow> $draftPicks Array of team draft pick data
     * @return string HTML table rows
     */
    private function renderTeamTableRows(array $draftPicks): string
    {
        $output = '';

        foreach ($draftPicks as $pick) {
            $pid = $pick['pid'];
            /** @var string $name */
            $name = HtmlSanitizer::safeHtmlOutput($pick['name']);
            /** @var string $pos */
            $pos = HtmlSanitizer::safeHtmlOutput($pick['pos']);
            $round = $pick['draftround'];
            $pickNo = $pick['draftpickno'];
            $draftYear = $pick['draftyear'];
            /** @var string $college */
            $college = HtmlSanitizer::safeHtmlOutput($pick['college']);
            $isRetired = $pick['retired'] !== '0';

            $retiredBadge = $isRetired ? ' <span class="draft-retired-badge">(ret.)</span>' : '';
            $playerThumbnail = PlayerImageHelper::renderThumbnail($pid);

            $output .= "<tr>
    <td>{$round}</td>
    <td>{$pickNo}</td>
    <td class=\"name-cell\"><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$playerThumbnail}{$name}</a>{$retiredBadge}</td>
    <td>{$pos}</td>
    <td>{$college}</td>
    <td>{$draftYear}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render the end of the table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
