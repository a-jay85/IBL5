#!/bin/bash

# Bootstrap PHPUnit for GitHub Copilot Agent
# This script sets up the PHP development environment with proper caching and fallback strategies
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

# Check if vendor directory already exists and is complete
if [ -d "vendor" ] && [ -f "vendor/bin/phpunit" ]; then
    success "PHPUnit already installed: $(vendor/bin/phpunit --version)"
    success "Environment is ready!"
    echo ""
    echo "Available commands:"
    echo "  cd ibl5 && phpunit                     # Run all tests"
    echo "  cd ibl5 && phpunit tests/Player/       # Run specific test suite"
    echo "  cd ibl5 && vendor/bin/phpstan analyse  # Run static analysis"
    echo ""
    exit 0
fi

# Need to install dependencies
info "Installing Composer dependencies..."
echo ""

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

# Strategy 1: Use dist (fastest, but requires good network)
if try_composer_install "--prefer-dist" "distribution archives (fastest)"; then
    INSTALL_SUCCESS=true
fi

# Strategy 2: Use source (git clone, more reliable)
if [ "$INSTALL_SUCCESS" = "false" ]; then
    echo ""
    warning "Distribution install failed, trying source install..."
    if try_composer_install "--prefer-source" "git source (more reliable)"; then
        INSTALL_SUCCESS=true
    fi
fi

# Strategy 3: Default method
if [ "$INSTALL_SUCCESS" = "false" ]; then
    echo ""
    warning "Both dist and source failed, trying default method..."
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
    error ""
    error "Possible solutions:"
    error "  1. Wait a few minutes and try again (rate limiting)"
    error "  2. Use a VPN or different network (firewall issues)"
    error "  3. Set COMPOSER_AUTH env var with GitHub token"
    error "  4. Run from a cached environment (GitHub Actions cache)"
    echo ""
    error "Full error log:"
    cat /tmp/composer-install.log
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
echo "  cd ibl5 && vendor/bin/phpstan analyse  # Run static analysis"
echo "  cd ibl5 && composer lint:php           # Check code style"
echo ""
