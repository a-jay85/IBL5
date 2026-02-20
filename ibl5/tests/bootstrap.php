<?php
/**
 * PHPUnit Bootstrap for IBL5 Tests
 * 
 * This bootstrap file sets up the testing environment for all IBL5 tests
 * without requiring the full IBL5 application context.
 */

// Define that we're running in PHPUnit - prevents Discord HTTP calls, etc.
if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

// Define constants that might be needed
if (!defined('DIRECTORY_SEPARATOR')) {
    define('DIRECTORY_SEPARATOR', '/');
}

// Load Composer autoloader (handles both application classes and test helpers via PSR-4)
require_once __DIR__ . '/../vendor/autoload.php';

// Now that autoloader is registered, define class aliases for backward compatibility
// This maps the old global mock classes to the new namespaced ones
class_alias('Tests\\Integration\\Mocks\\MockDatabase', 'MockDatabase');
class_alias('Tests\\Integration\\Mocks\\MockDatabaseResult', 'MockDatabaseResult');
class_alias('Tests\\Integration\\Mocks\\MockPreparedStatement', 'MockPreparedStatement');
class_alias('Tests\\Integration\\Mocks\\MockMysqliResult', 'MockMysqliResult');
class_alias('Tests\\Integration\\Mocks\\UI', 'UI');
class_alias('Tests\\Integration\\Mocks\\Season', 'Season');

// Set up $_SERVER variables needed by config.php
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}
if (!isset($_SERVER['SCRIPT_FILENAME'])) {
    $_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
}

// Load the configuration for database access
require_once __DIR__ . '/../config.php';

// Set up global $mysqli_db mock for tests that use Player or other refactored classes
// Note: Integration tests should set up their own $mysqli_db that shares the same MockDatabase
// instance used by the test. See ExtensionIntegrationTest for example.
//
// Unit tests that directly mock Player/PlayerRepository don't need this global.

