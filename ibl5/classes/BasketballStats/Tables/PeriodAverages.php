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
 *
 * @phpstan-import-type TeamOffenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from \TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface
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

        /** @var list<array{name: string, pos: string, pid: int, games: int, min: string|null, fgm: string|null, fga: string|null, fgp: string, ftm: string|null, fta: string|null, ftp: string, tgm: string|null, tga: string|null, tgp: string, orb: string|null, reb: string|null, ast: string|null, stl: string|null, tov: string|null, blk: string|null, pf: string|null, pts: string|null}> $playerRows */
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
        echo self::renderHeader();
        ?>
    <tbody>
<?php foreach ($playerRows as $row):
    echo self::renderPlayerRow($row, $starterPids);
endforeach; ?>
    </tbody>
    <tfoot>
<?php if ($bothStats !== null):
    echo self::renderTeamFooterRow($team, 'Offense', $bothStats['offense']);
    echo self::renderTeamFooterRow($team, 'Defense', $bothStats['defense']);
endif; ?>
    </tfoot>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * The `<thead>` block, byte-for-byte the former inline template.
     */
    private static function renderHeader(): string
    {
        return "    <thead>\n"
            . "        <tr>\n"
            . "            <th>Pos</th>\n"
            . '            <th class="sticky-col">Player</th>' . "\n"
            . "            <th>g</th>\n"
            . '            <th class="sep-r-team">min</th>' . "\n"
            . "            <th>fgm</th>\n"
            . "            <th>fga</th>\n"
            . "            <th>fgp</th>\n"
            . "            <th>ftm</th>\n"
            . "            <th>fta</th>\n"
            . "            <th>ftp</th>\n"
            . "            <th>3gm</th>\n"
            . "            <th>3ga</th>\n"
            . '            <th class="sep-r-team">3gp</th>' . "\n"
            . "            <th>orb</th>\n"
            . "            <th>reb</th>\n"
            . "            <th>ast</th>\n"
            . "            <th>stl</th>\n"
            . "            <th>to</th>\n"
            . "            <th>blk</th>\n"
            . "            <th>pf</th>\n"
            . "            <th>pts</th>\n"
            . "        </tr>\n"
            . "    </thead>\n";
    }

    /**
     * One `<tbody>` row. The player cell is followed by the games cell on the same line with
     * no line break between them: the former template's `?>` swallowed that newline.
     *
     * @param array{name: string, pos: string, pid: int, games: int, min: string|null, fgm: string|null, fga: string|null, fgp: string, ftm: string|null, fta: string|null, ftp: string, tgm: string|null, tga: string|null, tgp: string, orb: string|null, reb: string|null, ast: string|null, stl: string|null, tov: string|null, blk: string|null, pf: string|null, pts: string|null} $row
     * @param list<int> $starterPids
     */
    private static function renderPlayerRow(array $row, array $starterPids): string
    {
        return "        <tr>\n"
            . '            <td>' . HtmlSanitizer::e($row['pos']) . "</td>\n"
            . '            ' . PlayerImageHelper::renderPlayerCell($row['pid'], $row['name'], $starterPids)
            . '            <td>' . (int)$row['games'] . "</td>\n"
            . '            <td class="sep-r-team">' . $row['min'] . "</td>\n"
            . '            <td>' . $row['fgm'] . "</td>\n"
            . '            <td>' . $row['fga'] . "</td>\n"
            . '            <td class="sep-r-weak">' . $row['fgp'] . "</td>\n"
            . '            <td>' . $row['ftm'] . "</td>\n"
            . '            <td>' . $row['fta'] . "</td>\n"
            . '            <td class="sep-r-weak">' . $row['ftp'] . "</td>\n"
            . '            <td>' . $row['tgm'] . "</td>\n"
            . '            <td>' . $row['tga'] . "</td>\n"
            . '            <td class="sep-r-team">' . $row['tgp'] . "</td>\n"
            . '            <td>' . $row['orb'] . "</td>\n"
            . '            <td>' . $row['reb'] . "</td>\n"
            . '            <td>' . $row['ast'] . "</td>\n"
            . '            <td>' . $row['stl'] . "</td>\n"
            . '            <td>' . $row['tov'] . "</td>\n"
            . '            <td>' . $row['blk'] . "</td>\n"
            . '            <td>' . $row['pf'] . "</td>\n"
            . '            <td>' . $row['pts'] . "</td>\n"
            . "        </tr>\n";
    }

    /**
     * One `<tfoot>` row: the escaped team label, the raw games count, an empty minutes cell,
     * then the per-game team stat cells.
     *
     * @param 'Offense'|'Defense' $label
     * @param TeamOffenseStatsRow|TeamDefenseStatsRow $stats
     */
    private static function renderTeamFooterRow(Team $team, string $label, array $stats): string
    {
        $games = $stats['games'];
        $pts = StatsFormatter::calculatePoints($stats['fgm'], $stats['ftm'], $stats['tgm']);

        return "        <tr>\n"
            . '            <td colspan="2">' . HtmlSanitizer::e($team->name) . ' ' . $label . "</td>\n"
            . '            <td>' . $games . "</td>\n"
            . '            <td class="sep-r-team"></td>' . "\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fgm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fga'], $games) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($stats['fgm'], $stats['fga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['ftm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['fta'], $games) . "</td>\n"
            . '            <td class="sep-r-weak">' . StatsFormatter::formatPercentage($stats['ftm'], $stats['fta']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tgm'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tga'], $games) . "</td>\n"
            . '            <td class="sep-r-team">' . StatsFormatter::formatPercentage($stats['tgm'], $stats['tga']) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['orb'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['reb'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['ast'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['stl'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['tvr'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['blk'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($stats['pf'], $games) . "</td>\n"
            . '            <td>' . StatsFormatter::formatPerGameAverage($pts, $games) . "</td>\n"
            . "        </tr>\n";
    }
}
