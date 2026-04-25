<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStatsRepository;
use Player\PlayerStats;
use Player\Contracts\PlayerOverviewViewInterface;
use Services\CommonMysqliRepository;
use BasketballStats\StatsFormatter;
use Utilities\HtmlSanitizer;
use Season\Season;

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
    private PlayerStatsRepository $statsRepository;
    private CommonMysqliRepository $commonRepository;

    public function __construct(
        PlayerStatsRepository $statsRepository,
        CommonMysqliRepository $commonRepository
    ) {
        $this->statsRepository = $statsRepository;
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
        Season $season,
        \Shared\Contracts\SharedRepositoryInterface $sharedRepository,
        ?array $colorScheme = null
    ): string {
        // Calculate date range based on season phase
        if ($season->phase === "Preseason") {
            $startDate = $season->beginningYear . "-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = $season->endingYear . "-07-01";
        } elseif ($season->phase === "HEAT") {
            $startDate = $season->beginningYear . "-" . Season::IBL_HEAT_MONTH . "-01";
            $endDate = $season->endingYear . "-07-01";
        } else {
            $startDate = $season->beginningYear . "-" . Season::IBL_REGULAR_SEASON_STARTING_MONTH . "-01";
            $endDate = $season->endingYear . "-07-01";
        }
        
        ob_start();
        
        // Render game log with stats card styling
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::render($this->renderGameLog($playerID, $startDate, $endDate), '', $colorScheme);
        echo '</td></tr>';
        
        return (string) ob_get_clean();
    }

    /**
     * Render game log table
     */
    private function renderGameLog(int $playerID, string $startDate, string $endDate): string
    {
        $boxScores = $this->statsRepository->getBoxScoresBetweenDates($playerID, $startDate, $endDate);

        ob_start();
        ?>
<table class="sortable player-table">
    <tr>
        <td colspan=22 class="player-table-header">Game Log</td>
    </tr>
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
        <?php
        foreach ($boxScores as $row) {
            /** @var array{game_date: string, home_teamid: int, visitor_teamid: int, game_of_that_day: int, box_id: int, game_min: int, game_2gm: int, game_2ga: int, game_3gm: int, game_3ga: int, game_ftm: int, game_fta: int, game_orb: int, game_drb: int, game_ast: int, game_stl: int, game_tov: int, game_blk: int, game_pf: int} $row */
            $fgm = $row['game_2gm'] + $row['game_3gm'];
            $fga = $row['game_2ga'] + $row['game_3ga'];
            $pts = (2 * $row['game_2gm']) + (3 * $row['game_3gm']) + $row['game_ftm'];
            $reb = $row['game_orb'] + $row['game_drb'];

            $fgPct = StatsFormatter::formatPercentage($fgm, $fga);
            $ftPct = StatsFormatter::formatPercentage($row['game_ftm'], $row['game_fta']);
            $tgPct = StatsFormatter::formatPercentage($row['game_3gm'], $row['game_3ga']);

            $awayTeam = $this->commonRepository->getTeamnameFromTeamID($row['home_teamid']);
            $homeTeam = $this->commonRepository->getTeamnameFromTeamID($row['visitor_teamid']);
            $safeDate = HtmlSanitizer::safeHtmlOutput($row['game_date']);
            $boxScoreUrl = \Utilities\BoxScoreUrlBuilder::buildUrl($row['game_date'], (int) ($row['game_of_that_day'] ?? 0), (int) ($row['box_id'] ?? 0));
            $safeBoxScoreUrl = HtmlSanitizer::safeHtmlOutput($boxScoreUrl);
            $safeAwayTeam = HtmlSanitizer::safeHtmlOutput($awayTeam);
            $safeHomeTeam = HtmlSanitizer::safeHtmlOutput($homeTeam);
            ?>
    <tr>
        <td class="gamelog"><?php if ($boxScoreUrl !== ''): ?><a href="<?= $safeBoxScoreUrl ?>"><?= $safeDate ?></a><?php else: ?><?= $safeDate ?><?php endif; ?></td>
        <td class="gamelog"><?= $safeAwayTeam ?></td>
        <td class="gamelog"><?= $safeHomeTeam ?></td>
        <td class="gamelog"><?= $row['game_min'] ?></td>
        <td class="gamelog"><?= $pts ?></td>
        <td class="gamelog"><?= $fgm ?></td>
        <td class="gamelog"><?= $fga ?></td>
        <td class="gamelog"><?= $fgPct ?></td>
        <td class="gamelog"><?= $row['game_ftm'] ?></td>
        <td class="gamelog"><?= $row['game_fta'] ?></td>
        <td class="gamelog"><?= $ftPct ?></td>
        <td class="gamelog"><?= $row['game_3gm'] ?></td>
        <td class="gamelog"><?= $row['game_3ga'] ?></td>
        <td class="gamelog"><?= $tgPct ?></td>
        <td class="gamelog"><?= $row['game_orb'] ?></td>
        <td class="gamelog"><?= $row['game_drb'] ?></td>
        <td class="gamelog"><?= $reb ?></td>
        <td class="gamelog"><?= $row['game_ast'] ?></td>
        <td class="gamelog"><?= $row['game_stl'] ?></td>
        <td class="gamelog"><?= $row['game_tov'] ?></td>
        <td class="gamelog"><?= $row['game_blk'] ?></td>
        <td class="gamelog"><?= $row['game_pf'] ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
