#!/usr/bin/env bash
#
# Run visual regression tests inside a pinned Playwright Docker container.
# Same image locally and in CI = deterministic font rendering = no dimension drift.
#
# Usage:
#   ./bin/visual-regression.sh                    # Compare against baselines
#   ./bin/visual-regression.sh --update-snapshots # Generate/update baselines

set -euo pipefail

PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.58.2-jammy"

# Resolve ibl5/ directory (script lives in ibl5/bin/)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IBL5_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# ---------- Pre-flight checks ----------

if ! docker info >/dev/null 2>&1; then
  echo "ERROR: Docker is not running." >&2
  exit 1
fi

# Credentials come from .env.test (mounted into container, read by Playwright TS config)
# or from environment variables passed via -e flags.
ENV_FILE="$IBL5_DIR/.env.test"
if [[ ! -f "$ENV_FILE" ]] && [[ -z "${IBL_TEST_USER:-}" || -z "${IBL_TEST_PASS:-}" ]]; then
  echo "ERROR: .env.test not found and IBL_TEST_USER/IBL_TEST_PASS not set." >&2
  exit 1
fi

# HOST_URL is what we check from the host; CONTAINER_URL is what Playwright uses inside Docker.
# From inside the container, main.localhost resolves to the container's own loopback — useless.
# Instead, we target the PHP container directly by its Docker service name.
HOST_URL="${BASE_URL:-http://main.localhost/ibl5/}"
CONTAINER_URL="${CONTAINER_URL:-http://ibl5-php/ibl5/}"

# Check PHP container is responding (from host)
if ! curl -sf --max-time 5 "$HOST_URL" >/dev/null 2>&1; then
  echo "WARNING: $HOST_URL is not responding. Make sure Docker containers are running." >&2
  echo "  Try: docker compose up -d" >&2
fi

# ---------- Resolve symlinks for Docker mounts ----------

# In worktrees, node_modules/.env.test/config.php are symlinks to the main repo.
# Docker can't follow macOS symlinks — mount the real paths.
EXTRA_MOUNTS=()
for item in node_modules .env.test config.php; do
  if [[ -L "$IBL5_DIR/$item" ]]; then
    REAL_PATH="$(readlink -f "$IBL5_DIR/$item")"
    if [[ -e "$REAL_PATH" ]]; then
      EXTRA_MOUNTS+=(-v "$REAL_PATH:/ibl5/$item")
    fi
  fi
done

# ---------- Build env var flags ----------

# Pass credentials via -e only if set in the environment (CI scenario).
# Locally, .env.test is mounted and the Playwright TS config reads it directly.
ENV_FLAGS=(-e "BASE_URL=$CONTAINER_URL" -e "HOME=/tmp")
if [[ -n "${IBL_TEST_USER:-}" ]]; then
  ENV_FLAGS+=(-e "IBL_TEST_USER=$IBL_TEST_USER")
fi
if [[ -n "${IBL_TEST_PASS:-}" ]]; then
  ENV_FLAGS+=(-e "IBL_TEST_PASS=$IBL_TEST_PASS")
fi

# ---------- Run Playwright in Docker ----------

echo "Running visual regression tests in $PLAYWRIGHT_IMAGE"
echo "  BASE_URL (in container): $CONTAINER_URL"
echo ""

docker run --rm \
  --network ibl5-proxy \
  -v "$IBL5_DIR:/ibl5" \
  "${EXTRA_MOUNTS[@]}" \
  -w /ibl5 \
  "${ENV_FLAGS[@]}" \
  "$PLAYWRIGHT_IMAGE" \
  npx playwright test --config=playwright.visual.config.ts "$@"
