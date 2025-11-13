# Development Environment Setup

This document explains how the GitHub Copilot Agent and local developers can set up a working development environment with PHPUnit and all necessary tools.

## Quick Start

### For Local Development

```bash
# From repository root
bash setup-dev.sh
```

This will:
1. Check for PHP 8.3+ installation
2. Install Composer if needed
3. Install all Composer dependencies
4. Verify PHPUnit, PHPStan, and PHP_CodeSniffer are available

### For GitHub Copilot Agent

The Copilot Agent uses VS Code Dev Containers for a consistent, reproducible environment:

1. Dev Container automatically initializes when workspace opens
2. `.devcontainer/post-create.sh` runs automatically
3. All dependencies are installed within the container
4. PHPUnit is ready to use without additional setup

## Understanding the Setup Architecture

### Problem: Why Can't Copilot Install From Scratch?

When the Copilot Agent tries to run `composer install` from scratch, it encounters:

- **Network timeouts**: Package downloads can fail or timeout
- **Private repository access**: Some dependencies may reference private repos requiring authentication
- **Rate limiting**: Repeated fresh installs hit Packagist rate limits
- **Time overhead**: Installing 100+ packages on every run is inefficient
- **Unpredictable failures**: Network-dependent operations can fail randomly

### Solution: Pre-Configured Development Environments

Instead of installing from scratch, we use three complementary approaches:

#### 1. **Dev Container (For Copilot Agent) ⭐ PRIMARY**

**Files:**
- `.devcontainer/devcontainer.json` - Container configuration
- `.devcontainer/post-create.sh` - Dependency installation script

**How it works:**
```
1. Copilot opens workspace
   ↓
2. VS Code detects .devcontainer/
   ↓
3. Dev container starts (Ubuntu + PHP 8.3)
   ↓
4. Container runs post-create.sh automatically
   ↓
5. composer install runs in clean environment
   ↓
6. Vendor directory available in container
   ↓
7. PHPUnit ready to use immediately
```

**Benefits:**
- ✅ Isolated, reproducible environment
- ✅ No conflicts with local machine PHP/Composer
- ✅ Automatic dependency installation
- ✅ Cached across container restarts
- ✅ All tools pre-configured

#### 2. **Setup Script (For Local Development)**

**File:** `setup-dev.sh` (repository root)

**How it works:**
```bash
bash setup-dev.sh
  ↓
Checks PHP version (8.3+)
  ↓
Checks Composer installation
  ↓
cd ibl5 && composer install
  ↓
Verifies vendor/bin/phpunit exists
  ↓
Done!
```

**Run before:**
- Starting a new development session
- Pulling changes that modify `composer.lock`
- Setting up after cloning repository

#### 3. **GitHub Actions CI/CD (For Testing)**

**File:** `.github/workflows/tests.yml`

**How it works:**
```
Code pushed to GitHub
  ↓
GitHub Actions workflow triggers
  ↓
Setup PHP 8.3
  ↓
Check if vendor/ is in cache
  ↓
If cached: Restore vendor/
If not: composer install && cache it
  ↓
Run phpunit tests
  ↓
Upload test results
```

**Caching strategy:**
- Cache key: `ibl5/composer.lock` hash
- Cache invalidates when dependencies change
- Subsequent runs use cached dependencies

**Benefits:**
- ✅ Fast feedback on pull requests
- ✅ Consistent test environment
- ✅ Catches dependency issues early
- ✅ No manual intervention needed

## Running Tests

### In Dev Container (Recommended for Copilot)

```bash
cd ibl5
phpunit                                    # All tests
phpunit tests/Player/                      # Specific suite
phpunit --filter testRenderPlayerHeader    # Specific test
```

### Locally (After Running setup-dev.sh)

```bash
cd ibl5
vendor/bin/phpunit
vendor/bin/phpstan analyse
composer lint:php
```

## Verifying Setup

### Check All Tools Are Installed

```bash
cd ibl5

# PHPUnit
vendor/bin/phpunit --version
# Expected: PHPUnit 12.4.3 by Sebastian Bergmann

# PHPStan
vendor/bin/phpstan --version
# Expected: PHPStan X.X.X

# PHP_CodeSniffer
vendor/bin/phpcs --version
# Expected: PHP_CodeSniffer X.X.X
```

### Run Test Suite

```bash
cd ibl5
phpunit --no-coverage -q
# Expected: OK (449 tests, XXXX assertions)
```

## Troubleshooting

### "Command 'phpunit' not found"

**Cause:** Dependencies not installed

**Solution:**
```bash
cd ibl5
composer install
```

### "Permission denied" on setup-dev.sh

**Cause:** Script doesn't have execute permissions

**Solution:**
```bash
chmod +x setup-dev.sh
bash setup-dev.sh
```

### PHP version too old

**Cause:** PHP < 8.3 installed

**Solution:**

**macOS (using Homebrew):**
```bash
brew install php@8.3
brew link php@8.3
```

**Linux (Ubuntu/Debian):**
```bash
sudo apt-get update
sudo apt-get install php8.3 php8.3-common php8.3-intl php8.3-mbstring
```

**Windows:**
Download from https://windows.php.net/download/

Or use the dev container (no local PHP needed):
```bash
# In VS Code: Re-open in container
# Command Palette → "Dev Containers: Reopen in Container"
```

### Composer install takes too long

**Cause:** First install, or cache cleared

**Solution:**

1. Ensure network connection is stable
2. If using `composer.lock`, subsequent installs will be faster
3. In GitHub Actions, cache will speed up subsequent runs

**To clear Composer cache:**
```bash
composer clearcache
```

### Can't access private repositories

**Note:** This is the main problem this setup solves. If you encounter this:

1. **For Copilot:** Use dev container (doesn't need private repo access after initial setup)
2. **For Local Dev:** Configure Composer credentials in `~/.composer/auth.json`
3. **For GitHub Actions:** Use GitHub Secrets to pass credentials

## File Organization

```
IBL5/
├── .devcontainer/
│   ├── devcontainer.json           ← Dev container config
│   └── post-create.sh              ← Auto-setup for containers
├── .github/
│   ├── copilot-instructions.md     ← Instructions for Copilot Agent
│   └── workflows/
│       ├── main.yml                ← Deployment workflow
│       └── tests.yml               ← CI/CD with caching
├── setup-dev.sh                    ← Manual setup script
└── ibl5/
    ├── composer.json               ← Dependencies declaration
    ├── composer.lock               ← Locked versions (commit this!)
    ├── vendor/                     ← Installed dependencies
    ├── phpunit.xml                 ← PHPUnit configuration
    └── tests/                      ← Test files
```

## Key Takeaways for Copilot Agent Developers

1. **Use Dev Containers** - They solve the dependency installation problem
2. **Don't run `composer install` in shell commands** - The container handles it automatically
3. **All tools are available after container loads** - No extra setup needed
4. **Run tests directly** - `phpunit` works just like local development
5. **Commit `composer.lock`** - Ensures reproducible environments

## References

- [VS Code Dev Containers Documentation](https://code.visualstudio.com/docs/devcontainers/containers)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHPUnit Documentation](https://phpunit.de/)
- [GitHub Actions Caching](https://docs.github.com/en/actions/using-workflows/caching-dependencies-to-speed-up-workflows)
