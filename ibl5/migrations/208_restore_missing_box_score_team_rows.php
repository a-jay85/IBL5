<?php

declare(strict_types=1);

/**
 * Migration 208: restore the two missing ibl_box_scores_teams rows.
 *
 * Two games have one team row but all 24 player rows in ibl_box_scores:
 *   - 1999-11-03, Knicks (3) at Nets (4): the visitor (Knicks) row is missing.
 *   - 2006-10-02, Lakers (20) at Mavericks (28): the home (Mavericks) row is missing.
 * The missing side is known from the survivor row: the survivor's name is the other team.
 * The missing row is rebuilt from the 12 player rows of the missing team.
 *
 * game_orb / game_drb are the player sums, so they are a lower bound. The box file credits
 * some team rebounds to no player and no stored column records them. Every other stat
 * column sums exactly.
 *
 * This file is a thin driver. Targets, guards and SQL live in
 * Boxscore\Legacy\MissingTeamRowRestore, which the DatabaseIntegration suite exercises.
 *
 * Exit codes: 0 = restored, already paired, absent, or dry run; 1 = a guard failed (the
 * deploy fails loudly and nothing was written); 3 = DRY_RUN=1 in the environment.
 *
 * Undo: delete the two inserted rows by (game_date, visitor_teamid, home_teamid, name).
 *
 * Usage:
 *   php ibl5/migrations/208_restore_missing_box_score_team_rows.php --dry-run   # report only, exit 0
 *   php ibl5/migrations/208_restore_missing_box_score_team_rows.php             # real run
 *
 * DRY_RUN=1 in the environment also reports without writing, but exits 3. MigrationRunner
 * records a migration as applied whenever the subprocess exits 0, so a dry run that exited
 * 0 under the runner would mark the repair done without restoring anything.
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

try {
    $results = (new Boxscore\Legacy\MissingTeamRowRestore($mysqli_db))->run($flagDryRun);
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Restore aborted, nothing written: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($results as $result) {
    echo $result['game_date'] . ': ' . $result['outcome']
        . ($result['inserted_id'] !== null ? ' (id ' . $result['inserted_id'] . ')' : '') . "\n";
}

exit(0);
