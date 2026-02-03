<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersViewInterface;
use UI\Components\TableViewSwitcher;
use Utilities\HtmlSanitizer;

/**
 * LeagueStartersView - HTML rendering for league starters
 *
 * Generates HTML display for starting lineups across the league.
 *
 * @see LeagueStartersViewInterface For the interface contract
 */
class LeagueStartersView implements LeagueStartersViewInterface
{
    private object $db;
    private \Season $season;
    private string $moduleName;

    /** @var array<string, string> Position labels */
    private const POSITION_LABELS = [
        'PG' => 'Point Guards',
        'SG' => 'Shooting Guards',
        'SF' => 'Small Forwards',
        'PF' => 'Power Forwards',
        'C' => 'Centers',
    ];

    /**
     * Constructor
     *
     * @param object $db Database connection
     * @param \Season $season Current season
     * @param string $moduleName Module name
     */
    public function __construct(object $db, \Season $season, string $moduleName)
    {
        $this->db = $db;
        $this->season = $season;
        $this->moduleName = $moduleName;
    }

    /**
     * @see LeagueStartersViewInterface::render()
     */
    public function render(array $startersByPosition, \Team $userTeam, string $display = 'ratings'): string
    {
        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Season Totals',
            'avg_s' => 'Season Averages',
            'per36mins' => 'Per 36 Minutes',
        ];

        $baseUrl = 'modules.php?name=LeagueStarters';
        $switcher = new TableViewSwitcher($tabDefinitions, $display, $baseUrl, $userTeam->color1, $userTeam->color2);

        $html = '<div class="text-center"><h2 class="ibl-title">League Starters</h1></div>';
        $html .= '<table style="width: 100%; margin: 0 auto;">';

        foreach (self::POSITION_LABELS as $position => $label) {
            $html .= '<tr><td>';
            $html .= '<h2 class="ibl-table-title">' . HtmlSanitizer::safeHtmlOutput($label) . '</h2>';
            $tableHtml = $this->renderTableForDisplay($display, $startersByPosition[$position], $userTeam);
            $html .= $switcher->wrap($tableHtml);
            $html .= '</td></tr>';
            $html .= '<tr><td style="height: 15px;"></td></tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Render the appropriate table HTML based on display type
     */
    private function renderTableForDisplay(string $display, array $result, \Team $team): string
    {
        switch ($display) {
            case 'total_s':
                return \UI::seasonTotals($this->db, $result, $team, '', [], $this->moduleName);
            case 'avg_s':
                return \UI::seasonAverages($this->db, $result, $team, '', [], $this->moduleName);
            case 'per36mins':
                return \UI::per36Minutes($this->db, $result, $team, '', [], $this->moduleName);
            default:
                return \UI::ratings($this->db, $result, $team, '', $this->season, $this->moduleName);
        }
    }
}
