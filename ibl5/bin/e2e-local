#!/usr/bin/env bash
# Run E2E tests locally with isolated CI-like database via Docker Apache.
# Usage: ./bin/e2e-local.sh [playwright-args...]
#   e.g. ./bin/e2e-local.sh --headed
#        ./bin/e2e-local.sh --grep "trading"
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IBL5_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Worktree-footgun guard: this is the MAIN-checkout runner — it seeds ibl5_e2e_test,
# injects the .e2e-active guard into the symlink-resolved MAIN config.php, and runs
# Playwright against http://main.localhost/ibl5/ (the dev/main stack). A worktree's
# checkout is a linked worktree, so its git-dir (.git/worktrees/<name>) differs from
# its git-common-dir (.git) — a layout-independent, cwd-independent detection key that
# survives worktrees moving out of the repo tree. Fire before any DB/config side effect.
# (CONFIG_DIR is readlink-resolved back to main, so it can't be used here.)
E2E_GIT_DIR="$(git -C "$IBL5_DIR" rev-parse --absolute-git-dir 2>/dev/null || true)"
E2E_GIT_COMMON="$(git -C "$IBL5_DIR" rev-parse --path-format=absolute --git-common-dir 2>/dev/null || true)"
if [ -n "$E2E_GIT_DIR" ] && [ "$E2E_GIT_DIR" != "$E2E_GIT_COMMON" ]; then
    echo "ERROR: e2e-local.sh is the MAIN-checkout E2E runner and serves http://main.localhost/ibl5/." >&2
    echo "       You are inside a worktree — it would seed ibl5_e2e_test but Playwright would hit the MAIN stack, not this worktree's <slug>.localhost DB." >&2
    echo "       Use the worktree-correct path instead:" >&2
    echo "         bin/wt-up <slug> --seed && bin/e2e-wt.sh <slug>" >&2
    exit 1
fi

# Resolve config.php's real directory (follows symlinks to main repo)
CONFIG_DIR="$(cd "$(dirname "$(readlink "$IBL5_DIR/config.php" || echo "$IBL5_DIR/config.php")")" && pwd)"

MYSQL="mariadb"
MYSQL_ARGS="-h 127.0.0.1 --skip-ssl -u root -proot"
DB_NAME="ibl5_e2e_test"

# Pre-flight: ensure vendor/ exists (auto-install if missing)
if [[ ! -f "$IBL5_DIR/vendor/autoload.php" ]]; then
    echo "==> vendor/autoload.php missing — running composer install..."
    (cd "$IBL5_DIR" && composer install --no-interaction --no-progress)
    # Restart PHP container so it picks up the new vendor/
    (cd "$IBL5_DIR/.." && docker compose restart php)
fi

# Pre-flight: verify Docker Apache is running
if ! curl -sf "http://main.localhost/ibl5/" > /dev/null 2>&1; then
    echo "ERROR: Docker Apache is not responding at http://main.localhost/ibl5/"
    echo "Start it with: docker compose up -d"
    exit 1
fi

# Pre-flight: verify MariaDB is reachable
if ! $MYSQL $MYSQL_ARGS -e "SELECT 1" > /dev/null 2>&1; then
    echo "ERROR: Cannot connect to MariaDB at 127.0.0.1:3306."
    echo "Is Docker MariaDB running? Try: docker compose up -d"
    exit 1
fi

# Load .env.test credentials
eval "$(grep -E '^(IBL_TEST_USER|IBL_TEST_PASS)=' "$IBL5_DIR/.env.test")"
export IBL_TEST_USER IBL_TEST_PASS

E2E_GUARD='if (file_exists(__DIR__ . "/.e2e-active")) { require __DIR__ . "/config.e2e.php"; return; }'

inject_config_guard() {
    local config
    local config="$CONFIG_DIR/config.php"
    if [[ -f "$config" ]] && ! grep -q '.e2e-active' "$config" 2>/dev/null; then
        sed -i '' '1 a\
'"$E2E_GUARD"'
' "$config"
        echo "==> Injected E2E config guard into $(basename "$config")"
    fi
}

remove_config_guard() {
    local config
    local config="$CONFIG_DIR/config.php"
    if [[ -f "$config" ]] && grep -q '.e2e-active' "$config" 2>/dev/null; then
        sed -i '' '/.e2e-active/d' "$config"
        echo "Removed E2E config guard from $(basename "$config")"
    fi
}

cleanup() {
    set +e  # Disable errexit — cleanup must run to completion
    echo ""
    echo "==> Cleaning up..."
    rm -f "$CONFIG_DIR/.e2e-active"
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

echo "==> Applying migrations (000 = baseline schema, then alterations)..."
for f in "$IBL5_DIR"/migrations/*.sql; do
    base=$(basename "$f")
    [[ "$base" =~ ^[0-9]{1,3}[a-z]?_ ]] || continue
    echo "  -> $base"
    sed 's/DEFINER=`[^`]*`@`[^`]*`//g' "$f" | $MYSQL $MYSQL_ARGS "$DB_NAME"
done

echo "==> Importing ci-seed.sql..."
$MYSQL $MYSQL_ARGS "$DB_NAME" < "$IBL5_DIR/tests/e2e/fixtures/ci-seed.sql"

echo "==> Creating test user: $IBL_TEST_USER..."
IBL_TEST_USER="$IBL_TEST_USER" IBL_TEST_PASS="$IBL_TEST_PASS" php <<'PHPSCRIPT'
<?php
$hash = password_hash(getenv('IBL_TEST_PASS'), PASSWORD_BCRYPT);
$user = getenv('IBL_TEST_USER');
$email = 'e2e-local@example.com';
$db = new mysqli('127.0.0.1', 'root', 'root', 'ibl5_e2e_test', 3306);

$stmt = $db->prepare("INSERT INTO auth_users (email, password, username, status, verified, resettable, roles_mask, registered, force_logout) VALUES (?, ?, ?, 0, 1, 1, 1, ?, 0)");
$t = time();
$stmt->bind_param('sssi', $email, $hash, $user, $t);
$stmt->execute();

$stmt = $db->prepare("UPDATE ibl_team_info SET gm_username = ? WHERE team_name = 'Metros'");
$stmt->bind_param('s', $user);
$stmt->execute();

$db->close();
echo "Test user created: $user\n";
PHPSCRIPT

# --- Generate test config ---
# Use 'mariadb' as dbhost since Apache runs inside Docker and resolves via Docker DNS
echo "==> Generating config.e2e.php..."
sed -E \
    -e "s/^\\\$dbhost = .*/\\\$dbhost = 'mariadb';/" \
    -e "s/^\\\$dbuname = .*/\\\$dbuname = 'root';/" \
    -e "s/^\\\$dbpass = .*/\\\$dbpass = 'root';/" \
    -e "s/^\\\$dbname = .*/\\\$dbname = '$DB_NAME';/" \
    -e "s/^\\\$display_errors = .*/\\\$display_errors = true;/" \
    "$CONFIG_DIR/config.php" > "$CONFIG_DIR/config.e2e.php"

# Remove the E2E guard from the generated file (avoid infinite recursion)
sed -i '' '/.e2e-active/d' "$CONFIG_DIR/config.e2e.php"

# --- Activate E2E config ---
echo "==> Activating E2E config (touching .e2e-active)..."
touch "$CONFIG_DIR/.e2e-active"

# --- Run tests ---
echo "==> Running E2E tests against Docker Apache..."
set +e  # Don't exit on test failure — let cleanup run
cd "$IBL5_DIR" && bunx playwright test "$@"
TEST_EXIT=$?
exit $TEST_EXIT
