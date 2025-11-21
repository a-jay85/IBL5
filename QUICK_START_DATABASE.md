# Quick Start: Copilot Database Connection

## One-Liner Test Connection

Use credentials from `ibl5/config.php` (`$dbuname` and `$dbpass`):

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql -h localhost -u $DB_USERNAME -p'$DB_PASSWORD' -D iblhoops_ibl5 -e "SELECT VERSION();"
```

## PHP Database Class (Recommended)

```php
<?php
require_once __DIR__ . '/mainfile.php';

// Fetch one row
$player = DatabaseConnection::fetchRow("SELECT * FROM ibl_plr WHERE pid = ?", [123]);

// Fetch multiple rows
$players = DatabaseConnection::fetchAll("SELECT * FROM ibl_plr LIMIT 10");

// Fetch single value
$count = DatabaseConnection::fetchValue("SELECT COUNT(*) FROM ibl_plr");

// Test connection
if (DatabaseConnection::testConnection()) {
    echo "✅ Connected!";
}

// Get status
$status = DatabaseConnection::getStatus();
print_r($status);
?>
```

## Database Credentials

```
Host:     localhost
Port:     3306
Database: iblhoops_ibl5
Socket:   /Applications/MAMP/tmp/mysql/mysql.sock
User & Password: See ibl5/config.php ($dbuname and $dbpass)
```

## Run Database Tests

```bash
cd /Users/ajaynicolas/Documents/GitHub/IBL5/ibl5
vendor/bin/phpunit tests/DatabaseConnectionTest.php
```

## In Unit Tests

```php
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase {
    public function testFetchesPlayerData()
    {
        // Query real data
        $player = DatabaseConnection::fetchRow(
            "SELECT * FROM ibl_plr WHERE pid = ?", 
            [1]
        );
        
        $this->assertIsArray($player);
        $this->assertNotEmpty($player['pid']);
    }
}
```

## Common Queries

```sql
-- Get a player
SELECT * FROM ibl_plr WHERE pid = 1;

-- Get a team
SELECT * FROM ibl_team_info WHERE teamid = 1;

-- Get player stats
SELECT * FROM ibl_hist WHERE pid = 1 LIMIT 1;

-- Count tables
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'iblhoops_ibl5';

-- List all tables
SHOW TABLES FROM iblhoops_ibl5;
```

## Files Reference

| File | Purpose |
|------|---------|
| `.github/copilot-instructions.md` | Full setup documentation |
| `ibl5/classes/DatabaseConnection.php` | Connection helper class |
| `ibl5/tests/DatabaseConnectionTest.php` | Connection tests |
| `ibl5/MAMP_DATABASE_CONNECTION.md` | Quick reference |
| `ibl5/schema.sql` | Database schema |

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Connection refused | Start MAMP MySQL |
| Access denied | Check credentials (user/password/database) |
| No such file (socket) | MAMP not installed or socket path wrong |
| Tests fail | Run `vendor/bin/phpunit tests/DatabaseConnectionTest.php` first |
