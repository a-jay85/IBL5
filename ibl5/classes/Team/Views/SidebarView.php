<?php

declare(strict_types=1);

namespace Team\Views;

/**
 * Pure renderer for the team page sidebar card composition.
 *
 * Wraps individual rendered components (current season, awards, franchise history)
 * in team-card containers with appropriate headers and team colors.
 */
class SidebarView
{
    /**
     * Render the current season card.
     */
    public function renderCurrentSeasonCard(string $currentSeasonHtml, string $teamColorStyle): string
    {
        return "<div class=\"team-card\" style=\"$teamColorStyle\">"
            . '<div class="team-card__header"><h2 class="team-card__title">Current Season</h2></div>'
            . "<div class=\"team-card__body\">$currentSeasonHtml</div>"
            . '</div>';
    }

    /**
     * Render the awards card combining GM History and Team Accomplishments.
     */
    public function renderAwardsCard(string $gmHistoryHtml, string $teamAccomplishmentsHtml, string $teamColorStyle): string
    {
        if ($gmHistoryHtml === '' && $teamAccomplishmentsHtml === '') {
            return '';
        }

        $output = "<div class=\"team-card\" style=\"$teamColorStyle\">"
            . '<div class="team-card__header"><h2 class="team-card__title">Awards</h2></div>';

        if ($gmHistoryHtml !== '') {
            $output .= "<div class=\"team-card__body team-card__body--tight\">"
                . "<strong class=\"team-card__section-label\">GM History</strong>"
                . "</div><div class=\"team-card__body\">$gmHistoryHtml</div>";
        }

        if ($teamAccomplishmentsHtml !== '') {
            $output .= "<div class=\"team-card__body team-card__body--tight team-card__body--bordered\">"
                . "<strong class=\"team-card__section-label\">Team Accomplishments</strong>"
                . "</div><div class=\"team-card__body\">$teamAccomplishmentsHtml</div>";
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render the franchise history card with HEAT, Regular Season, and Playoffs columns.
     */
    public function renderFranchiseHistoryCard(string $heatHtml, string $regularSeasonHtml, string $playoffsHtml, string $teamColorStyle): string
    {
        return "<div class=\"team-card\" style=\"$teamColorStyle\">"
            . '<div class="team-card__header"><h2 class="team-card__title">Franchise History</h2></div>'
            . '<div class="franchise-history-columns">'
            . '<div class="franchise-history-column">'
            . '<h3 class="franchise-history-column__title">H.E.A.T.</h3>'
            . $heatHtml
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h3 class="franchise-history-column__title">Regular Season</h3>'
            . $regularSeasonHtml
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h3 class="franchise-history-column__title">Playoffs</h3>'
            . $playoffsHtml
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
