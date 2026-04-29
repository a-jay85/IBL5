<?php

declare(strict_types=1);

namespace TrainingCampRatingsDiff;

use Player\PlayerImageHelper;
use TrainingCampRatingsDiff\Contracts\TrainingCampRatingsDiffViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * TrainingCampRatingsDiffView — renders the admin ratings diff page.
 *
 * Column name notes (migration 113):
 *   - `do`  → r_drive_off, `to`  → r_trans_off, `r_to` → r_tvr
 */
class TrainingCampRatingsDiffView implements TrainingCampRatingsDiffViewInterface
{
    /**
     * Short display labels for each rated field, keyed by RATED_FIELDS name.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'r_fga'       => '2ga',
        'r_fgp'       => '2g%',
        'r_fta'       => 'fta',
        'r_ftp'       => 'ft%',
        'r_3ga'       => '3ga',
        'r_3gp'       => '3g%',
        'r_orb'       => 'orb',
        'r_drb'       => 'drb',
        'r_ast'       => 'ast',
        'r_stl'       => 'stl',
        'r_tvr'       => 'tvr',
        'r_blk'       => 'blk',
        'r_foul'      => 'foul',
        'oo'          => 'oo',
        'r_drive_off' => 'do',
        'po'          => 'po',
        'r_trans_off' => 'to',
        'od'          => 'od',
        'dd'          => 'dd',
        'pd'          => 'pd',
        'td'          => 'td',
    ];

    // Fixed columns before the per-rating columns: Player, Team, Age, Pos, Max Δ = 5
    private const FIXED_COL_COUNT = 5;

    /**
     * @see TrainingCampRatingsDiffViewInterface::render()
     *
     * @param list<RatingRow> $rows
     */
    public function render(?int $baselineYear, array $rows, string $filterStatus = ''): string
    {
        if ($baselineYear === null || $rows === []) {
            return '<div class="ibl-card"><p>No prior-season baseline found. This page is meaningful after at least one <code>end-of-season</code> snapshot has been captured.</p></div>';
        }

        return $this->renderTable($baselineYear, $rows, $filterStatus);
    }

    /**
     * @param list<RatingRow> $rows
     */
    private function renderTable(int $baselineYear, array $rows, string $filterStatus): string
    {
        $totalCols = self::FIXED_COL_COUNT + count(TrainingCampRatingsDiffService::RATED_FIELDS);

        // Separate real rows from new-player rows (Service has already sorted them)
        /** @var list<RatingRow> $realRows */
        $realRows = [];
        /** @var list<RatingRow> $newRows */
        $newRows = [];
        foreach ($rows as $row) {
            if ($row->isNewPlayer) {
                $newRows[] = $row;
            } else {
                $realRows[] = $row;
            }
        }

        $html  = '<h2 class="ibl-title">Training Camp Ratings Diff</h2>';
        $html .= '<p>Live player ratings vs their end-of-season ratings from '
            . HtmlSanitizer::e($baselineYear)
            . '. Sorted by largest single rating change.</p>';
        $html .= $this->renderStatusFilter($filterStatus);

        $html .= '<div class="sticky-scroll-wrapper page-sticky"><div class="sticky-scroll-container">';
        $html .= '<table class="sortable ibl-data-table sticky-table ratings-diff-table">';

        // thead
        $html .= '<thead><tr>';
        $html .= '<th class="sticky-col sticky-corner">Player</th>';
        $html .= '<th class="ibl-team-cell--colored">Team</th>';
        $html .= '<th>Age</th>';
        $html .= '<th>Pos</th>';
        $html .= '<th>Max &#916;</th>';
        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            $label = self::FIELD_LABELS[$field];
            $html .= '<th>' . HtmlSanitizer::e($label) . '</th>';
        }
        $html .= '</tr></thead>';

        // tbody
        $html .= '<tbody>';
        foreach ($realRows as $row) {
            $html .= $this->buildRealRow($row);
        }
        if ($realRows !== [] && $newRows !== []) {
            $html .= '<tr class="ratings-separator"><td colspan="' . (string) $totalCols . '"></td></tr>';
        }
        foreach ($newRows as $row) {
            $html .= $this->buildNewRow($row);
        }
        $html .= '</tbody>';

        $html .= '</table>';
        $html .= '</div></div>';

        return $html;
    }

    private function renderStatusFilter(string $filterStatus): string
    {
        $options = [
            ''       => 'All Players',
            'signed' => 'Signed Players',
            'fa'     => 'Free Agents',
        ];

        $html = '<div class="ratings-diff-filter">';
        $html .= '<label for="ratings-diff-status">Show: </label>';
        $html .= '<select id="ratings-diff-status">';
        foreach ($options as $value => $label) {
            $selected = ($filterStatus === $value) ? ' selected' : '';
            $html .= '<option value="' . HtmlSanitizer::e($value) . '"' . $selected . '>'
                . HtmlSanitizer::e($label) . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        $html .= '<script>document.getElementById("ratings-diff-status").addEventListener("change",function(){'
            . 'var u=new URLSearchParams(location.search);'
            . 'if(this.value)u.set("status",this.value);else u.delete("status");'
            . 'location.search=u;'
            . '});</script>';

        return $html;
    }

    /**
     * Builds the HTML for a single real (non-new-player) row.
     */
    private function buildRealRow(RatingRow $row): string
    {
        $safeName = HtmlSanitizer::e($row->name);
        $html  = '<tr>';
        $html .= PlayerImageHelper::renderPlayerCell($row->pid, $safeName);
        $html .= TeamCellHelper::renderTeamCellOrFreeAgent($row->teamid, $row->teamName ?? '', $row->teamColor1, $row->teamColor2);
        $html .= '<td>' . ($row->age !== null ? HtmlSanitizer::e($row->age) : '') . '</td>';
        $html .= '<td>' . HtmlSanitizer::e($row->pos) . '</td>';
        $html .= '<td sorttable_customkey="' . HtmlSanitizer::e($row->maxAbsDelta) . '">'
            . HtmlSanitizer::e($row->maxAbsDelta) . '</td>';

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            $delta = $row->deltas[$field] ?? null;
            if ($delta !== null) {
                $sortKey = ($delta->delta !== null) ? $delta->delta : 0;
                $cellClass = $this->cellClass($delta->delta);
                $styleAttr = $this->intensityStyle($delta->delta);
                $html .= '<td class="' . $cellClass . '"' . $styleAttr . ' sorttable_customkey="' . HtmlSanitizer::e($sortKey) . '">'
                    . HtmlSanitizer::e($delta->after)
                    . $this->buildDeltaSpan($delta->delta)
                    . '</td>';
            } else {
                $html .= '<td></td>';
            }
        }

        $html .= '</tr>';
        return $html;
    }

    /**
     * Builds the HTML for a single new-player row (no snapshot baseline).
     */
    private function buildNewRow(RatingRow $row): string
    {
        $safeName = HtmlSanitizer::e($row->name);
        $html  = '<tr>';
        $html .= PlayerImageHelper::renderPlayerCell($row->pid, $safeName);
        $html .= TeamCellHelper::renderTeamCellOrFreeAgent($row->teamid, $row->teamName ?? '', $row->teamColor1, $row->teamColor2);
        $html .= '<td>' . ($row->age !== null ? HtmlSanitizer::e($row->age) : '') . '</td>';
        $html .= '<td>' . HtmlSanitizer::e($row->pos) . '</td>';
        // -9999999 keeps NEW rows at the bottom when any column is JS-sorted.
        $html .= '<td sorttable_customkey="-9999999"><span class="badge-new">NEW</span></td>';

        foreach (TrainingCampRatingsDiffService::RATED_FIELDS as $field) {
            $delta = $row->deltas[$field] ?? null;
            $html .= '<td sorttable_customkey="-9999999">'
                . HtmlSanitizer::e($delta !== null ? $delta->after : '') . '</td>';
        }

        $html .= '</tr>';
        return $html;
    }

    private function cellClass(?int $delta): string
    {
        if ($delta === null || $delta === 0) {
            return 'rating-cell';
        }

        return $delta > 0 ? 'rating-cell rating-cell--up' : 'rating-cell rating-cell--down';
    }

    private function intensityStyle(?int $delta): string
    {
        if ($delta === null || $delta === 0) {
            return '';
        }

        $alpha = min(abs($delta) / 30, 1.0) * 0.40;
        $alpha = max(0.06, round($alpha, 2));
        return ' style="--di: ' . $alpha . '"';
    }

    private function buildDeltaSpan(?int $delta): string
    {
        if ($delta === null || $delta === 0) {
            return '';
        }

        $cssClass  = $delta > 0 ? 'delta-up' : 'delta-down';
        $formatted = sprintf('(%+d)', $delta);
        return ' <span class="delta ' . $cssClass . '">' . HtmlSanitizer::e($formatted) . '</span>';
    }
}
