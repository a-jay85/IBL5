<?php

declare(strict_types=1);

/**
 * Reconstruct 2006-07 All-Star Weekend box scores.
 *
 * The .sco file for the 06-07 season was corrupted, so the Rising Stars Game
 * (Feb 2) and the All-Star Game (Feb 3) are missing from ibl_box_scores /
 * ibl_box_scores_teams. This script rebuilds them from the season's archived
 * HTML box scores:
 *
 *   https://www.iblhoops.net/ibl5/ibl/archive/06-07/asg/box0.htm  (Rising Stars)
 *   https://www.iblhoops.net/ibl5/ibl/archive/06-07/asg/box1.htm  (All-Star Game)
 *
 * Every stat below was transcribed from those two files and cross-checked:
 * each player's PTS == 2*(FGM-3PM) + 3*3PM + FTM, and per-team player PTS sum
 * matches the quarter-score finals (RSG 134-145, ASG 144-182).
 *
 * Conventions mirror Boxscore\BoxscoreProcessor::processAllStarGames():
 *   - Rising Stars: visitor teamid 40 (Rookies), home 41 (Sophomores), Feb 2.
 *   - All-Star:     visitor teamid 50, home 51, Feb 3.
 *   - game_of_that_day = 1; visitor team-row inserted first.
 *   - DB splits twos from threes:  game_2gm = FGM - 3PM, game_2ga = FGA - 3PA.
 *   - DB stores ORB + total REB separately:  game_drb = REB - ORB.
 *   - Player/team `name` = first 16 chars of ibl_plr.name (varchar(16) limit).
 *   - game_type / season_year / calc_* are STORED GENERATED — never written.
 *
 * Values NOT recoverable from the HTML (documented assumptions):
 *   - Team W-L records: the original .sco game-info bytes are lost and the HTML
 *     carries no records, so all *_wins / *_losses are 0. (Historical ASG rows
 *     hold non-meaningful leftover .sco values that vary by season; 0 is the
 *     honest reconstruction.)
 *   - attendance = 5244 / capacity = 20000 come straight from the HTML footer.
 *     (Older ASG rows store attendance 0; the 2007 source has a real value, so
 *     we use it.)
 *
 * Idempotent: deletes any existing rows for each game key before inserting.
 *
 * Usage:
 *   php reconstruct_2007_asg_boxscores.php           # dry run (no writes)
 *   php reconstruct_2007_asg_boxscores.php --apply   # perform the backfill
 */

use Boxscore\BoxscoreRepository;
use Utilities\UuidGenerator;

if (PHP_SAPI !== 'cli') {
    echo 'This script must be run from the command line.';
    exit(1);
}

// ── Minimal bootstrap (mirrors bulkJsbImport.php) ────────────────────────────
$_SERVER['PHP_SELF'] = 'reconstruct_2007_asg_boxscores.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

$localClassesDir = realpath(__DIR__ . '/../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

$apply = in_array('--apply', $argv, true);

const ATTENDANCE = 5244;
const CAPACITY = 20000;

/**
 * Player stat row, transcribed from the HTML box score.
 * Order: [pid, pos, min, fgm, fga, ftm, fta, tpm, tpa, orb, reb, ast, stl, tov, blk, pf]
 * where fgm/fga are TOTAL field goals (incl. threes) and tpm/tpa are threes.
 */
$games = [
    'Rising Stars Game' => [
        'date' => '2007-02-02',
        'visitor_teamid' => 40,
        'home_teamid' => 41,
        'visitor_name' => 'Rookies',
        'home_name' => 'Sophomores',
        'visitor_q' => [34, 39, 30, 31, 0],
        'home_q' => [34, 31, 39, 41, 0],
        // team totals: [fgm, fga, ftm, fta, tpm, tpa, orb, reb, ast, stl, tov, blk, pf]
        'visitor_team' => [55, 119, 16, 21, 8, 21, 25, 66, 30, 11, 22, 8, 19],
        'home_team' => [64, 127, 5, 9, 12, 27, 24, 67, 34, 10, 21, 14, 21],
        'visitor_players' => [
            [5936, 'PG', 32, 7, 18, 5, 5, 0, 3, 3, 4, 7, 0, 3, 0, 3],
            [5938, 'PG', 16, 2, 7, 0, 0, 1, 5, 1, 2, 1, 0, 2, 1, 0],
            [5931, 'SG', 32, 10, 21, 3, 5, 2, 5, 4, 8, 0, 3, 1, 1, 2],
            [5930, 'SG', 19, 3, 5, 2, 3, 2, 2, 1, 2, 2, 2, 1, 1, 1],
            [5937, 'SF', 29, 6, 13, 0, 0, 1, 2, 0, 4, 11, 1, 5, 0, 2],
            [5939, 'SF', 13, 6, 11, 1, 2, 0, 0, 1, 3, 4, 0, 3, 1, 4],
            [5929, 'PF', 30, 9, 18, 2, 2, 2, 3, 3, 7, 2, 1, 2, 1, 1],
            [5935, 'PF', 30, 7, 14, 3, 4, 0, 1, 6, 12, 2, 4, 3, 1, 3],
            [5942, 'C', 14, 2, 5, 0, 0, 0, 0, 3, 7, 0, 0, 2, 1, 1],
            [5964, 'C', 22, 3, 7, 0, 0, 0, 0, 1, 9, 1, 0, 0, 1, 2],
        ],
        'home_players' => [
            [5640, 'PG', 31, 13, 23, 0, 0, 0, 2, 4, 11, 12, 3, 6, 3, 1],
            [5649, 'PG', 13, 2, 5, 3, 4, 1, 2, 0, 1, 5, 3, 2, 0, 0],
            [5642, 'SG', 20, 11, 14, 1, 3, 1, 2, 1, 2, 6, 0, 3, 0, 2],
            [5659, 'SG', 30, 10, 24, 0, 0, 6, 11, 0, 5, 5, 2, 3, 2, 2],
            [5645, 'SF', 31, 9, 19, 1, 1, 2, 5, 0, 5, 1, 0, 0, 0, 4],
            [5685, 'SF', 20, 3, 9, 0, 0, 2, 4, 0, 1, 2, 1, 0, 0, 2],
            [5646, 'PF', 17, 3, 5, 0, 0, 0, 0, 2, 6, 0, 1, 2, 0, 5],
            [5663, 'PF', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // DNP
            [5641, 'C', 31, 7, 17, 0, 0, 0, 1, 10, 16, 2, 0, 1, 4, 4],
            [5644, 'C', 43, 6, 11, 0, 1, 0, 0, 6, 17, 1, 0, 4, 5, 1],
        ],
    ],
    'All-Star Game' => [
        'date' => '2007-02-03',
        'visitor_teamid' => 50,
        'home_teamid' => 51,
        'visitor_name' => 'Team Diep',
        'home_name' => 'Team Lilley',
        'visitor_q' => [43, 31, 44, 26, 0],
        'home_q' => [43, 48, 55, 36, 0],
        'visitor_team' => [53, 108, 33, 38, 5, 20, 11, 53, 23, 4, 20, 10, 23],
        'home_team' => [72, 132, 26, 29, 12, 30, 20, 67, 39, 16, 10, 9, 28],
        'visitor_players' => [
            [3852, 'PG', 13, 3, 8, 2, 2, 1, 4, 0, 2, 3, 0, 1, 0, 3],
            [5640, 'PG', 17, 3, 4, 4, 4, 0, 0, 1, 3, 5, 0, 2, 0, 0],
            [3851, 'PG', 28, 9, 14, 4, 4, 1, 3, 0, 3, 4, 2, 2, 0, 1],
            [4148, 'PF', 21, 2, 4, 5, 5, 0, 0, 2, 7, 2, 0, 1, 0, 4],
            [5258, 'SF', 25, 6, 13, 5, 6, 0, 1, 0, 5, 4, 1, 3, 0, 0],
            [4500, 'C', 22, 9, 12, 7, 7, 2, 2, 3, 7, 0, 0, 0, 5, 2],
            [3282, 'SG', 15, 0, 2, 2, 2, 0, 1, 0, 3, 3, 0, 2, 0, 4],
            [3561, 'SG', 13, 3, 7, 0, 0, 1, 2, 0, 1, 0, 0, 3, 0, 0],
            [2975, 'C', 25, 7, 15, 3, 6, 0, 0, 1, 9, 1, 1, 3, 4, 4],
            [3277, 'SF', 15, 4, 13, 0, 0, 0, 5, 0, 0, 0, 0, 0, 0, 2],
            [5265, 'SG', 20, 5, 6, 0, 0, 0, 0, 0, 3, 0, 0, 1, 0, 0],
            [4507, 'PF', 21, 2, 10, 1, 2, 0, 2, 1, 5, 1, 0, 2, 1, 3],
        ],
        'home_players' => [
            [4150, 'PG', 18, 6, 10, 0, 0, 1, 2, 1, 4, 3, 2, 2, 1, 1],
            [3556, 'PG', 18, 7, 13, 4, 4, 0, 3, 1, 7, 5, 2, 1, 1, 1],
            [3552, 'SG', 18, 6, 13, 0, 0, 2, 5, 0, 3, 4, 2, 0, 1, 3],
            [5261, 'PF', 22, 4, 7, 7, 8, 1, 1, 1, 2, 2, 0, 0, 1, 1],
            [3555, 'C', 12, 3, 6, 0, 0, 0, 0, 2, 7, 0, 0, 1, 0, 0],
            [5259, 'SG', 27, 9, 23, 1, 2, 1, 4, 7, 8, 8, 1, 1, 1, 2],
            [4490, 'C', 20, 8, 14, 3, 3, 3, 5, 1, 5, 1, 1, 1, 0, 3],
            [4492, 'C', 20, 6, 11, 2, 2, 0, 0, 2, 9, 1, 0, 1, 1, 3],
            [4494, 'SG', 16, 7, 9, 6, 6, 1, 2, 1, 3, 2, 3, 1, 0, 3],
            [4502, 'PG', 11, 2, 3, 0, 0, 0, 1, 0, 3, 3, 0, 0, 0, 6],
            [4824, 'C', 29, 8, 10, 3, 4, 0, 1, 2, 8, 0, 3, 2, 1, 5],
            [4825, 'PG', 23, 6, 13, 0, 0, 3, 6, 1, 5, 10, 2, 0, 2, 0],
        ],
    ],
];

$repository = new BoxscoreRepository($mysqli_db);

/**
 * Box-score name = first 16 chars of the canonical ibl_plr.name (varchar(16)).
 * Special-case Dražen Dalipagić to the dominant existing ASCII variant.
 */
$nameFor = static function (int $pid) use ($mysqli_db): string {
    if ($pid === 5265) {
        return 'Drazen Dalipagic';
    }
    $stmt = $mysqli_db->prepare('SELECT name FROM ibl_plr WHERE pid = ? LIMIT 1');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("pid {$pid} not found in ibl_plr");
    }
    return mb_substr((string) $row['name'], 0, 16);
};

echo $apply ? "APPLYING backfill...\n\n" : "DRY RUN (pass --apply to write)\n\n";

$mysqli_db->begin_transaction();

try {
    foreach ($games as $label => $g) {
        echo "== {$label} ({$g['date']}) ==\n";

        // Idempotency: clear any existing rows for this game key.
        $repository->deleteTeamBoxscoresByGame($g['date'], $g['visitor_teamid'], $g['home_teamid'], 1);
        $repository->deletePlayerBoxscoresByGame($g['date'], $g['visitor_teamid'], $g['home_teamid']);

        [$vq1, $vq2, $vq3, $vq4, $vot] = $g['visitor_q'];
        [$hq1, $hq2, $hq3, $hq4, $hot] = $g['home_q'];

        // --- Team rows (visitor first, then home) ---
        foreach (['visitor', 'home'] as $side) {
            $t = $g[$side . '_team'];
            [$fgm, $fga, $ftm, $fta, $tpm, $tpa, $orb, $reb, $ast, $stl, $tov, $blk, $pf] = $t;
            $name = $g[$side . '_name'];

            $repository->insertTeamBoxscore([
                'game_date' => $g['date'],
                'name' => $name,
                'game_of_that_day' => 1,
                'visitor_teamid' => $g['visitor_teamid'],
                'home_teamid' => $g['home_teamid'],
                'attendance' => ATTENDANCE,
                'capacity' => CAPACITY,
                'visitor_wins' => 0,
                'visitor_losses' => 0,
                'home_wins' => 0,
                'home_losses' => 0, // W/L unrecoverable
                'visitor_q1_points' => $vq1,
                'visitor_q2_points' => $vq2,
                'visitor_q3_points' => $vq3,
                'visitor_q4_points' => $vq4,
                'visitor_ot_points' => $vot,
                'home_q1_points' => $hq1,
                'home_q2_points' => $hq2,
                'home_q3_points' => $hq3,
                'home_q4_points' => $hq4,
                'home_ot_points' => $hot,
                'game_2gm' => $fgm - $tpm,
                'game_2ga' => $fga - $tpa,
                'game_ftm' => $ftm,
                'game_fta' => $fta,
                'game_3gm' => $tpm,
                'game_3ga' => $tpa,
                'game_orb' => $orb,
                'game_drb' => $reb - $orb,
                'game_ast' => $ast,
                'game_stl' => $stl,
                'game_tov' => $tov,
                'game_blk' => $blk,
                'game_pf' => $pf,
            ]);
            $teamPts = ($fgm - $tpm) * 2 + $ftm + $tpm * 3;
            echo sprintf("  team  %-12s 2GM %2d 3GM %2d FT %2d ORB %2d DRB %2d  PTS %d\n",
                $name, $fgm - $tpm, $tpm, $ftm, $orb, $reb - $orb, $teamPts);
        }

        // --- Player rows ---
        foreach (['visitor', 'home'] as $side) {
            $teamid = $side === 'visitor' ? $g['visitor_teamid'] : $g['home_teamid'];
            $sidePts = 0;
            foreach ($g[$side . '_players'] as $p) {
                [$pid, $pos, $min, $fgm, $fga, $ftm, $fta, $tpm, $tpa, $orb, $reb, $ast, $stl, $tov, $blk, $pf] = $p;
                $name = $nameFor($pid);

                $repository->insertPlayerBoxscore(
                    $g['date'],
                    UuidGenerator::generateUuid(),
                    $name,
                    $pos,
                    $pid,
                    $g['visitor_teamid'],
                    $g['home_teamid'],
                    1,
                    ATTENDANCE,
                    CAPACITY,
                    0, 0, 0, 0, // W/L unrecoverable
                    $teamid,
                    $min,
                    $fgm - $tpm, // game_2gm
                    $fga - $tpa, // game_2ga
                    $ftm, $fta,
                    $tpm, $tpa,
                    $orb,
                    $reb - $orb, // game_drb
                    $ast, $stl, $tov, $blk, $pf,
                );
                $pts = ($fgm - $tpm) * 2 + $ftm + $tpm * 3;
                $sidePts += $pts;
            }
            echo sprintf("  %-7s players: %d rows, %d PTS\n",
                $side, count($g[$side . '_players']), $sidePts);
        }
        echo "\n";
    }

    if ($apply) {
        $mysqli_db->commit();
        echo "Committed.\n";
    } else {
        $mysqli_db->rollback();
        echo "Rolled back (dry run).\n";
    }
} catch (Throwable $e) {
    $mysqli_db->rollback();
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
