#!/usr/bin/env bash
# Run E2E tests locally with isolated CI-like database.
# Usage: ./bin/e2e-local.sh [playwright-args...]
#   e.g. ./bin/e2e-local.sh --headed
#        ./bin/e2e-local.sh --grep "trading"
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IBL5_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
REPO_ROOT="$(cd "$IBL5_DIR/.." && pwd)"

# Resolve config.php's real directory (follows symlinks to main repo)
CONFIG_DIR="$(cd "$(dirname "$(readlink "$IBL5_DIR/config.php" || echo "$IBL5_DIR/config.php")")" && pwd)"

MYSQL="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
MYSQL_ARGS="--socket=/Applications/MAMP/tmp/mysql/mysql.sock -u root -proot"
DB_NAME="ibl5_e2e_test"
PORT=8081
PHP_PID=""

# Load .env.test credentials
eval "$(grep -E '^(IBL_TEST_USER|IBL_TEST_PASS)=' "$IBL5_DIR/.env.test")"
export IBL_TEST_USER IBL_TEST_PASS

# Fail fast if port is already in use
if lsof -ti:"$PORT" > /dev/null 2>&1; then
    echo "ERROR: Port $PORT is already in use. Kill the process or choose a different port."
    exit 1
fi

E2E_GUARD="if (defined('IBL_E2E_CONFIG')) { require __DIR__ . '/config.e2e.php'; return; }"

inject_config_guard() {
    local config
    for config in "$CONFIG_DIR/config.php" "$CONFIG_DIR/configOlympics.php"; do
        [[ -f "$config" ]] || continue
        if ! grep -q 'IBL_E2E_CONFIG' "$config" 2>/dev/null; then
            sed -i '' '6 a\
'"$E2E_GUARD"'
' "$config"
            echo "==> Injected E2E config guard into $(basename "$config")"
        fi
    done
}

remove_config_guard() {
    local config
    for config in "$CONFIG_DIR/config.php" "$CONFIG_DIR/configOlympics.php"; do
        [[ -f "$config" ]] || continue
        if grep -q 'IBL_E2E_CONFIG' "$config" 2>/dev/null; then
            sed -i '' '/IBL_E2E_CONFIG/d' "$config"
            echo "Removed E2E config guard from $(basename "$config")"
        fi
    done
}

cleanup() {
    set +e  # Disable errexit — cleanup must run to completion
    echo ""
    echo "==> Cleaning up..."
    # Kill PHP server and all forked workers (PHP_CLI_SERVER_WORKERS creates child processes)
    local pids
    pids=$(lsof -ti:"$PORT" 2>/dev/null)
    [[ -n "$pids" ]] && kill $pids 2>/dev/null
    [[ -f "$CONFIG_DIR/config.e2e.php" ]] && rm -f "$CONFIG_DIR/config.e2e.php"
    remove_config_guard
    $MYSQL $MYSQL_ARGS -e "DROP DATABASE IF EXISTS $DB_NAME;" 2>/dev/null
    echo "Done."
}
trap cleanup EXIT

# --- Inject config guard ---
inject_config_guard

# --- Database Setup (mirrors CI workflow) ---
echo "==> Creating database $DB_NAME..."
$MYSQL $MYSQL_ARGS -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;"

echo "==> Importing schema.sql..."
# Patches for MySQL 8.0 compatibility (schema.sql is generated from MariaDB 10.6):
# - Strip DEFINER clauses from views/triggers
# - Strip DEFAULT uuid() (MariaDB expression defaults)
# - Rename duplicate CHECK constraint names (MySQL 8.0 has schema-scoped constraints)
sed -e 's/DEFINER=`[^`]*`@`[^`]*`//g' \
    -e 's/ DEFAULT uuid()//g' \
    "$IBL5_DIR/schema.sql" \
    | awk '
      /CREATE TABLE `ibl_olympics_plr`/ { in_olympics=1 }
      /ENGINE=InnoDB/ && in_olympics { in_olympics=0 }
      in_olympics && /CONSTRAINT `chk_plr_/ {
        gsub(/`chk_plr_/, "`chk_olympics_plr_")
      }
      { print }
    ' \
    | $MYSQL $MYSQL_ARGS "$DB_NAME"

# Migrations are skipped: schema.sql is auto-synced from production after each
# migration run, so it already contains all migration changes. CI runs migrations
# because its schema.sql may lag behind, but locally we always have the latest.

echo "==> Importing ci-seed.sql..."
$MYSQL $MYSQL_ARGS "$DB_NAME" < "$IBL5_DIR/tests/e2e/fixtures/ci-seed.sql"

echo "==> Creating test user: $IBL_TEST_USER..."
IBL_TEST_USER="$IBL_TEST_USER" IBL_TEST_PASS="$IBL_TEST_PASS" php <<'PHPSCRIPT'
<?php
$hash = password_hash(getenv('IBL_TEST_PASS'), PASSWORD_BCRYPT);
$user = getenv('IBL_TEST_USER');
$email = 'e2e-local@example.com';
$db = new mysqli('localhost', 'root', 'root', 'ibl5_e2e_test', 0,
    '/Applications/MAMP/tmp/mysql/mysql.sock');

$stmt = $db->prepare("INSERT INTO auth_users (email, password, username, status, verified, resettable, roles_mask, registered, force_logout) VALUES (?, ?, ?, 0, 1, 1, 1, ?, 0)");
$t = time();
$stmt->bind_param('sssi', $email, $hash, $user, $t);
$stmt->execute();

$stmt = $db->prepare("INSERT INTO nuke_users (username, user_email, user_ibl_team, user_password, name, user_avatar, bio, ublock, theme, user_regdate) VALUES (?, ?, 'Metros', ?, 'E2E Test User', '', '', '', '', NOW())");
$stmt->bind_param('sss', $user, $email, $hash);
$stmt->execute();
$db->close();
echo "Test user created: $user\n";
PHPSCRIPT

# --- Generate test config ---
echo "==> Generating config.e2e.php..."
sed -E \
    -e "s/^\\\$dbhost = .*/\\\$dbhost = '127.0.0.1';/" \
    -e "s/^\\\$dbuname = .*/\\\$dbuname = 'root';/" \
    -e "s/^\\\$dbpass = .*/\\\$dbpass = 'root';/" \
    -e "s/^\\\$dbname = .*/\\\$dbname = '$DB_NAME';/" \
    -e "s/^\\\$display_errors = .*/\\\$display_errors = true;/" \
    "$CONFIG_DIR/config.php" > "$CONFIG_DIR/config.e2e.php"

# Remove the E2E guard from the generated file (avoid infinite recursion)
sed -i '' '/IBL_E2E_CONFIG/d' "$CONFIG_DIR/config.e2e.php"

# --- Start server ---
echo "==> Starting PHP server on port $PORT..."
E2E_TESTING=1 PHP_CLI_SERVER_WORKERS=8 php \
    -d "auto_prepend_file=$SCRIPT_DIR/e2e-prepend.php" \
    -d "log_errors=1" \
    -d "error_log=/tmp/e2e-php-errors.log" \
    -S "0.0.0.0:$PORT" \
    -t "$REPO_ROOT" \
    "$IBL5_DIR/router.php" 2>/tmp/e2e-php-server.log &
PHP_PID=$!

for i in $(seq 1 15); do
    if curl -sf "http://localhost:$PORT/ibl5/" > /dev/null 2>&1; then
        echo "==> Server ready (${i}s)"
        break
    fi
    [[ $i -eq 15 ]] && { echo "Server failed to start"; exit 1; }
    sleep 1
done

# --- Run tests ---
echo "==> Running E2E tests..."
set +e  # Don't exit on test failure — let cleanup run
cd "$IBL5_DIR" && BASE_URL="http://localhost:$PORT/ibl5/" bunx playwright test "$@"
TEST_EXIT=$?
exit $TEST_EXIT
