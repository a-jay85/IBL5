#!/bin/sh
# Ensure vendor/ is populated before Apache starts.
# On first run (named volume empty), runs composer install automatically.
# On subsequent runs, detects autoload.php and skips install immediately.

APP_DIR="/var/www/html/ibl5"
VENDOR="$APP_DIR/vendor/autoload.php"
LOGS_DIR="$APP_DIR/logs"

# Ensure logs/ directory exists and is writable by www-data (Apache user).
mkdir -p "$LOGS_DIR"
chown www-data:www-data "$LOGS_DIR"

# Detect broken symlink (shouldn't happen with named volume, but guard anyway)
if [ -L "$APP_DIR/vendor" ] && [ ! -e "$APP_DIR/vendor" ]; then
    echo "ERROR: $APP_DIR/vendor is a broken symlink."
    exit 1
fi

# Run composer install if vendor is missing or incomplete
if [ ! -f "$VENDOR" ]; then
    echo "vendor/autoload.php not found — running composer install..."
    cd "$APP_DIR" && composer install --no-interaction --no-progress --prefer-dist --no-dev
    echo "composer install complete."
fi

exec "$@"
