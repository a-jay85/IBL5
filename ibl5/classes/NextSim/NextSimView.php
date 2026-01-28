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
        $html .= '<div class="next-sim-container">';
        $html .= '<h1 class="next-sim-title">Next Sim</h1>';

        if (empty($games)) {
            $html .= '<div class="next-sim-empty">No games projected next sim!</div></div>';
            return $html;
        }

        for ($i = 0; $i < $simLengthInDays; $i++) {
            if (isset($games[$i])) {
                $html .= $this->renderGameRow($games[$i]);
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate CSS styles for the next sim display
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

        $html = '<div class="next-sim-day-game">';
        $html .= '<div class="next-sim-day-row">';
        $html .= '<div class="next-sim-day-label"><h2 title="' . $gameDate . '">' . $dayLabel . '</h2></div>';
        $html .= '<div class="next-sim-logo">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opposingTeamId . '">';
        $html .= '<img src="./images/logo/' . $opposingTeamId . '.jpg" alt="Team Logo" width="415" height="50">';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<div class="next-sim-record"><h2>' . $seasonRecord . '</h2></div>';
        $html .= '</div>';
        
        // Render matchup ratings
        $html .= '<div>';
        $matchupPlayers = $this->prepareMatchupPlayers($gameData);
        $html .= \UI::ratings($this->db, $matchupPlayers, $opposingTeam, '', $this->season, $this->moduleName);
        $html .= '</div>';
        $html .= '</div>';

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
