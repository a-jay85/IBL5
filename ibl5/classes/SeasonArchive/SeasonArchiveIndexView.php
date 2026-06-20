<?php

declare(strict_types=1);

namespace SeasonArchive;

use SeasonArchive\Contracts\SeasonArchiveIndexViewInterface;
use SeasonArchive\Contracts\SeasonArchiveServiceInterface;

/**
 * SeasonArchiveIndexView - HTML rendering for the season archive index page
 *
 * Generates the table listing all seasons with links to detail pages.
 * All dynamic output is sanitized via HtmlSanitizer::safeHtmlOutput().
 *
 * @phpstan-import-type SeasonSummary from SeasonArchiveServiceInterface
 *
 * @see SeasonArchiveIndexViewInterface For the interface contract
 */
class SeasonArchiveIndexView implements SeasonArchiveIndexViewInterface
{
    use SeasonArchiveRenderHelpers;

    /**
     * @see SeasonArchiveIndexViewInterface::renderIndex()
     *
     * @param list<SeasonSummary> $seasons
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     * @param array<string, int> $playerIds
     * @param array<string, int> $teamIds
     */
    public function renderIndex(
        array $seasons,
        array $teamColors = [],
        array $playerIds = [],
        array $teamIds = []
    ): string {
        $html = '';
        if ($teamColors !== [] || $playerIds !== []) {
            $html .= $this->renderStyles();
        }
        $html .= '<h2 class="ibl-title">IBL Season Archive</h2>';
        $html .= '<table class="sortable ibl-data-table season-archive-index-table" data-no-responsive>';
        $html .= '<thead><tr><th>Season</th><th>HEAT Champion</th><th>IBL Champion</th><th>MVP</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($seasons as $season) {
            /** @var array{year: int, label: string, iblChampion: string, heatChampion: string, mvp: string} $season */
            $year = $season['year'];
            $label = self::esc($season['label']);

            $html .= '<tr>';
            $html .= '<td><a href="modules.php?name=SeasonArchive&amp;year=' . $year . '">' . $label . '</a></td>';
            $html .= self::renderTeamCell($season['heatChampion'], $teamColors, $year);
            $html .= self::renderTeamCell($season['iblChampion'], $teamColors, $year);
            $html .= '<td>' . self::renderPlayerName($season['mvp'], $playerIds) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
