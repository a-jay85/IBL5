#!/usr/bin/env bash
# Verify that schema.sql + migrations produces no schema drift.
# Loads schema.sql into DB A, then schema.sql + all migrations into DB B,
# dumps both, and diffs. Any differences = migrations not yet on production.
#
# Uses MAMP local defaults. Override with environment variables:
#   MYSQL_HOST, MYSQL_USER, MYSQL_PASS
set -euo pipefail

DB_HOST="${MYSQL_HOST:-127.0.0.1}"
DB_USER="${MYSQL_USER:-root}"
DB_PASS="${MYSQL_PASS:-root}"

MYSQL="mysql -h $DB_HOST -u $DB_USER -p$DB_PASS"
MYSQLDUMP="mysqldump -h $DB_HOST -u $DB_USER -p$DB_PASS --no-data --skip-comments"

cleanup() {
    $MYSQL -e "DROP DATABASE IF EXISTS ibl5_verify_base; DROP DATABASE IF EXISTS ibl5_verify_migrated;" 2>/dev/null || true
    rm -f /tmp/schema_base.sql /tmp/schema_migrated.sql /tmp/schema_diff.txt 2>/dev/null || true
}
trap cleanup EXIT

$MYSQL -e "DROP DATABASE IF EXISTS ibl5_verify_base; CREATE DATABASE ibl5_verify_base;"
$MYSQL -e "DROP DATABASE IF EXISTS ibl5_verify_migrated; CREATE DATABASE ibl5_verify_migrated;"

# DB A: schema.sql only (production baseline)
sed 's/DEFINER=`[^`]*`@`[^`]*`//g' ibl5/schema.sql | $MYSQL ibl5_verify_base

# DB B: schema.sql + all migrations
sed 's/DEFINER=`[^`]*`@`[^`]*`//g' ibl5/schema.sql | $MYSQL ibl5_verify_migrated
MYSQL_HOST="$DB_HOST" MYSQL_USER="$DB_USER" MYSQL_PASS="$DB_PASS" \
  ibl5/bin/run-migrations-ci.sh -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" ibl5_verify_migrated

# Compare
$MYSQLDUMP ibl5_verify_base > /tmp/schema_base.sql
$MYSQLDUMP ibl5_verify_migrated > /tmp/schema_migrated.sql

if diff -u /tmp/schema_base.sql /tmp/schema_migrated.sql > /tmp/schema_diff.txt; then
    echo "PASS: schema.sql and schema.sql+migrations produce identical schemas."
    echo "All migrations are already reflected in production."
else
    echo "DRIFT DETECTED: The following differences exist between schema.sql and schema.sql+migrations:"
    echo "These represent migrations not yet applied to production."
    cat /tmp/schema_diff.txt
    echo ""
    echo "Review the diff above. Expected diffs are from in-flight PR migrations."
    echo "Unexpected diffs mean a migration was never applied to production."
fi
