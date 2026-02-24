<?php

declare(strict_types=1);

namespace DraftPickLocator;

use DraftPickLocator\Contracts\DraftPickLocatorViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * DraftPickLocatorView - HTML rendering for draft pick matrix
 *
 * Generates HTML table displaying draft pick ownership.
 *
 * @phpstan-import-type TeamWithPicks from DraftPickLocatorViewInterface
 *
 * @see DraftPickLocatorViewInterface For the interface contract
 */
class DraftPickLocatorView implements DraftPickLocatorViewInterface
{
    /**
     * @see DraftPickLocatorViewInterface::render()
     *
     * @param list<array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}> $teamsWithPicks
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
        return '<h2 class="ibl-title">Draft Pick Locator</h2>';
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
            $safeYear = HtmlSanitizer::safeHtmlOutput((string)$year);
            $html .= '<th colspan="2">' . $safeYear . '</th>';
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
     * @param list<array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}> $teamsWithPicks Teams with draft pick data
     * @return array<string, array{color1: string, color2: string, teamId: int}> Map of team name to info
     */
    private function buildTeamColorMap(array $teamsWithPicks): array
    {
        $map = [];
        foreach ($teamsWithPicks as $team) {
            $map[$team['teamName']] = [
                'color1' => $team['color1'],
                'color2' => $team['color2'],
                'teamId' => $team['teamId'],
            ];
        }

        return $map;
    }

    /**
     * Render all team rows
     *
     * @param list<array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}> $teamsWithPicks Teams with draft pick data
     * @param array<string, array{color1: string, color2: string, teamId: int}> $teamColorMap Team color lookup
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
     * @param array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>} $team Team with draft pick data
     * @param array<string, array{color1: string, color2: string, teamId: int}> $teamColorMap Team color lookup
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team, array $teamColorMap): string
    {
        $teamId = $team['teamId'];

        $html = '<tr data-team-id="' . $teamId . '">';
        $html .= TeamCellHelper::renderTeamCell($teamId, $team['teamName'], $team['color1'], $team['color2'], 'sticky-col');

        // Pick cells - color traded picks with owning team's colors
        foreach ($team['picks'] as $pick) {
            $ownerOfPick = $pick['ownerofpick'];
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
