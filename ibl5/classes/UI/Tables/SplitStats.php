<?php

declare(strict_types=1);

namespace UI\Tables;

use Player\PlayerImageHelper;

/**
 * SplitStats - Renders per-game averages table for split stats views
 *
 * Same column layout as PeriodAverages (Pos, Player, G, MIN, FGM, FGA, FG%, etc.)
 * with a split label badge shown in the table area.
 */
class SplitStats
{
    /**
     * Render the split stats per-game averages table
     *
     * @param list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}> $rows Pre-queried split stats rows
     * @param \Team $team Team object
     * @param string $splitLabel Human-readable split label
     * @param list<int> $starterPids Starter player IDs for highlighting
     * @return string HTML table
     */
    public static function render(array $rows, \Team $team, string $splitLabel, array $starterPids = []): string
    {
        /** @var list<array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string}> $playerRows */
        $playerRows = [];

        foreach ($rows as $dbRow) {
            $playerRows[] = [
                'name' => htmlspecialchars((string) ($dbRow['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'pos' => (string) ($dbRow['pos'] ?? ''),
                'pid' => (int) $dbRow['pid'],
                'games' => (int) $dbRow['games'],
                'min' => (string) ($dbRow['gameMINavg'] ?? '0.0'),
                'fgm' => (string) ($dbRow['gameFGMavg'] ?? '0.00'),
                'fga' => (string) ($dbRow['gameFGAavg'] ?? '0.00'),
                'fgp' => (string) ($dbRow['gameFGPavg'] ?? '0.000'),
                'ftm' => (string) ($dbRow['gameFTMavg'] ?? '0.00'),
                'fta' => (string) ($dbRow['gameFTAavg'] ?? '0.00'),
                'ftp' => (string) ($dbRow['gameFTPavg'] ?? '0.000'),
                'tgm' => (string) ($dbRow['game3GMavg'] ?? '0.00'),
                'tga' => (string) ($dbRow['game3GAavg'] ?? '0.00'),
                'tgp' => (string) ($dbRow['game3GPavg'] ?? '0.000'),
                'orb' => (string) ($dbRow['gameORBavg'] ?? '0.0'),
                'reb' => (string) ($dbRow['gameREBavg'] ?? '0.0'),
                'ast' => (string) ($dbRow['gameASTavg'] ?? '0.0'),
                'stl' => (string) ($dbRow['gameSTLavg'] ?? '0.0'),
                'tov' => (string) ($dbRow['gameTOVavg'] ?? '0.0'),
                'blk' => (string) ($dbRow['gameBLKavg'] ?? '0.0'),
                'pf' => (string) ($dbRow['gamePFavg'] ?? '0.0'),
                'pts' => (string) ($dbRow['gamePTSavg'] ?? '0.0'),
            ];
        }

        /** @var string $safeSplitLabel */
        $safeSplitLabel = htmlspecialchars($splitLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
<?php if ($playerRows === []): ?>
        <tr><td colspan="25" style="padding: 2rem; color: var(--gray-500);">No games found for <strong><?= $safeSplitLabel ?></strong> split.</td></tr>
<?php endif; ?>
<?php foreach ($playerRows as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['pos']) ?></td>
            <?= PlayerImageHelper::renderPlayerCell($row['pid'], $row['name'], $starterPids) ?>
            <td><?= $row['games'] ?></td>
            <td><?= $row['min'] ?></td>
            <td class="sep-team"></td>
            <td><?= $row['fgm'] ?></td>
            <td><?= $row['fga'] ?></td>
            <td><?= $row['fgp'] ?></td>
            <td class="sep-weak"></td>
            <td><?= $row['ftm'] ?></td>
            <td><?= $row['fta'] ?></td>
            <td><?= $row['ftp'] ?></td>
            <td class="sep-weak"></td>
            <td><?= $row['tgm'] ?></td>
            <td><?= $row['tga'] ?></td>
            <td><?= $row['tgp'] ?></td>
            <td class="sep-team"></td>
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
