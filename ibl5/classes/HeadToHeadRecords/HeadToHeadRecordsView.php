<?php

declare(strict_types=1);

namespace HeadToHeadRecords;

use HeadToHeadRecords\Contracts\HeadToHeadRecordsRepositoryInterface;
use Security\HtmlSanitizer;
use UI\TableStyles;

/**
 * HeadToHeadRecordsView - Renders the head-to-head records matrix and filter form.
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
        $out .= '<button type="submit">Filter</button>';
        $out .= '</form>';

        return $out;
    }

    /**
     * @param array<string, string> $options
     */
    private function renderSelect(string $name, string $label, array $options, string $current): string
    {
        $out = '<label>' . HtmlSanitizer::e($label);
        $out .= '<select name="' . HtmlSanitizer::e($name) . '">';
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
     * @param MatrixPayload $payload
     * @param list<string> $userMatchKeys Axis keys belonging to the logged-in user
     */
    public function renderMatrix(array $payload, array $userMatchKeys): string
    {
        $axis    = $payload['axis'];
        $records = $payload['records'];

        if ($axis === []) {
            return '<p class="h2h-empty">No games recorded for this filter.</p>';
        }

        $userKeySet = array_flip($userMatchKeys);

        $out  = '<div class="h2h-matrix-wrap">';
        $out .= '<table class="h2h-matrix ibl-data-table">';
        $out .= '<thead><tr>';
        // Corner cell: empty to sighted readers, labelled for screen readers.
        $out .= '<th><span class="sr-only">Team</span></th>';
        foreach ($axis as $entry) {
            $out .= $this->renderColumnHeader($entry);
        }
        $out .= '</tr></thead>';
        $out .= '<tbody>';

        foreach ($axis as $rowEntry) {
            $rowKey    = $rowEntry['key'];
            $isUserRow = isset($userKeySet[$rowKey]);
            $out .= '<tr>';
            $out .= $this->renderRowLabelCell($rowEntry, $isUserRow);

            foreach ($axis as $colEntry) {
                $colKey = $colEntry['key'];

                if ($rowKey === $colKey) {
                    $out .= '<td class="h2h-self"></td>';
                    continue;
                }

                /** @var MatchupRecord|null $record */
                $record = $records[$rowKey][$colKey] ?? null;
                $wins   = $record['wins']   ?? 0;
                $losses = $record['losses'] ?? 0;

                $total = $wins + $losses;
                if ($total > 0) {
                    $pct = (int) round(($wins / $total) * 100);
                } else {
                    $pct = 0;
                }

                $class = match (true) {
                    $wins > $losses => 'h2h-winning',
                    $wins < $losses => 'h2h-losing',
                    default         => 'h2h-tied',
                };

                $title = HtmlSanitizer::e($wins . '-' . $losses . ' (' . $pct . '%)');
                $text  = HtmlSanitizer::e((string) $wins) . '-' . HtmlSanitizer::e((string) $losses);

                $out .= '<td class="' . $class . '" title="' . $title . '">' . $text . '</td>';
            }

            $out .= '</tr>';
        }

        $out .= '</tbody></table></div>';

        return $out;
    }

    /**
     * Render the column header cell for one axis entry.
     *
     * @param AxisEntry $entry
     */
    private function renderColumnHeader(array $entry): string
    {
        $safeLabel = HtmlSanitizer::e($entry['label']);
        $safeSub   = HtmlSanitizer::e($entry['sublabel']);
        $title     = $safeLabel . ($safeSub !== '' ? ' (' . $safeSub . ')' : '');

        $inner = '';
        if ($entry['logo'] !== '') {
            $inner .= '<img src="' . HtmlSanitizer::e('images/logo/' . $entry['logo']) . '" alt="" width="24" height="24">';
        }
        $inner .= '<span title="' . $title . '">' . $safeLabel . '</span>';

        return '<th>' . $inner . '</th>';
    }

    /**
     * Render the row label cell for one axis entry.
     *
     * When the entry has a non-empty color1, emits CSS custom properties for
     * --h2h-row-bg and --h2h-row-fg; omits the style attribute entirely otherwise.
     *
     * @param AxisEntry $entry
     */
    private function renderRowLabelCell(array $entry, bool $isUserMatch): string
    {
        $safeLabel = HtmlSanitizer::e($entry['label']);
        $safeSub   = HtmlSanitizer::e($entry['sublabel']);
        $safeLogo  = HtmlSanitizer::e('images/logo/' . $entry['logo']);

        $classes = 'h2h-row-label';
        if ($isUserMatch) {
            $classes .= ' h2h-user-row';
        }

        $styleAttr = '';
        if ($entry['color1'] !== '') {
            $c1        = TableStyles::sanitizeColor($entry['color1']);
            $c2        = TableStyles::sanitizeColor($entry['color2']);
            $styleAttr = ' style="--h2h-row-bg:#' . $c1 . ';--h2h-row-fg:#' . $c2 . '"';
        }

        $inner = '';
        if ($entry['logo'] !== '') {
            $inner .= '<img src="' . $safeLogo . '" alt="" width="24" height="24">';
        }
        $inner .= ' ' . $safeLabel;
        if ($safeSub !== '') {
            $inner .= '<small>' . $safeSub . '</small>';
        }

        return '<th scope="row" class="' . $classes . '"' . $styleAttr . '>' . $inner . '</th>';
    }

    /**
     * Return a small inline script that toggles h2h-tip-open on touch tap,
     * revealing the h2h-tooltip for devices without hover.
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
})();
</script>';
    }
}
