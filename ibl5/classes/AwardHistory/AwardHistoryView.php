<?php

declare(strict_types=1);

namespace AwardHistory;

use Player\PlayerImageHelper;
use AwardHistory\Contracts\AwardHistoryViewInterface;
use Utilities\HtmlSanitizer;

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
        /** @var string $name */
        $name = HtmlSanitizer::safeHtmlOutput($params['name'] ?? '');
        /** @var string $award */
        $award = HtmlSanitizer::safeHtmlOutput($params['award'] ?? '');
        $yearRaw = $params['year'];
        $yearStr = $yearRaw !== null ? (string) $yearRaw : '';
        /** @var string $year */
        $year = HtmlSanitizer::safeHtmlOutput($yearStr);
        $sortby = $params['sortby'] ?? 3;

        /** @var array<int, string> $sortOptions */
        $sortOptions = $this->service->getSortOptions();

        $output = '<form method="post" action="modules.php?name=AwardHistory" class="ibl-filter-form">';

        // Input row
        $output .= '<div class="ibl-filter-form__row">';
        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<label class="ibl-filter-form__label" for="aw_name">Name</label>';
        $output .= '<input type="text" name="aw_name" id="aw_name" value="' . $name . '" placeholder="Player name...">';
        $output .= '</div>';

        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<label class="ibl-filter-form__label" for="aw_Award">Award</label>';
        $output .= '<input type="text" name="aw_Award" id="aw_Award" value="' . $award . '" placeholder="Award name...">';
        $output .= '</div>';

        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<label class="ibl-filter-form__label" for="aw_year">Year</label>';
        $output .= '<input type="text" name="aw_year" id="aw_year" value="' . $year . '" placeholder="Year" style="width: 5rem;">';
        $output .= '</div>';
        $output .= '</div>';

        // Sort row
        $output .= '<div class="ibl-filter-form__row" style="margin-top: var(--space-3);">';
        $output .= '<div class="ibl-filter-form__group">';
        $output .= '<span class="ibl-filter-form__label">Sort by</span>';
        foreach ($sortOptions as $value => $label) {
            $checked = ($sortby === $value) ? ' checked' : '';
            $id = 'sort-' . $value;
            $output .= '<label class="player-awards-sort__option" for="' . $id . '">';
            $output .= '<input type="radio" name="aw_sortby" value="' . $value . '" id="' . $id . '"' . $checked . '>';
            /** @var string $safeLabel */
            $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
            $output .= ' <span>' . $safeLabel . '</span>';
            $output .= '</label>';
        }
        $output .= '</div>';

        $output .= '<button type="submit" class="ibl-filter-form__submit">Search</button>';
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
        $output .= '<div class="table-scroll-container">';
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
     * @param array{year: int, Award: string, name: string, pid?: int} $award
     */
    public function renderAwardRow(array $award, int $rowIndex): string
    {
        /** @var string $year */
        $year = HtmlSanitizer::safeHtmlOutput((string)($award['year'] ?? ''));
        /** @var string $awardName */
        $awardName = HtmlSanitizer::safeHtmlOutput($award['Award'] ?? '');
        $pid = (int)($award['pid'] ?? 0);

        if ($pid > 0) {
            $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $award['name'] ?? '');
        } else {
            /** @var string $escapedName */
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
