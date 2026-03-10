#!/bin/bash
# Run Playwright E2E tests against a worktree's Docker environment.
# Usage: bin/e2e-wt.sh <worktree-name> [playwright-args...]
#
# Examples:
#   bin/e2e-wt.sh my-feature
#   bin/e2e-wt.sh my-feature --headed
#   bin/e2e-wt.sh my-feature --grep "trading"
#
# Prerequisites:
#   1. Worktree exists at worktrees/<name>
#   2. Docker env running via: bin/wt-up <name> --seed

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# --- Parse arguments ---
WORKTREE_NAME=""
PLAYWRIGHT_ARGS=()

for arg in "$@"; do
    if [ -z "$WORKTREE_NAME" ] && [[ "$arg" != -* ]]; then
        WORKTREE_NAME="$arg"
    else
        PLAYWRIGHT_ARGS+=("$arg")
    fi
done

if [ -z "$WORKTREE_NAME" ]; then
    echo "Usage: bin/e2e-wt.sh <worktree-name> [playwright-args...]" >&2
    echo "" >&2
    echo "Examples:" >&2
    echo "  bin/e2e-wt.sh my-feature" >&2
    echo "  bin/e2e-wt.sh my-feature --headed" >&2
    echo "  bin/e2e-wt.sh my-feature --grep \"trading\"" >&2
    exit 1
fi

SLUG="$WORKTREE_NAME"
WORKTREE_PATH="$REPO_ROOT/worktrees/$WORKTREE_NAME"
PHP_CONTAINER="ibl5-php-$SLUG"
DB_CONTAINER="ibl5-db-$SLUG"

# --- Pre-flight checks ---
if [ ! -d "$WORKTREE_PATH" ]; then
    echo "Error: Worktree not found at $WORKTREE_PATH" >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q "^${PHP_CONTAINER}$"; then
    echo "Error: PHP container '$PHP_CONTAINER' is not running." >&2
    echo "Start it with: bin/wt-up $WORKTREE_NAME --seed" >&2
    exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo "Error: DB container '$DB_CONTAINER' is not running." >&2
    echo "Start it with: bin/wt-up $WORKTREE_NAME --seed" >&2
    exit 1
fi

# --- Fix .env.test inside Docker ---
# The worktree symlink points to a host path that doesn't exist inside the container.
# Copy .env.test.example as a real file so test-state.php can read E2E_TESTING=1.
echo "Fixing .env.test inside Docker container..."
docker exec "$PHP_CONTAINER" sh -c \
    'rm -f /var/www/html/ibl5/.env.test && cp /var/www/html/ibl5/.env.test.example /var/www/html/ibl5/.env.test'

# --- Create test user (idempotent) ---
echo "Ensuring test user exists..."

# Read credentials from .env.test in the main repo
ENV_TEST="$REPO_ROOT/ibl5/.env.test"
if [ ! -f "$ENV_TEST" ]; then
    echo "Error: $ENV_TEST not found. Copy .env.test.example and fill in credentials." >&2
    exit 1
fi

IBL_TEST_USER=$(grep '^IBL_TEST_USER=' "$ENV_TEST" | cut -d= -f2-)
IBL_TEST_PASS=$(grep '^IBL_TEST_PASS=' "$ENV_TEST" | cut -d= -f2-)

if [ -z "$IBL_TEST_USER" ] || [ -z "$IBL_TEST_PASS" ]; then
    echo "Error: IBL_TEST_USER or IBL_TEST_PASS not set in $ENV_TEST" >&2
    exit 1
fi

# Create user via PHP inside the DB container's network.
# Pass credentials as env vars to avoid shell quoting issues with special characters.
docker exec \
    -e "E2E_USER=$IBL_TEST_USER" \
    -e "E2E_PASS=$IBL_TEST_PASS" \
    -e "E2E_DB_HOST=db-$SLUG" \
    "$PHP_CONTAINER" php -r '
$db = new mysqli(getenv("E2E_DB_HOST"), "root", "root", "iblhoops_ibl5");
if ($db->connect_error) { fwrite(STDERR, "DB connection failed: " . $db->connect_error . "\n"); exit(1); }

$user = getenv("E2E_USER");
$hash = password_hash(getenv("E2E_PASS"), PASSWORD_BCRYPT);
$email = "e2e-test@example.com";
$time = time();

// auth_users — ON DUPLICATE KEY UPDATE for idempotency (unique on email)
$stmt = $db->prepare("INSERT INTO auth_users (email, password, username, status, verified, resettable, roles_mask, registered, force_logout) VALUES (?, ?, ?, 0, 1, 1, 1, ?, 0) ON DUPLICATE KEY UPDATE password = VALUES(password), username = VALUES(username)");
$stmt->bind_param("sssi", $email, $hash, $user, $time);
$stmt->execute();

// nuke_users — check if exists first (username is the key)
$check = $db->prepare("SELECT uid FROM nuke_users WHERE username = ?");
$check->bind_param("s", $user);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    $stmt2 = $db->prepare("INSERT INTO nuke_users (username, user_email, user_ibl_team, user_password, name, user_avatar, bio, ublock, theme, user_regdate) VALUES (?, ?, \"Metros\", ?, \"E2E Test User\", \"\", \"\", \"\", \"\", NOW())");
    $stmt2->bind_param("sss", $user, $email, $hash);
    $stmt2->execute();
}

// Assign team
$stmt3 = $db->prepare("UPDATE ibl_team_info SET gm_username = ? WHERE team_name = \"Metros\"");
$stmt3->bind_param("s", $user);
$stmt3->execute();

$db->close();
echo "Test user ready: $user\n";
'

# --- Run Playwright ---
BASE_URL="http://$SLUG.localhost/ibl5/"
echo ""
echo "Running E2E tests against $BASE_URL"
echo ""

cd "$REPO_ROOT/ibl5"
BASE_URL="$BASE_URL" \
IBL_TEST_USER="$IBL_TEST_USER" \
IBL_TEST_PASS="$IBL_TEST_PASS" \
    bunx playwright test ${PLAYWRIGHT_ARGS[@]+"${PLAYWRIGHT_ARGS[@]}"}
