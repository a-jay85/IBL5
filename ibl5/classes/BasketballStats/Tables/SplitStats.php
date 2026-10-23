<?php

declare(strict_types=1);

namespace BasketballStats\Tables;

use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use Team\Team;

/**
 * SplitStats - Renders per-game averages table for split stats views
 *
 * Same column layout as PeriodAverages (Pos, Player, G, MIN, FGM, FGA, FG%, etc.)
 * with a split label badge shown in the table area.
 */
class SplitStats
{
    /**
     * Map pre-queried split stats rows to the typed render shape, applying the per-stat defaults.
     *
     * @param list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}> $rows
     * @return list<array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string}>
     */
    private static function mapRows(array $rows): array
    {
        /** @var list<array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string}> $playerRows */
        $playerRows = [];

        foreach ($rows as $dbRow) {
            $playerRows[] = [
                'name' => (string) ($dbRow['name'] ?? ''),
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

        return $playerRows;
    }

    /**
     * Render the split stats per-game averages table
     *
     * @param list<array{name: string, pos: string, pid: int, games: int, gameMINavg: string|null, gameFGMavg: string|null, gameFGAavg: string|null, gameFGPavg: string|null, gameFTMavg: string|null, gameFTAavg: string|null, gameFTPavg: string|null, game3GMavg: string|null, game3GAavg: string|null, game3GPavg: string|null, gameORBavg: string|null, gameREBavg: string|null, gameASTavg: string|null, gameSTLavg: string|null, gameTOVavg: string|null, gameBLKavg: string|null, gamePFavg: string|null, gamePTSavg: string|null}> $rows Pre-queried split stats rows
     * @param Team $team Team object
     * @param string $splitLabel Human-readable split label
     * @param list<int> $starterPids Starter player IDs for highlighting
     * @return string HTML table
     */
    public static function render(array $rows, Team $team, string $splitLabel, array $starterPids = []): string
    {
        $playerRows = self::mapRows($rows);

        /** @var string $safeSplitLabel */
        $safeSplitLabel = HtmlSanitizer::e($splitLabel);

        ob_start();
        // "\n" restores the template line break: PHP swallows the newline right after the closing PHP tag.
        // @phpstan-ignore ibl.trustedVariable (openTag() escapes the aria-label and sanitizes the colors)
        echo HtmlSanitizer::trusted(TeamStatsTableContext::openTag($team)), "\n";
        echo TeamStatsRowHelper::perGameAveragesHead();
        ?>
    <tbody>
<?php if ($playerRows === []): ?>
        <tr><td colspan="21" class="table-empty-message">No games found for <strong><?= $safeSplitLabel ?></strong> split.</td></tr>
<?php endif; ?>
<?php foreach ($playerRows as $row):
    echo self::renderPlayerRow($row, $starterPids);
endforeach; ?>
    </tbody>
</table>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * One `<tbody>` row. The player cell is followed by the games cell on the same line with
     * no line break between them: the former template's `?>` swallowed that newline.
     *
     * @param array{name: string, pos: string, pid: int, games: int, min: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, reb: string, ast: string, stl: string, tov: string, blk: string, pf: string, pts: string} $row
     * @param list<int> $starterPids
     */
    private static function renderPlayerRow(array $row, array $starterPids): string
    {
        return "        <tr>\n"
            . '            <td>' . HtmlSanitizer::e($row['pos']) . "</td>\n"
            . '            ' . PlayerImageHelper::renderPlayerCell($row['pid'], $row['name'], $starterPids)
            . '            <td>' . $row['games'] . "</td>\n"
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
}
