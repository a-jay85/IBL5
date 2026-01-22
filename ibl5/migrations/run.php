<?php

declare(strict_types=1);

/**
 * Migration Runner
 *
 * Runs database migrations for the IBL5 project.
 *
 * Usage:
 *   php migrations/run.php [--down] [--force] [migration-file]
 *
 * Options:
 *   --down     Run the down() method to rollback migrations
 *   --force    Skip confirmation prompt
 *   [file]     Run a specific migration file only
 */

// Change to project root
chdir(dirname(__DIR__));

// Parse command line arguments
$options = getopt('', ['down', 'force', 'help']);
$args = array_slice($argv, 1);
$args = array_filter($args, fn($a) => !str_starts_with($a, '--'));
$specificFile = $args[0] ?? null;

if (isset($options['help'])) {
    echo <<<HELP
Migration Runner
================

Runs database migrations for the IBL5 project.

Usage:
  php migrations/run.php [--down] [--force] [migration-file]

Options:
  --down     Run the down() method to rollback migrations
  --force    Skip confirmation prompt
  --help     Show this help message

Examples:
  php migrations/run.php                                    # Run all pending migrations
  php migrations/run.php --down                             # Rollback all migrations
  php migrations/run.php 2026_01_20_000001_add_auth.php    # Run specific migration

HELP;
    exit(0);
}

$rollback = isset($options['down']);
$force = isset($options['force']);

// Load configuration
require_once 'config.php';

// Connect to database
$mysqli = new mysqli($dbhost, $dbuname, $dbpass, $dbname);

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}

echo "=== IBL5 Migration Runner ===\n\n";

// Get migration files
$migrationPath = __DIR__;
$migrations = [];

if ($specificFile) {
    $file = $migrationPath . '/' . $specificFile;
    if (file_exists($file)) {
        $migrations[] = $file;
    } else {
        die("Migration file not found: {$specificFile}\n");
    }
} else {
    // Get all migration files sorted by name
    $files = glob($migrationPath . '/*.php');
    $files = array_filter($files, fn($f) => basename($f) !== 'run.php');
    sort($files);
    $migrations = $files;
}

if (empty($migrations)) {
    echo "No migrations found.\n";
    exit(0);
}

echo "Found " . count($migrations) . " migration(s):\n";
foreach ($migrations as $file) {
    echo "  - " . basename($file) . "\n";
}
echo "\n";

// Confirm
if (!$force) {
    $action = $rollback ? 'rollback' : 'run';
    echo "Continue to {$action} these migrations? (yes/no): ";

    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);

    if (strtolower($line) !== 'yes') {
        echo "Aborted.\n";
        exit(1);
    }
    echo "\n";
}

// Run migrations
if ($rollback) {
    // Reverse order for rollback
    $migrations = array_reverse($migrations);
}

foreach ($migrations as $file) {
    echo "Processing: " . basename($file) . "\n";
    echo str_repeat('-', 50) . "\n";

    try {
        // Load migration file - it should return a callable or object
        $migrationFactory = require $file;

        // Handle different return types
        if (is_callable($migrationFactory)) {
            // If it's a callable, call it with mysqli to get the migration object
            $migration = $migrationFactory($mysqli);
        } elseif (is_object($migrationFactory)) {
            // If it's already an object, use it directly
            $migration = $migrationFactory;
        } else {
            echo "ERROR: Migration file did not return a valid object or callable\n\n";
            continue;
        }

        if ($rollback) {
            if (method_exists($migration, 'down')) {
                $migration->down();
            } else {
                echo "No down() method found\n";
            }
        } else {
            if (method_exists($migration, 'up')) {
                $migration->up();
            } else {
                echo "No up() method found\n";
            }
        }
    } catch (Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

$mysqli->close();

echo "=== Migration complete ===\n";
exit(0);
