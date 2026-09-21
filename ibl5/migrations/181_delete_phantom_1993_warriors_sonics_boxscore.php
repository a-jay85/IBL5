<?php

declare(strict_types=1);

/**
 * Migration 181: delete the phantom 1993-06-04 Warriors @ Supersonics box-score rows.
 *
 * A 1993 playoff sim wrote the round-1 game 4 (Warriors at Supersonics, 1993-06-04)
 * twice on the same date: once at game_of_that_day = 1 (the phantom) and once at
 * game_of_that_day = 5 (the real game). A (date, visitor, home) triple is unique in
 * this league, so the slot-1 copy is a duplicate. This migration backs up and deletes
 * the slot-1 rows only. It never touches slot 5.
 *
 * This file is a thin driver. All selection logic, every SQL statement, and the
 * fingerprint guard live in Boxscore\Season1993PhantomRepair, which is what the
 * DatabaseIntegration suite exercises; nothing is re-literalled here.
 *
 * Migration 180 creates the two backup tables. MigrationFileResolver sorts with
 * strnatcasecmp, so 180_*.sql always applies before 181_*.php and the backups
 * exist before the repair runs.
 *
 * Usage:
 *   php ibl5/migrations/181_delete_phantom_1993_warriors_sonics_boxscore.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/181_delete_phantom_1993_warriors_sonics_boxscore.php             # real run
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

$flagDryRun = in_array('--dry-run', $argv ?? [], true);
$envDryRun = getenv('DRY_RUN') === '1';

if ($envDryRun) {
    fwrite(STDERR, "DRY_RUN=1 was set in the environment - refusing to report success.\n");
    fwrite(STDERR, "Exiting 3 so a migration runner cannot record this repair as applied.\n");
    exit(3);
}

/**
 * Count rows at one (date, visitor, home, ordinal) coordinate in a boxscore table.
 *
 * @throws RuntimeException on a failed prepare
 */
function countCoordinateRows(mysqli $db, string $table, int $gameOfThatDay): int
{
    // Table names are compile-time literals from the call sites below, never input.
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM `' . $table . '`
         WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
           AND game_of_that_day = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare count query for ' . $table);
    }

    $date = Boxscore\Season1993PhantomRepair::GAME_DATE;
    $visitor = Boxscore\Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
    $home = Boxscore\Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
    $stmt->bind_param('siii', $date, $visitor, $home, $gameOfThatDay);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

$repair = new Boxscore\Season1993PhantomRepair($mysqli_db);

$phantomOrdinal = Boxscore\Season1993PhantomRepair::GAME_OF_THAT_DAY;
$realOrdinal = Boxscore\Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY;

$coordinates = [
    'phantom teams (ordinal 1)'   => [Boxscore\Season1993PhantomRepair::TEAM_TABLE, $phantomOrdinal],
    'phantom players (ordinal 1)' => [Boxscore\Season1993PhantomRepair::PLAYER_TABLE, $phantomOrdinal],
    'real teams (ordinal 5)'      => [Boxscore\Season1993PhantomRepair::TEAM_TABLE, $realOrdinal],
    'real players (ordinal 5)'    => [Boxscore\Season1993PhantomRepair::PLAYER_TABLE, $realOrdinal],
];

$before = [];
foreach ($coordinates as $label => [$table, $ordinal]) {
    $before[$label] = countCoordinateRows($mysqli_db, $table, $ordinal);
}

try {
    $result = $repair->runRepair($flagDryRun);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($result['status'] === 'noop') {
    $label = $before['real teams (ordinal 5)'] > 0 ? 'noop: phantom already deleted' : 'noop: season 1993 absent';
    echo $label . "\n";
} else {
    echo "proceed\n";
    echo ($flagDryRun ? "MODE: dry run (changes rolled back)\n" : "MODE: live run\n");
    echo "\n";

    foreach ($coordinates as $label => [$table, $ordinal]) {
        printf("  %-30s %d -> %d\n", $label, $before[$label], countCoordinateRows($mysqli_db, $table, $ordinal));
    }

    printf(
        "\nDeleted %d phantom team rows, %d phantom player rows.\n",
        $result['deleted']['teams'],
        $result['deleted']['players']
    );
}

if ($flagDryRun) {
    echo "Skipping ibl_playoff_series_results refresh: dry run writes nothing.\n";
    exit(0);
}

$refresh = new Updater\Steps\RefreshPlayoffSeriesResultsStep($mysqli_db);
$step = $refresh->execute();
if (!$step->success) {
    fwrite(STDERR, 'Refresh of ibl_playoff_series_results failed: ' . $step->errorMessage . "\n");
    fwrite(STDERR, "The phantom delete is committed; re-run this migration to retry the refresh.\n");
    exit(1);
}
echo $step->label . ': ' . $step->detail . "\n";
exit(0);
