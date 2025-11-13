#!/bin/bash

# Development setup script for IBL5
# This script ensures all dependencies are installed and the development environment is ready
# Run this script before working on the project: bash setup-dev.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR/ibl5"

echo "🚀 IBL5 Development Environment Setup"
echo "====================================="
echo ""

# Color output helpers
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

success() {
    echo -e "${GREEN}✓${NC} $1"
}

error() {
    echo -e "${RED}✗${NC} $1"
}

warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check PHP installation
echo "Checking PHP installation..."
if ! command -v php &> /dev/null; then
    error "PHP is not installed"
    error "Please install PHP 8.3 or higher from https://www.php.net/downloads"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
success "PHP $PHP_VERSION detected"

# Check if PHP version is 8.3 or higher
REQUIRED_VERSION="8.3"
if ! php -r "exit(version_compare(PHP_VERSION, '$REQUIRED_VERSION', '>=') ? 0 : 1);"; then
    warning "PHP $REQUIRED_VERSION or higher is recommended for optimal compatibility"
fi

# Check Composer installation
echo ""
echo "Checking Composer installation..."
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    
    # Try to install Composer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" 2>/dev/null || {
        error "Failed to download Composer installer"
        error "Please install Composer manually: https://getcomposer.org/download/"
        exit 1
    }
    
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>/dev/null || {
        error "Failed to install Composer"
        exit 1
    }
    
    rm -f composer-setup.php
    success "Composer installed"
else
    COMPOSER_VERSION=$(composer --version | grep -oP '(?<=version )[0-9.]+')
    success "Composer $COMPOSER_VERSION detected"
fi

# Navigate to project root
echo ""
echo "Setting up project dependencies..."
cd "$PROJECT_ROOT" || {
    error "Could not navigate to project directory: $PROJECT_ROOT"
    exit 1
}

# Install dependencies
if [ -f "composer.json" ]; then
    echo "Running: composer install --prefer-dist"
    if composer install --prefer-dist --no-progress --no-interaction; then
        success "Composer dependencies installed"
    else
        error "Composer install failed. Run without --no-interaction for details:"
        echo ""
        echo "  cd $PROJECT_ROOT"
        echo "  composer install --prefer-dist"
        echo ""
        exit 1
    fi
else
    error "composer.json not found in $PROJECT_ROOT"
    exit 1
fi

# Verify PHPUnit
echo ""
echo "Verifying test environment..."
echo "Current directory: $(pwd)"
echo "Checking for vendor/bin/phpunit..."
if [ -f "vendor/bin/phpunit" ]; then
    PHPUNIT_VERSION=$(vendor/bin/phpunit --version 2>/dev/null | grep -oP '(?<=PHPUnit )[0-9.]+')
    success "PHPUnit $PHPUNIT_VERSION ready"
else
    error "PHPUnit not found. Composer install may have failed."
    echo ""
    echo "Diagnostics:"
    echo "  vendor/ contents:"
    ls -la vendor/ 2>/dev/null | head -20 || echo "    vendor/ directory not found"
    echo ""
    echo "  vendor/bin/ contents:"
    ls -la vendor/bin/ 2>/dev/null | head -20 || echo "    vendor/bin/ directory not found"
    echo ""
    echo "  Trying to run composer again with verbose output..."
    composer install --prefer-dist 2>&1 | tail -30
    exit 1
fi

# Verify PHPStan (optional)
if [ -f "vendor/bin/phpstan" ]; then
    success "PHPStan ready"
else
    warning "PHPStan not available (optional)"
fi

# Summary
echo ""
echo "✅ Setup complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Review the DEVELOPMENT_GUIDE.md for contribution guidelines"
echo "   2. Read the copilot-instructions.md for coding standards"
echo ""
echo "🧪 Run tests:"
echo "   cd ibl5"
echo "   phpunit                           # Run all tests"
echo "   phpunit tests/Player/             # Run specific test suite"
echo ""
echo "📊 Analysis:"
echo "   composer analyse                  # Run PHPStan static analysis"
echo "   composer lint:php                 # Check code style (PSR-12)"
echo "   composer lint:php:fix             # Auto-fix code style"
echo ""
