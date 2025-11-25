<?php

namespace UI\Tables;

use Services\DatabaseService;

/**
 * PeriodAverages - Displays period (simulation) averages statistics table
 */
class PeriodAverages
{
    /**
     * Render the period averages table
     *
     * @param object $db Database connection
     * @param object $team Team object
     * @param object $season Season object
     * @param string|null|\DateTime $startDate Start date for the period (defaults to last sim)
     * @param string|null|\DateTime $endDate End date for the period (defaults to last sim)
     * @return string HTML table
     */
    public static function render($db, $team, $season, $startDate = null, $endDate = null): string
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
        $query = "SELECT name,
            pos,
            pid,
            COUNT(DISTINCT `Date`) as games,
            ROUND(SUM(gameMIN)/COUNT(DISTINCT `Date`), 1) as gameMINavg,
            ROUND(SUM(game2GM + game3GM)/COUNT(DISTINCT `Date`), 2) as gameFGMavg,
            ROUND(SUM(game2GA + game3GA)/COUNT(DISTINCT `Date`), 2) as gameFGAavg,
            ROUND((SUM(game2GM) + SUM(game3GM)) / (SUM(game2GA) + SUM(game3GA)), 3) as gameFGPavg,
            ROUND(SUM(gameFTM)/COUNT(DISTINCT `Date`), 2) as gameFTMavg,
            ROUND(SUM(gameFTA)/COUNT(DISTINCT `Date`), 2) as gameFTAavg,
            ROUND((SUM(gameFTM)) / (SUM(gameFTA)), 3) as gameFTPavg,
            ROUND(SUM(game3GM)/COUNT(DISTINCT `Date`), 2) as game3GMavg,
            ROUND(SUM(game3GA)/COUNT(DISTINCT `Date`), 2) as game3GAavg,
            ROUND((SUM(game3GM)) / (SUM(game3GA)), 3) as game3GPavg,
            ROUND(SUM(gameORB)/COUNT(DISTINCT `Date`), 1) as gameORBavg,
            ROUND((SUM(gameORB) + SUM(gameDRB))/COUNT(DISTINCT `Date`), 1) as gameREBavg,
            ROUND(SUM(gameAST)/COUNT(DISTINCT `Date`), 1) as gameASTavg,
            ROUND(SUM(gameSTL)/COUNT(DISTINCT `Date`), 1) as gameSTLavg,
            ROUND(SUM(gameTOV)/COUNT(DISTINCT `Date`), 1) as gameTOVavg,
            ROUND(SUM(gameBLK)/COUNT(DISTINCT `Date`), 1) as gameBLKavg,
            ROUND(SUM(gamePF)/COUNT(DISTINCT `Date`) , 1) as gamePFavg,
            ROUND(((2 * SUM(game2GM)) + SUM(gameFTM) + (3 * SUM(game3GM)))/COUNT(DISTINCT `Date`) , 1) as gamePTSavg
        FROM   ibl_box_scores
        WHERE  date BETWEEN ? AND ?
            AND ( hometid = ?
                OR visitortid = ? )
            AND gameMIN > 0
            AND pid IN (SELECT pid
                        FROM   ibl_plr
                        WHERE  tid = ?
                            AND retired = 0
                            AND `name` NOT LIKE '%|%')
        GROUP  BY name, pos, pid
        ORDER  BY name ASC";
        
        $stmt = $db->db_connect_id->prepare($query);
        if ($stmt === false) {
            throw new \Exception('Prepare failed: ' . $db->db_connect_id->error);
        }
        
        $stmt->bind_param('sssii', $startDate, $endDate, $teamID, $teamID, $teamID);
        if (!$stmt->execute()) {
            throw new \Exception('Execute failed: ' . $stmt->error);
        }
        
        $resultPlayerSimBoxScores = $stmt->get_result();

        $playerRows = [];
        $i = 0;

        while ($row = $db->sql_fetch_assoc($resultPlayerSimBoxScores)) {
            $bgcolor = (($i % 2) == 0) ? "FFFFFF" : "EEEEEE";

            $playerRows[] = [
                'name' => DatabaseService::safeHtmlOutput($row['name']),
                'pos' => $row['pos'],
                'pid' => $row['pid'],
                'games' => $row['games'],
                'min' => $row['gameMINavg'],
                'fgm' => $row['gameFGMavg'],
                'fga' => $row['gameFGAavg'],
                'fgp' => $row['gameFGPavg'] ?? '0.000',
                'ftm' => $row['gameFTMavg'],
                'fta' => $row['gameFTAavg'],
                'ftp' => $row['gameFTPavg'] ?? '0.000',
                'tgm' => $row['game3GMavg'],
                'tga' => $row['game3GAavg'],
                'tgp' => $row['game3GPavg'] ?? '0.000',
                'orb' => $row['gameORBavg'],
                'reb' => $row['gameREBavg'],
                'ast' => $row['gameASTavg'],
                'stl' => $row['gameSTLavg'],
                'tov' => $row['gameTOVavg'],
                'blk' => $row['gameBLKavg'],
                'pf' => $row['gamePFavg'],
                'pts' => $row['gamePTSavg'],
                'bgcolor' => $bgcolor,
            ];

            $i++;
        }

        ob_start();
        echo \UI\TableStyles::render('sim-avg', $team->color1, $team->color2);
        ?>
<table style="margin: 0 auto;" class="sortable sim-avg">
    <thead>
        <tr style="background-color: #<?= htmlspecialchars($team->color1) ?>;">
            <th>Pos</th>
            <th colspan="3">Player</th>
            <th>g</th>
            <th>min</th>
            <th class="sep-team"></th>
            <th>fgm</th>
            <th>fga</th>
            <th>fgp</th>
            <th class="sep-weak"></th>
            <th>ftm</th>
            <th>fta</th>
            <th>ftp</th>
            <th class="sep-weak"></th>
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
        <tr style="background-color: #<?= $row['bgcolor'] ?>;">
            <td><?= htmlspecialchars($row['pos']) ?></td>
            <td colspan="3"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=<?= (int)$row['pid'] ?>"><?= $row['name'] ?></a></td>
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
        return ob_get_clean();
    }
}
