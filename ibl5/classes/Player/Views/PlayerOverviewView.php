<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerRepository;
use Player\PlayerStats;
use Player\Contracts\PlayerOverviewViewInterface;
use Services\CommonMysqliRepository;
use BasketballStats\StatsFormatter;

/**
 * PlayerOverviewView - Renders the player overview page
 * 
 * Displays player ratings, free agency preferences, and current season game log.
 * Uses repositories for all database access - no inline queries.
 * 
 * @see PlayerOverviewViewInterface
 */
class PlayerOverviewView implements PlayerOverviewViewInterface
{
    private PlayerRepository $playerRepository;
    private CommonMysqliRepository $commonRepository;

    public function __construct(
        PlayerRepository $playerRepository,
        CommonMysqliRepository $commonRepository
    ) {
        $this->playerRepository = $playerRepository;
        $this->commonRepository = $commonRepository;
    }

    /**
     * @see PlayerViewInterface::render()
     */
    public function render(): string
    {
        // This method requires context - use renderOverview() instead
        return '';
    }

    /**
     * @see PlayerOverviewViewInterface::renderOverview()
     */
    public function renderOverview(
        int $playerID,
        Player $player,
        PlayerStats $playerStats,
        \Season $season,
        \Shared $sharedFunctions
    ): string {
        // Calculate date range based on season phase
        if ($season->phase === "Preseason") {
            $startDate = \Season::IBL_PRESEASON_YEAR . "-" . \Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = (\Season::IBL_PRESEASON_YEAR + 1) . "-07-01";
        } elseif ($season->phase === "HEAT") {
            $startDate = $season->beginningYear . "-" . \Season::IBL_HEAT_MONTH . "-01";
            $endDate = $season->endingYear . "-07-01";
        } else {
            $startDate = $season->beginningYear . "-" . \Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = $season->endingYear . "-07-01";
        }
        
        ob_start();
        
        // Render ratings table
        echo $this->renderRatingsTable($player);
        
        // Render free agency preferences table
        echo $this->renderFreeAgencyPreferences($player);
        
        // Render game log
        echo $this->renderGameLog($playerID, $startDate, $endDate);
        
        return ob_get_clean();
    }

    /**
     * Render player ratings table
     */
    private function renderRatingsTable(Player $player): string
    {
        ob_start();
        ?>
<center>
<table>
    <tr align=center>
        <td><b>Talent</b></td>
        <td><b>Skill</b></td>
        <td><b>Intangibles</b></td>
        <td><b>Clutch</b></td>
        <td><b>Consistency</b></td>
    </tr>
    <tr align=center>
        <td><?= htmlspecialchars((string)$player->ratingTalent) ?></td>
        <td><?= htmlspecialchars((string)$player->ratingSkill) ?></td>
        <td><?= htmlspecialchars((string)$player->ratingIntangibles) ?></td>
        <td><?= htmlspecialchars((string)$player->ratingClutch) ?></td>
        <td><?= htmlspecialchars((string)$player->ratingConsistency) ?></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render free agency preferences table
     */
    private function renderFreeAgencyPreferences(Player $player): string
    {
        ob_start();
        ?>
<table>
    <tr>
        <td><b>Loyalty</b></td>
        <td><b>Play for Winner</b></td>
        <td><b>Playing Time</b></td>
        <td><b>Security</b></td>
        <td><b>Tradition</b></td>
    </tr>
    <tr align=center>
        <td><?= htmlspecialchars((string)$player->freeAgencyLoyalty) ?></td>
        <td><?= htmlspecialchars((string)$player->freeAgencyPlayForWinner) ?></td>
        <td><?= htmlspecialchars((string)$player->freeAgencyPlayingTime) ?></td>
        <td><?= htmlspecialchars((string)$player->freeAgencySecurity) ?></td>
        <td><?= htmlspecialchars((string)$player->freeAgencyTradition) ?></td>
    </tr>
</table>
</center>
        <?php
        return ob_get_clean();
    }

    /**
     * Render game log table
     */
    private function renderGameLog(int $playerID, string $startDate, string $endDate): string
    {
        $boxScores = $this->playerRepository->getBoxScoresBetweenDates($playerID, $startDate, $endDate);

        ob_start();
        ?>
<p>
<H1><center>GAME LOG</center></H1>
<p>
<table class="sortable">
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
    </tr>
<style>
    .gamelog {text-align: center;}
</style>
        <?php
        foreach ($boxScores as $row) {
            $fgm = $row['game2GM'] + $row['game3GM'];
            $fga = $row['game2GA'] + $row['game3GA'];
            $pts = (2 * $row['game2GM']) + (3 * $row['game3GM']) + $row['gameFTM'];
            $reb = $row['gameORB'] + $row['gameDRB'];
            
            $fgPct = StatsFormatter::formatPercentage($fgm, $fga);
            $ftPct = StatsFormatter::formatPercentage($row['gameFTM'], $row['gameFTA']);
            $tgPct = StatsFormatter::formatPercentage($row['game3GM'], $row['game3GA']);
            
            $awayTeam = $this->commonRepository->getTeamnameFromTeamID($row['homeTID']);
            $homeTeam = $this->commonRepository->getTeamnameFromTeamID($row['visitorTID']);
            ?>
    <tr>
        <td class="gamelog"><?= htmlspecialchars($row['Date']) ?></td>
        <td class="gamelog"><?= htmlspecialchars($awayTeam) ?></td>
        <td class="gamelog"><?= htmlspecialchars($homeTeam) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameMIN']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$pts) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$fgm) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$fga) ?></td>
        <td class="gamelog"><?= htmlspecialchars($fgPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameFTM']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameFTA']) ?></td>
        <td class="gamelog"><?= htmlspecialchars($ftPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['game3GM']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['game3GA']) ?></td>
        <td class="gamelog"><?= htmlspecialchars($tgPct) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameORB']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameDRB']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$reb) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameAST']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameSTL']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameTOV']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gameBLK']) ?></td>
        <td class="gamelog"><?= htmlspecialchars((string)$row['gamePF']) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }
}
