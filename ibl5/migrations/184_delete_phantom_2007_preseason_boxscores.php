<?php

declare(strict_types=1);

use Boxscore\Phantom2007BoxscoreRepair as Repair;

/**
 * Migration 184: delete the phantom 2007 preseason and HEAT box-score rows.
 *
 * A 2026-09-18 import batch wrote Nov 2007 games shifted to Sep 2007 and Dec
 * 2007 games shifted to Oct 2007. This migration backs up and deletes the
 * phantom rows only via Boxscore\Phantom2007BoxscoreRepair. Backup tables are
 * created by migration 183. MigrationFileResolver sorts with strnatcasecmp,
 * so 183_*.sql always applies before 184_*.php and the backups exist before
 * the repair runs.
 *
 * Usage:
 *   php ibl5/migrations/184_delete_phantom_2007_preseason_boxscores.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/184_delete_phantom_2007_preseason_boxscores.php             # real run
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
$envDryRun  = getenv('DRY_RUN') === '1';

if ($envDryRun) {
    fwrite(STDERR, "DRY_RUN=1 was set in the environment - refusing to report success.\n");
    fwrite(STDERR, "Exiting 3 so a migration runner cannot record this repair as applied.\n");
    exit(3);
}

/**
 * Count rows in a boxscore table over a date range.
 *
 * @throws RuntimeException on a failed prepare
 */
function countRange(mysqli $db, string $table, string $from, string $to): int
{
    // Table names are compile-time literals from the call sites below, never input.
    $stmt = $db->prepare('SELECT COUNT(*) FROM `' . $table . '` WHERE game_date BETWEEN ? AND ?');
    if ($stmt === false) {
        throw new RuntimeException('Failed to prepare count query for ' . $table);
    }
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

$ranges = [
    'Sep 2007 team rows'       => [Repair::TEAM_TABLE,   Repair::SEP_START, Repair::SEP_END],
    'Oct 2007 team rows'       => [Repair::TEAM_TABLE,   Repair::OCT_START, Repair::OCT_END],
    'Nov-Dec 2007 team rows'   => [Repair::TEAM_TABLE,   '2007-11-01', '2007-12-31'],
    'Sep-Oct 2007 player rows' => [Repair::PLAYER_TABLE, Repair::SEP_START, Repair::OCT_END],
    'Nov-Dec 2007 player rows' => [Repair::PLAYER_TABLE, '2007-11-01', '2007-12-31'],
];

$before = [];
foreach ($ranges as $label => [$table, $from, $to]) {
    $before[$label] = countRange($mysqli_db, $table, $from, $to);
}

$repair = new Repair($mysqli_db);

try {
    $result = $repair->runRepair($flagDryRun);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if ($result['status'] === 'noop') {
    $label = $before['Nov-Dec 2007 team rows'] > 0 ? 'noop: phantom already deleted' : 'noop: season 2007 absent';
    echo $label . "\n";
} else {
    echo "proceed\n";
    echo ($flagDryRun ? "MODE: dry run (changes rolled back)\n" : "MODE: live run\n");
    echo "\n";

    foreach ($ranges as $label => [$table, $from, $to]) {
        printf("  %-28s %d -> %d\n", $label, $before[$label], countRange($mysqli_db, $table, $from, $to));
    }

    printf(
        "\nDeleted %d phantom team rows, %d phantom player rows.\n",
        $result['deleted']['teams'],
        $result['deleted']['players']
    );
}

if ($flagDryRun) {
    echo "Skipping ibl_team_season_records refresh: dry run writes nothing.\n";
    exit(0);
}

$refresh = new Updater\Steps\RefreshTeamSeasonRecordsStep($mysqli_db);
$step    = $refresh->execute();
if (!$step->success) {
    fwrite(STDERR, 'Refresh of ibl_team_season_records failed: ' . $step->errorMessage . "\n");
    fwrite(STDERR, "The phantom delete is committed; re-run this migration to retry the refresh.\n");
    exit(1);
}
echo $step->label . ': ' . $step->detail . "\n";
exit(0);
