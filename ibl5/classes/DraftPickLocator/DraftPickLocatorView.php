<?php

declare(strict_types=1);

namespace DraftPickLocator;

use DraftPickLocator\Contracts\DraftPickLocatorViewInterface;
use Utilities\HtmlSanitizer;

/**
 * DraftPickLocatorView - HTML rendering for draft pick matrix
 *
 * Generates HTML table displaying draft pick ownership.
 *
 * @see DraftPickLocatorViewInterface For the interface contract
 */
class DraftPickLocatorView implements DraftPickLocatorViewInterface
{
    /**
     * @see DraftPickLocatorViewInterface::render()
     */
    public function render(array $teamsWithPicks, int $currentEndingYear): string
    {
        $html = $this->getStyleBlock();
        $html .= $this->renderTitle();
        $html .= $this->renderTableStart($currentEndingYear);
        $html .= $this->renderTableRows($teamsWithPicks);
        $html .= '</table></div>';

        return $html;
    }

    /**
     * Generate CSS styles for the draft pick table
     *
     * Styles are now in the design system (existing-components.css).
     *
     * @return string Empty string - styles are centralized
     */
    private function getStyleBlock(): string
    {
        return '';
    }

    /**
     * Render the title
     *
     * @return string HTML for title
     */
    private function renderTitle(): string
    {
        return '<div class="draft-pick-locator-container">
            <h2 class="draft-pick-title">Dude, Where\'s My Pick?</h2>
            <p class="draft-pick-description">Use this locator to see exactly who has your draft pick.</p>
        </div>';
    }

    /**
     * Render table start with headers
     *
     * @param int $currentEndingYear Current season ending year
     * @return string HTML for table start
     */
    private function renderTableStart(int $currentEndingYear): string
    {
        $html = '<div class="draft-pick-locator-container"><table class="draft-pick-table">';

        // First header row - year spans
        $html .= '<tr><th rowspan="2">Team</th>';
        for ($i = 0; $i < 6; $i++) {
            $year = $currentEndingYear + $i;
            $html .= '<th colspan="2">' . HtmlSanitizer::safeHtmlOutput((string)$year) . '</th>';
        }
        $html .= '</tr>';

        // Second header row - round labels
        $html .= '<tr>';
        for ($i = 0; $i < 6; $i++) {
            $html .= '<th>R1</th><th>R2</th>';
        }
        $html .= '</tr>';

        return $html;
    }

    /**
     * Render all team rows
     *
     * @param array $teamsWithPicks Teams with draft pick data
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $teamsWithPicks): string
    {
        $html = '';

        foreach ($teamsWithPicks as $team) {
            $html .= $this->renderTeamRow($team);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $team Team with draft pick data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = (int)$team['teamId'];
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        $teamCity = HtmlSanitizer::safeHtmlOutput($team['teamCity']);
        $teamName = HtmlSanitizer::safeHtmlOutput($team['teamName']);

        $html = '<tr>';

        // Team name cell with team colors
        $html .= '<td class="league-stats-team-cell" style="background-color: #' . $color1 . ';">';
        $html .= '<a href="../modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" ';
        $html .= 'style="color: #' . $color2 . ';">' . $teamCity . ' ' . $teamName . '</a>';
        $html .= '</td>';

        // Pick cells - highlight traded picks
        foreach ($team['picks'] as $pick) {
            $ownerOfPick = $pick['ownerofpick'] ?? '';
            $isOwn = ($ownerOfPick === $team['teamName']);
            $cellClass = $isOwn ? 'draft-pick-own' : 'draft-pick-traded';

            $html .= '<td class="' . $cellClass . '">';
            $html .= HtmlSanitizer::safeHtmlOutput($ownerOfPick);
            $html .= '</td>';
        }

        $html .= '</tr>';

        return $html;
    }
}
