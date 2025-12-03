# IBL5 Development Environment Setup

**Purpose:** Environment setup, dependency caching, and database connection for Copilot Agent.  
**When to reference:** Initial setup, troubleshooting dependencies, database connection issues.

---

## ⚠️ CRITICAL: Check for Cached Dependencies FIRST

**Before running `composer install` or any PHPUnit commands:**

```bash
# Check if vendor directory exists
ls -la ibl5/vendor/bin/phpunit 2>/dev/null && echo "✅ PHPUnit cached - use directly"
```

**If vendor exists**, use PHPUnit directly WITHOUT running composer install:
```bash
cd ibl5 && vendor/bin/phpunit
```

**If vendor does NOT exist**, run the bootstrap script:
```bash
bash bootstrap-phpunit.sh
cd ibl5 && vendor/bin/phpunit
```

**DO NOT** run `composer install` directly - always use the bootstrap script or check for existing dependencies first.

---

## Dependency Caching Architecture

### GitHub Actions Dependency Caching

The `.github/workflows/cache-dependencies.yml` and `.github/workflows/tests.yml` workflows implement intelligent dependency caching:

**Cache Dependencies Workflow:**
- Runs daily to keep cache fresh
- Runs when `composer.json` or `composer.lock` changes
- Can be triggered manually
- Pre-caches all PHP dependencies (PHPUnit, etc.)
- **Cache-first strategy**: Checks GitHub cache BEFORE attempting network downloads

**How it works:**
1. GitHub Actions cache checked first using `composer.lock` hash as key
2. If vendor cache exists, restored immediately (no network calls)
3. If cache miss, Composer checks its own cache before downloading
4. Only if both caches miss does Composer download from repositories
5. Downloaded packages are cached for future runs

**Benefits:**
- ✅ Cache-first priority avoids network timeouts
- ✅ No network calls when cache available
- ✅ Fast test execution
- ✅ Consistent PHP version (8.3)
- ✅ Automatic cache refresh on dependency changes

---

## Testing from Command Line

```bash
# Step 1: Check if dependencies are already cached
if [ -f "ibl5/vendor/bin/phpunit" ]; then
    echo "✅ Dependencies cached, running tests directly"
    cd ibl5 && vendor/bin/phpunit
else
    echo "⚠️ No cached dependencies, running bootstrap script"
    bash bootstrap-phpunit.sh
    cd ibl5 && vendor/bin/phpunit
fi
```

**Quick commands when dependencies are cached:**
```bash
cd ibl5
vendor/bin/phpunit                                    # Run all tests
vendor/bin/phpunit tests/Player/                      # Run specific test suite
vendor/bin/phpunit --filter testRenderPlayerHeader    # Run specific test
```

---

## Verifying Setup

```bash
cd ibl5
vendor/bin/phpunit --version               # Should show PHPUnit 12.4.3+
vendor/bin/phpcs --version                 # Should show PHP_CodeSniffer version
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| `Command 'phpunit' not found` | Dependencies not cached - run "Cache PHP Dependencies" workflow manually |
| `Composer install fails` | Check `.github/workflows/cache-dependencies.yml` workflow logs |
| `Tests fail to run` | Verify cache-dependencies workflow completed successfully |
| `Cache outdated` | Manually trigger "Cache PHP Dependencies" workflow |

---

## Key Files

- `.github/workflows/cache-dependencies.yml` - Pre-cache workflow (runs daily)
- `.github/workflows/tests.yml` - CI/CD with dependency caching
- `ibl5/composer.json` - Project dependencies (dev tools)
- `ibl5/composer.lock` - Locked dependency versions

---

## Local Database Connection (MAMP)

### Connection Details
- **Host:** `localhost`
- **Port:** `3306` (MAMP default)
- **Database Name:** `iblhoops_ibl5`
- **Socket:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **Credentials Location:** See `ibl5/config.php` (in `.gitignore`)

### Setting Up DatabaseConnection for Tests

1. **Copy the template file:**
   ```bash
   cd ibl5/classes
   cp DatabaseConnection.php.template DatabaseConnection.php
   ```

2. **Add your credentials:**
   - Find credentials in `ibl5/config.php` (`$dbuname`, `$dbpass`, `$dbname`)
   - Replace `REPLACE_ME_*` placeholders in `DatabaseConnection.php`

3. **Verify .gitignore:**
   - `DatabaseConnection.php` is in `.gitignore` and will never be committed

### PHP Connection Using DatabaseConnection

```php
<?php
// Use the helper class for database access in tests
$player = DatabaseConnection::fetchRow("SELECT * FROM ibl_plr WHERE pid = ?", [123]);

// Fetch multiple rows
$players = DatabaseConnection::fetchAll("SELECT * FROM ibl_plr LIMIT 10");

// Fetch a single value
$playerCount = DatabaseConnection::fetchValue("SELECT COUNT(*) FROM ibl_plr");

// Test connection
if (DatabaseConnection::testConnection()) {
    echo "Connected to database successfully";
}
```

**Key Features:**
- Automatically handles MAMP socket path
- Static methods for simple queries
- Supports prepared statements with parameter binding
- Includes error handling and connection validation
- UTF-8 charset automatically set

**Location:** `ibl5/classes/DatabaseConnection.php` (autoloaded, not committed)  
**Template:** `ibl5/classes/DatabaseConnection.php.template`

### Command Line Verification

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h localhost \
  -u <username_from_config.php> \
  -p'<password_from_config.php>' \
  -D iblhoops_ibl5 \
  -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5';"
```

### Security Notes

- **Credentials in Code:** `DatabaseConnection.php` contains hardcoded credentials for development only
- **Git Protection:** `DatabaseConnection.php` is in `.gitignore` - NEVER committed
- **Never Share:** Do not copy this file or credentials outside local development
- **Template Only:** Only `DatabaseConnection.php.template` is version controlled
- **Production Data:** Local database contains production IBL data - be careful with destructive queries
