#!/bin/bash

set -e

echo "🚀 Setting up IBL5 development environment..."

# Navigate to the ibl5 directory
cd "$(dirname "$0")/../ibl5"

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP 8.3 or higher."
    exit 1
fi

echo "✓ PHP version: $(php -v | head -n 1)"

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo "📦 Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
else
    echo "✓ Composer is installed: $(composer --version)"
fi

# Install/update dependencies
echo "📥 Installing Composer dependencies..."
composer install --prefer-dist --no-progress

# Verify PHPUnit is available
if [ -f "vendor/bin/phpunit" ]; then
    echo "✓ PHPUnit installed: $(vendor/bin/phpunit --version)"
else
    echo "❌ PHPUnit installation failed"
    exit 1
fi

# Verify PHPStan is available
if [ -f "vendor/bin/phpstan" ]; then
    echo "✓ PHPStan installed: $(vendor/bin/phpstan --version)"
else
    echo "⚠️  PHPStan not available"
fi

echo ""
echo "✅ Development environment setup complete!"
echo ""
echo "Available commands:"
echo "  phpunit tests/              - Run the test suite"
echo "  phpunit tests/Player/       - Run specific test suite"
echo "  composer analyse            - Run PHPStan analysis"
echo "  composer lint:php           - Check code style"
echo "  composer lint:php:fix       - Fix code style"
