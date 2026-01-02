<?php

use Player\Player;
use Services\DatabaseService;

require_once __DIR__ . '/BaseView.php';

class OverviewView extends BaseView {
    private $season;
    private $sharedFunctions;
    private $commonRepository;
    private $mysqli_db;

    public function __construct($db, Player $player, PlayerStats $playerStats, Season $season, Shared $sharedFunctions) {
        parent::__construct($db, $player, $playerStats);
        $this->season = $season;
        $this->sharedFunctions = $sharedFunctions;
        global $mysqli_db;
        $this->mysqli_db = $mysqli_db;
        $this->commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    }

    public function render() {
        echo "<center>
        <table>
            <tr align=center>
                <td><b>Talent</b></td>
                <td><b>Skill</b></td>
                <td><b>Intangibles</b></td>
                <td><b>Clutch</b></td>
                <td><b>Consistency</b></td>
            </tr>
            <tr align=center>
                <td>" . $this->player->ratingTalent . "</td>
                <td>" . $this->player->ratingSkill . "</td>
                <td>" . $this->player->ratingIntangibles . "</td>
                <td>" . $this->player->ratingClutch . "</td>
                <td>" . $this->player->ratingConsistency . "</td>
            </tr>
        </table>
        <table>
            <tr>
                <td><b>Loyalty</b></td>
                <td><b>Play for Winner</b></td>
                <td><b>Playing Time</b></td>
                <td><b>Security</b></td>
                <td><b>Tradition</b></td>
            </tr>
            <tr align=center>
                <td>" . $this->player->freeAgencyLoyalty . "</td>
                <td>" . $this->player->freeAgencyPlayForWinner . "</td>
                <td>" . $this->player->freeAgencyPlayingTime . "</td>
                <td>" . $this->player->freeAgencySecurity . "</td>
                <td>" . $this->player->freeAgencyTradition . "</td>
            </tr>
        </table>
        </center>";

        if ($this->season->phase == "Preseason") {
            $startDate = Season::IBL_PRESEASON_YEAR . "-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = (Season::IBL_PRESEASON_YEAR + 1) . "-07-01";
        } elseif ($this->season->phase == "HEAT") {
            $startDate = $this->season->beginningYear . "-" . Season::IBL_HEAT_MONTH . "-01";
            $endDate = $this->season->endingYear . "-07-01";
        } else {
            $startDate = $this->season->beginningYear . "-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = $this->season->endingYear . "-07-01";
        }
        
        global $leagueContext;
        $table = isset($leagueContext) ? $leagueContext->getTableName('ibl_box_scores') : 'ibl_box_scores';
        
        $stmt = $this->mysqli_db->prepare("SELECT * FROM {$table} WHERE Date BETWEEN ? AND ? AND pid = ? ORDER BY Date ASC");
        $stmt->bind_param("ssi", $startDate, $endDate, $this->player->playerID);
        $stmt->execute();
        $result = $stmt->get_result();

        echo "<p>
            <H1><center>GAME LOG</center></H1>
            <p>
            <table class=\"sortable\">
                <tr>
                    <th>Date</th>
                    <th>Away</th>
                    <th>Home</th>
                    <th>MIN</th>
                    <th>PTS</th>
                    <th>FGM</th>
                    <th>FGA</th>
                    <th>FG%</th>
                    <th>FTM</th>
                    <th>FTA</th>
                    <th>FT%</th>
                    <th>3GM</th>
                    <th>3GA</th>
                    <th>3G%</th>
                    <th>ORB</th>
                    <th>DRB</th>
                    <th>REB</th>
                    <th>AST</th>
                    <th>STL</th>
                    <th>TO</th>
                    <th>BLK</th>
                    <th>PF</th>
                </tr>";

        echo "<style>
            td {}
            .gamelog {text-align: center;}
        </style>";

        while ($row = $result->fetch_assoc()) {
            $fieldGoalPercentage = ($row['game2GA'] + $row['game3GA']) ? number_format(($row['game2GM'] + $row['game3GM']) / ($row['game2GA'] + $row['game3GA']), 3, '.', '') : "0.000";
            $freeThrowPercentage = ($row['gameFTA']) ? number_format($row['gameFTM'] / $row['gameFTA'], 3, '.', '') : "0.000";
            $threePointPercentage = ($row['game3GA']) ? number_format($row['game3GM'] / $row['game3GA'], 3, '.', '') : "0.000";

            echo "<tr>
                <td class=\"gamelog\">" . $row['Date'] . "</td>
                <td class=\"gamelog\">" . $this->commonRepository->getTeamnameFromTeamID($row['homeTID']) . "</td>
                <td class=\"gamelog\">" . $this->commonRepository->getTeamnameFromTeamID($row['visitorTID']) . "</td>
                <td class=\"gamelog\">" . $row['gameMIN'] . "</td>
                <td class=\"gamelog\">" . ((2 * $row['game2GM']) + (3 * $row['game3GM']) + $row['gameFTM']) . "</td>
                <td class=\"gamelog\">" . ($row['game2GM'] + $row['game3GM']) . "</td>
                <td class=\"gamelog\">" . ($row['game2GA'] + $row['game3GA']) . "</td>
                <td class=\"gamelog\">" . $fieldGoalPercentage . "</td>
                <td class=\"gamelog\">" . $row['gameFTM'] . "</td>
                <td class=\"gamelog\">" . $row['gameFTA'] . "</td>
                <td class=\"gamelog\">" . $freeThrowPercentage . "</td>
                <td class=\"gamelog\">" . $row['game3GM'] . "</td>
                <td class=\"gamelog\">" . $row['game3GA'] . "</td>
                <td class=\"gamelog\">" . $threePointPercentage . "</td>
                <td class=\"gamelog\">" . $row['gameORB'] . "</td>
                <td class=\"gamelog\">" . $row['gameDRB'] . "</td>
                <td class=\"gamelog\">" . ($row['gameORB'] + $row['gameDRB']) . "</td>
                <td class=\"gamelog\">" . $row['gameAST'] . "</td>
                <td class=\"gamelog\">" . $row['gameSTL'] . "</td>
                <td class=\"gamelog\">" . $row['gameTOV'] . "</td>
                <td class=\"gamelog\">" . $row['gameBLK'] . "</td>
                <td class=\"gamelog\">" . $row['gamePF'] . "</td>
            </tr>";
        }
        echo "</table>";
    }
}