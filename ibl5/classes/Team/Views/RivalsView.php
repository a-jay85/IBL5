<?php

declare(strict_types=1);

namespace Team\Views;

use Rivalries\Contracts\RivalryScorerInterface;
use Security\HtmlSanitizer;

/**
 * RivalsView - "Top Rivals" sidebar card on the Team page.
 *
 * Copies SidebarView::renderCurrentSeasonCard markup. Renders nothing when the
 * team and its GM have no qualifying rival, so the Team page bytes stay unchanged.
 *
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 */
class RivalsView
{
    private const BASE = 'modules.php?name=Rivalries';

    /**
     * @param list<ScoredRivalry> $franchiseRivals oriented so a is this franchise
     * @param list<ScoredRivalry> $gmRivals oriented so a is $gmName
     */
    public function render(array $franchiseRivals, ?string $gmName, array $gmRivals, string $teamColorStyle): string
    {
        if ($franchiseRivals === [] && $gmRivals === []) {
            return '';
        }

        $html = '<div class="team-card" style="' . HtmlSanitizer::e($teamColorStyle) . '">'
            . '<div class="team-card__header"><h2 class="team-card__title">Top Rivals</h2></div>';

        if ($franchiseRivals !== []) {
            $html .= '<div class="team-card__body">' . $this->renderList(array_slice($franchiseRivals, 0, 3), 'teams') . '</div>';
        }

        if ($gmName !== null && $gmRivals !== []) {
            $html .= '<div class="team-card__body team-card__body--tight team-card__body--bordered">'
                . '<strong class="team-card__section-label">' . HtmlSanitizer::e($gmName) . "'s rivals</strong></div>"
                . '<div class="team-card__body">' . $this->renderList(array_slice($gmRivals, 0, 3), 'gms')
                . '<a href="' . HtmlSanitizer::e(self::BASE . '&op=gm&gm=' . urlencode($gmName)) . '">All of '
                . HtmlSanitizer::e($gmName) . "'s rivals</a></div>";
        }

        return $html . '</div>';
    }

    /**
     * @param list<ScoredRivalry> $rivals
     * @param 'teams'|'gms' $kind
     */
    private function renderList(array $rivals, string $kind): string
    {
        $items = '';
        foreach ($rivals as $r) {
            $href = self::BASE . '&op=' . $kind . '&a=' . urlencode($r['a']) . '&b=' . urlencode($r['b']);
            $items .= '<li><a href="' . HtmlSanitizer::e($href) . '">' . HtmlSanitizer::e($r['b_label']) . '</a> '
                . $r['a_wins'] . '-' . $r['b_wins'] . ' <span class="ibl-badge">' . $r['score'] . '</span></li>';
        }

        return '<ol>' . $items . '</ol>';
    }
}
