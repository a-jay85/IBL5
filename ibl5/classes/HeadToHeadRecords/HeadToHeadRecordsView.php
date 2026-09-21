<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Security\HtmlSanitizer;
use UI\TableStyles;
use UI\TeamCellHelper;

/**
 * HeadToHeadRecordsView - Renders the head-to-head records matrix and filter form.
 *
 * The matrix reuses the site-wide sticky-table pattern (.sticky-scroll-wrapper +
 * .sticky-table + .sticky-col) and the team-cell / series-record classes that the
 * legacy SeriesRecords page used, so it inherits the same compact look.
 *
 * All user-visible strings pass through HtmlSanitizer::e().
 * All color values pass through TableStyles::sanitizeColor().
 *
 * @phpstan-import-type AxisEntry from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatchupRecord from HeadToHeadRecordsRepositoryInterface
 * @phpstan-import-type MatrixPayload from HeadToHeadRecordsRepositoryInterface
 */
final class HeadToHeadRecordsView
{
    /**
     * Render the filter form.
     *
     * The form POSTs back to the module URL with class h2h-filter.
     */
    public function renderFilterForm(string $dimension, string $phase, string $scope): string
    {
        $dimOpts = [
            'franchises' => 'Franchises',
            'teams'      => 'Teams',
            'gms'        => 'GMs',
        ];
        $phaseOpts = [
            'heat'     => 'HEAT',
            'regular'  => 'Regular Season',
            'playoffs' => 'Playoffs',
            'all'      => 'All Phases',
        ];
        $scopeOpts = [
            'current' => 'Current Season',
            'all'     => 'All-Time',
        ];

        $out  = '<h1 class="ibl-title">Head-to-Head Records</h1>';
        $out .= '<form method="post" action="modules.php?name=HeadToHeadRecords" class="h2h-filter">';
        $out .= $this->renderSelect('dimension', 'Dimension', $dimOpts, $dimension);
        $out .= $this->renderSelect('phase', 'Phase', $phaseOpts, $phase);
        $out .= $this->renderSelect('scope', 'Scope', $scopeOpts, $scope);
        $out .= '<button type="submit" class="ibl-btn ibl-btn--primary ibl-btn--sm">Filter</button>';
        $out .= '</form>';

        return $out;
    }

    /**
     * @param array<string, string> $options
     */
    private function renderSelect(string $name, string $label, array $options, string $current): string
    {
        $out  = '<label class="h2h-filter__field">';
        $out .= '<span class="ibl-label ibl-label--sm">' . HtmlSanitizer::e($label) . '</span>';
        $out .= '<select name="' . HtmlSanitizer::e($name) . '" class="ibl-select">';
        foreach ($options as $value => $text) {
            $selected = ($value === $current) ? ' selected' : '';
            $out .= '<option value="' . HtmlSanitizer::e($value) . '"' . $selected . '>'
                . HtmlSanitizer::e($text) . '</option>';
        }
        $out .= '</select></label>';
        return $out;
    }

    /**
     * Render the full head-to-head matrix table.
     *
     * Axis entries with no games at all (0-0 down the whole row and column)
     * are hidden so the matrix only shows participants.
     *
     * @param MatrixPayload $payload
     * @param list<string> $userMatchKeys Axis keys belonging to the logged-in user
     */
    public function renderMatrix(array $payload, array $userMatchKeys): string
    {
        $records = $payload['records'];
        $axis    = $this->filterActiveAxis($payload['axis'], $records);

        if ($axis === []) {
            return '<p class="table-empty-message">No games recorded for this filter.</p>';
        }

        // Header text only when a logo alone is ambiguous: every GM axis (GMs
        // share franchise logos) and any team axis where two eras reuse a logo.
        $showHeaderText = $this->hasDuplicateLogos($axis);
        $isTeamAxis     = $payload['dimension'] !== 'gms';
        $userKeySet     = array_flip($userMatchKeys);

        $out  = '<div class="sticky-scroll-wrapper page-sticky">';
        $out .= '<div class="sticky-scroll-container">';
        $out .= '<table class="ibl-data-table sticky-table h2h-matrix">';
        $out .= '<thead><tr>';
        $out .= $this->renderCornerCell();
        foreach ($axis as $entry) {
            $out .= $this->renderColumnHeader($entry, $showHeaderText, isset($userKeySet[$entry['key']]));
        }
        $out .= '</tr></thead>';
        $out .= '<tbody>';

        foreach ($axis as $rowEntry) {
            $rowKey    = $rowEntry['key'];
            $isUserRow = isset($userKeySet[$rowKey]);
            $out .= $isUserRow ? '<tr class="h2h-user-row">' : '<tr>';
            $out .= $this->renderRowLabelCell($rowEntry, $isTeamAxis);

            foreach ($axis as $colEntry) {
                $colKey    = $colEntry['key'];
                $isUserCol = isset($userKeySet[$colKey]);

                if ($rowKey === $colKey) {
                    $out .= '<td class="h2h-self' . ($isUserCol ? ' h2h-user-col' : '') . '"></td>';
                    continue;
                }

                /** @var MatchupRecord|null $record */
                $record = $records[$rowKey][$colKey] ?? null;
                $wins   = $record['wins']   ?? 0;
                $losses = $record['losses'] ?? 0;

                $out .= $this->renderRecordCell($wins, $losses, $isUserCol);
            }

            $out .= '</tr>';
        }

        $out .= '</tbody></table></div></div>';

        return $out;
    }

    /**
     * Drop axis entries that have never played a game in this filter.
     *
     * Records are stored per row; an entry is active when its row or any
     * column against it carries at least one win or loss.
     *
     * @param list<AxisEntry> $axis
     * @param array<array-key, array<array-key, MatchupRecord>> $records
     * @return list<AxisEntry>
     */
    private function filterActiveAxis(array $axis, array $records): array
    {
        $games = [];
        foreach ($records as $rowKey => $row) {
            foreach ($row as $colKey => $record) {
                $n = $record['wins'] + $record['losses'];
                $games[(string) $rowKey] = ($games[(string) $rowKey] ?? 0) + $n;
                $games[(string) $colKey] = ($games[(string) $colKey] ?? 0) + $n;
            }
        }

        $active = [];
        foreach ($axis as $entry) {
            if (($games[$entry['key']] ?? 0) > 0) {
                $active[] = $entry;
            }
        }

        return $active;
    }

    /**
     * True when two or more axis entries would render the same logo file.
     *
     * @param list<AxisEntry> $axis
     */
    private function hasDuplicateLogos(array $axis): bool
    {
        $seen = [];
        foreach ($axis as $entry) {
            $logo = $entry['logo'] !== '' ? $entry['logo'] : 'new' . $entry['franchise_id'] . '.png';
            if (isset($seen[$logo])) {
                return true;
            }
            $seen[$logo] = true;
        }
        return false;
    }

    /**
     * Corner cell: the reading-direction hint from the legacy page.
     * Arrows point at the column axis, the up arrow at the row axis.
     */
    private function renderCornerCell(): string
    {
        return '<th class="sticky-col sticky-corner h2h-corner">'
            . '<span class="sr-only">Row versus column, shown as wins-losses for the row</span>'
            . '<span class="h2h-corner__cols" aria-hidden="true">&rarr;&rarr;</span>'
            . '<span class="h2h-corner__vs" aria-hidden="true">vs.</span>'
            . '<span class="h2h-corner__rows" aria-hidden="true">&uarr;</span>'
            . '</th>';
    }

    /**
     * Render the column header cell for one axis entry.
     *
     * Logo only by default. When $showText is set, the label is stacked above
     * the logo and rotated so it reads top-to-bottom, keeping every column the
     * same width.
     *
     * @param AxisEntry $entry
     */
    private function renderColumnHeader(array $entry, bool $showText, bool $isUserCol): string
    {
        $safeLabel = HtmlSanitizer::e($entry['label']);
        $safeSub   = HtmlSanitizer::e($entry['sublabel']);
        $title     = $safeLabel . ($safeSub !== '' ? ' (' . $safeSub . ')' : '');
        $logoFile  = $entry['logo'] !== '' ? $entry['logo'] : 'new' . $entry['franchise_id'] . '.png';
        $safeLogo  = HtmlSanitizer::e('images/logo/' . $logoFile);

        $classes = 'h2h-col-header';
        if ($showText) {
            $classes .= ' h2h-col-header--labeled';
        }
        if ($isUserCol) {
            $classes .= ' h2h-user-col';
        }

        $inner = '';
        if ($showText) {
            $inner .= '<span class="h2h-col-header__text">' . $safeLabel . '</span>';
        }
        $inner .= '<img src="' . $safeLogo . '" width="40" height="40" class="series-logo-img"'
            . ' alt="' . ($showText ? '' : $title) . '" loading="lazy">';

        return '<th class="' . $classes . '" title="' . $title . '">' . $inner . '</th>';
    }

    /**
     * Render the row label cell for one axis entry.
     *
     * Mirrors TeamCellHelper::renderTeamCell() markup so the shared
     * .ibl-team-cell--colored / .sticky-col styles apply. Entries with no
     * color1 skip the colored modifier and fall back to the zebra background.
     *
     * @param AxisEntry $entry
     */
    private function renderRowLabelCell(array $entry, bool $linkToTeamPage): string
    {
        $safeLabel = HtmlSanitizer::e($entry['label']);
        $safeSub   = HtmlSanitizer::e($entry['sublabel']);
        $logoFile  = $entry['logo'] !== '' ? $entry['logo'] : 'new' . $entry['franchise_id'] . '.png';
        $safeLogo  = HtmlSanitizer::e('images/logo/' . $logoFile);
        $title     = $safeLabel . ($safeSub !== '' ? ' (' . $safeSub . ')' : '');

        $classes   = 'sticky-col h2h-row-label';
        $styleAttr = '';
        if ($entry['color1'] !== '') {
            $c1        = TableStyles::sanitizeColor($entry['color1']);
            $c2        = TableStyles::sanitizeColor($entry['color2']);
            $classes  .= ' ibl-team-cell--colored';
            $styleAttr = ' style="--team-cell-bg: #' . $c1 . '; --team-cell-color: #' . $c2 . ';"';
        }

        $inner = '<img src="' . $safeLogo . '" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">';
        $inner .= '<span class="ibl-team-cell__text">' . $safeLabel;
        if ($safeSub !== '') {
            $inner .= ' <small>' . $safeSub . '</small>';
        }
        $inner .= '</span>';

        if ($linkToTeamPage && $entry['franchise_id'] > 0) {
            $inner = '<a href="' . TeamCellHelper::teamPageUrl($entry['franchise_id']) . '"'
                . ' class="ibl-team-cell__name" aria-label="' . $title . '">' . $inner . '</a>';
        } else {
            $inner = '<span class="ibl-team-cell__name">' . $inner . '</span>';
        }

        return '<td class="' . $classes . '"' . $styleAttr . '>' . $inner . '</td>';
    }

    /**
     * Render one wins-losses cell.
     *
     * Colour comes from the h2h-winning / h2h-losing / h2h-tied modifier on the
     * shared .series-record-cell class; an unplayed 0-0 is additionally muted.
     */
    private function renderRecordCell(int $wins, int $losses, bool $isUserCol): string
    {
        $total = $wins + $losses;
        $pct   = $total > 0 ? (int) round(($wins / $total) * 100) : 0;

        $classes = 'series-record-cell ' . match (true) {
            $wins > $losses => 'h2h-winning',
            $wins < $losses => 'h2h-losing',
            default         => 'h2h-tied',
        };
        if ($total === 0) {
            $classes .= ' h2h-unplayed';
        }
        if ($isUserCol) {
            $classes .= ' h2h-user-col';
        }

        $title = HtmlSanitizer::e($wins . '-' . $losses . ' (' . $pct . '%)');
        $text  = HtmlSanitizer::e((string) $wins) . '-' . HtmlSanitizer::e((string) $losses);

        return '<td class="' . $classes . '" title="' . $title . '">' . $text . '</td>';
    }

    /**
     * Return a small inline script for the matrix:
     *  - toggles h2h-tip-open on touch tap, revealing the cell's title text
     *    for devices without hover;
     *  - mirrors row hover onto the hovered column (crosshair);
     *  - submits the filter form as soon as a select changes.
     */
    public function renderTapTooltipScript(): string
    {
        return '<script>
(function(){
    var tip=null;
    document.addEventListener("touchend",function(e){
        var td=e.target.closest("td[title]");
        if(tip){tip.classList.remove("h2h-tip-open");tip=null;}
        if(!td)return;
        e.preventDefault();
        td.classList.add("h2h-tip-open");
        tip=td;
    },{passive:false});

    var table=document.querySelector(".h2h-matrix");
    if(table){
        var lit=[];
        var clear=function(){lit.forEach(function(c){c.classList.remove("h2h-col-hover");});lit=[];};
        table.addEventListener("mouseover",function(e){
            var cell=e.target.closest("td,th");
            if(!cell||!table.contains(cell))return;
            var idx=cell.cellIndex;
            clear();
            if(idx<1)return;
            Array.prototype.forEach.call(table.rows,function(r){
                var c=r.cells[idx];
                if(c){c.classList.add("h2h-col-hover");lit.push(c);}
            });
        });
        table.addEventListener("mouseleave",clear);
    }

    var form=document.querySelector("form.h2h-filter");
    if(form){
        form.addEventListener("change",function(e){
            if(e.target&&e.target.tagName==="SELECT"){form.submit();}
        });
    }
})();
</script>';
    }
}
