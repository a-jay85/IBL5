<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * PeriodAverages - Displays period (simulation) averages statistics table
 */
class PeriodAverages
{
    /**
     * Render the period averages table
     *
     * @param \mysqli $db Database connection
     * @param \Team $team Team object
     * @param \Season $season Season object
     * @param string|null|\DateTime $startDate Start date for the period (defaults to last sim)
     * @param string|null|\DateTime $endDate End date for the period (defaults to last sim)
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     * @throws \Exception If database connection is invalid
     */
    public static function render(\mysqli $db, $team, $season, $startDate = null, $endDate = null, array $starterPids = []): string
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

        $teamID = (int)$team->teamID;

        // Use prepared statement for date filtering
        $query = "SELECT p.name,
            bs.pos,
            bs.pid,
            COUNT(DISTINCT bs.`Date`) as games,
            ROUND(SUM(bs.gameMIN)/COUNT(DISTINCT bs.`Date`), 1) as gameMINavg,
            ROUND(SUM(bs.game2GM + bs.game3GM)/COUNT(DISTINCT bs.`Date`), 2) as gameFGMavg,
            ROUND(SUM(bs.game2GA + bs.game3GA)/COUNT(DISTINCT bs.`Date`), 2) as gameFGAavg,
            ROUND((SUM(bs.game2GM) + SUM(bs.game3GM)) / (SUM(bs.game2GA) + SUM(bs.game3GA)), 3) as gameFGPavg,
            ROUND(SUM(bs.gameFTM)/COUNT(DISTINCT bs.`Date`), 2) as gameFTMavg,
            ROUND(SUM(bs.gameFTA)/COUNT(DISTINCT bs.`Date`), 2) as gameFTAavg,
            ROUND((SUM(bs.gameFTM)) / (SUM(bs.gameFTA)), 3) as gameFTPavg,
            ROUND(SUM(bs.game3GM)/COUNT(DISTINCT bs.`Date`), 2) as game3GMavg,
            ROUND(SUM(bs.game3GA)/COUNT(DISTINCT bs.`Date`), 2) as game3GAavg,
            ROUND((SUM(bs.game3GM)) / (SUM(bs.game3GA)), 3) as game3GPavg,
            ROUND(SUM(bs.gameORB)/COUNT(DISTINCT bs.`Date`), 1) as gameORBavg,
            ROUND((SUM(bs.gameORB) + SUM(bs.gameDRB))/COUNT(DISTINCT bs.`Date`), 1) as gameREBavg,
            ROUND(SUM(bs.gameAST)/COUNT(DISTINCT bs.`Date`), 1) as gameASTavg,
            ROUND(SUM(bs.gameSTL)/COUNT(DISTINCT bs.`Date`), 1) as gameSTLavg,
            ROUND(SUM(bs.gameTOV)/COUNT(DISTINCT bs.`Date`), 1) as gameTOVavg,
            ROUND(SUM(bs.gameBLK)/COUNT(DISTINCT bs.`Date`), 1) as gameBLKavg,
            ROUND(SUM(bs.gamePF)/COUNT(DISTINCT bs.`Date`) , 1) as gamePFavg,
            ROUND(((2 * SUM(bs.game2GM)) + SUM(bs.gameFTM) + (3 * SUM(bs.game3GM)))/COUNT(DISTINCT bs.`Date`) , 1) as gamePTSavg
        FROM   ibl_box_scores bs
        JOIN   ibl_plr p ON bs.pid = p.pid
        WHERE  bs.date BETWEEN ? AND ?
            AND ( bs.hometid = ?
                OR bs.visitortid = ? )
            AND bs.gameMIN > 0
            AND p.tid = ?
            AND p.retired = 0
            AND p.name NOT LIKE '%|%'
        GROUP  BY p.name, bs.pos, bs.pid
        ORDER  BY p.name ASC";
        
        // Use mysqli prepared statement directly
        $stmt = $db->prepare($query);
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->error);
        }
        
        $stmt->bind_param('sssii', $startDate, $endDate, $teamID, $teamID, $teamID);
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
                'name' => htmlspecialchars((string) ($dbRow['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
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
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-team"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-team"></th>
            <th>3gm</th>
            <th>3ga</th>
            <th>3gp</th>
            <th class="sep-team"></th>
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
            <td><?= htmlspecialchars($row['pos']) ?></td>
            <?= PlayerImageHelper::renderPlayerCell($row['pid'], $row['name'], $starterPids) ?>
            <td style="text-align: center;"><?= (int)$row['games'] ?></td>
            <td style="text-align: center;"><?= $row['min'] ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $row['fgm'] ?></td>
            <td style="text-align: center;"><?= $row['fga'] ?></td>
            <td style="text-align: center;"><?= $row['fgp'] ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $row['ftm'] ?></td>
            <td style="text-align: center;"><?= $row['fta'] ?></td>
            <td style="text-align: center;"><?= $row['ftp'] ?></td>
            <td class="sep-weak"></td>
            <td style="text-align: center;"><?= $row['tgm'] ?></td>
            <td style="text-align: center;"><?= $row['tga'] ?></td>
            <td style="text-align: center;"><?= $row['tgp'] ?></td>
            <td class="sep-team"></td>
            <td style="text-align: center;"><?= $row['orb'] ?></td>
            <td style="text-align: center;"><?= $row['reb'] ?></td>
            <td style="text-align: center;"><?= $row['ast'] ?></td>
            <td style="text-align: center;"><?= $row['stl'] ?></td>
            <td style="text-align: center;"><?= $row['tov'] ?></td>
            <td style="text-align: center;"><?= $row['blk'] ?></td>
            <td style="text-align: center;"><?= $row['pf'] ?></td>
            <td style="text-align: center;"><?= $row['pts'] ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }
}
