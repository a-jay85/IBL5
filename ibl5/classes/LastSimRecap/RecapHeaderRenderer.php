<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Dto\RecapSlate;
use Security\HtmlSanitizer;

/**
 * Renders the `last-sim-recap__head` block: date window, W–L record, and the
 * net-margin / best / worst meta strip.
 */
final class RecapHeaderRenderer
{
    public function render(RecapSlate $slate): string
    {
        $windowLabel = $this->formatDateRange($slate->startDate, $slate->endDate);
        $hasGames = $slate->games !== [];

        if ($hasGames) {
            $gameCount = count($slate->games);
            $gameWord = $gameCount === 1 ? 'game' : 'games';
            $subtitle = $windowLabel . ' (' . $gameCount . ' ' . $gameWord . ')';
        } else {
            $subtitle = $windowLabel;
        }

        $h  = '<header class="last-sim-recap__head">';
        $h .= '  <div class="last-sim-recap__head-dates">';
        $h .= '    <span class="last-sim-recap__sub">' . HtmlSanitizer::e($subtitle) . '</span>';
        $h .= '  </div>';
        $h .= '  <div class="last-sim-recap__head-center">';
        $h .= '    Last sim:';
        if ($hasGames) {
            $h .= '    <span class="last-sim-recap__record-w">' . HtmlSanitizer::e((string) $slate->wins) . '</span>';
            $h .= '    <span class="last-sim-recap__record-sep">–</span>';
            $h .= '    ' . HtmlSanitizer::e((string) $slate->losses);
        }
        $h .= '  </div>';

        if ($hasGames) {
            $netSign = $slate->netMargin >= 0 ? '+' : '−';
            $netAbs = abs($slate->netMargin);
            $netValue = $netSign . $netAbs;

            $h .= '  <div class="last-sim-recap__meta">';
            $h .= '    <span>Net margin: <span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($netValue) . '</span></span>';
            $h .= '    <span class="last-sim-recap__meta-bw">';
            $h .= '      <span class="last-sim-recap__meta-bw-row"><span class="last-sim-recap__meta-bw-label">&nbsp;Best:</span>&nbsp;<span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($slate->bestLabel) . '</span></span>';
            $h .= '      <span class="last-sim-recap__meta-bw-row"><span class="last-sim-recap__meta-bw-label">Worst:</span>&nbsp;<span class="last-sim-recap__meta-value">' . HtmlSanitizer::e($slate->worstLabel) . '</span></span>';
            $h .= '    </span>';
            $h .= '  </div>';
        }

        $h .= '</header>';

        return $h;
    }

    /**
     * "2026-05-01" + "2026-05-13" → "May 1 – May 13, 2026"
     * "2026-05-01" + "2026-06-02" → "May 1 – Jun 2, 2026"
     */
    private function formatDateRange(string $start, string $end): string
    {
        $sTs = strtotime($start);
        $eTs = strtotime($end);
        if ($sTs === false || $eTs === false) {
            return $start . ' – ' . $end;
        }
        $startLabel = date('M j', $sTs);
        $endLabel = date('M j', $eTs);
        $year = date('Y', $eTs);
        return $startLabel . ' – ' . $endLabel . ', ' . $year;
    }
}
