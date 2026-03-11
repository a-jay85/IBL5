#!/usr/bin/env bash
# Apply all idempotent SQL migrations in order for CI.
# Usage: ./run-migrations-ci.sh <mysql-args>
#
# Applies 000_baseline_schema.sql first (creates all tables), then numbered
# migrations (001_*, 002_*, 033b_*, etc.) which alter them.
# Skips .php data migrations (need production data) and .md files.
# Strips DEFINER clauses from all migrations (only 000 has them from the prod dump).
set -euo pipefail
trap 'echo "ERROR: Failed applying migration: $(basename "${f:-unknown}")"; exit 1' ERR

MIGRATIONS_DIR="$(cd "$(dirname "$0")/../migrations" && pwd)"
numbered=()
for f in "$MIGRATIONS_DIR"/*.sql; do
    base=$(basename "$f")
    if [[ "$base" =~ ^[0-9]{1,3}[a-z]?_ ]]; then
        numbered+=("$f")
    fi
done
# Lexicographic sort: 033_ < 033b_ < 034_ (sort -V gets this wrong)
IFS=$'\n' numbered=($(printf '%s\n' "${numbered[@]}" | sort))

echo "Applying ${#numbered[@]} migrations..."
for f in "${numbered[@]}"; do
    echo "  -> $(basename "$f")"
    sed 's/DEFINER=`[^`]*`@`[^`]*`//g' "$f" | mysql "$@"
done
echo "Done."
