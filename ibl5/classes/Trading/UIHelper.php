<?php

class Trading_UIHelper
{
    protected $db;
    protected $sharedFunctions;
    protected $season;

    public function __construct($db)
    {
        $this->db = $db;
        $this->sharedFunctions = new Shared($db);
        $this->season = new Season($db);
    }

    /**
     * Build team future salary data and HTML for trade form
     * @param resource $resultTeamPlayers Database result for team players
     * @param int $k Counter for form field numbering
     * @return array Future salary array and updated counter
     */
    public function buildTeamFutureSalary($resultTeamPlayers, $k)
    {
        $future_salary_array = [
            'player' => [],
            'hold' => [],
            'picks' => []
        ];
        
        while ($rowTeamPlayers = $this->db->sql_fetch_assoc($resultTeamPlayers)) {
            $player_pos = $rowTeamPlayers["pos"];
            $player_name = $rowTeamPlayers["name"];
            $player_pid = $rowTeamPlayers["pid"];
            $player_ordinal = $rowTeamPlayers["ordinal"];
            $contract_year = $rowTeamPlayers["cy"];

            // Adjust contract year based on season phase
            if (
                $this->season->phase == "Playoffs"
                || $this->season->phase == "Draft"
                || $this->season->phase == "Free Agency"
            ) {
                $contract_year++;
            }
            if ($contract_year == 0) {
                $contract_year = 1;
            }

            $player_contract = $rowTeamPlayers["cy$contract_year"];
            if ($contract_year == 7) {
                $player_contract = 0;
            }

            // Calculate future salary commitments
            $i = 0;
            while ($contract_year < 7) {
                $future_salary_array['player'][$i] += $rowTeamPlayers["cy$contract_year"];
                if ($rowTeamPlayers["cy$contract_year"] > 0) {
                    $future_salary_array['hold'][$i]++;
                }
                $contract_year++;
                $i++;
            }

            echo $this->renderPlayerRow($k, $player_pid, $player_contract, $player_pos, $player_name, $player_ordinal);
            $k++;
        }

        $future_salary_array['k'] = $k;
        return $future_salary_array;
    }

    /**
     * Build team future draft picks data and HTML for trade form
     * @param resource $resultTeamPicks Database result for team draft picks
     * @param array $future_salary_array Existing future salary array
     * @return array Updated future salary array
     */
    public function buildTeamFuturePicks($resultTeamPicks, $future_salary_array)
    {
        $k = $future_salary_array['k'];

        while ($rowTeamDraftPicks = $this->db->sql_fetch_assoc($resultTeamPicks)) {
            $pick_year = $rowTeamDraftPicks["year"];
            $pick_team = $rowTeamDraftPicks["teampick"];
            $pick_round = $rowTeamDraftPicks["round"];
            $pick_notes = $rowTeamDraftPicks["notes"];
            $pick_id = $rowTeamDraftPicks["pickid"];

            echo $this->renderDraftPickRow($k, $pick_id, $pick_year, $pick_team, $pick_round, $pick_notes);
            $k++;
        }

        $future_salary_array['k'] = $k;
        return $future_salary_array;
    }

    /**
     * Render a player row in the trade form
     * @param int $k Row number
     * @param int $player_pid Player ID
     * @param int $player_contract Player contract amount
     * @param string $player_pos Player position
     * @param string $player_name Player name
     * @param int $player_ordinal Player ordinal (waiver status)
     * @return string HTML for player row
     */
    protected function renderPlayerRow($k, $player_pid, $player_contract, $player_pos, $player_name, $player_ordinal)
    {
        $html = "<tr>
            <input type=\"hidden\" name=\"index$k\" value=\"$player_pid\">
            <input type=\"hidden\" name=\"contract$k\" value=\"$player_contract\">
            <input type=\"hidden\" name=\"type$k\" value=\"1\">";

        if ($player_contract != 0 && $player_ordinal <= JSB::WAIVERS_ORDINAL) {
            // Player can be traded
            $html .= "<td align=\"center\"><input type=\"checkbox\" name=\"check$k\"></td>";
        } else {
            // Player cannot be traded (waived or no contract)
            $html .= "<td align=\"center\"><input type=\"hidden\" name=\"check$k\"></td>";
        }

        $html .= "
            <td>$player_pos</td>
            <td>$player_name</td>
            <td align=\"right\">$player_contract</td>
        </tr>";

        return $html;
    }

    /**
     * Render a draft pick row in the trade form
     * @param int $k Row number
     * @param int $pick_id Pick ID
     * @param int $pick_year Pick year
     * @param string $pick_team Original team
     * @param int $pick_round Pick round
     * @param string $pick_notes Pick notes
     * @return string HTML for draft pick row
     */
    protected function renderDraftPickRow($k, $pick_id, $pick_year, $pick_team, $pick_round, $pick_notes)
    {
        $html = "<tr>
            <td align=\"center\">
                <input type=\"hidden\" name=\"index$k\" value=\"$pick_id\">
                <input type=\"hidden\" name=\"type$k\" value=\"0\">
                <input type=\"checkbox\" name=\"check$k\">
            </td>
            <td colspan=3>
                $pick_year $pick_team Round $pick_round
            </td>
        </tr>";

        if ($pick_notes != NULL) {
            $html .= "<tr>
                <td colspan=3 width=150>$pick_notes</td>
            </tr>";
        }

        return $html;
    }

    /**
     * Get list of all teams for partner selection dropdown
     * @return array Array of team data
     */
    public function getAllTeamsForTrading()
    {
        $teams = [];
        $queryListOfAllTeams = "SELECT team_name, team_city FROM ibl_team_info ORDER BY team_city ASC";
        $resultListOfAllTeams = $this->db->sql_query($queryListOfAllTeams);

        while ($rowInListOfAllTeams = $this->db->sql_fetchrow($resultListOfAllTeams)) {
            $team_name = $rowInListOfAllTeams['team_name'];
            $team_city = $rowInListOfAllTeams['team_city'];

            if ($team_name != 'Free Agents') {
                $teams[] = [
                    'name' => $team_name,
                    'city' => $team_city,
                    'full_name' => "$team_city $team_name"
                ];
            }
        }

        return $teams;
    }

    /**
     * Render team selection links for trading
     * @param array $teams Array of team data
     * @return string HTML for team selection links
     */
    public function renderTeamSelectionLinks($teams)
    {
        $html = '';
        foreach ($teams as $team) {
            $html .= "<a href=\"modules.php?name=Trading&op=offertrade&partner={$team['name']}\">{$team['full_name']}</a><br>";
        }
        return $html;
    }
}