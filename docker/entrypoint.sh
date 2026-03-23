#!/bin/sh
# Validate vendor/ before Apache starts.
# Catches broken symlinks, missing autoload, or other corruption.

VENDOR="/var/www/html/ibl5/vendor/autoload.php"
LOGS_DIR="/var/www/html/ibl5/logs"

# Ensure logs/ directory exists and is writable by www-data (Apache user).
# In CI and Docker, the ibl5/ volume is mounted from the host, so directory
# ownership may not match the container's www-data user.
mkdir -p "$LOGS_DIR"
chown www-data:www-data "$LOGS_DIR"

if [ -L "/var/www/html/ibl5/vendor" ] && [ ! -e "/var/www/html/ibl5/vendor" ]; then
    echo "ERROR: ibl5/vendor is a broken symlink."
    echo "Fix: rm ibl5/vendor && composer install"
    echo "Then: docker compose restart php"
    exit 1
elif [ ! -f "$VENDOR" ]; then
    echo "ERROR: $VENDOR not found."
    echo "Fix: cd ibl5 && composer install"
    echo "Then: docker compose restart php"
    exit 1
fi

exec "$@"
