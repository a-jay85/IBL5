<?php

declare(strict_types=1);

/**
 * Migration 168: delete the 621 phantom season-2008 boxscore games.
 *
 * Season 2008 carries 618 boxscore "games" that have no ibl_schedule row at all, plus
 * 3 scheduled games that were recorded twice. Together they inflate every 2008 record
 * derived from ibl_box_scores_teams - the Lakers show 83-39 on franchise history
 * instead of 59-23. This migration removes them, after backing every deleted row up.
 *
 * This file is a thin driver. All selection logic, every SQL statement and the two
 * exemption sets live in Boxscore\PhantomBoxscoreRepair, which is what the
 * DatabaseIntegration suite exercises; nothing is re-literalled here.
 *
 * Migration 167 creates the three backup tables. MigrationFileResolver sorts with
 * strnatcasecmp, so 167_*.sql always applies before 168_*.php and the backups exist
 * before the fill. The backups are never dropped - they are the source of record for
 * bin/rollback-phantom-repair.
 *
 * Usage:
 *   php ibl5/migrations/168_delete_phantom_season2008_boxscores.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/168_delete_phantom_season2008_boxscores.php             # real run
 *
 * DRY_RUN=1 in the environment also reports without writing, but exits 3. That is
 * deliberate: MigrationRunner records a migration as applied whenever the subprocess
 * exits 0 and migrations are forward-only, so a dry run that exited 0 under the runner
 * would permanently mark the repair as done without deleting anything. Failing closed
 * makes a stray env var break the deploy loudly instead of silently skipping the repair.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to the main repo, so PSR-4 resolves classes/ there;
// register the local classes/ dir first so this worktree's code is the code that runs.
// No-op in the main checkout and in production, where the two paths coincide.
$localClassesDir = realpath(__DIR__ . '/../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }, true, true);
}

include __DIR__ . '/../config.php';
include __DIR__ . '/../db/db.php';

/** @var mysqli $mysqli_db */

const PHANTOM_SEASON = 2008;

$flagDryRun = in_array('--dry-run', $argv ?? [], true);
$envDryRun = getenv('DRY_RUN') === '1';
$dryRun = $flagDryRun || $envDryRun;

/**
 * @param array<string, int> $counts
 * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, source: string}> $keys
 * @param list<array{game_date: string, visitor_teamid: int, home_teamid: int, keeper_gotd: int, candidate_gotds: list<int>}> $resolutions
 */
function printPhantomReport(array $counts, array $keys, array $resolutions, bool $dryRun): void
{
    echo "=== Phantom season-" . PHANTOM_SEASON . " boxscore repair ===\n";
    echo $dryRun ? "MODE: dry run (nothing will be written)\n\n" : "MODE: live run\n\n";

    foreach ($counts as $name => $value) {
        printf("  %-24s %d\n", $name, $value);
    }

    $byMonth = [];
    foreach ($keys as $key) {
        if ($key['source'] !== 'orphan') {
            continue;
        }
        $month = substr($key['game_date'], 0, 7);
        $byMonth[$month] = ($byMonth[$month] ?? 0) + 1;
    }
    ksort($byMonth);

    echo "\n  Orphan games by month:\n";
    foreach ($byMonth as $month => $count) {
        printf("    %s  %d\n", $month, $count);
    }

    echo "\n  Duplicated scheduled games (score-matched copy kept):\n";
    foreach ($resolutions as $resolution) {
        printf(
            "    %s  %d@%d  keep gotd=%d  of candidates [%s]\n",
            $resolution['game_date'],
            $resolution['visitor_teamid'],
            $resolution['home_teamid'],
            $resolution['keeper_gotd'],
            implode(', ', array_map('strval', $resolution['candidate_gotds']))
        );
    }
    echo "\n";
}

function countRows(mysqli $db, string $table): int
{
    // Table names here are compile-time literals from the call sites below, never input.
    $result = $db->query('SELECT COUNT(*) AS n FROM `' . $table . '`');
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Failed to count rows in ' . $table);
    }
    $row = $result->fetch_assoc();
    $result->free();

    return is_array($row) && is_numeric($row['n']) ? (int) $row['n'] : 0;
}

$repair = new Boxscore\PhantomBoxscoreRepair($mysqli_db, new Boxscore\BoxscoreRepository($mysqli_db));

try {
    $keys = $repair->findPhantomTeamRows(PHANTOM_SEASON);
    $counts = $repair->countAffectedRows(PHANTOM_SEASON);
    printPhantomReport($counts, $keys, $repair->describeDuplicateResolutions(), $dryRun);
} catch (Throwable $e) {
    fwrite(STDERR, "Phantom repair report failed: " . $e->getMessage() . "\n");
    exit(1);
}

if ($dryRun) {
    if ($envDryRun) {
        fwrite(STDERR, "DRY_RUN=1 was set in the environment - refusing to report success.\n");
        fwrite(STDERR, "Exiting 3 so a migration runner cannot record this repair as applied.\n");
        exit(3);
    }
    echo "Dry run complete - nothing was written.\n";
    exit(0);
}

$before = [
    'ibl_box_scores_teams' => countRows($mysqli_db, 'ibl_box_scores_teams'),
    'ibl_box_scores' => countRows($mysqli_db, 'ibl_box_scores'),
    'ibl_sim_game_recaps' => countRows($mysqli_db, 'ibl_sim_game_recaps'),
];

try {
    $result = $repair->deletePhantomRows(PHANTOM_SEASON);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($result['state'] === 'noop') {
    echo "already repaired - nothing to delete\n";
    exit(0);
}

foreach ($before as $table => $countBefore) {
    printf("  %-24s %d -> %d\n", $table, $countBefore, countRows($mysqli_db, $table));
}
foreach (['ibl_box_scores_teams_phantom_backup', 'ibl_box_scores_phantom_backup', 'ibl_sim_game_recaps_phantom_backup'] as $backup) {
    printf("  %-24s %d rows backed up\n", $backup, countRows($mysqli_db, $backup));
}

printf(
    "\nDeleted %d team rows, %d player rows, %d recaps.\n",
    $result['team_rows'],
    $result['player_rows'],
    $result['recap_rows']
);

exit(0);
