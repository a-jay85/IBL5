<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;
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

        $teamid = (int)$team->teamid;

        // Use prepared statement for date filtering
        // Build optional PID filter clause
        $pidFilterClause = '';
        $pidFilterTypes = '';
        $pidFilterParams = [];
        if ($pidFilter !== []) {
            $placeholders = implode(',', array_fill(0, count($pidFilter), '?'));
            $pidFilterClause = " AND bs.pid IN ({$placeholders})";
            $pidFilterTypes = str_repeat('i', count($pidFilter));
            $pidFilterParams = $pidFilter;
        }

        $query = "SELECT p.name,
            bs.pos,
            bs.pid,
            COUNT(DISTINCT bs.game_date) as games,
            ROUND(SUM(bs.game_min)/COUNT(DISTINCT bs.game_date), 1) as gameMINavg,
            ROUND(SUM(bs.game_2gm + bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as gameFGMavg,
            ROUND(SUM(bs.game_2ga + bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) as gameFGAavg,
            ROUND((SUM(bs.game_2gm) + SUM(bs.game_3gm)) / (SUM(bs.game_2ga) + SUM(bs.game_3ga)), 3) as gameFGPavg,
            ROUND(SUM(bs.game_ftm)/COUNT(DISTINCT bs.game_date), 2) as gameFTMavg,
            ROUND(SUM(bs.game_fta)/COUNT(DISTINCT bs.game_date), 2) as gameFTAavg,
            ROUND((SUM(bs.game_ftm)) / (SUM(bs.game_fta)), 3) as gameFTPavg,
            ROUND(SUM(bs.game_3gm)/COUNT(DISTINCT bs.game_date), 2) as game3GMavg,
            ROUND(SUM(bs.game_3ga)/COUNT(DISTINCT bs.game_date), 2) as game3GAavg,
            ROUND((SUM(bs.game_3gm)) / (SUM(bs.game_3ga)), 3) as game3GPavg,
            ROUND(SUM(bs.game_orb)/COUNT(DISTINCT bs.game_date), 1) as gameORBavg,
            ROUND((SUM(bs.game_orb) + SUM(bs.game_drb))/COUNT(DISTINCT bs.game_date), 1) as gameREBavg,
            ROUND(SUM(bs.game_ast)/COUNT(DISTINCT bs.game_date), 1) as gameASTavg,
            ROUND(SUM(bs.game_stl)/COUNT(DISTINCT bs.game_date), 1) as gameSTLavg,
            ROUND(SUM(bs.game_tov)/COUNT(DISTINCT bs.game_date), 1) as gameTOVavg,
            ROUND(SUM(bs.game_blk)/COUNT(DISTINCT bs.game_date), 1) as gameBLKavg,
            ROUND(SUM(bs.game_pf)/COUNT(DISTINCT bs.game_date) , 1) as gamePFavg,
            ROUND(((2 * SUM(bs.game_2gm)) + SUM(bs.game_ftm) + (3 * SUM(bs.game_3gm)))/COUNT(DISTINCT bs.game_date) , 1) as gamePTSavg
        FROM   ibl_box_scores bs
        JOIN   ibl_plr p ON bs.pid = p.pid
        WHERE  bs.game_date BETWEEN ? AND ?
            AND ( bs.home_teamid = ?
                OR bs.visitor_teamid = ? )
            AND bs.game_min > 0
            AND p.teamid = ?
            AND p.retired = 0
            {$pidFilterClause}
        GROUP  BY p.name, bs.pos, bs.pid
        ORDER  BY p.name ASC";

        // Use mysqli prepared statement directly
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->error);
        }

        $types = 'sssii' . $pidFilterTypes;
        $params = [$startDate, $endDate, $teamid, $teamid, $teamid, ...$pidFilterParams];
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }

        $resultPlayerSimBoxScores = $stmt->get_result();
        $stmt->close();

        if ($resultPlayerSimBoxScores === false) {
            throw new \Exception('Failed to get result set');
        }

        /** @var list<array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string}> $playerRows */
        $playerRows = [];

        while (true) {
            $dbRow = $resultPlayerSimBoxScores->fetch_assoc();
            if (!is_array($dbRow)) {
                break;
            }
            $playerRows[] = [
                'name' => HtmlSanitizer::e((string) ($dbRow['name'] ?? '')),
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
        ?>
<table class="ibl-data-table team-table responsive-table sortable" style="<?= \UI\TableStyles::inlineVars($team->color1, $team->color2) ?>">
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
</table>
        <?php
        return (string) ob_get_clean();
    }
}
