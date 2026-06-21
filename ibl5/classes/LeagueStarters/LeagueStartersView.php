<?php

declare(strict_types=1);

namespace LeagueStarters;

use LeagueStarters\Contracts\LeagueStartersViewInterface;
use Player\Player;
use UI\Components\TableViewSwitcher;
use Security\HtmlSanitizer;
use Team\Team;
use Season\Season;

/**
 * LeagueStartersView - HTML rendering for league starters
 *
 * Generates HTML display for starting lineups across the league.
 *
 * @see LeagueStartersViewInterface For the interface contract
 */
class LeagueStartersView implements LeagueStartersViewInterface
{
    private string $moduleName;

    /** @var array<string, string> Position labels */
    private const POSITION_LABELS = [
        'PG' => 'Point Guards',
        'SG' => 'Shooting Guards',
        'SF' => 'Small Forwards',
        'PF' => 'Power Forwards',
        'C' => 'Centers',
    ];

    public function __construct(string $moduleName)
    {
        $this->moduleName = $moduleName;
    }

    /**
     * @see LeagueStartersViewInterface::render()
     *
     * @param array<string, array<int, Player>> $startersByPosition
     */
    public function render(\mysqli $db, Season $season, array $startersByPosition, Team $userTeam, string $display = 'ratings'): string
    {
        $tabDefinitions = [
            'ratings' => 'Ratings',
            'total_s' => 'Totals',
            'avg_s' => 'Averages',
            'per36mins' => 'Per 36',
        ];

        $baseUrl = 'modules.php?name=LeagueStarters';
        $apiUrl = 'modules.php?name=LeagueStarters&op=api';
        $switcher = new TableViewSwitcher(
            $tabDefinitions,
            $display,
            $baseUrl,
            $userTeam->color1,
            $userTeam->color2,
            $apiUrl,
            '#league-starters-tables',
        );

        $html = '<div class="text-center"><h1 class="ibl-title">League Starters</h1></div>';
        $html .= $switcher->renderTabs();
        $html .= '<div id="league-starters-tables">';
        $html .= $this->renderTableContent($db, $season, $startersByPosition, $userTeam, $display);
        $html .= '</div>';

        return $html;
    }

    /**
     * Render only the position tables for HTMX partial updates.
     *
     * @see LeagueStartersViewInterface::renderTableContent()
     *
     * @param array<string, array<int, Player>> $startersByPosition
     */
    public function renderTableContent(\mysqli $db, Season $season, array $startersByPosition, Team $userTeam, string $display = 'ratings'): string
    {
        $html = '<div class="space-y-4">';

        foreach (self::POSITION_LABELS as $position => $label) {
            $labelSafe = HtmlSanitizer::safeHtmlOutput($label);
            $html .= '<div>';
            $html .= '<h2 class="ibl-table-title">' . $labelSafe . '</h2>';
            $html .= $this->renderTableForDisplay($db, $season, $display, $startersByPosition[$position], $userTeam, $label);
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the appropriate table HTML based on display type
     *
     * @param array<int, Player> $result
     */
    private function renderTableForDisplay(\mysqli $db, Season $season, string $display, array $result, Team $team, string $ariaLabel = ''): string
    {
        switch ($display) {
            case 'total_s':
                return \BasketballStats\Tables\SeasonTotals::render($db, $result, $team, '', [], $this->moduleName, $ariaLabel);
            case 'avg_s':
                return \BasketballStats\Tables\SeasonAverages::render($db, $result, $team, '', [], $this->moduleName, $ariaLabel);
            case 'per36mins':
                return \BasketballStats\Tables\Per36Minutes::render($db, $result, $team, '', [], $this->moduleName, $ariaLabel);
            default:
                return \UI\Tables\Ratings::render($db, $result, $team, '', $season, $this->moduleName, [], $ariaLabel);
        }
    }
}
