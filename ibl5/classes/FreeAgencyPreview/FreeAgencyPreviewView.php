<?php

declare(strict_types=1);

namespace FreeAgencyPreview;

use FreeAgencyPreview\Contracts\FreeAgencyPreviewViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering free agency preview table.
 *
 * @see FreeAgencyPreviewViewInterface
 */
class FreeAgencyPreviewView implements FreeAgencyPreviewViewInterface
{
    /**
     * @see FreeAgencyPreviewViewInterface::render()
     */
    public function render(int $seasonEndingYear, array $freeAgents): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle($seasonEndingYear);
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($freeAgents);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the free agency preview table.
     *
     * Uses consolidated .ibl-data-table from design system - no overrides needed.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-data-table
    }

    /**
     * Render the page title.
     *
     * @param int $seasonEndingYear Season ending year
     * @return string HTML title
     */
    private function renderTitle(int $seasonEndingYear): string
    {
        return '<h2 class="ibl-table-title">Players Currently to be Free Agents at the end of the ' . $seasonEndingYear . ' Season</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Age</th>
                    <th>2ga</th>
                    <th>2g%</th>
                    <th>fta</th>
                    <th>ft%</th>
                    <th>3ga</th>
                    <th>3g%</th>
                    <th>orb</th>
                    <th>drb</th>
                    <th>ast</th>
                    <th>stl</th>
                    <th>to</th>
                    <th>blk</th>
                    <th>foul</th>
                    <th>o-o</th>
                    <th>d-o</th>
                    <th>p-o</th>
                    <th>t-o</th>
                    <th>o-d</th>
                    <th>d-d</th>
                    <th>p-d</th>
                    <th>t-d</th>
                    <th>Loy</th>
                    <th>PFW</th>
                    <th>PT</th>
                    <th>Sec</th>
                    <th>Trad</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param array $freeAgents Array of free agent data
     * @return string HTML table rows
     */
    private function renderTableRows(array $freeAgents): string
    {
        $output = '';

        foreach ($freeAgents as $player) {
            $pid = (int) ($player['pid'] ?? 0);
            $tid = (int) ($player['tid'] ?? 0);
            $name = HtmlSanitizer::safeHtmlOutput($player['name'] ?? '');
            $teamname = HtmlSanitizer::safeHtmlOutput($player['teamname'] ?? '');
            $pos = HtmlSanitizer::safeHtmlOutput($player['pos'] ?? '');
            $age = (int) ($player['age'] ?? 0);

            $output .= "<tr>
    <td>{$pos}</td>
    <td><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$name}</a></td>
    <td><a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$tid}\">{$teamname}</a></td>
    <td>{$age}</td>
    <td>{$player['r_fga']}</td>
    <td>{$player['r_fgp']}</td>
    <td>{$player['r_fta']}</td>
    <td>{$player['r_ftp']}</td>
    <td>{$player['r_tga']}</td>
    <td>{$player['r_tgp']}</td>
    <td>{$player['r_orb']}</td>
    <td>{$player['r_drb']}</td>
    <td>{$player['r_ast']}</td>
    <td>{$player['r_stl']}</td>
    <td>{$player['r_to']}</td>
    <td>{$player['r_blk']}</td>
    <td>{$player['r_foul']}</td>
    <td>{$player['oo']}</td>
    <td>{$player['do']}</td>
    <td>{$player['po']}</td>
    <td>{$player['to']}</td>
    <td>{$player['od']}</td>
    <td>{$player['dd']}</td>
    <td>{$player['pd']}</td>
    <td>{$player['td']}</td>
    <td>{$player['loyalty']}</td>
    <td>{$player['winner']}</td>
    <td>{$player['playingTime']}</td>
    <td>{$player['security']}</td>
    <td>{$player['tradition']}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render the end of the table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
