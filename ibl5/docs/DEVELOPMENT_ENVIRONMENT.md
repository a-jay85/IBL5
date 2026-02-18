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
vendor/bin/phpunit --version               # Should show PHPUnit 13.0+
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
| `Authentication plugin error` | You're using Homebrew's mysql client — use MAMP's: `/Applications/MAMP/Library/bin/mysql80/bin/mysql` |
| `Can't connect via socket` | Check socket exists: `/Applications/MAMP/tmp/mysql/mysql.sock`. Ensure MAMP is running. |

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

### Command Line Verification

```bash
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  -h localhost \
  -u <username_from_config.php> \
  -p'<password_from_config.php>' \
  -D iblhoops_ibl5 \
  -e "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5';"
```

### Homebrew MySQL Client Incompatibility

**Do NOT use** the Homebrew-installed `mysql` command:
```bash
# WRONG - Will fail with authentication plugin error
mysql -h 127.0.0.1 -u root -p'root' iblhoops_ibl5
```

**Always use** MAMP's bundled mysql client:
```bash
# CORRECT - Use MAMP's mysql client with socket
/Applications/MAMP/Library/bin/mysql80/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p'root' \
  iblhoops_ibl5
```

**Why?** Homebrew's MySQL 9.x client expects plugins that MAMP's MySQL 8.0 server doesn't provide. The PHP mysqli extension works fine because it uses a different connection method.

### Why the Socket Path Matters

- **Command-line MySQL client:** Uses port 3306 directly
- **PHP mysqli:** Requires explicit socket path to connect locally
- **MAMP Socket Location:** `/Applications/MAMP/tmp/mysql/mysql.sock`
- **DatabaseConnection class:** Automatically handles this

### Security Notes

- **Credentials Location:** Database credentials live in `ibl5/config.php` (gitignored)
- **Never Share:** Do not copy credentials outside local development
- **Production Data:** Local database contains production IBL data - be careful with destructive queries
