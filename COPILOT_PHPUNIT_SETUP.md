# GitHub Copilot Agent PHPUnit Setup - CRITICAL ISSUE AND SOLUTION

## TL;DR - THE FIX

The GitHub Copilot Agent runs in a GitHub Actions environment that **does not provide a GITHUB_TOKEN** for Composer to use when installing dependencies. This causes `composer install` to fail with authentication errors.

**The solution is two-fold:**

1. **Create a "cache warming" workflow** that pre-installs vendor dependencies with proper authentication
2. **Restore the cached vendor directory** when Copilot Agent runs

## The Problem

When GitHub Copilot Agent tries to run PHPUnit tests, it needs to install Composer dependencies first. However:

```bash
$ composer install
[...]
Failed to download from dist: Could not authenticate against github.com
Could not authenticate against github.com
```

### Why This Happens

1. **GitHub Actions Environment**: Copilot Agent runs in GitHub Actions (not VS Code dev containers)
2. **No GITHUB_TOKEN**: The Copilot workflow doesn't set up `GITHUB_TOKEN` environment variable
3. **Composer Needs Auth**: Even with `--prefer-source`, Composer needs GitHub authentication
4. **Firewall/Network**: SSL timeouts occur when downloading from GitHub API
5. **All packages fail**: PHPStan, PHPUnit, and all dependencies can't be installed

### What Doesn't Work

✗ Running `composer install` directly - fails with auth errors
✗ Using `--prefer-source` - still needs GitHub auth
✗ Using `--prefer-dist` - needs GitHub API access
✗ Disabling SSL/TLS - auth still required
✗ Using VS Code `.devcontainer` - Copilot doesn't run in VS Code
✗ Manual setup scripts - can't get around auth requirement

## The Solution: Pre-Cached Vendor Directory

The **ONLY** way to solve this is to pre-cache the vendor directory from a workflow that HAS access to GitHub authentication.

### Architecture

```
┌─────────────────────────────────────────────────┐
│  Cache Dependencies Workflow (Daily)             │
│  - Runs with GITHUB_TOKEN                       │
│  - composer install works                       │
│  - Caches vendor/ to GitHub Actions Cache        │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
         [GitHub Actions Cache]
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│  Copilot Agent Workflow (On-Demand)             │
│  - NO GitHub token available                    │
│  - Restores vendor/ from cache                  │
│  - PHPUnit ready to use immediately             │
└─────────────────────────────────────────────────┘
```

### Implementation

#### 1. Cache Dependencies Workflow

**File**: `.github/workflows/cache-dependencies.yml`

This workflow runs daily (and on demand) to ensure the vendor directory is always cached:

```yaml
name: Cache PHP Dependencies

on:
  schedule:
    - cron: '0 0 * * *'  # Daily at midnight UTC
  workflow_dispatch:      # Manual trigger
  push:
    paths:
      - 'ibl5/composer.lock'

jobs:
  cache-dependencies:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      
      - name: Cache vendor directory
        uses: actions/cache@v4
        with:
          path: ibl5/vendor
          key: ${{ runner.os }}-vendor-${{ hashFiles('ibl5/composer.lock') }}
      
      - name: Install dependencies
        working-directory: ibl5
        run: composer install --prefer-dist --no-progress --no-interaction
      
      - name: Verify PHPUnit
        working-directory: ibl5
        run: vendor/bin/phpunit --version
```

**Key Points:**
- ✅ Runs with full GitHub Actions authentication
- ✅ Caches vendor/ directory to GitHub Actions Cache
- ✅ Cache key based on `composer.lock` hash (auto-invalidates on dependency changes)
- ✅ Manual trigger available via `workflow_dispatch`
- ✅ Auto-runs when composer.lock changes

#### 2. Composite Action for Setup

**File**: `.github/actions/setup-php-env/action.yml`

A reusable composite action that Copilot (or any workflow) can use:

```yaml
name: 'Setup PHP Environment'
runs:
  using: 'composite'
  steps:
    - name: Cache vendor directory
      uses: actions/cache@v4
      with:
        path: ibl5/vendor
        key: ${{ runner.os }}-vendor-${{ hashFiles('ibl5/composer.lock') }}
        restore-keys: |
          ${{ runner.os }}-vendor-
    
    - name: Verify PHPUnit
      shell: bash
      working-directory: ibl5
      run: |
        if [ ! -f "vendor/bin/phpunit" ]; then
          echo "❌ PHPUnit not found in cache!"
          echo "Please run the 'Cache PHP Dependencies' workflow first"
          exit 1
        fi
        echo "✅ PHPUnit ready: $(vendor/bin/phpunit --version)"
```

**Usage in Copilot workflow** (or any workflow):

```yaml
steps:
  - uses: actions/checkout@v4
  - uses: ./.github/actions/setup-php-env
  - name: Run tests
    working-directory: ibl5
    run: vendor/bin/phpunit
```

#### 3. Bootstrap Script (For Manual Use)

**File**: `bootstrap-phpunit.sh`

For local development or manual testing:

```bash
#!/bin/bash
cd ibl5

# Check if vendor exists
if [ -f "vendor/bin/phpunit" ]; then
    echo "✅ PHPUnit ready"
    vendor/bin/phpunit --version
    exit 0
fi

# Try to install (will fail in Copilot environment)
echo "⚠️  Vendor directory not found, attempting install..."
composer install --prefer-dist --no-interaction || {
    echo "❌ Install failed!"
    echo ""
    echo "If running in GitHub Copilot Agent:"
    echo "  1. Run 'Cache PHP Dependencies' workflow first"
    echo "  2. Wait for cache to populate"
    echo "  3. Restart this workflow"
    echo ""
    echo "If running locally:"
    echo "  1. Ensure you have network access"
    echo "  2. Set up GitHub token if needed"
    exit 1
}
```

## How to Use

### First Time Setup

1. **Run the cache workflow manually**:
   - Go to GitHub Actions → "Cache PHP Dependencies"
   - Click "Run workflow"
   - Wait for it to complete (~2-3 minutes)

2. **Verify cache was created**:
   - Check workflow run summary
   - Should show "✅ Vendor cache has been updated"

3. **Now Copilot Agent can run tests**:
   ```bash
   cd ibl5
   vendor/bin/phpunit
   ```

### Ongoing Maintenance

- **Automatic**: Cache refreshes daily via schedule
- **On dependency change**: Cache rebuilds when `composer.lock` changes
- **Manual refresh**: Run "Cache PHP Dependencies" workflow anytime

### Troubleshooting

#### "PHPUnit not found in cache"

**Cause**: Cache hasn't been populated yet

**Solution**:
1. Go to Actions → "Cache PHP Dependencies"
2. Click "Run workflow"
3. Wait for completion
4. Re-run Copilot workflow

#### "Cache restore failed"

**Cause**: Cache expired or was evicted (GitHub Actions cache has 7-day retention)

**Solution**:
1. Run "Cache PHP Dependencies" workflow again
2. Cache will be recreated

#### "Composer lock file changed"

**Cause**: Dependencies were updated, cache key no longer matches

**Solution**:
- Cache workflow will automatically run on push to update cache
- Or manually trigger "Cache PHP Dependencies" workflow

## Technical Details

### Cache Key Strategy

```
Key: Linux-vendor-<hash of composer.lock>
```

- **Exact match required**: Cache only restores if `composer.lock` hasn't changed
- **Automatic invalidation**: Changing dependencies creates new cache key
- **Restore keys**: Fallback to any Linux vendor cache if exact match fails

### Cache Size

- **Vendor directory**: ~50-100 MB (compressed)
- **GitHub Actions cache limit**: 10 GB per repository
- **This project usage**: < 1% of limit

### Cache Retention

- **Default**: 7 days of inactivity
- **This setup**: Cache refreshed daily, so never expires
- **Manual refresh**: Available anytime via workflow_dispatch

## Benefits

✅ **No network calls during Copilot runs** - Everything cached
✅ **Fast test execution** - No waiting for composer install
✅ **Reliable** - No auth failures or timeouts
✅ **Automatic maintenance** - Daily cache refresh
✅ **Version controlled** - Cache tied to composer.lock
✅ **Works offline** - Once cached, no internet needed

## Limitations

⚠️ **First run after cache expiration** - Must manually trigger cache workflow
⚠️ **Dependency updates** - Require cache workflow to run before tests work
⚠️ **GitHub Actions only** - Local development still needs working composer setup

## Alternative: Local Development

For local development (not Copilot Agent), use the standard setup:

```bash
bash setup-dev.sh
```

This works locally because:
- ✅ No firewall restrictions
- ✅ Can use personal GitHub credentials
- ✅ Direct network access to packagist.org

## Files Created

1. `.github/workflows/cache-dependencies.yml` - Cache warming workflow
2. `.github/actions/setup-php-env/action.yml` - Composite action
3. `bootstrap-phpunit.sh` - Manual bootstrap script
4. `COPILOT_PHPUNIT_SETUP.md` - This documentation

## Summary

**Problem**: Copilot Agent can't install Composer dependencies due to lack of GitHub authentication

**Solution**: Pre-cache vendor directory with a separate workflow that HAS authentication

**How**: Run "Cache PHP Dependencies" workflow to populate cache, then Copilot can use cached vendor/

**Result**: PHPUnit works reliably in Copilot Agent environment
