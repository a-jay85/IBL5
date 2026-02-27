<?php

declare(strict_types=1);

namespace Team\Views;

use Utilities\HtmlSanitizer;

/**
 * Pure renderer for draft picks list.
 *
 * @phpstan-import-type DraftPickItemData from \Team\Contracts\TeamServiceInterface
 */
class DraftPicksView
{
    /**
     * Render draft picks list from pre-computed data.
     *
     * @param list<DraftPickItemData> $draftPicks
     */
    public function render(array $draftPicks): string
    {
        $output = '<ul class="draft-picks-list">';

        foreach ($draftPicks as $pick) {
            $teamID = $pick['originalTeamID'];
            $city = HtmlSanitizer::e($pick['originalTeamCity']);
            $teamName = HtmlSanitizer::e($pick['originalTeamName']);
            $year = HtmlSanitizer::e($pick['year']);
            $round = $pick['round'];

            $output .= '<li class="draft-picks-list__item">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$teamID\">"
                . "<img class=\"draft-picks-list__logo\" src=\"images/logo/$teamName.png\" height=\"33\" width=\"33\" alt=\"$teamName\"></a>"
                . '<div class="draft-picks-list__info">'
                . "<a href=\"modules.php?name=Team&amp;op=team&amp;teamID=$teamID\">$year R$round $city $teamName</a>";

            if ($pick['notes'] !== null && $pick['notes'] !== '') {
                $notesSafe = HtmlSanitizer::e($pick['notes']);
                $output .= '<div class="draft-picks-list__notes">'
                    . $notesSafe . '</div>';
            }

            $output .= '</div></li>';
        }

        $output .= '</ul>';

        return $output;
    }
}
