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
        $teamColorMap = $this->buildTeamColorMap($teamsWithPicks);

        $html = '';
        $html .= $this->renderTitle();
        $html .= '<div class="sticky-scroll-wrapper">';
        $html .= '<div class="sticky-scroll-container">';
        $html .= $this->renderTableStart($currentEndingYear);
        $html .= $this->renderTableRows($teamsWithPicks, $teamColorMap);
        $html .= '</tbody></table>';
        $html .= '</div></div></div>';

        return $html;
    }

    /**
     * Render the title
     *
     * @return string HTML for title
     */
    private function renderTitle(): string
    {
        return '<div class="draft-pick-header">
            <h2 class="ibl-title">Draft Pick Locator</h2>
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
        $html = '<div class="draft-pick-locator-container">';
        $html .= '<table class="draft-pick-table sticky-table">';
        $html .= '<thead>';

        // First header row - year spans
        $html .= '<tr><th rowspan="2" class="sticky-col sticky-corner">Team</th>';
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

        $html .= '</thead><tbody>';

        return $html;
    }

    /**
     * Build a lookup map of team name to team info (colors, ID)
     *
     * @param array $teamsWithPicks Teams with draft pick data
     * @return array<string, array{color1: string, color2: string, teamId: int}> Map of team name to info
     */
    private function buildTeamColorMap(array $teamsWithPicks): array
    {
        $map = [];
        foreach ($teamsWithPicks as $team) {
            $map[$team['teamName']] = [
                'color1' => $team['color1'],
                'color2' => $team['color2'],
                'teamId' => (int)$team['teamId'],
            ];
        }

        return $map;
    }

    /**
     * Render all team rows
     *
     * @param array $teamsWithPicks Teams with draft pick data
     * @param array<string, array{color1: string, color2: string}> $teamColorMap Team color lookup
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $teamsWithPicks, array $teamColorMap): string
    {
        $html = '';

        foreach ($teamsWithPicks as $team) {
            $html .= $this->renderTeamRow($team, $teamColorMap);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $team Team with draft pick data
     * @param array<string, array{color1: string, color2: string}> $teamColorMap Team color lookup
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team, array $teamColorMap): string
    {
        $teamId = (int)$team['teamId'];
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        $teamName = HtmlSanitizer::safeHtmlOutput($team['teamName']);

        $html = '<tr>';

        // Team name cell with team colors and logo - sticky column
        $html .= '<td class="ibl-team-cell--colored sticky-col" style="background-color: #' . $color1 . ';">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" ';
        $html .= 'class="ibl-team-cell__name" style="color: #' . $color2 . ';">';
        $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">';
        $html .= '<span class="ibl-team-cell__text">' . $teamName . '</span></a>';
        $html .= '</td>';

        // Pick cells - color traded picks with owning team's colors
        foreach ($team['picks'] as $pick) {
            $ownerOfPick = $pick['ownerofpick'] ?? '';
            $isOwn = ($ownerOfPick === $team['teamName']);
            $ownerInfo = $teamColorMap[$ownerOfPick] ?? null;

            if ($isOwn) {
                $html .= '<td class="draft-pick-own">';
            } else {
                if ($ownerInfo !== null) {
                    $bgColor = HtmlSanitizer::safeHtmlOutput($ownerInfo['color1']);
                    $textColor = HtmlSanitizer::safeHtmlOutput($ownerInfo['color2']);
                    $html .= '<td class="draft-pick-traded" style="background-color: #' . $bgColor . '; color: #' . $textColor . ';">';
                } else {
                    $html .= '<td class="draft-pick-traded">';
                }
            }

            $escapedOwner = HtmlSanitizer::safeHtmlOutput($ownerOfPick);
            if ($ownerInfo !== null) {
                $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $ownerInfo['teamId'] . '" style="color: inherit; text-decoration: none;">';
                $html .= $escapedOwner . '</a>';
            } else {
                $html .= $escapedOwner;
            }
            $html .= '</td>';
        }

        $html .= '</tr>';

        return $html;
    }
}
