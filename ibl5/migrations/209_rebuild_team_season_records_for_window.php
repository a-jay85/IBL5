<?php

declare(strict_types=1);

/**
 * Migration 209: rebuild ibl_team_season_records for the five-season window.
 *
 * FranchiseHistoryRepository::FIVE_SEASON_WINDOW_SQL now reads the regular-season
 * rows of ibl_team_season_records instead of the ibl_team_win_loss view. This script
 * rebuilds that table with RefreshTeamSeasonRecordsStep, which materializes the
 * dedupe from PR #3419 and the NULL-quarter rules from PR #3502. Earlier box-score
 * repair migrations deleted rows without refreshing the table, so it can be stale
 * on prod.
 *
 * After the rebuild the script compares the regular-season rows with the live
 * ibl_team_win_loss view in both directions. Any disagreement rolls the rebuild
 * back and exits 1, so the deploy stops before the window reads a wrong table.
 *
 * Idempotent: a rerun rebuilds the same rows. The rebuild is additive and
 * data-only. It changes no schema and tightens nothing, so no schema-safety guard
 * applies.
 *
 * Usage:
 *   php ibl5/migrations/209_rebuild_team_season_records_for_window.php --dry-run   # rebuild, check, roll back, exit 0
 *   php ibl5/migrations/209_rebuild_team_season_records_for_window.php             # real run
 *
 * DRY_RUN=1 in the environment also reports without writing, but exits 3. That is
 * deliberate: MigrationRunner records a migration as applied whenever the subprocess
 * exits 0 and migrations are forward-only, so a dry run that exited 0 under the runner
 * would permanently mark the rebuild as done without writing anything. Failing closed
 * makes a stray env var break the deploy loudly instead of silently skipping the rebuild.
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
    fwrite(STDERR, "Exiting 3 so a migration runner cannot record this rebuild as applied.\n");
    exit(3);
}

$dryRun = $flagDryRun || $envDryRun;
$step = new Updater\Steps\RefreshTeamSeasonRecordsStep($mysqli_db);

$mysqli_db->begin_transaction();
try {
    $counts = $step->rebuildWithinCurrentTransaction();
    $result = $mysqli_db->query(Updater\Steps\RefreshTeamSeasonRecordsStep::REGULAR_SEASON_DRIFT_SQL);
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $drift = is_array($row) && is_numeric($row['drift'] ?? null) ? (int) $row['drift'] : -1;
    if ($drift !== 0) {
        $mysqli_db->rollback();
        fwrite(STDERR, "ibl_team_season_records disagrees with ibl_team_win_loss on {$drift} rows after rebuild; rolled back.\n");
        exit(1);
    }
    if ($dryRun) {
        $mysqli_db->rollback();
        echo "dry run: rebuilt {$counts['regular']} regular + {$counts['heat']} HEAT rows, drift 0, rolled back\n";
        exit(0);
    }
    $mysqli_db->commit();
    echo "rebuilt {$counts['regular']} regular + {$counts['heat']} HEAT rows, drift 0\n";
} catch (Throwable $e) {
    $mysqli_db->rollback();
    fwrite(STDERR, 'ibl_team_season_records rebuild failed: ' . $e->getMessage() . "\n");
    exit(1);
}
