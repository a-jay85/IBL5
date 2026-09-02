<?php

declare(strict_types=1);

/**
 * Migration 173: restore the missing 2008 IBL Finals Game 4 boxscore.
 *
 * The 2008-06-25 Knicks @ Clippers game was never written to ibl_box_scores_teams
 * or ibl_box_scores, causing ibl_playoff_series_results to show "3-0" instead of
 * "Clippers 4, Knicks 0". This migration inserts the recovered payload and refreshes
 * ibl_playoff_series_results so the series result is correct.
 *
 * This file is a thin driver. All selection logic, every SQL statement, and the
 * embedded stat payload live in Boxscore\Season2008Finals4Restore, which is
 * what the DatabaseIntegration suite exercises; nothing is re-literalled here.
 *
 * Usage:
 *   php ibl5/migrations/173_restore_2008_finals_g4_boxscore.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/173_restore_2008_finals_g4_boxscore.php             # real run
 *
 * DRY_RUN=1 in the environment also reports without writing, but exits 3. That is
 * deliberate: MigrationRunner records a migration as applied whenever the subprocess
 * exits 0 and migrations are forward-only, so a dry run that exited 0 under the runner
 * would permanently mark the repair as done without restoring anything. Failing closed
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

$flagDryRun = in_array('--dry-run', $argv ?? [], true);
$envDryRun = getenv('DRY_RUN') === '1';

if ($envDryRun) {
    fwrite(STDERR, "DRY_RUN=1 was set in the environment - refusing to report success.\n");
    fwrite(STDERR, "Exiting 3 so a migration runner cannot record this repair as applied.\n");
    exit(3);
}

/**
 * Count rows already inserted for the 2008 Finals Game 4 coordinate.
 *
 * @throws RuntimeException on a failed prepare
 */
function countInsertedTeamRows(mysqli $db): int
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `' . Boxscore\Season2008Finals4Restore::TEAM_TABLE . '`
         WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
           AND game_of_that_day = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare count query');
    }

    $date    = Boxscore\Season2008Finals4Restore::GAME_DATE;
    $visitor = Boxscore\Season2008Finals4Restore::VISITOR_TEAMID;
    $home    = Boxscore\Season2008Finals4Restore::HOME_TEAMID;
    $ordinal = Boxscore\Season2008Finals4Restore::GAME_OF_THAT_DAY;
    $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

/**
 * Count rows in a table matching the 2008 Finals Game 4 game coordinate.
 *
 * @throws RuntimeException on a failed prepare
 */
function countGameRows(mysqli $db, string $table): int
{
    // Table names here are compile-time literals from the call sites below, never input.
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `' . $table . '`
         WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
           AND game_of_that_day = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare count query for ' . $table);
    }

    $date    = Boxscore\Season2008Finals4Restore::GAME_DATE;
    $visitor = Boxscore\Season2008Finals4Restore::VISITOR_TEAMID;
    $home    = Boxscore\Season2008Finals4Restore::HOME_TEAMID;
    $ordinal = Boxscore\Season2008Finals4Restore::GAME_OF_THAT_DAY;
    $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

$restore = new Boxscore\Season2008Finals4Restore($mysqli_db);

$before = [
    Boxscore\Season2008Finals4Restore::TEAM_TABLE   => countGameRows($mysqli_db, Boxscore\Season2008Finals4Restore::TEAM_TABLE),
    Boxscore\Season2008Finals4Restore::PLAYER_TABLE => countGameRows($mysqli_db, Boxscore\Season2008Finals4Restore::PLAYER_TABLE),
];

try {
    $result = $restore->runRestore($flagDryRun);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($result['status'] === 'noop') {
    $label = countInsertedTeamRows($mysqli_db) > 0 ? 'noop: already inserted' : 'noop: season 2008 absent';
    echo $label . "\n";
    exit(0);
}

echo "proceed\n";
echo ($flagDryRun ? "MODE: dry run (changes rolled back)\n" : "MODE: live run\n");
echo "\n";

foreach ($before as $table => $countBefore) {
    printf("  %-28s %d -> %d\n", $table, $countBefore, countGameRows($mysqli_db, $table));
}

printf(
    "\nInserted %d team rows, %d player rows.\n",
    $result['inserted']['teams'],
    $result['inserted']['players']
);

if ($result['refreshed'] === false && !$flagDryRun) {
    fwrite(STDERR, "WARNING: playoff series refresh failed — boxscore rows committed.\n");
    fwrite(STDERR, "Re-run RefreshPlayoffSeriesResultsStep to fix ibl_playoff_series_results.\n");
    exit(1);
}

exit(0);
