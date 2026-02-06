#!/bin/bash

# Production Database Schema Dump Script
# Exports only the schema structure (no data) from remote production database to local file

# ============================================
# Load credentials from .env
# ============================================
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"
if [ ! -f "${ENV_FILE}" ]; then
  echo "FAILED: ${ENV_FILE} not found. Copy .env.example to .env and fill in credentials."
  exit 1
fi
source "${ENV_FILE}"

# Output schema file location
SCHEMA_FILE="../schema.sql"

# Use MAMP's MySQL binaries if available (compatible with older auth plugins)
MAMP_MYSQL_BIN="/Applications/MAMP/Library/bin/mysql57/bin"
if [ -f "${MAMP_MYSQL_BIN}/mysqldump" ]; then
  MYSQLDUMP_CMD="${MAMP_MYSQL_BIN}/mysqldump"
  echo "Using MAMP MySQL 5.7 binaries"
else
  MYSQLDUMP_CMD="mysqldump"
  echo "Using system MySQL binaries"
fi

# ============================================
# Script execution - No need to modify below
# ============================================

echo "Starting production database schema export..."
echo "================================================"
echo ""
echo "Exporting schema from production database..."
echo "Remote: ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PORT}/${REMOTE_DATABASE}"
echo "Output: ${SCHEMA_FILE}"
echo ""

# Export schema only (no data) from remote database
# --no-data: export only the schema structure, not the actual data
# --routines: include stored procedures and functions if they exist
# --triggers: include triggers if they exist
MYSQL_PWD="${REMOTE_PASSWORD}" "${MYSQLDUMP_CMD}" \
  --host="${REMOTE_HOST}" \
  --port="${REMOTE_PORT}" \
  --user="${REMOTE_USER}" \
  --no-data \
  --routines \
  --triggers \
  "${REMOTE_DATABASE}" > "${SCHEMA_FILE}"

# Check if export was successful
if [ $? -ne 0 ]; then
  echo "ERROR: Failed to export production database schema!"
  exit 1
fi

echo "✓ Schema export completed successfully!"
echo "  Output file: ${SCHEMA_FILE}"
echo "  File size: $(du -h "${SCHEMA_FILE}" | cut -f1)"
echo ""

# Git commit the schema changes
echo "Committing schema.sql to local master branch..."
git add "${SCHEMA_FILE}"
git commit -m "chore: update schema.sql"

if [ $? -eq 0 ]; then
  echo "✓ Schema changes committed successfully!"
else
  echo "⚠ Warning: Git commit failed or no changes to commit"
fi

echo ""
echo "================================================"
echo "Schema dump process completed successfully!"
echo "================================================"
