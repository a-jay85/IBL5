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

        // Team and GM sit side by side; a lone section spans the card.
        $html .= '<div class="team-rivals-grid">';
        if ($franchiseRivals !== []) {
            $html .= '<div class="team-rivals-section"><div class="team-card__body team-card__body--tight">'
                . '<strong class="team-card__section-label">Team</strong></div>'
                . '<div class="team-card__body">' . $this->renderList(array_slice($franchiseRivals, 0, 3), 'teams')
                . '<a class="team-rivals-more" href="' . HtmlSanitizer::e(self::BASE . '&op=team&team=' . urlencode($franchiseRivals[0]['a'])) . '">See all team rivals</a></div></div>';
        }

        if ($gmName !== null && $gmRivals !== []) {
            $html .= '<div class="team-rivals-section"><div class="team-card__body team-card__body--tight">'
                . '<strong class="team-card__section-label">GM</strong></div>'
                . '<div class="team-card__body">' . $this->renderList(array_slice($gmRivals, 0, 3), 'gms')
                . '<a class="team-rivals-more" href="' . HtmlSanitizer::e(self::BASE . '&op=gm&gm=' . urlencode($gmName)) . '">See all GM rivals</a></div></div>';
        }

        return $html . '</div></div>';
    }

    /**
     * Laid out like the Awards card: the record leads in bold, the rival follows.
     *
     * @param list<ScoredRivalry> $rivals
     * @param 'teams'|'gms' $kind
     */
    private function renderList(array $rivals, string $kind): string
    {
        $items = '';
        foreach ($rivals as $r) {
            $href = self::BASE . '&op=' . $kind . '&a=' . urlencode($r['a']) . '&b=' . urlencode($r['b']);
            $items .= '<li><span class="award-year">' . $r['a_wins'] . '-' . $r['b_wins'] . '</span> '
                . '<a href="' . HtmlSanitizer::e($href) . '">' . HtmlSanitizer::e($r['b_label']) . '</a></li>';
        }

        return '<ol class="team-awards-list">' . $items . '</ol>';
    }
}
