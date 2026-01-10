<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimViewInterface;
use Utilities\HtmlSanitizer;

/**
 * NextSimView - HTML rendering for next simulation games
 *
 * Generates HTML display for upcoming games and matchups.
 *
 * @see NextSimViewInterface For the interface contract
 */
class NextSimView implements NextSimViewInterface
{
    private object $db;
    private \Season $season;
    private string $moduleName;

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
     * @see NextSimViewInterface::render()
     */
    public function render(array $games, int $simLengthInDays): string
    {
        $html = $this->getStyleBlock();
        $html .= '<div style="text-align: center;"><h1>Next Sim</h1></div>';

        if (empty($games)) {
            $html .= '<div style="text-align: center;">No games projected next sim!</div>';
            return $html;
        }

        $html .= '<table style="width: 100%;" align="center">';

        for ($i = 0; $i < $simLengthInDays; $i++) {
            if (isset($games[$i])) {
                $html .= $this->renderGameRow($games[$i]);
                $html .= '<tr style="height: 15px;"></tr>';
            }
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Generate CSS styles for the next sim display
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
            .next-sim-day-label {
                text-align: right;
                width: 150px;
            }
            .next-sim-logo {
                text-align: center;
                padding-left: 4px;
                padding-right: 4px;
            }
            .next-sim-record {
                text-align: left;
                width: 150px;
            }
        </style>';
    }

    /**
     * Render a single game row
     *
     * @param array $gameData Game data
     * @return string HTML for game row
     */
    private function renderGameRow(array $gameData): string
    {
        /** @var \Game $game */
        $game = $gameData['game'];
        /** @var \Team $opposingTeam */
        $opposingTeam = $gameData['opposingTeam'];

        $dayLabel = 'Day ' . HtmlSanitizer::safeHtmlOutput($gameData['dayNumber']) . ' ' . 
            HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);
        $gameDate = HtmlSanitizer::safeHtmlOutput($game->date);
        $opposingTeamId = (int)$opposingTeam->teamID;
        $seasonRecord = HtmlSanitizer::safeHtmlOutput($opposingTeam->seasonRecord);

        $html = '<tr><td>';
        $html .= '<table align="center">';
        $html .= '<tr>';
        $html .= '<td class="next-sim-day-label"><h2 title="' . $gameDate . '">' . $dayLabel . '</h2></td>';
        $html .= '<td class="next-sim-logo">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opposingTeamId . '">';
        $html .= '<img src="./images/logo/' . $opposingTeamId . '.jpg" alt="Team Logo">';
        $html .= '</a>';
        $html .= '</td>';
        $html .= '<td class="next-sim-record"><h2>' . $seasonRecord . '</h2></td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td></tr>';

        // Render matchup ratings
        $html .= '<tr><td>';
        $matchupPlayers = $this->prepareMatchupPlayers($gameData);
        $html .= \UI::ratings($this->db, $matchupPlayers, $opposingTeam, '', $this->season, $this->moduleName);
        $html .= '</td></tr>';

        return $html;
    }

    /**
     * Prepare player matchup data in alternating order
     *
     * Formats players as: Opponent PG, User PG, Opponent SG, User SG, etc.
     *
     * @param array $gameData Game data containing starting lineups
     * @return array Alternating opponent and user starters
     */
    private function prepareMatchupPlayers(array $gameData): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $matchupPlayers = [];

        foreach ($positions as $position) {
            $userKey = 'userStarting' . $position;
            $oppKey = 'opposingStarting' . $position;

            if (isset($gameData[$oppKey])) {
                $matchupPlayers[] = $gameData[$oppKey];
            }
            if (isset($gameData[$userKey])) {
                $matchupPlayers[] = $gameData[$userKey];
            }
        }

        return $matchupPlayers;
    }
}
