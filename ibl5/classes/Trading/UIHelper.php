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
    protected \Shared $sharedFunctions;
    protected \Season $season;

    public function __construct($db)
    {
        $this->db = $db;
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

            echo $this->renderPlayerRow($k, $playerPid, $playerContractAmount, $playerPosition, $playerName, $playerOrdinal);
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

            echo $this->renderDraftPickRow($k, $pickId, $pickYear, $pickTeam, $pickRound, $pickNotes);
            $k++;
        }

        $futureSalaryArray['k'] = $k;
        return $futureSalaryArray;
    }

    /**
     * Render a player row in the trade form
     * @param int $k Row number
     * @param int $playerPid Player ID
     * @param int $playerContractAmount Player contract amount
     * @param string $playerPosition Player position
     * @param string $playerName Player name
     * @param int $playerOrdinal Player ordinal (waiver status)
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
     * @param int $k Row number
     * @param int $pickId Pick ID
     * @param int $pickYear Pick year
     * @param string $pickTeam Original team
     * @param int $pickRound Pick round
     * @param string|null $pickNotes Pick notes
     * @return string HTML for draft pick row
     */
    protected function renderDraftPickRow(int $k, int $pickId, int $pickYear, string $pickTeam, int $pickRound, ?string $pickNotes): string
    {
        $html = "<tr>
            <td align=\"center\">
                <input type=\"hidden\" name=\"index$k\" value=\"$pickId\">
                <input type=\"hidden\" name=\"type$k\" value=\"0\">
                <input type=\"checkbox\" name=\"check$k\">
            </td>
            <td colspan=3>
                $pickYear $pickTeam Round $pickRound
            </td>
        </tr>";

        if ($pickNotes != NULL) {
            $html .= "<tr>
                <td colspan=3 width=150>$pickNotes</td>
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