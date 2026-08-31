<?php

declare(strict_types=1);

/**
 * Migration 171: restore the missing 2004-02-09 Suns @ Heat boxscore.
 *
 * A single 2004 sim wrote a duplicate Aces @ Jazz boxscore into the
 * game_of_that_day = 5 ordinal slot and never wrote the Suns @ Heat
 * game that belonged there. This migration removes the phantom Aces @ Jazz rows
 * and inserts the recovered Suns @ Heat payload.
 *
 * This file is a thin driver. All selection logic, every SQL statement, and the
 * embedded stat payload live in Boxscore\Season2004BoxscoreRestore, which is
 * what the DatabaseIntegration suite exercises; nothing is re-literalled here.
 *
 * Migration 170 creates the two backup tables. MigrationFileResolver sorts with
 * strnatcasecmp, so 170_*.sql always applies before 171_*.php and the backups
 * exist before the repair runs.
 *
 * Usage:
 *   php ibl5/migrations/171_restore_2004_suns_heat_boxscore.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/171_restore_2004_suns_heat_boxscore.php             # real run
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
 * Count rows at the 2004-02-09 ordinal-5 coordinate in a boxscore table,
 * regardless of which team pair occupies the slot (phantom or restored).
 *
 * @throws RuntimeException on a failed prepare
 */
function countCoordinateRows(mysqli $db, string $table): int
{
    // Table names here are compile-time literals from the call sites below, never input.
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `' . $table . '`
         WHERE game_date = ? AND game_of_that_day = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare count query for ' . $table);
    }

    $date = Boxscore\Season2004BoxscoreRestore::GAME_DATE;
    $ordinal = Boxscore\Season2004BoxscoreRestore::GAME_OF_THAT_DAY;
    $stmt->bind_param('si', $date, $ordinal);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

/**
 * Count rows for the restored (Suns @ Heat) coordinate in the team table.
 * Used to distinguish "already restored" from "season absent" on a noop result.
 *
 * @throws RuntimeException on a failed prepare
 */
function countRestoredTeamRows(mysqli $db): int
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `' . Boxscore\Season2004BoxscoreRestore::TEAM_TABLE . '`
         WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
           AND game_of_that_day = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare restored-row count query');
    }

    $date = Boxscore\Season2004BoxscoreRestore::GAME_DATE;
    $visitor = Boxscore\Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID;
    $home = Boxscore\Season2004BoxscoreRestore::RESTORED_HOME_TEAMID;
    $ordinal = Boxscore\Season2004BoxscoreRestore::GAME_OF_THAT_DAY;
    $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

$restore = new Boxscore\Season2004BoxscoreRestore($mysqli_db);

$before = [
    Boxscore\Season2004BoxscoreRestore::TEAM_TABLE   => countCoordinateRows($mysqli_db, Boxscore\Season2004BoxscoreRestore::TEAM_TABLE),
    Boxscore\Season2004BoxscoreRestore::PLAYER_TABLE => countCoordinateRows($mysqli_db, Boxscore\Season2004BoxscoreRestore::PLAYER_TABLE),
];

try {
    $result = $restore->runRestore($flagDryRun);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($result['status'] === 'noop') {
    $label = countRestoredTeamRows($mysqli_db) > 0 ? 'noop: already restored' : 'noop: season 2004 absent';
    echo $label . "\n";
    exit(0);
}

echo "proceed\n";
echo ($flagDryRun ? "MODE: dry run (changes rolled back)\n" : "MODE: live run\n");
echo "\n";

foreach ($before as $table => $countBefore) {
    printf("  %-28s %d -> %d\n", $table, $countBefore, countCoordinateRows($mysqli_db, $table));
}

printf(
    "\nDeleted %d phantom team rows, %d phantom player rows.\n",
    $result['deleted']['teams'],
    $result['deleted']['players']
);
printf(
    "Inserted %d restored team rows, %d restored player rows.\n",
    $result['inserted']['teams'],
    $result['inserted']['players']
);

exit(0);
