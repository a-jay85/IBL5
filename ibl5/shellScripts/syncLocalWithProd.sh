#!/bin/bash

# Local Database Update Script
# Exports MySQL database from remote production server and imports to localhost

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

# Dump file location
DUMP_FILE="./database_dump_$(date +%Y%m%d_%H%M%S).sql"

MYSQLDUMP_CMD="mariadb-dump"
MYSQL_CMD="mariadb"

# ============================================
# Script execution - No need to modify below
# ============================================

echo "Starting database export and import process..."
echo "================================================"

# Step 1: Export from remote database with inline DEFINER removal
echo ""
echo "Step 1: Exporting from remote database..."
echo "Remote: ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PORT}/${REMOTE_DATABASE}"

MYSQL_PWD="${REMOTE_PASSWORD}" "${MYSQLDUMP_CMD}" \
  --host="${REMOTE_HOST}" \
  --port="${REMOTE_PORT}" \
  --user="${REMOTE_USER}" \
  --add-drop-table \
  --skip-lock-tables \
  --single-transaction \
  --compress \
  "${REMOTE_DATABASE}" \
  | sed 's/\/\*!50017 DEFINER=[^*]*\*\///g; s/ DEFINER=[^ ]*/ /g; s/  */ /g' \
  > "${DUMP_FILE}"

# Check if export was successful (check PIPESTATUS for the dump command)
if [ ${PIPESTATUS[0]} -ne 0 ]; then
  echo "ERROR: Failed to export remote database!"
  exit 1
fi

echo "Export completed successfully: ${DUMP_FILE}"
echo "  File size: $(du -h "${DUMP_FILE}" | cut -f1)"

# Step 2: Import to local database
echo ""
echo "Step 2: Importing to local database..."
echo "Local: ${LOCAL_USER}@${LOCAL_HOST}:${LOCAL_PORT}/${LOCAL_DATABASE}"

MYSQL_PWD="${LOCAL_PASSWORD}" "${MYSQL_CMD}" \
  --host="${LOCAL_HOST}" \
  --port="${LOCAL_PORT}" \
  --user="${LOCAL_USER}" \
  --max-allowed-packet=256M \
  --init-command="SET FOREIGN_KEY_CHECKS=0, UNIQUE_CHECKS=0, AUTOCOMMIT=0;" \
  "${LOCAL_DATABASE}" < "${DUMP_FILE}"

# Check if import was successful
if [ $? -ne 0 ]; then
  echo "ERROR: Failed to import to local database!"
  echo "Dump file preserved at: ${DUMP_FILE}"
  exit 1
fi

echo "Import completed successfully!"

# Remove dump file after successful import
rm "${DUMP_FILE}"
echo "Dump file deleted"

echo ""
echo "================================================"
echo "Database import process completed successfully!"
echo "================================================"
