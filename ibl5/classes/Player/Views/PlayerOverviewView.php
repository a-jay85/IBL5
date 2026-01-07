<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Contracts\PlayerPageViewInterface;
use Player\Player;
use Player\PlayerStats;
use Player\PlayerStatsRepository;
use Season;
use Services\CommonMysqliRepository;
use Utilities\HtmlSanitizer;

/**
 * PlayerOverviewView - Renders the player overview page
 * 
 * @see PlayerPageViewInterface
 */
class PlayerOverviewView implements PlayerPageViewInterface
{
    private Player $player;
    private PlayerStats $playerStats;
    private PlayerStatsRepository $statsRepository;
    private Season $season;
    private CommonMysqliRepository $commonRepository;
    private object $db;

    public function __construct(
        Player $player,
        PlayerStats $playerStats,
        PlayerStatsRepository $statsRepository,
        Season $season,
        CommonMysqliRepository $commonRepository,
        object $db
    ) {
        $this->player = $player;
        $this->playerStats = $playerStats;
        $this->statsRepository = $statsRepository;
        $this->season = $season;
        $this->commonRepository = $commonRepository;
        $this->db = $db;
    }

    /**
     * @see PlayerPageViewInterface::render
     */
    public function render(): string
    {
        ob_start();
        echo $this->renderRatingsTable();
        echo $this->renderFreeAgencyPreferences();
        echo $this->renderGameLog();
        return ob_get_clean();
    }

    private function renderRatingsTable(): string
    {
        $h = HtmlSanitizer::class;
        
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
        <td><?= $h::safeHtmlOutput($this->player->ratingTalent) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->ratingSkill) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->ratingIntangibles) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->ratingClutch) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->ratingConsistency) ?></td>
    </tr>
</table>
</center>
        <?php
        return ob_get_clean();
    }

    private function renderFreeAgencyPreferences(): string
    {
        $h = HtmlSanitizer::class;
        
        ob_start();
        ?>
<center>
<table>
    <tr>
        <td><b>Loyalty</b></td>
        <td><b>Play for Winner</b></td>
        <td><b>Playing Time</b></td>
        <td><b>Security</b></td>
        <td><b>Tradition</b></td>
    </tr>
    <tr align=center>
        <td><?= $h::safeHtmlOutput($this->player->freeAgencyLoyalty) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->freeAgencyPlayForWinner) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->freeAgencyPlayingTime) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->freeAgencySecurity) ?></td>
        <td><?= $h::safeHtmlOutput($this->player->freeAgencyTradition) ?></td>
    </tr>
</table>
</center>
        <?php
        return ob_get_clean();
    }

    private function renderGameLog(): string
    {
        $h = HtmlSanitizer::class;
        
        $dateRange = $this->getDateRange();
        $boxScores = $this->statsRepository->getPlayerBoxScores(
            $this->player->playerID,
            $dateRange['start'],
            $dateRange['end']
        );
        
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
    td {}
    .gamelog {text-align: center;}
</style>
        <?php
        foreach ($boxScores as $row) {
            $fg2GM = (int) $row['game2GM'];
            $fg3GM = (int) $row['game3GM'];
            $fg2GA = (int) $row['game2GA'];
            $fg3GA = (int) $row['game3GA'];
            $ftm = (int) $row['gameFTM'];
            $fta = (int) $row['gameFTA'];
            
            $fgm = $fg2GM + $fg3GM;
            $fga = $fg2GA + $fg3GA;
            $pts = (2 * $fg2GM) + (3 * $fg3GM) + $ftm;
            
            $fgPercent = $fga > 0 ? number_format($fgm / $fga, 3, '.', '') : '0.000';
            $ftPercent = $fta > 0 ? number_format($ftm / $fta, 3, '.', '') : '0.000';
            $tgPercent = $fg3GA > 0 ? number_format($fg3GM / $fg3GA, 3, '.', '') : '0.000';
            
            $awayTeam = $this->commonRepository->getTeamnameFromTeamID((int) $row['homeTID']);
            $homeTeam = $this->commonRepository->getTeamnameFromTeamID((int) $row['visitorTID']);
            ?>
    <tr>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['Date']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($awayTeam) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($homeTeam) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameMIN']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($pts) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fgm) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fga) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fgPercent) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($ftm) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fta) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($ftPercent) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fg3GM) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($fg3GA) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($tgPercent) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameORB']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameDRB']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput((int) $row['gameORB'] + (int) $row['gameDRB']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameAST']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameSTL']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameTOV']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gameBLK']) ?></td>
        <td class="gamelog"><?= $h::safeHtmlOutput($row['gamePF']) ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    private function getDateRange(): array
    {
        if ($this->season->phase === 'Preseason') {
            return [
                'start' => Season::IBL_PRESEASON_YEAR . '-' . Season::IBL_REGULAR_SEASON_STARTING_MONTH . '-01',
                'end' => (Season::IBL_PRESEASON_YEAR + 1) . '-07-01'
            ];
        } elseif ($this->season->phase === 'HEAT') {
            return [
                'start' => $this->season->beginningYear . '-' . Season::IBL_HEAT_MONTH . '-01',
                'end' => $this->season->endingYear . '-07-01'
            ];
        } else {
            return [
                'start' => $this->season->beginningYear . '-' . Season::IBL_REGULAR_SEASON_STARTING_MONTH . '-01',
                'end' => $this->season->endingYear . '-07-01'
            ];
        }
    }
}
