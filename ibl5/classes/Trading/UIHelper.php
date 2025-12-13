<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\UIHelperInterface;

/**
 * UIHelper - Trade form UI rendering
 *
 * Handles rendering of trade form elements including player rows,
 * draft pick rows, team selection, and future salary displays.
 * 
 * @see UIHelperInterface
 */
class UIHelper implements UIHelperInterface
{
    protected $db;
    protected TradingRepository $repository;
    protected \Shared $sharedFunctions;
    protected \Season $season;

    public function __construct($db, ?TradingRepository $repository = null)
    {
        $this->db = $db;
        // Extract mysqli connection from legacy $db object for repositories
        $mysqli = $db->db_connect_id ?? $db;
        $this->repository = $repository ?? new TradingRepository($mysqli);
        $this->sharedFunctions = new \Shared($db);
        $this->season = new \Season($db);
    }

    /**
     * @see UIHelperInterface::buildTeamFutureSalary()
     */
    public function buildTeamFutureSalary($resultTeamPlayers, int $k): array
    {
        $futureSalaryArray = [
            'player' => [],
            'hold' => [],
            'picks' => []
        ];
        
        while ($rowTeamPlayers = $this->db->sql_fetch_assoc($resultTeamPlayers)) {
            $playerPosition = $rowTeamPlayers["pos"];
            $playerName = $rowTeamPlayers["name"];
            $playerPid = $rowTeamPlayers["pid"];
            $playerOrdinal = $rowTeamPlayers["ordinal"];
            $contractYear = $rowTeamPlayers["cy"];

            // Adjust contract year based on season phase
            if (
                $this->season->phase == "Playoffs"
                || $this->season->phase == "Draft"
                || $this->season->phase == "Free Agency"
            ) {
                $contractYear++;
            }
            if ($contractYear == 0) {
                $contractYear = 1;
            }

            $playerContractAmount = $rowTeamPlayers["cy$contractYear"];
            if ($contractYear == 7) {
                $playerContractAmount = 0;
            }

            // Calculate future salary commitments
            $i = 0;
            while ($contractYear < 7) {
                $futureSalaryArray['player'][$i] += $rowTeamPlayers["cy$contractYear"];
                if ($rowTeamPlayers["cy$contractYear"] > 0) {
                    $futureSalaryArray['hold'][$i]++;
                }
                $contractYear++;
                $i++;
            }

            echo $this->renderPlayerRow($k, (int) $playerPid, (int) $playerContractAmount, $playerPosition, $playerName, (int) $playerOrdinal);
            $k++;
        }

        $futureSalaryArray['k'] = $k;
        return $futureSalaryArray;
    }

    /**
     * @see UIHelperInterface::buildTeamFuturePicks()
     */
    public function buildTeamFuturePicks($resultTeamPicks, array $futureSalaryArray): array
    {
        $k = $futureSalaryArray['k'];

        while ($rowTeamDraftPicks = $this->db->sql_fetch_assoc($resultTeamPicks)) {
            $pickYear = $rowTeamDraftPicks["year"];
            $pickTeam = $rowTeamDraftPicks["teampick"];
            $pickRound = $rowTeamDraftPicks["round"];
            $pickNotes = $rowTeamDraftPicks["notes"];
            $pickId = $rowTeamDraftPicks["pickid"];

            echo $this->renderDraftPickRow($k, (int) $pickId, (int) $pickYear, $pickTeam, (int) $pickRound, $pickNotes);
            $k++;
        }

        $futureSalaryArray['k'] = $k;
        return $futureSalaryArray;
    }

    /**
     * Render a player row in the trade form
     * 
     * Generates HTML row for player with checkbox (if tradeable), position, name, and contract.
     * Players with 0 contract or on waivers are shown but not checkable.
     * 
     * @param int $k Row number for form field naming
     * @param int $playerPid Player ID
     * @param int $playerContractAmount Player contract amount
     * @param string $playerPosition Player position
     * @param string $playerName Player name
     * @param int $playerOrdinal Player ordinal (waiver status indicator)
     * @return string HTML for player row
     */
    protected function renderPlayerRow(int $k, int $playerPid, int $playerContractAmount, string $playerPosition, string $playerName, int $playerOrdinal): string
    {
        $html = "<tr>
            <input type=\"hidden\" name=\"index$k\" value=\"$playerPid\">
            <input type=\"hidden\" name=\"contract$k\" value=\"$playerContractAmount\">
            <input type=\"hidden\" name=\"type$k\" value=\"1\">";

        if ($playerContractAmount != 0 && $playerOrdinal <= \JSB::WAIVERS_ORDINAL) {
            // Player can be traded
            $html .= "<td align=\"center\"><input type=\"checkbox\" name=\"check$k\"></td>";
        } else {
            // Player cannot be traded (waived or no contract)
            $html .= "<td align=\"center\"><input type=\"hidden\" name=\"check$k\"></td>";
        }

        $html .= "
            <td>$playerPosition</td>
            <td>$playerName</td>
            <td align=\"right\">$playerContractAmount</td>
        </tr>";

        return $html;
    }

    /**
     * Render a draft pick row in the trade form
     * 
     * Generates HTML row for draft pick with checkbox, year, team, and round.
     * Includes separate row for pick notes if present. All values are HTML-escaped.
     * 
     * @param int $k Row number for form field naming
     * @param int $pickId Pick ID
     * @param int $pickYear Pick year
     * @param string $pickTeam Original team
     * @param int $pickRound Pick round
     * @param string|null $pickNotes Optional pick notes
     * @return string HTML for draft pick row(s)
     */
    protected function renderDraftPickRow(int $k, int $pickId, int $pickYear, string $pickTeam, int $pickRound, ?string $pickNotes): string
    {
        // Escape all dynamic values for HTML context to prevent XSS
        $escapedPickYear = htmlspecialchars((string)$pickYear, ENT_QUOTES, 'UTF-8');
        $escapedPickTeam = htmlspecialchars($pickTeam, ENT_QUOTES, 'UTF-8');
        $escapedPickRound = htmlspecialchars((string)$pickRound, ENT_QUOTES, 'UTF-8');
        $escapedPickId = htmlspecialchars((string)$pickId, ENT_QUOTES, 'UTF-8');
        
        $html = "<tr>
            <td align=\"center\">
                <input type=\"hidden\" name=\"index$k\" value=\"$escapedPickId\">
                <input type=\"hidden\" name=\"type$k\" value=\"0\">
                <input type=\"checkbox\" name=\"check$k\">
            </td>
            <td colspan=3>
                $escapedPickYear $escapedPickTeam Round $escapedPickRound
            </td>
        </tr>";

        if ($pickNotes != NULL) {
            $escapedPickNotes = htmlspecialchars($pickNotes, ENT_QUOTES, 'UTF-8');
            $html .= "<tr>
                <td colspan=3 width=150>$escapedPickNotes</td>
            </tr>";
        }

        return $html;
    }

    /**
     * @see UIHelperInterface::getAllTeamsForTrading()
     */
    public function getAllTeamsForTrading(): array
    {
        $teams = [];
        
        // Note: TradingRepository->getAllTeams() returns only team_name
        // We need team_city too - using legacy db temporarily
        $queryListOfAllTeams = "SELECT team_name, team_city FROM ibl_team_info ORDER BY team_city ASC";
        $resultListOfAllTeams = $this->db->sql_query($queryListOfAllTeams);

        while ($rowInListOfAllTeams = $this->db->sql_fetchrow($resultListOfAllTeams)) {
            $teamName = $rowInListOfAllTeams['team_name'];
            $teamCity = $rowInListOfAllTeams['team_city'];

            if ($teamName != 'Free Agents') {
                $teams[] = [
                    'name' => $teamName,
                    'city' => $teamCity,
                    'fullName' => "$teamCity $teamName"
                ];
            }
        }

        return $teams;
    }

    /**
     * @see UIHelperInterface::renderTeamSelectionLinks()
     */
    public function renderTeamSelectionLinks(array $teams): string
    {
        $html = '';
        foreach ($teams as $team) {
            $html .= "<a href=\"modules.php?name=Trading&op=offertrade&partner={$team['name']}\">{$team['fullName']}</a><br>";
        }
        return $html;
    }
}