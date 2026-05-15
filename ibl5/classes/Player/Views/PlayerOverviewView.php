<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStatsRepository;
use Player\PlayerStats;
use Player\Contracts\PlayerOverviewViewInterface;
use Services\CommonMysqliRepository;
use BasketballStats\StatsFormatter;
use Security\HtmlSanitizer;
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
        
        // Render game log with stats card styling
        $cardHtml = PlayerStatsCardView::render($this->renderGameLog($playerID, $startDate, $endDate), '', $colorScheme);
        return '<tr><td colspan="2">' . $cardHtml . '</td></tr>';
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

            $awayTeam = $this->commonRepository->getTeamnameFromTeamID($row['home_teamid']);
            $homeTeam = $this->commonRepository->getTeamnameFromTeamID($row['visitor_teamid']);
            $boxScoreUrl = \Utilities\BoxScoreUrlBuilder::buildUrl($row['game_date'], (int) ($row['game_of_that_day'] ?? 0), (int) ($row['box_id'] ?? 0));
            ?>
    <tr>
        <td class="gamelog"><?php if ($boxScoreUrl !== ''): ?><a href="<?= HtmlSanitizer::e($boxScoreUrl) ?>"><?= HtmlSanitizer::e($row['game_date']) ?></a><?php else: ?><?= HtmlSanitizer::e($row['game_date']) ?><?php endif; ?></td>
        <td class="gamelog"><?= HtmlSanitizer::e($awayTeam) ?></td>
        <td class="gamelog"><?= HtmlSanitizer::e($homeTeam) ?></td>
        <td class="gamelog"><?= (int) $row['game_min'] ?></td>
        <td class="gamelog"><?= (int) $pts ?></td>
        <td class="gamelog"><?= (int) $fgm ?></td>
        <td class="gamelog"><?= (int) $fga ?></td>
        <td class="gamelog"><?= HtmlSanitizer::e(StatsFormatter::formatPercentage($fgm, $fga)) ?></td>
        <td class="gamelog"><?= (int) $row['game_ftm'] ?></td>
        <td class="gamelog"><?= (int) $row['game_fta'] ?></td>
        <td class="gamelog"><?= HtmlSanitizer::e(StatsFormatter::formatPercentage($row['game_ftm'], $row['game_fta'])) ?></td>
        <td class="gamelog"><?= (int) $row['game_3gm'] ?></td>
        <td class="gamelog"><?= (int) $row['game_3ga'] ?></td>
        <td class="gamelog"><?= HtmlSanitizer::e(StatsFormatter::formatPercentage($row['game_3gm'], $row['game_3ga'])) ?></td>
        <td class="gamelog"><?= (int) $row['game_orb'] ?></td>
        <td class="gamelog"><?= (int) $row['game_drb'] ?></td>
        <td class="gamelog"><?= (int) $reb ?></td>
        <td class="gamelog"><?= (int) $row['game_ast'] ?></td>
        <td class="gamelog"><?= (int) $row['game_stl'] ?></td>
        <td class="gamelog"><?= (int) $row['game_tov'] ?></td>
        <td class="gamelog"><?= (int) $row['game_blk'] ?></td>
        <td class="gamelog"><?= (int) $row['game_pf'] ?></td>
    </tr>
            <?php
        }
        ?>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
