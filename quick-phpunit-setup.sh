#!/bin/bash

# Quick fix for PHPUnit installation in GitHub Copilot Agent
# This script temporarily removes PHPStan to allow composer install to succeed

set -e

cd "$(dirname "$0")/ibl5"

echo "🔧 Quick PHPUnit Setup (without PHPStan)"
echo "========================================="
echo ""
echo "This script works around the GitHub authentication issue by"
echo "temporarily removing PHPStan (which requires GitHub API access)."
echo ""

# Configure composer to use git sources
echo "📝 Configuring Composer..."
composer config --global use-github-api false 2>/dev/null || true

# Backup original files
if [ -f "composer.json" ] && [ ! -f "composer.json.original" ]; then
    cp composer.json composer.json.original
    echo "✓ Backed up composer.json"
fi

if [ -f "composer.lock" ] && [ ! -f "composer.lock.original" ]; then
    cp composer.lock composer.lock.original
    echo "✓ Backed up composer.lock"
fi

# Remove PHPStan temporarily
echo ""
echo "📦 Removing PHPStan temporarily..."
cat composer.json | jq 'del(.["require-dev"]["phpstan/phpstan"])' > composer.json.tmp
mv composer.json.tmp composer.json
rm -f composer.lock

# Install dependencies
echo ""
echo "📥 Installing dependencies (this may take 1-2 minutes)..."
if composer install --prefer-source --no-interaction 2>&1 | grep -E "(Installing|Generating|packages you are using)"; then
    echo ""
    echo "✅ Installation successful!"
else
    echo ""
    echo "❌ Installation failed"
    # Restore originals
    if [ -f "composer.json.original" ]; then
        mv composer.json.original composer.json
    fi
    if [ -f "composer.lock.original" ]; then
        mv composer.lock.original composer.lock
    fi
    exit 1
fi

# Verify PHPUnit
echo ""
echo "🧪 Verifying installation..."
if [ -f "vendor/bin/phpunit" ]; then
    PHPUNIT_VERSION=$(vendor/bin/phpunit --version)
    echo "✅ $PHPUNIT_VERSION"
else
    echo "❌ PHPUnit not found!"
    exit 1
fi

# Restore original composer.json but keep vendor directory
echo ""
echo "📝 Restoring original composer.json..."
if [ -f "composer.json.original" ]; then
    mv composer.json.original composer.json
    echo "✓ Original composer.json restored"
fi

# Keep the working composer.lock for reference
if [ -f "composer.lock.original" ]; then
    rm composer.lock.original
fi

echo ""
echo "✅ Setup complete!"
echo ""
echo "PHPUnit is ready to use:"
echo "  cd ibl5"
echo "  vendor/bin/phpunit"
echo "  vendor/bin/phpunit tests/Player/"
echo ""
echo "⚠️  Note: PHPStan is not installed (requires GitHub authentication)"
echo "   PHPUnit tests will work, but static analysis won't."
echo ""
