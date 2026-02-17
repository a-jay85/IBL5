<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimServiceInterface;
use NextSim\Contracts\NextSimViewInterface;
use Utilities\HtmlSanitizer;

/**
 * NextSimView - HTML rendering for next simulation games
 *
 * Generates HTML display for upcoming games and matchups.
 *
 * @phpstan-import-type NextSimGameData from NextSimServiceInterface
 *
 * @see NextSimViewInterface For the interface contract
 */
class NextSimView implements NextSimViewInterface
{
    private \mysqli $db;
    private \Season $season;
    private string $moduleName;

    /**
     * Constructor
     *
     * @param \mysqli $db Database connection
     * @param \Season $season Current season
     * @param string $moduleName Module name
     */
    public function __construct(\mysqli $db, \Season $season, string $moduleName)
    {
        $this->db = $db;
        $this->season = $season;
        $this->moduleName = $moduleName;
    }

    /**
     * @see NextSimViewInterface::render()
     *
     * @param array<int, NextSimGameData> $games Processed game data
     */
    public function render(array $games, int $simLengthInDays): string
    {
        $html = '';
        $html .= '<div class="next-sim-container">';
        $html .= '<h2 class="ibl-title">Next Sim</h1>';

        if ($games === []) {
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
     * Render a single game row
     *
     * @param NextSimGameData $gameData Game data
     * @return string HTML for game row
     */
    private function renderGameRow(array $gameData): string
    {
        $game = $gameData['game'];
        $opposingTeam = $gameData['opposingTeam'];

        /** @var string $dayNumberSafe */
        $dayNumberSafe = HtmlSanitizer::safeHtmlOutput((string)$gameData['dayNumber']);
        /** @var string $locationPrefixSafe */
        $locationPrefixSafe = HtmlSanitizer::safeHtmlOutput($gameData['locationPrefix']);
        $dayLabel = 'Day ' . $dayNumberSafe . ' ' . $locationPrefixSafe;
        /** @var string $gameDate */
        $gameDate = HtmlSanitizer::safeHtmlOutput($game->date);
        $opposingTeamId = $opposingTeam->teamID;
        /** @var string $seasonRecord */
        $seasonRecord = HtmlSanitizer::safeHtmlOutput($opposingTeam->seasonRecord ?? '');

        $html = '<div class="next-sim-day-game">';
        $html .= '<div class="next-sim-day-row">';
        $html .= '<div class="next-sim-day-label"><h2 title="' . $gameDate . '">' . $dayLabel . '</h2></div>';
        $html .= '<div class="next-sim-logo">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opposingTeamId . '">';
        $html .= '<img src="./images/logo/' . $opposingTeamId . '.jpg" alt="Team Logo" class="next-sim-banner" width="415" height="50">';
        $html .= '<img src="./images/logo/new' . $opposingTeamId . '.png" alt="Team Logo" class="next-sim-mobile-logo" width="50" height="50">';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<div class="next-sim-record"><h2>' . $seasonRecord . '</h2>';
        if (isset($gameData['opponentTier']) && is_string($gameData['opponentTier']) && $gameData['opponentTier'] !== '') {
            $tier = $gameData['opponentTier'];
            /** @var string $safeTierLabel */
            $safeTierLabel = HtmlSanitizer::safeHtmlOutput(ucfirst($tier));
            $html .= '<span class="next-sim-opponent-tier sos-tier--' . $tier . '">' . $safeTierLabel . '</span>';
        }
        $html .= '</div>';
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
     * @param array<string, mixed> $gameData Game data containing starting lineups
     * @return array<int, \Player\Player> Alternating opponent and user starters
     */
    private function prepareMatchupPlayers(array $gameData): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        /** @var array<int, \Player\Player> $matchupPlayers */
        $matchupPlayers = [];

        foreach ($positions as $position) {
            $userKey = 'userStarting' . $position;
            $oppKey = 'opposingStarting' . $position;

            if (isset($gameData[$oppKey]) && $gameData[$oppKey] instanceof \Player\Player) {
                $matchupPlayers[] = $gameData[$oppKey];
            }
            if (isset($gameData[$userKey]) && $gameData[$userKey] instanceof \Player\Player) {
                $matchupPlayers[] = $gameData[$userKey];
            }
        }

        return $matchupPlayers;
    }
}
