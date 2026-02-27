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
            . '<div class="team-card__header"><h3 class="team-card__title">Current Season</h3></div>'
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
            . '<div class="team-card__header"><h3 class="team-card__title">Awards</h3></div>';

        if ($gmHistoryHtml !== '') {
            $output .= "<div class=\"team-card__body\" style=\"padding-bottom: 0;\">"
                . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">GM History</strong>"
                . "</div><div class=\"team-card__body\">$gmHistoryHtml</div>";
        }

        if ($teamAccomplishmentsHtml !== '') {
            $output .= "<div class=\"team-card__body\" style=\"padding-bottom: 0; border-top: 1px solid var(--gray-100);\">"
                . "<strong style=\"font-weight: 700; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500);\">Team Accomplishments</strong>"
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
            . '<div class="team-card__header"><h3 class="team-card__title">Franchise History</h3></div>'
            . '<div class="franchise-history-columns">'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">H.E.A.T.</h4>'
            . $heatHtml
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">Regular Season</h4>'
            . $regularSeasonHtml
            . '</div>'
            . '<div class="franchise-history-column">'
            . '<h4 class="franchise-history-column__title">Playoffs</h4>'
            . $playoffsHtml
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
