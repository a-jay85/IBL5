<?php

declare(strict_types=1);

namespace AwardHistory;

use Player\PlayerImageHelper;
use AwardHistory\Contracts\AwardHistoryViewInterface;
use Security\HtmlSanitizer;

/**
 * AwardHistoryView - HTML rendering for player awards search interface
 *
 * Implements the view contract defined in AwardHistoryViewInterface.
 * See the interface for detailed behavior documentation.
 *
 * @see AwardHistoryViewInterface
 */
class AwardHistoryView implements AwardHistoryViewInterface
{
    private AwardHistoryService $service;

    /**
     * @param AwardHistoryService $service Service for getting sort options
     */
    public function __construct(AwardHistoryService $service)
    {
        $this->service = $service;
    }

    /**
     * @see AwardHistoryViewInterface::renderSearchForm()
     *
     * @param array{name: string|null, award: string|null, year: int|null, sortby: int} $params
     */
    public function renderSearchForm(array $params): string
    {
        $name = HtmlSanitizer::safeHtmlOutput($params['name'] ?? '');
        $award = HtmlSanitizer::safeHtmlOutput($params['award'] ?? '');
        $yearRaw = $params['year'];
        $yearStr = $yearRaw !== null ? (string) $yearRaw : '';
        $year = HtmlSanitizer::safeHtmlOutput($yearStr);
        $sortby = $params['sortby'] ?? 3;

        /** @var array<int, string> $sortOptions */
        $sortOptions = $this->service->getSortOptions();

        $output = '<form method="post" action="modules.php?name=AwardHistory" class="d-card d-card-border bg-base-200 p-4 mb-6">';

        // Input row
        $output .= '<div class="flex flex-wrap items-center justify-center gap-4">';
        $output .= '<div class="flex items-center gap-2">';
        $output .= '<label class="d-label" for="aw_name">Name</label>';
        $output .= '<input type="text" name="aw_name" id="aw_name" value="' . $name . '" placeholder="Player name..." class="d-input d-input-sm w-40">';
        $output .= '</div>';

        $output .= '<div class="flex items-center gap-2">';
        $output .= '<label class="d-label" for="aw_Award">Award</label>';
        $output .= '<input type="text" name="aw_Award" id="aw_Award" value="' . $award . '" placeholder="Award name..." class="d-input d-input-sm w-40">';
        $output .= '</div>';

        $output .= '<div class="flex items-center gap-2">';
        $output .= '<label class="d-label" for="aw_year">Year</label>';
        $output .= '<input type="text" name="aw_year" id="aw_year" value="' . $year . '" placeholder="Year" class="d-input d-input-sm w-20">';
        $output .= '</div>';
        $output .= '</div>';

        // Sort row
        $output .= '<div class="flex flex-wrap items-center justify-center gap-4 mt-3">';
        $output .= '<div class="flex items-center gap-2">';
        $output .= '<span class="d-label">Sort by</span>';
        foreach ($sortOptions as $value => $label) {
            $checked = ($sortby === $value) ? ' checked' : '';
            $id = 'sort-' . $value;
            $output .= '<label class="player-awards-sort__option" for="' . $id . '">';
            $output .= '<input type="radio" name="aw_sortby" value="' . $value . '" id="' . $id . '"' . $checked . '>';
            $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
            $output .= ' <span>' . $safeLabel . '</span>';
            $output .= '</label>';
        }
        $output .= '</div>';

        $output .= '<button type="submit" class="d-btn d-btn-primary d-btn-sm">Search</button>';
        $output .= '</div>';

        $output .= '</form>';

        return $output;
    }

    /**
     * @see AwardHistoryViewInterface::renderTableHeader()
     */
    public function renderTableHeader(): string
    {
        $output = '<div class="table-scroll-wrapper">';
        $output .= '<div class="table-scroll-container" tabindex="0" role="region" aria-label="Award history">';
        $output .= '<table class="ibl-data-table sortable">';
        $output .= '<thead><tr>';
        $output .= '<th>Year</th>';
        $output .= '<th>Player</th>';
        $output .= '<th>Award</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        return $output;
    }

    /**
     * @see AwardHistoryViewInterface::renderAwardRow()
     *
     * @param array{year: int|string|null, award: string|null, name: string|null, pid?: int|null, table_id?: int} $award
     */
    public function renderAwardRow(array $award, int $rowIndex): string
    {
        $year = HtmlSanitizer::safeHtmlOutput((string)($award['year'] ?? ''));
        $awardName = HtmlSanitizer::safeHtmlOutput($award['award'] ?? '');
        $pid = (int)($award['pid'] ?? 0);

        if ($pid > 0) {
            $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $award['name'] ?? '');
        } else {
            $escapedName = HtmlSanitizer::safeHtmlOutput($award['name'] ?? '');
            $playerCell = '<td>' . $escapedName . '</td>';
        }

        return '<tr><td>' . $year . '</td>' . $playerCell . '<td>' . $awardName . '</td></tr>';
    }

    /**
     * @see AwardHistoryViewInterface::renderTableFooter()
     */
    public function renderTableFooter(): string
    {
        return "</tbody></table></div></div>\n";
    }
}
