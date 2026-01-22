<?php

declare(strict_types=1);

/**
 * Migration Cutover Script
 *
 * Performs the one-time cutover from PHP-Nuke auth to Laravel Auth.
 *
 * This script:
 * 1. Invalidates all existing sessions
 * 2. Clears nuke session tables
 * 3. Clears remember tokens
 * 4. Enables the NUKE_AUTH_DISABLED flag
 *
 * After running this script, all users will need to log in again
 * and their passwords will be migrated from MD5 to bcrypt on first login.
 *
 * Usage:
 *   php scripts/migration-cutover.php [--dry-run] [--force]
 *
 * Options:
 *   --dry-run  Show what would be done without making changes
 *   --force    Skip confirmation prompt
 */

// Change to project root
chdir(dirname(__DIR__));

// Parse command line arguments
$options = getopt('', ['dry-run', 'force', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Migration Cutover Script
=========================

This script performs the one-time cutover from PHP-Nuke authentication
to Laravel Auth. After running this script:

1. All existing sessions will be invalidated
2. All users will need to log in again
3. On first login, passwords are migrated from MD5 to bcrypt

Usage:
  php scripts/migration-cutover.php [--dry-run] [--force]

Options:
  --dry-run  Show what would be done without making changes
  --force    Skip confirmation prompt
  --help     Show this help message

HELP;
    exit(0);
}

$dryRun = isset($options['dry-run']);
$force = isset($options['force']);

// Load configuration
require_once 'config.php';

// Connect to database
$mysqli = new mysqli($dbhost, $dbuname, $dbpass, $dbname);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== PHP-Nuke to Laravel Auth Migration Cutover ===\n\n";

if ($dryRun) {
    echo "*** DRY RUN MODE - No changes will be made ***\n\n";
}

// Show current stats
echo "Current State:\n";
echo "-------------\n";

// Count nuke users
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM nuke_users WHERE user_active = 1");
$nukeCount = $result ? $result->fetch_assoc()['cnt'] : 0;
echo "Active nuke_users: {$nukeCount}\n";

// Count Laravel users
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM users");
$usersCount = $result ? $result->fetch_assoc()['cnt'] : 0;
echo "Laravel users: {$usersCount}\n";

// Count migrated users
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM users WHERE nuke_user_id IS NOT NULL");
$migratedCount = $result ? $result->fetch_assoc()['cnt'] : 0;
echo "Migrated users: {$migratedCount}\n";

// Count active sessions
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM sessions");
$sessionsCount = $result ? $result->fetch_assoc()['cnt'] : 0;
echo "Active sessions: {$sessionsCount}\n";

// Count nuke sessions
$result = $mysqli->query("SHOW TABLES LIKE 'nuke_session'");
if ($result && $result->num_rows > 0) {
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM nuke_session");
    $nukeSessionsCount = $result ? $result->fetch_assoc()['cnt'] : 0;
    echo "Nuke sessions: {$nukeSessionsCount}\n";
} else {
    $nukeSessionsCount = 0;
    echo "Nuke sessions: table not found\n";
}

echo "\n";

// Confirm cutover
if (!$force && !$dryRun) {
    echo "This will:\n";
    echo "1. Truncate the sessions table (invalidate all logins)\n";
    echo "2. Clear nuke_session table\n";
    echo "3. Clear all remember_token values in users table\n";
    echo "4. Display instructions to enable NUKE_AUTH_DISABLED in config.php\n";
    echo "\nAll users will need to log in again!\n\n";
    echo "Continue? (yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) !== 'yes') {
        echo "Aborted.\n";
        exit(1);
    }
}

echo "\nExecuting cutover...\n";
echo "-------------------\n";

// Step 1: Truncate sessions table
echo "1. Truncating sessions table... ";
if (!$dryRun) {
    $mysqli->query("TRUNCATE TABLE sessions");
    echo "done ({$sessionsCount} sessions cleared)\n";
} else {
    echo "[dry-run] would clear {$sessionsCount} sessions\n";
}

// Step 2: Clear nuke_session table
echo "2. Clearing nuke_session table... ";
if ($nukeSessionsCount > 0) {
    if (!$dryRun) {
        $mysqli->query("DELETE FROM nuke_session");
        echo "done ({$nukeSessionsCount} sessions cleared)\n";
    } else {
        echo "[dry-run] would clear {$nukeSessionsCount} sessions\n";
    }
} else {
    echo "skipped (table empty or not found)\n";
}

// Step 3: Clear remember tokens
echo "3. Clearing remember tokens... ";
if (!$dryRun) {
    $result = $mysqli->query("UPDATE users SET remember_token = NULL WHERE remember_token IS NOT NULL");
    $affected = $mysqli->affected_rows;
    echo "done ({$affected} tokens cleared)\n";
} else {
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM users WHERE remember_token IS NOT NULL");
    $tokensCount = $result ? $result->fetch_assoc()['cnt'] : 0;
    echo "[dry-run] would clear {$tokensCount} tokens\n";
}

// Step 4: Provide config.php instructions
echo "\n";
echo "=== MANUAL STEP REQUIRED ===\n";
echo "Add the following line to config.php after the database settings:\n\n";
echo "    define('NUKE_AUTH_DISABLED', true);\n\n";
echo "This will disable legacy PHP-Nuke authentication and\n";
echo "throw exceptions if any code tries to use it directly.\n";
echo "============================\n";

// Step 5: Optional - Archive nuke tables
echo "\n";
echo "=== OPTIONAL: Archive Legacy Tables (after 90-day verification) ===\n";
echo "Run these SQL commands to archive the legacy tables:\n\n";
echo "    RENAME TABLE nuke_users TO nuke_users_archive;\n";
echo "    RENAME TABLE nuke_authors TO nuke_authors_archive;\n";
echo "    RENAME TABLE nuke_session TO nuke_session_archive;\n";
echo "\n";
echo "Only do this after verifying all users can log in successfully.\n";
echo "================================================================\n";

// Close connection
$mysqli->close();

echo "\n";
if ($dryRun) {
    echo "Dry run complete. Run without --dry-run to apply changes.\n";
} else {
    echo "Cutover complete. All users will need to log in again.\n";
}

exit(0);
