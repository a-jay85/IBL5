#!/bin/bash

# Bootstrap PHPUnit for GitHub Copilot Agent
# This script sets up the PHP development environment with proper caching and fallback strategies
# 
# CACHE-FIRST STRATEGY:
# 1. Check if vendor directory already exists (from cache or previous install)
# 2. In GitHub Actions: Try to restore from GitHub Actions cache
# 3. Fall back to composer install only if cache is not available
#
# Run this before any PHPUnit tests: bash bootstrap-phpunit.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR/ibl5"

# Color output helpers
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

success() {
    echo -e "${GREEN}✓${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

echo ""
echo "========================================"
echo "  PHPUnit Environment Bootstrap"
echo "========================================"
echo ""

# Check if we're in GitHub Actions
if [ "$GITHUB_ACTIONS" = "true" ]; then
    info "Running in GitHub Actions environment"
    IS_GITHUB_ACTIONS=true
else
    info "Running in local environment"
    IS_GITHUB_ACTIONS=false
fi

# Navigate to project directory
cd "$PROJECT_DIR" || {
    error "Could not navigate to project directory: $PROJECT_DIR"
    exit 1
}

info "Working directory: $(pwd)"

# Check PHP
if ! command -v php &> /dev/null; then
    error "PHP is not installed"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
success "PHP $PHP_VERSION detected"

# Check Composer
if ! command -v composer &> /dev/null; then
    error "Composer is not installed"
    exit 1
fi

COMPOSER_VERSION=$(composer --version 2>&1 | grep -oP '(?<=version )[0-9.]+' || echo "unknown")
success "Composer $COMPOSER_VERSION detected"

# ============================================================================
# STEP 1: Check if vendor directory already exists and is complete
# ============================================================================
if [ -d "vendor" ] && [ -f "vendor/bin/phpunit" ]; then
    success "PHPUnit already installed: $(vendor/bin/phpunit --version)"
    success "Environment is ready! (Dependencies found in vendor/)"
    echo ""
    echo "Available commands:"
    echo "  cd ibl5 && vendor/bin/phpunit                     # Run all tests"
    echo "  cd ibl5 && vendor/bin/phpunit tests/Player/       # Run specific test suite"
    if [ -f "vendor/bin/phpstan" ]; then
        echo "  cd ibl5 && vendor/bin/phpstan analyse             # Run static analysis"
    fi
    echo ""
    exit 0
fi

# ============================================================================
# STEP 2: Try to restore from GitHub Actions cache (if in GitHub Actions)
# ============================================================================
if [ "$IS_GITHUB_ACTIONS" = "true" ]; then
    info "Checking for GitHub Actions cached dependencies..."
    
    # Calculate cache key based on composer.lock hash (matches workflow cache key)
    if [ -f "composer.lock" ]; then
        LOCK_HASH=$(sha256sum composer.lock | cut -d' ' -f1)
        CACHE_KEY="Linux-vendor-${LOCK_HASH}"
        info "Cache key: $CACHE_KEY"
    else
        warning "composer.lock not found, cannot determine cache key"
    fi
    
    # Check if cache environment variables are available
    if [ -n "$ACTIONS_RUNTIME_TOKEN" ] && [ -n "$ACTIONS_CACHE_URL" ]; then
        info "GitHub Actions cache API available, attempting cache restore..."
        
        # Try to restore cache using the cache API
        # The actions/cache uses a specific protocol for cache restoration
        CACHE_VERSION="gzip"
        CACHE_URL="${ACTIONS_CACHE_URL}_apis/artifactcache/cache?keys=${CACHE_KEY}&version=${CACHE_VERSION}"
        
        CACHE_RESPONSE=$(curl -s -H "Authorization: Bearer $ACTIONS_RUNTIME_TOKEN" \
            -H "Accept: application/json;api-version=6.0-preview.1" \
            "$CACHE_URL" 2>/dev/null || echo "")
        
        if echo "$CACHE_RESPONSE" | grep -q "archiveLocation"; then
            ARCHIVE_URL=$(echo "$CACHE_RESPONSE" | grep -oP '"archiveLocation"\s*:\s*"\K[^"]+')
            if [ -n "$ARCHIVE_URL" ]; then
                info "Cache found! Downloading and extracting..."
                
                # Download and extract the cache
                mkdir -p vendor
                if curl -sL "$ARCHIVE_URL" | tar -xzf - -C . 2>/dev/null; then
                    if [ -f "vendor/bin/phpunit" ]; then
                        success "Cache restored successfully!"
                        success "PHPUnit ready: $(vendor/bin/phpunit --version)"
                        success "Environment is ready! (Restored from GitHub Actions cache)"
                        echo ""
                        echo "Available commands:"
                        echo "  cd ibl5 && vendor/bin/phpunit                     # Run all tests"
                        echo "  cd ibl5 && vendor/bin/phpunit tests/Player/       # Run specific test suite"
                        echo ""
                        exit 0
                    else
                        warning "Cache extracted but PHPUnit not found, falling back to composer install"
                        rm -rf vendor 2>/dev/null || true
                    fi
                else
                    warning "Failed to extract cache, falling back to composer install"
                    rm -rf vendor 2>/dev/null || true
                fi
            fi
        else
            info "No matching cache found for key: $CACHE_KEY"
            info "Cache will be created after successful composer install"
        fi
    else
        info "GitHub Actions cache API not available in this context"
        info "Tip: Ensure the workflow includes the 'actions/cache' step before this script runs"
    fi
fi

# ============================================================================
# STEP 3: Fall back to composer install
# ============================================================================
info "Installing Composer dependencies..."
echo ""

# WORKAROUND: Disable GitHub API to use git cloning instead
# This avoids authentication issues when GitHub token is not available
info "Configuring Composer to use git sources instead of GitHub API..."
composer config --global use-github-api false
composer config --global secure-http false 2>/dev/null || true

# Function to try composer install with different strategies
try_composer_install() {
    local strategy="$1"
    local desc="$2"
    
    info "Trying: $desc"
    
    if composer install $strategy --no-interaction --no-progress 2>&1 | tee /tmp/composer-install.log; then
        if [ -f "vendor/bin/phpunit" ]; then
            success "Successfully installed dependencies using $desc"
            return 0
        else
            warning "Install appeared to succeed but PHPUnit not found"
            return 1
        fi
    else
        warning "Failed to install using $desc"
        # Show last few lines of output for debugging
        tail -10 /tmp/composer-install.log
        return 1
    fi
}

# Try installation with different strategies
INSTALL_SUCCESS=false

# Strategy 1: Use source (git clone from cache, more reliable in our case)
if try_composer_install "--prefer-source" "git source (uses cached repositories)"; then
    INSTALL_SUCCESS=true
fi

# Strategy 2: Use dist if source failed
if [ "$INSTALL_SUCCESS" = "false" ]; then
    echo ""
    warning "Source install failed, trying distribution archives..."
    if try_composer_install "--prefer-dist" "distribution archives"; then
        INSTALL_SUCCESS=true
    fi
fi

# Strategy 3: Default method
if [ "$INSTALL_SUCCESS" = "false" ]; then
    echo ""
    warning "Both source and dist failed, trying default method..."
    info "Trying: default method"
    
    if composer install --no-interaction 2>&1 | tee /tmp/composer-install.log; then
        if [ -f "vendor/bin/phpunit" ]; then
            success "Successfully installed dependencies using default method"
            INSTALL_SUCCESS=true
        fi
    fi
fi

# Check if installation succeeded
if [ "$INSTALL_SUCCESS" = "false" ]; then
    echo ""
    error "All composer install methods failed!"
    echo ""
    error "This is likely due to:"
    error "  1. Network connectivity issues (SSL timeouts)"
    error "  2. GitHub API rate limiting"
    error "  3. Firewall blocking GitHub API access"
    error "  4. Missing GITHUB_TOKEN for authentication"
    echo ""
    error "Possible solutions:"
    error "  1. RECOMMENDED: Manually trigger 'Cache PHP Dependencies' workflow in GitHub Actions"
    error "     This will pre-cache all dependencies for future runs"
    error "  2. Ensure 'actions/cache' step runs BEFORE this script in your workflow"
    error "  3. Wait a few minutes and try again (rate limiting)"
    error "  4. Set COMPOSER_AUTH env var with GitHub token"
    error "  5. Use a VPN or different network (firewall issues)"
    echo ""
    error "Full error log:"
    cat /tmp/composer-install.log | tail -50
    exit 1
fi

# Verify installation
echo ""
info "Verifying installation..."

if [ ! -f "vendor/bin/phpunit" ]; then
    error "PHPUnit not found after installation!"
    error "vendor/bin contents:"
    ls -la vendor/bin/ 2>&1 || echo "vendor/bin not found"
    exit 1
fi

PHPUNIT_VERSION=$(vendor/bin/phpunit --version)
success "PHPUnit ready: $PHPUNIT_VERSION"

if [ -f "vendor/bin/phpstan" ]; then
    PHPSTAN_VERSION=$(vendor/bin/phpstan --version 2>&1 | head -n 1)
    success "PHPStan ready: $PHPSTAN_VERSION"
else
    warning "PHPStan not installed (optional, requires GitHub API access)"
fi

if [ -f "vendor/bin/phpcs" ]; then
    PHPCS_VERSION=$(vendor/bin/phpcs --version)
    success "PHP_CodeSniffer ready: $PHPCS_VERSION"
fi

echo ""
success "Environment setup complete!"
echo ""
echo "Available commands:"
echo "  cd ibl5 && phpunit                     # Run all tests"
echo "  cd ibl5 && phpunit tests/Player/       # Run specific test suite"
if [ -f "vendor/bin/phpstan" ]; then
    echo "  cd ibl5 && vendor/bin/phpstan analyse  # Run static analysis"
fi
echo "  cd ibl5 && composer lint:php           # Check code style"
echo ""
