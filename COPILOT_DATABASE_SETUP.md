# GitHub Copilot Agent - Local Database Connection Setup

**Status:** ✅ Successfully Configured and Tested  
**Date:** November 21, 2025  
**Test Results:** 5/5 tests passing

## Overview

The GitHub Copilot Agent can now connect to your local MAMP MySQL database for fetching production data during test development and refactoring work. This eliminates the need for mocking and allows tests to work with real IBL data.

## What Was Set Up

### 1. **DatabaseConnection Helper Class**
- **Location:** `ibl5/classes/DatabaseConnection.php`
- **Purpose:** Unified interface for accessing the local MAMP database
- **Features:**
  - Automatic socket path handling (`/Applications/MAMP/tmp/mysql/mysql.sock`)
  - Connection pooling (reuses single connection)
  - Prepared statement support
  - Built-in error handling
  - Static methods for easy use in tests

### 2. **Database Connection Tests**
- **Location:** `ibl5/tests/DatabaseConnectionTest.php`
- **Tests Included:**
  - Connection verification
  - Database status retrieval
  - Player data fetching
  - Table counting
  - Multiple row retrieval
- **Status:** ✅ All 5 tests passing

### 3. **Documentation**
- **Updated:** `.github/copilot-instructions.md`
  - Added complete MAMP connection details
  - Documented PHP connection methods
  - Added DatabaseConnection class usage examples
  - Included troubleshooting section
  
- **Created:** `ibl5/MAMP_DATABASE_CONNECTION.md`
  - Quick reference guide
  - Connection details
  - Usage examples
  - Troubleshooting guide

## Connection Details

| Property | Value |
|----------|-------|
| **Host** | localhost |
| **Port** | 3306 |
| **Database** | iblhoops_ibl5 |
| **Socket** | /Applications/MAMP/tmp/mysql/mysql.sock |
| **Character Set** | utf8mb4 |
| **Username & Password** | See `ibl5/config.php` (`$dbuname` and `$dbpass`) |

## How to Use

### For Test Development

```php
<?php
require_once __DIR__ . '/mainfile.php';

// Fetch a single player
$player = DatabaseConnection::fetchRow("SELECT * FROM ibl_plr WHERE pid = ?", [123]);

// Fetch multiple players
$players = DatabaseConnection::fetchAll("SELECT * FROM ibl_plr LIMIT 10");

// Get a count
$playerCount = DatabaseConnection::fetchValue("SELECT COUNT(*) FROM ibl_plr");

// Check connection
if (DatabaseConnection::testConnection()) {
    $status = DatabaseConnection::getStatus();
    echo "Connected to {$status['database']} on {$status['host']}";
}
?>
```

### For CLI Testing

Verify connection using credentials from `ibl5/config.php`:
```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h localhost \
  -u $DB_USERNAME \
  -p'$DB_PASSWORD' \
  -D iblhoops_ibl5 \
  -e "SELECT VERSION();"
```

Run database tests:
```bash
cd ibl5
vendor/bin/phpunit tests/DatabaseConnectionTest.php
```

## Key Technical Details

### Why Socket Path Matters

PHP's mysqli extension requires an explicit socket path for local Unix connections:
- **MySQL CLI:** Uses TCP port 3306 (works with localhost)
- **PHP mysqli:** Requires explicit socket path for local connections
- **MAMP Location:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **DatabaseConnection:** Automatically handles this complexity

### Connection Pooling

The DatabaseConnection class maintains a single connection:
```php
// First call: Creates connection
$data1 = DatabaseConnection::fetchRow($query1);

// Subsequent calls: Reuses connection
$data2 = DatabaseConnection::fetchRow($query2);
$data3 = DatabaseConnection::fetchAll($query3);

// Optional: Close connection
DatabaseConnection::close();
```

## For Copilot Agent

When Copilot creates tests or needs database data:

1. **Include mainfile.php** in test bootstrap to autoload classes
2. **Use DatabaseConnection class** instead of creating direct mysqli connections
3. **Use prepared statements** for all parameterized queries
4. **Fetch real data** from database instead of mocking
5. **Clean up** with transactions or careful test isolation

## Verification

All systems working:

```
✅ MAMP MySQL server running
✅ Database credentials validated
✅ Socket path configured
✅ DatabaseConnection class created
✅ 5/5 connection tests passing
✅ 564/564 full test suite passing
✅ Documentation updated
```

## Important Notes

- **MAMP Must Be Running:** Ensure MAMP MySQL is started before running tests
- **Production Data:** Database contains real IBL data - be careful with destructive queries
- **Backup First:** Always backup before running migrations or data modifications
- **Prepared Statements:** Always use parameter binding for security
- **Test Isolation:** Consider transactions to keep tests isolated

## Next Steps for Copilot

The Copilot Agent can now:

1. ✅ Connect to the local database for test data
2. ✅ Fetch real player/team/game data for tests
3. ✅ Verify data integrity during refactoring
4. ✅ Create data-driven tests with actual IBL data
5. ✅ Run integration tests against production schema

## Reference Documentation

- **Main Setup:** `.github/copilot-instructions.md` (Local Database Connection section)
- **Quick Reference:** `ibl5/MAMP_DATABASE_CONNECTION.md`
- **Implementation:** `ibl5/classes/DatabaseConnection.php`
- **Tests:** `ibl5/tests/DatabaseConnectionTest.php`
- **Database Schema:** `ibl5/schema.sql`
- **Database Guide:** `DATABASE_GUIDE.md`

---

**Setup Completed By:** GitHub Copilot Agent  
**Tested:** November 21, 2025  
**Status:** Ready for Production Use
