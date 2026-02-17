# MAMP Database Connection for Copilot Agent

This document provides quick reference for connecting to the local MAMP MySQL database from PHP and command-line tools.

## Quick Connection Test

Verify the database is accessible using credentials from `ibl5/config.php`:

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h localhost \
  -u $DB_USERNAME \
  -p'$DB_PASSWORD' \
  -D iblhoops_ibl5 \
  -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5';"
```

## PHP Connection Details

**Connection Information:**
- Host: `localhost`
- Port: `3306`
- Database: `iblhoops_ibl5`
- Socket: `/Applications/MAMP/tmp/mysql/mysql.sock` (required for PHP mysqli)
- Username & Password: See `ibl5/config.php` (`$dbuname` and `$dbpass`)

## PHP mysqli Connection

Use credentials from `ibl5/config.php`:

```php
<?php
require_once __DIR__ . '/config.php';

$mysqli_db = new mysqli(
    'localhost',
    $dbuname,      // from config.php
    $dbpass,       // from config.php
    $dbname,       // from config.php
    3306,
    '/Applications/MAMP/tmp/mysql/mysql.sock'
);

if ($mysqli_db->connect_error) {
    die("Connection failed: " . $mysqli_db->connect_error);
}

$mysqli_db->set_charset('utf8mb4');
?>
```

## Why the Socket Path Matters

- **Command-line MySQL client:** Uses port 3306 directly
- **PHP mysqli:** Requires explicit socket path to connect locally
- **MAMP Socket Location:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **App bootstrap (`db/db.php`):** Automatically handles this

## Important Notes

1. **MAMP must be running** - Start MAMP before running tests
2. **Production data** - The database contains real IBL data. Always backup before destructive operations
3. **Prepared statements** - Always use prepared statements for security
4. **Test isolation** - Consider using transactions for tests to avoid permanent changes

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `Connection refused` | MAMP MySQL server is not running |
| `Access denied` | Verify credentials in `ibl5/config.php` (`$dbuname`, `$dbpass`, `$dbname`) |
| `Can't connect to MySQL server via socket` | Socket path may be different - check `/Applications/MAMP/tmp/mysql/mysql.sock` exists |
| `PDO default socket wrong` | PHP's default is `/tmp/mysql.sock` but MAMP uses `/Applications/MAMP/tmp/mysql/mysql.sock` |
| `Authentication plugin 'mysql_native_password' cannot be loaded` | You're using Homebrew's mysql client instead of MAMP's. Use `/Applications/MAMP/Library/bin/mysql80/bin/mysql` |

## Important: Homebrew MySQL Client Incompatibility

**Do NOT use** the Homebrew-installed `mysql` command:
```bash
# ❌ WRONG - Will fail with authentication plugin error
mysql -h 127.0.0.1 -u root -p'root' iblhoops_ibl5
```

**Always use** MAMP's bundled mysql client:
```bash
# ✅ CORRECT - Use MAMP's mysql client with socket
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p'root' \
  iblhoops_ibl5
```

**Why?** Homebrew's MySQL 9.x client expects plugins that MAMP's MySQL 8.0 server doesn't provide. The PHP mysqli extension works fine because it uses a different connection method.

## Related Documentation

- See `.github/copilot-instructions.md` for full Copilot agent setup
- See `DATABASE_GUIDE.md` for schema reference and query patterns
