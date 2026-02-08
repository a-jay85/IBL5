# Migration 007: Enable Native PHP Type Casting

## Overview

**Priority:** High (Runtime Type Safety)
**Estimated Time:** 5 minutes
**Risk Level:** Very Low
**Status:** Implementing

## Problem

PHP's mysqli driver returns ALL database values as strings by default, regardless of the actual column type in the database. This causes issues with PHP 8.x strict type declarations:

```php
// Database returns strings
$row['pid'] = "123";      // string "123", not int 123
$row['game3GM'] = "5";    // string "5", not int 5

// PHP strict types reject string assignments
class PlayerData {
    public ?int $playerID;  // Cannot assign string to ?int
}
```

## Solution

Enable `MYSQLI_OPT_INT_AND_FLOAT_NATIVE` option on the mysqli connection. This requires the **mysqlnd** (MySQL Native Driver), which is the default driver in PHP 7.0+ and required in PHP 8.0+.

### What It Does

With this option enabled:
- INT, TINYINT, SMALLINT, MEDIUMINT, BIGINT columns return PHP `int`
- FLOAT, DOUBLE, DECIMAL columns return PHP `float`
- VARCHAR, TEXT, CHAR columns still return PHP `string`
- DATE, DATETIME, TIMESTAMP columns still return PHP `string`

### Code Change

**File:** `ibl5/db/db.php`

Before:
```php
$mysqli_db = new mysqli($dbhost, $dbuname, $dbpass, $dbname);
```

After:
```php
$mysqli_db = new mysqli();
$mysqli_db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
$mysqli_db->real_connect($dbhost, $dbuname, $dbpass, $dbname);
```

## Prerequisites

1. **PHP Version:** PHP 7.0+ (PHP 8.0+ recommended, which requires mysqlnd)
2. **mysqlnd Driver:** Verify with `php -i | grep "mysqlnd"` or `phpinfo()`
3. **Migration 004:** Data type refinements completed (TINYINT, SMALLINT optimizations)

## Benefits

1. **Type Safety:** PHP 8.x strict types work correctly without manual casting
2. **Performance:** Eliminates need for `(int)` casts throughout codebase
3. **Correctness:** Database types match PHP types automatically
4. **Memory:** Integers use less memory than string representations

## Testing

### Verify mysqlnd is Installed

```bash
php -i | grep "mysqlnd"
# Should show: mysqlnd => enabled
```

### Verify Type Casting Works

```php
<?php
require_once 'autoloader.php';
include 'config.php';
include 'db/db.php';

$result = $mysqli_db->query("SELECT pid, age, r_fga FROM ibl_plr LIMIT 1");
$row = $result->fetch_assoc();

var_dump($row['pid']);   // Should be: int(123) not string("123")
var_dump($row['age']);   // Should be: int(25) not string("25")
var_dump($row['r_fga']); // Should be: int(50) not string("50")
```

## Rollback

To rollback, change db.php back to:

```php
$mysqli_db = new mysqli($dbhost, $dbuname, $dbpass, $dbname);
```

And re-add `(int)` casts in repository mapping methods.

## Related Files

- `ibl5/db/db.php` - Connection configuration
- `ibl5/classes/Player/PlayerRepository.php` - Uses native types in PlayerData
- `ibl5/classes/Services/CommonMysqliRepository.php` - Base repository

## Cleanup After Migration

Once native types are enabled, the following `(int)` casts can be removed from PlayerRepository.php (optional, as casts are no-ops on integers):

- `mapBasicFields()` - All integer field casts
- `mapRatingsFromCurrentRow()` - All rating casts
- `mapContractInfo()` - Contract field casts
- Other mapping methods with explicit casts

However, keeping the casts is harmless and provides documentation of expected types.
