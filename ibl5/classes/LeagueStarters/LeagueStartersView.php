<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersViewInterface;
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
    public function render(array $startersByPosition, \Team $userTeam): string
    {
        $html = '<div style="text-align: center;"><h1>League Starters</h1></div>';
        $html .= '<table style="width: 100%;" align="center">';

        foreach (self::POSITION_LABELS as $position => $label) {
            $html .= '<tr><td>';
            $html .= '<h2 style="text-align: center;">' . HtmlSanitizer::safeHtmlOutput($label) . '</h2>';
            $html .= \UI::ratings($this->db, $startersByPosition[$position], $userTeam, '', $this->season, $this->moduleName);
            $html .= '</td></tr>';
            $html .= '<tr style="height: 15px;"></tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
