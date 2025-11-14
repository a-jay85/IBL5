# GitHub Copilot Agent - PHPUnit Setup Guide

## ✅ WORKING SOLUTION

PHPUnit can now run successfully in GitHub Copilot Agent environment!

## Quick Start (Recommended)

```bash
# One command to set up PHPUnit
bash quick-phpunit-setup.sh
```

This takes ~1-2 minutes and sets up everything needed to run PHPUnit tests.

## How to Run Tests

```bash
cd ibl5

# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit tests/Player/

# Run with verbose output
vendor/bin/phpunit --testdox

# Run specific test
vendor/bin/phpunit --filter testGetCurrentSeasonSalary
```

## What the Solution Does

The `quick-phpunit-setup.sh` script:

1. **Configures Composer** to use git sources instead of GitHub API
2. **Temporarily removes PHPStan** (the problematic package)
3. **Installs all other dependencies** from cached git repositories
4. **Restores original composer.json** after installation
5. **Verifies PHPUnit** is ready to use

## Test Results ✅

- **PHPUnit 12.4.3**: ✅ Installed and working
- **26 packages**: ✅ Installed from git cache
- **61 tests**: ✅ All passing
- **Test execution**: ✅ < 1 second
- **PHP_CodeSniffer**: ✅ Available

## What's Not Included

- **PHPStan** (static analysis): ❌ Requires GitHub API authentication
  - Not needed for running PHPUnit tests
  - Can be installed separately if GitHub token is available

## Technical Details

### The Problem

GitHub Copilot Agent runs in a GitHub Actions environment without a `GITHUB_TOKEN` configured for Composer. This causes:

- All packages hosted on GitHub API fail to download
- Specifically, **PHPStan** is distributed as a zipball via GitHub API
- Composer can't authenticate, so downloads fail

### The Solution

Since PHPStan is the only blocker:

1. All other 26 packages are available as git repositories
2. Composer can clone these from its VCS cache without authentication
3. By temporarily removing PHPStan, installation succeeds
4. PHPUnit works perfectly without PHPStan

### Why This Works

```
composer.lock (with PHPStan)
  ├─ phpstan/phpstan → zipball from api.github.com → ❌ Needs auth
  ├─ phpunit/phpunit → git clone → ✅ Works from cache
  ├─ sebastian/* → git clone → ✅ Works from cache
  └─ 23 other packages → git clone → ✅ Works from cache

composer.lock (without PHPStan)
  ├─ phpunit/phpunit → git clone → ✅ Works from cache
  ├─ sebastian/* → git clone → ✅ Works from cache
  └─ 24 other packages → git clone → ✅ Works from cache
  Result: All installed successfully! ✅
```

## Alternative Solutions

### Option 1: Manual Trigger (If you have repo access)

1. Go to GitHub Actions → "Cache PHP Dependencies"
2. Click "Run workflow"  
3. Wait for completion
4. Copilot can then restore from cache

### Option 2: Bootstrap Script

```bash
bash bootstrap-phpunit.sh
```

This tries multiple installation strategies but may still fail due to auth issues.

### Option 3: Emergency Vendor Build

```bash
bash emergency-vendor-build.sh
```

This manually extracts packages from git cache using `git archive`.

## Files Created

| File | Purpose |
|------|---------|
| `quick-phpunit-setup.sh` | **Recommended**: Fast, reliable setup |
| `bootstrap-phpunit.sh` | Alternative with multiple strategies |
| `emergency-vendor-build.sh` | Manual fallback using git cache |
| `.github/workflows/cache-dependencies.yml` | Pre-cache workflow (requires auth) |
| `.github/actions/setup-php-env/action.yml` | Composite action for workflows |
| `COPILOT_PHPUNIT_SETUP.md` | Technical documentation |

## Troubleshooting

### "PHPUnit not found"

Run: `bash quick-phpunit-setup.sh`

### "Permission denied"

Run: `chmod +x quick-phpunit-setup.sh && bash quick-phpunit-setup.sh`

### "Composer install failed"

The quick setup script should work. If not:
1. Check network connectivity
2. Verify PHP 8.3+ is installed
3. Check if composer cache exists: `ls ~/.cache/composer/vcs/`

### "Tests fail with class not found"

The autoloader may not be complete. Try:
```bash
cd ibl5
composer dump-autoload
vendor/bin/phpunit
```

## Future Improvements

1. **Add PHPStan download**: Create a script to download PHPStan phar directly
2. **Workflow integration**: Add cache warming to PR workflows
3. **Docker image**: Pre-build container with vendor directory
4. **Packagist mirror**: Use alternative package source

## Summary

✅ **PHPUnit works** in Copilot Agent environment  
✅ **Quick setup** (< 2 minutes)  
✅ **All tests passing** (61/61)  
✅ **Reliable solution** using git cache  
⚠️ **PHPStan unavailable** (not needed for testing)

---

**Last tested**: November 14, 2025  
**PHPUnit version**: 12.4.3  
**PHP version**: 8.3.6  
**Test suite**: 61 tests, 0 failures
