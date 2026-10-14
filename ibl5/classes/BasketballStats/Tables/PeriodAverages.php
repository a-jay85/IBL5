<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use BasketballStats\StatsFormatter;
use Player\PlayerImageHelper;
use TeamOffDefStats\TeamOffDefStatsRepository;
use Security\HtmlSanitizer;
use Team\SplitStatsRepository;
use Team\Team;
use Season\Season;

/**
 * PeriodAverages - Displays period (simulation) averages statistics table
 */
class PeriodAverages
{
    /**
     * Render the period averages table
     *
     * @param \mysqli $db Database connection
     * @param Team $team Team object
     * @param Season $season Season object
     * @param string|null|\DateTime $startDate Start date for the period (defaults to last sim)
     * @param string|null|\DateTime $endDate End date for the period (defaults to last sim)
     * @param list<int> $starterPids Starter player IDs
     * @param list<int> $pidFilter When non-empty, only include these player PIDs
     * @return string HTML table
     * @throws \Exception If database connection is invalid
     */
    public static function render(\mysqli $db, $team, $season, $startDate = null, $endDate = null, array $starterPids = [], array $pidFilter = []): string
    {
        if ($startDate === null && $endDate === null) {
            // default to last simulated period
            $startDate = $season->lastSimStartDate;
            $endDate = $season->lastSimEndDate;
        }

        // convert to Y-m-d format if DateTime object
        if ($startDate instanceof \DateTime) {
            $startDate = $startDate->format('Y-m-d');
        }
        if ($endDate instanceof \DateTime) {
            $endDate = $endDate->format('Y-m-d');
        }

        $offDefRepo = new TeamOffDefStatsRepository($db);
        $bothStats = $offDefRepo->getTeamBothStatsForDateRange($team->name, (string) $startDate, (string) $endDate);

        $teamid = (int)$team->teamid;

        $splitStatsRepository = new SplitStatsRepository($db);
        $resultPlayerSimBoxScores = $splitStatsRepository->getPeriodStats($teamid, is_string($startDate) ? $startDate : null, is_string($endDate) ? $endDate : null, $pidFilter);

        /** @var list<array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string}> $playerRows */
        $playerRows = [];

        foreach ($resultPlayerSimBoxScores as $dbRow) {
            $playerRows[] = [
                'name' => (string) ($dbRow['name'] ?? ''),
                'pos' => (string) $dbRow['pos'],
                'pid' => (int) $dbRow['pid'],
                'games' => $dbRow['games'],
                'min' => $dbRow['gameMINavg'],
                'fgm' => $dbRow['gameFGMavg'],
                'fga' => $dbRow['gameFGAavg'],
                'fgp' => $dbRow['gameFGPavg'] ?? '0.000',
                'ftm' => $dbRow['gameFTMavg'],
                'fta' => $dbRow['gameFTAavg'],
                'ftp' => $dbRow['gameFTPavg'] ?? '0.000',
                'tgm' => $dbRow['game3GMavg'],
                'tga' => $dbRow['game3GAavg'],
                'tgp' => $dbRow['game3GPavg'] ?? '0.000',
                'orb' => $dbRow['gameORBavg'],
                'reb' => $dbRow['gameREBavg'],
                'ast' => $dbRow['gameASTavg'],
                'stl' => $dbRow['gameSTLavg'],
                'tov' => $dbRow['gameTOVavg'],
                'blk' => $dbRow['gameBLKavg'],
                'pf' => $dbRow['gamePFavg'],
                'pts' => $dbRow['gamePTSavg'],
            ];
        }

        ob_start();
        // "\n" restores the template line break: PHP swallows the newline right after the closing PHP tag.
        // @phpstan-ignore ibl.trustedVariable (openTag() escapes the aria-label and sanitizes the colors)
        echo HtmlSanitizer::trusted(TeamStatsTableContext::openTag($team)), "\n";
        ?>
    <thead>
        <tr>
            <th>Pos</th>
            <th class="sticky-col">Player</th>
            <th>g</th>
            <th class="sep-r-team">min</th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th>3gm</th>
            <th>3ga</th>
            <th class="sep-r-team">3gp</th>
            <th>orb</th>
            <th>reb</th>
            <th>ast</th>
            <th>stl</th>
            <th>to</th>
            <th>blk</th>
            <th>pf</th>
            <th>pts</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($playerRows as $row): ?>
        <tr>
            <td><?= HtmlSanitizer::e($row['pos']) ?></td>
            <?= PlayerImageHelper::renderPlayerCell($row['pid'], $row['name'], $starterPids) ?>
            <td><?= (int)$row['games'] ?></td>
            <td class="sep-r-team"><?= $row['min'] ?></td>
            <td><?= $row['fgm'] ?></td>
            <td><?= $row['fga'] ?></td>
            <td class="sep-r-weak"><?= $row['fgp'] ?></td>
            <td><?= $row['ftm'] ?></td>
            <td><?= $row['fta'] ?></td>
            <td class="sep-r-weak"><?= $row['ftp'] ?></td>
            <td><?= $row['tgm'] ?></td>
            <td><?= $row['tga'] ?></td>
            <td class="sep-r-team"><?= $row['tgp'] ?></td>
            <td><?= $row['orb'] ?></td>
            <td><?= $row['reb'] ?></td>
            <td><?= $row['ast'] ?></td>
            <td><?= $row['stl'] ?></td>
            <td><?= $row['tov'] ?></td>
            <td><?= $row['blk'] ?></td>
            <td><?= $row['pf'] ?></td>
            <td><?= $row['pts'] ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($bothStats !== null):
    $off = $bothStats['offense'];
    $def = $bothStats['defense'];
    $offGames = $off['games'];
    $defGames = $def['games'];
    $offPts = StatsFormatter::calculatePoints($off['fgm'], $off['ftm'], $off['tgm']);
    $defPts = StatsFormatter::calculatePoints($def['fgm'], $def['ftm'], $def['tgm']);
?>
        <tr>
            <td colspan="2"><?= HtmlSanitizer::e($team->name) ?> Offense</td>
            <td><?= $offGames ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fgm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fga'], $offGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($off['fgm'], $off['fga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['ftm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['fta'], $offGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($off['ftm'], $off['fta']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tgm'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tga'], $offGames) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatPercentage($off['tgm'], $off['tga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['orb'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['reb'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['ast'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['stl'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['tvr'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['blk'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($off['pf'], $offGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($offPts, $offGames) ?></td>
        </tr>
        <tr>
            <td colspan="2"><?= HtmlSanitizer::e($team->name) ?> Defense</td>
            <td><?= $defGames ?></td>
            <td class="sep-r-team"></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fgm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fga'], $defGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($def['fgm'], $def['fga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['ftm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['fta'], $defGames) ?></td>
            <td class="sep-r-weak"><?= StatsFormatter::formatPercentage($def['ftm'], $def['fta']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tgm'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tga'], $defGames) ?></td>
            <td class="sep-r-team"><?= StatsFormatter::formatPercentage($def['tgm'], $def['tga']) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['orb'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['reb'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['ast'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['stl'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['tvr'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['blk'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($def['pf'], $defGames) ?></td>
            <td><?= StatsFormatter::formatPerGameAverage($defPts, $defGames) ?></td>
        </tr>
<?php endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
