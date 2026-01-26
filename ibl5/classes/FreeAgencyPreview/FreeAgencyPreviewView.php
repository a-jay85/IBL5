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
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.fa-preview-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-align: center;
    margin: 0 0 1.5rem 0;
}
.fa-preview-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    margin: 0 auto;
    font-size: 1rem;
}
.fa-preview-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.fa-preview-table th {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1.25rem;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    padding: 0.5rem 0.25rem;
    text-align: center;
}
.fa-preview-table td {
    color: var(--gray-800, #1f2937);
    padding: 0.375rem 0.25rem;
    text-align: center;
}
.fa-preview-table tbody tr {
    transition: background-color 150ms ease;
}
.fa-preview-table tbody tr:nth-child(odd) {
    background-color: white;
}
.fa-preview-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.fa-preview-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.fa-preview-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.fa-preview-table a:hover {
    color: var(--accent-500, #f97316);
}
</style>';
    }

    /**
     * Render the page title.
     *
     * @param int $seasonEndingYear Season ending year
     * @return string HTML title
     */
    private function renderTitle(int $seasonEndingYear): string
    {
        return '<h2 class="fa-preview-title">Players Currently to be Free Agents at the end of the ' . $seasonEndingYear . ' Season</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable fa-preview-table">
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
