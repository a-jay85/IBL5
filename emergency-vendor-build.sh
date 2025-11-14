#!/bin/bash

# Emergency fallback: Manually build vendor directory from composer cache
# This is a workaround for when composer install fails due to authentication issues

set -e

CACHE_DIR="$HOME/.cache/composer/vcs"
PROJECT_DIR="/home/runner/work/IBL5/IBL5/ibl5"
VENDOR_DIR="$PROJECT_DIR/vendor"

echo "🚨 Emergency vendor directory builder"
echo "======================================"
echo ""
echo "This script manually builds the vendor directory from cached git repositories"
echo "when composer install fails due to authentication issues."
echo ""

cd "$PROJECT_DIR"

# Clean vendor directory
rm -rf "$VENDOR_DIR"
mkdir -p "$VENDOR_DIR"

# Function to copy package from cache
copy_package() {
    local github_url="$1"
    local vendor_name="$2"
    local package_name="$3"
    local version_tag="$4"
    
    local cache_path="$CACHE_DIR/https---github.com-${github_url}.git"
    local vendor_path="$VENDOR_DIR/$vendor_name/$package_name"
    
    if [ ! -d "$cache_path" ]; then
        echo "⚠️  Cache not found for $vendor_name/$package_name"
        return 1
    fi
    
    echo "📦 Copying $vendor_name/$package_name..."
    mkdir -p "$(dirname "$vendor_path")"
    mkdir -p "$vendor_path"
    
    # Use git archive to extract files from the cached repository
    cd "$cache_path"
    if git archive --format=tar "$version_tag" 2>/dev/null | tar -x -C "$vendor_path"; then
        echo "   ✅ Copied to vendor/$vendor_name/$package_name"
        cd "$PROJECT_DIR"
        return 0
    else
        echo "   ⚠️  Tag $version_tag not found, trying HEAD..."
        if git archive --format=tar HEAD | tar -x -C "$vendor_path"; then
            echo "   ✅ Copied to vendor/$vendor_name/$package_name (HEAD)"
            cd "$PROJECT_DIR"
            return 0
        else
            echo "   ❌ Failed to copy"
            cd "$PROJECT_DIR"
            return 1
        fi
    fi
}

echo "Copying packages from cache..."
echo ""

# PHPUnit and related packages
copy_package "sebastianbergmann-phpunit" "phpunit" "phpunit" "12.4.3"
copy_package "sebastianbergmann-php-code-coverage" "phpunit" "php-code-coverage" "12.4.0"
copy_package "sebastianbergmann-php-timer" "phpunit" "php-timer" "8.0.0"
copy_package "sebastianbergmann-php-text-template" "phpunit" "php-text-template" "5.0.0"
copy_package "sebastianbergmann-php-invoker" "phpunit" "php-invoker" "6.0.0"
copy_package "sebastianbergmann-php-file-iterator" "phpunit" "php-file-iterator" "6.0.0"

# Sebastian Bergmann utilities
copy_package "sebastianbergmann-version" "sebastian" "version" "6.0.0"
copy_package "sebastianbergmann-type" "sebastian" "type" "6.0.3"
copy_package "sebastianbergmann-recursion-context" "sebastian" "recursion-context" "7.0.1"
copy_package "sebastianbergmann-object-reflector" "sebastian" "object-reflector" "5.0.0"
copy_package "sebastianbergmann-object-enumerator" "sebastian" "object-enumerator" "7.0.0"
copy_package "sebastianbergmann-global-state" "sebastian" "global-state" "8.0.2"
copy_package "sebastianbergmann-exporter" "sebastian" "exporter" "7.0.2"
copy_package "sebastianbergmann-environment" "sebastian" "environment" "8.0.3"
copy_package "sebastianbergmann-diff" "sebastian" "diff" "7.0.0"
copy_package "sebastianbergmann-comparator" "sebastian" "comparator" "7.1.3"
copy_package "sebastianbergmann-cli-parser" "sebastian" "cli-parser" "4.2.0"
copy_package "sebastianbergmann-lines-of-code" "sebastian" "lines-of-code" "4.0.0"
copy_package "sebastianbergmann-complexity" "sebastian" "complexity" "5.0.0"

# Other dependencies
copy_package "myclabs-DeepCopy" "myclabs" "deep-copy" "1.13.4"
copy_package "nikic-PHP-Parser" "nikic" "php-parser" "v5.6.2"
copy_package "phar-io-version" "phar-io" "version" "3.2.1"
copy_package "phar-io-manifest" "phar-io" "manifest" "2.0.4"
copy_package "theseer-tokenizer" "theseer" "tokenizer" "1.3.0"
copy_package "PHPCSStandards-PHP-CodeSniffer" "squizlabs" "php_codesniffer" "4.0.1"
copy_package "staabm-side-effects-detector" "staabm" "side-effects-detector" "1.0.5"

echo ""
echo "Generating autoloader..."

# Generate composer autoloader
if composer dump-autoload --no-interaction 2>&1; then
    echo "✅ Autoloader generated"
else
    echo "❌ Failed to generate autoloader"
    exit 1
fi

echo ""
echo "Creating bin directory..."

# Create bin directory and symlinks
mkdir -p "$VENDOR_DIR/bin"

# PHPUnit
if [ -f "$VENDOR_DIR/phpunit/phpunit/phpunit" ]; then
    ln -sf "../phpunit/phpunit/phpunit" "$VENDOR_DIR/bin/phpunit"
    chmod +x "$VENDOR_DIR/phpunit/phpunit/phpunit"
    echo "✅ Created bin/phpunit symlink"
fi

# PHP_CodeSniffer
if [ -f "$VENDOR_DIR/squizlabs/php_codesniffer/bin/phpcs" ]; then
    ln -sf "../squizlabs/php_codesniffer/bin/phpcs" "$VENDOR_DIR/bin/phpcs"
    ln -sf "../squizlabs/php_codesniffer/bin/phpcbf" "$VENDOR_DIR/bin/phpcbf"
    chmod +x "$VENDOR_DIR/squizlabs/php_codesniffer/bin/phpcs"
    chmod +x "$VENDOR_DIR/squizlabs/php_codesniffer/bin/phpcbf"
    echo "✅ Created bin/phpcs and bin/phpcbf symlinks"
fi

echo ""
echo "Verifying installation..."

if [ -f "$VENDOR_DIR/bin/phpunit" ]; then
    echo "✅ PHPUnit: $($VENDOR_DIR/bin/phpunit --version)"
else
    echo "❌ PHPUnit not found!"
    exit 1
fi

if [ -f "$VENDOR_DIR/bin/phpcs" ]; then
    echo "✅ PHP_CodeSniffer: $($VENDOR_DIR/bin/phpcs --version)"
else
    echo "⚠️  PHP_CodeSniffer not found"
fi

echo ""
echo "✅ Emergency vendor directory build complete!"
echo ""
echo "You can now run:"
echo "  cd ibl5"
echo "  vendor/bin/phpunit"
echo ""
