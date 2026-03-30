#!/bin/bash
# Run E2E tests sharded across multiple PHP containers for a worktree.
# Usage: bin/e2e-wt-sharded.sh <worktree-name> [--shards N] [playwright-args...]
#
# Spins up N-1 extra PHP-Apache containers sharing the worktree's DB,
# then runs N parallel Playwright shards (1 worker each) against them.
#
# Prerequisites:
#   1. Worktree exists at worktrees/<name>
#   2. Docker env running via: bin/wt-up <name> --seed

set -euo pipefail

# Always resolve to the main repo root, even when run from a worktree.
REPO_ROOT="$(cd "$(dirname "$0")/.." && \
    dirname "$(git rev-parse --path-format=absolute --git-common-dir)")"
COMPOSE_FILE="$REPO_ROOT/docker/shard-compose.yml"

# --- Parse arguments ---
SHARD_COUNT=4
WORKTREE_NAME=""
PW_EXTRA_ARGS=()
SKIP_NEXT=false

for arg in "$@"; do
    if $SKIP_NEXT; then
        SHARD_COUNT="$arg"
        SKIP_NEXT=false
        continue
    fi
    if [[ "$arg" == "--shards" ]]; then
        SKIP_NEXT=true
        continue
    fi
    if [[ -z "$WORKTREE_NAME" ]] && [[ "$arg" != -* ]]; then
        WORKTREE_NAME="$arg"
    else
        PW_EXTRA_ARGS+=("$arg")
    fi
done

if [[ -z "$WORKTREE_NAME" ]]; then
    echo "Usage: bin/e2e-wt-sharded.sh <worktree-name> [--shards N] [playwright-args...]" >&2
    echo "" >&2
    echo "Options:" >&2
    echo "  --shards N    Number of shards (default: 4)" >&2
    echo "" >&2
    echo "Examples:" >&2
    echo "  bin/e2e-wt-sharded.sh my-feature" >&2
    echo "  bin/e2e-wt-sharded.sh my-feature --shards 2" >&2
    exit 1
fi

# --- Validate shard count ---
if [[ "$SHARD_COUNT" -le 1 ]]; then
    exec "$REPO_ROOT/bin/e2e-wt.sh" "$WORKTREE_NAME" ${PW_EXTRA_ARGS[@]+"${PW_EXTRA_ARGS[@]}"}
fi
if [[ "$SHARD_COUNT" -gt 4 ]]; then
    echo "Error: Maximum 4 shards supported (shard-compose.yml defines shard-2/3/4)." >&2
    exit 1
fi

WORKTREE_PATH="$REPO_ROOT/worktrees/$WORKTREE_NAME"

if [[ ! -d "$WORKTREE_PATH" ]]; then
    echo "Error: Worktree not found at $WORKTREE_PATH" >&2
    exit 1
fi

# --- Detect slug (same logic as e2e-wt.sh) ---
SANITIZED_NAME="${WORKTREE_NAME//\//-}"
SLUG=""
if docker ps --format '{{.Names}}' | grep -q "^ibl5-php-${SANITIZED_NAME}$"; then
    SLUG="$SANITIZED_NAME"
elif docker ps --format '{{.Names}}' | grep -q "^ibl5-php-${WORKTREE_NAME}$"; then
    SLUG="$WORKTREE_NAME"
else
    PR_NUMBER=$(cd "$WORKTREE_PATH" && gh pr view --json number -q .number 2>/dev/null || true)
    if [[ -n "$PR_NUMBER" ]] && docker ps --format '{{.Names}}' \
        | grep -q "^ibl5-php-pr-${PR_NUMBER}$"; then
        SLUG="pr-$PR_NUMBER"
    fi
fi

if [[ -z "$SLUG" ]]; then
    echo "Error: No running Docker containers found for worktree '$WORKTREE_NAME'." >&2
    echo "Start them with: bin/wt-up $WORKTREE_NAME --seed" >&2
    exit 1
fi

PHP_CONTAINER="ibl5-php-$SLUG"
echo "Detected slug: $SLUG (primary container: $PHP_CONTAINER)"
echo "Shard count: $SHARD_COUNT"

# --- Cleanup trap: always tear down shard containers ---
SHARD_PROJECT="ibl5-shards-$SLUG"
cleanup() {
    echo ""
    echo "Tearing down shard containers..."
    SLUG="$SLUG" REPO_ROOT="$REPO_ROOT" WORKTREE_PATH="$WORKTREE_PATH" \
        docker compose -f "$COMPOSE_FILE" -p "$SHARD_PROJECT" \
        down --remove-orphans 2>/dev/null || true
}
trap cleanup EXIT

# --- Fix .env.test on primary container ---
echo "Fixing .env.test on primary container..."
docker exec "$PHP_CONTAINER" sh -c \
    'rm -f /var/www/html/ibl5/.env.test && \
     cp /var/www/html/ibl5/.env.test.example /var/www/html/ibl5/.env.test'

# --- Create test user (idempotent, same as e2e-wt.sh) ---
echo "Ensuring test user exists..."
ENV_TEST="$REPO_ROOT/ibl5/.env.test"
if [[ ! -f "$ENV_TEST" ]]; then
    echo "Error: $ENV_TEST not found." >&2
    exit 1
fi

IBL_TEST_USER=$(grep '^IBL_TEST_USER=' "$ENV_TEST" | cut -d= -f2-)
IBL_TEST_PASS=$(grep '^IBL_TEST_PASS=' "$ENV_TEST" | cut -d= -f2-)

if [[ -z "$IBL_TEST_USER" ]] || [[ -z "$IBL_TEST_PASS" ]]; then
    echo "Error: IBL_TEST_USER or IBL_TEST_PASS not set in $ENV_TEST" >&2
    exit 1
fi

docker exec \
    -e "E2E_USER=$IBL_TEST_USER" \
    -e "E2E_PASS=$IBL_TEST_PASS" \
    -e "E2E_DB_HOST=db" \
    "$PHP_CONTAINER" php -r '
$db = new mysqli(getenv("E2E_DB_HOST"), "root", "root", "iblhoops_ibl5");
if ($db->connect_error) { fwrite(STDERR, "DB connection failed: " . $db->connect_error . "\n"); exit(1); }

$user = getenv("E2E_USER");
$hash = password_hash(getenv("E2E_PASS"), PASSWORD_BCRYPT);
$email = "e2e-test@example.com";
$time = time();

$stmt = $db->prepare("INSERT INTO auth_users (email, password, username, status, verified, resettable, roles_mask, registered, force_logout) VALUES (?, ?, ?, 0, 1, 1, 1, ?, 0) ON DUPLICATE KEY UPDATE password = VALUES(password), username = VALUES(username)");
$stmt->bind_param("sssi", $email, $hash, $user, $time);
$stmt->execute();

$check = $db->prepare("SELECT user_id FROM nuke_users WHERE username = ?");
$check->bind_param("s", $user);
$check->execute();
$result = $check->get_result();
if ($result->num_rows === 0) {
    $stmt2 = $db->prepare("INSERT INTO nuke_users (username, user_email, user_ibl_team, user_password, name, user_avatar, bio, ublock, theme, user_regdate) VALUES (?, ?, \"Metros\", ?, \"E2E Test User\", \"\", \"\", \"\", \"\", NOW())");
    $stmt2->bind_param("sss", $user, $email, $hash);
    $stmt2->execute();
}

$stmt3 = $db->prepare("UPDATE ibl_team_info SET gm_username = ? WHERE team_name = \"Metros\"");
$stmt3->bind_param("s", $user);
$stmt3->execute();

$db->close();
echo "Test user ready: $user\n";
'

# --- Start shard containers ---
if [[ "$SHARD_COUNT" -gt 1 ]]; then
    # Build list of shard services to start (only up to SHARD_COUNT)
    SHARD_SERVICES=()
    for i in $(seq 2 "$SHARD_COUNT"); do
        SHARD_SERVICES+=("shard-$i")
    done

    echo ""
    echo "Starting $((SHARD_COUNT - 1)) shard container(s)..."
    SLUG="$SLUG" REPO_ROOT="$REPO_ROOT" WORKTREE_PATH="$WORKTREE_PATH" \
        docker compose -f "$COMPOSE_FILE" -p "$SHARD_PROJECT" \
        up -d --build "${SHARD_SERVICES[@]}"

    # Fix .env.test in each shard container and wait for readiness
    for i in $(seq 2 "$SHARD_COUNT"); do
        SHARD_CONTAINER="ibl5-php-shard-$i-$SLUG"
        echo "Waiting for $SHARD_CONTAINER..."
        READY=false
        for attempt in $(seq 1 30); do
            if docker exec "$SHARD_CONTAINER" true 2>/dev/null; then
                READY=true
                break
            fi
            sleep 2
        done
        if [[ "$READY" != true ]]; then
            echo "Error: $SHARD_CONTAINER did not become ready after 60s." >&2
            exit 1
        fi
        docker exec "$SHARD_CONTAINER" sh -c \
            'rm -f /var/www/html/ibl5/.env.test && \
             cp /var/www/html/ibl5/.env.test.example /var/www/html/ibl5/.env.test'
        echo "$SHARD_CONTAINER ready."
    done
fi

# --- Create per-shard blob report directories ---
BLOB_DIR="$REPO_ROOT/ibl5/shard-blob-reports"
rm -rf "$BLOB_DIR"
mkdir -p "$BLOB_DIR"

# --- Run N parallel Playwright shards ---
echo ""
echo "Running $SHARD_COUNT parallel shards (1 worker each)..."
echo ""

PIDS=()

# Shard 1 → primary container
(
    cd "$REPO_ROOT/ibl5"
    PLAYWRIGHT_BLOB_OUTPUT_DIR="$BLOB_DIR/shard-1" \
    BASE_URL="http://$SLUG.localhost/ibl5/" \
    IBL_TEST_USER="$IBL_TEST_USER" \
    IBL_TEST_PASS="$IBL_TEST_PASS" \
        bunx playwright test \
            --shard=1/"$SHARD_COUNT" \
            --workers=1 \
            --reporter=blob \
            ${PW_EXTRA_ARGS[@]+"${PW_EXTRA_ARGS[@]}"} \
        2>&1 | sed "s/^/[shard-1] /"
) &
PIDS+=($!)

# Shards 2..N → shard containers
for i in $(seq 2 "$SHARD_COUNT"); do
    (
        cd "$REPO_ROOT/ibl5"
        PLAYWRIGHT_BLOB_OUTPUT_DIR="$BLOB_DIR/shard-$i" \
        BASE_URL="http://shard-$i-$SLUG.localhost/ibl5/" \
        IBL_TEST_USER="$IBL_TEST_USER" \
        IBL_TEST_PASS="$IBL_TEST_PASS" \
            bunx playwright test \
                --shard="$i/$SHARD_COUNT" \
                --workers=1 \
                --reporter=blob \
                ${PW_EXTRA_ARGS[@]+"${PW_EXTRA_ARGS[@]}"} \
            2>&1 | sed "s/^/[shard-$i] /"
    ) &
    PIDS+=($!)
done

# Wait for all shards and track failures
OVERALL_EXIT=0
for pid in "${PIDS[@]}"; do
    if ! wait "$pid"; then
        OVERALL_EXIT=1
    fi
done

# --- Merge blob reports ---
echo ""
echo "Merging shard reports..."
cd "$REPO_ROOT/ibl5"
bunx playwright merge-reports --reporter=list "$BLOB_DIR" 2>/dev/null || true

# Clean up blob directory
rm -rf "$BLOB_DIR"

if [[ "$OVERALL_EXIT" -eq 0 ]]; then
    echo ""
    echo "All $SHARD_COUNT shards passed."
else
    echo ""
    echo "One or more shards failed." >&2
fi

exit "$OVERALL_EXIT"
