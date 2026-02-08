#!/bin/bash

# Draft Test Data Setup Script
# Sets up local test data so user "A-Jay" (Jazz, tid=13) can make a draft pick.
# Ensures three conditions are met:
#   1. ibl_draft: Jazz is on the clock (first unfilled row)
#   2. ibl_draft_picks: Jazz owns that pick
#   3. ibl_draft_class: At least one undrafted prospect exists

set -e

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

# ============================================
# Color Codes
# ============================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# Configuration
# ============================================
MYSQL_BIN="/Applications/MAMP/Library/bin/mysql80/bin/mysql"
MYSQL_SOCKET="/Applications/MAMP/tmp/mysql/mysql.sock"
DB_USER="${LOCAL_USER}"
DB_NAME="${LOCAL_DATABASE}"

TEAM_NAME="Jazz"

# ============================================
# Safety Checks
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Draft Test Data Setup${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

if [ ! -f "${MYSQL_BIN}" ]; then
    echo -e "${RED}ERROR: MAMP MySQL binary not found at ${MYSQL_BIN}${NC}"
    echo -e "${RED}This script is for local development only.${NC}"
    exit 1
fi

if [ ! -S "${MYSQL_SOCKET}" ]; then
    echo -e "${RED}ERROR: MAMP MySQL socket not found at ${MYSQL_SOCKET}${NC}"
    echo -e "${RED}Is MAMP running?${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} MAMP MySQL binary found"
echo -e "${GREEN}✓${NC} MAMP MySQL socket found"
echo ""

# ============================================
# Helper Function
# ============================================
run_sql() {
    MYSQL_PWD="${LOCAL_PASSWORD}" "${MYSQL_BIN}" \
        --socket="${MYSQL_SOCKET}" \
        --user="${DB_USER}" \
        "${DB_NAME}" \
        --batch --skip-column-names \
        -e "$1"
}

# ============================================
# Step 1: Read Season Ending Year
# ============================================
echo -e "${BLUE}Step 1: Reading current season ending year...${NC}"

ENDING_YEAR=$(run_sql "SELECT value FROM ibl_settings WHERE name = 'Current Season Ending Year' LIMIT 1")

if [ -z "${ENDING_YEAR}" ]; then
    echo -e "${RED}ERROR: Could not find 'Current Season Ending Year' in ibl_settings${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} Season ending year: ${ENDING_YEAR}"
echo ""

# ============================================
# Step 2: Verify Team Exists
# ============================================
echo -e "${BLUE}Step 2: Verifying '${TEAM_NAME}' exists in ibl_team_info...${NC}"

TEAM_COUNT=$(run_sql "SELECT COUNT(*) FROM ibl_team_info WHERE team_name = '${TEAM_NAME}'")

if [ "${TEAM_COUNT}" -eq 0 ]; then
    echo -e "${RED}ERROR: '${TEAM_NAME}' not found in ibl_team_info${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} '${TEAM_NAME}' exists"
echo ""

# ============================================
# Step 3: ibl_draft — INSERT if needed
# ============================================
echo -e "${BLUE}Step 3: Ensuring '${TEAM_NAME}' has an unfilled draft row...${NC}"

DRAFT_ROW_COUNT=$(run_sql "SELECT COUNT(*) FROM ibl_draft WHERE team = '${TEAM_NAME}' AND player = '' AND round = 0 AND pick = 0")

if [ "${DRAFT_ROW_COUNT}" -gt 0 ]; then
    echo -e "${YELLOW}⚠${NC} Draft row already exists for '${TEAM_NAME}' (round=0, pick=0) — skipping insert"
else
    run_sql "INSERT INTO ibl_draft (year, team, player, round, pick, uuid) VALUES (${ENDING_YEAR}, '${TEAM_NAME}', '', 0, 0, UUID())"
    echo -e "${GREEN}✓${NC} Inserted draft row for '${TEAM_NAME}' (round=0, pick=0)"
fi

echo ""

# ============================================
# Step 4: Verify On-the-Clock
# ============================================
echo -e "${BLUE}Step 4: Verifying '${TEAM_NAME}' is on the clock...${NC}"

ON_CLOCK_TEAM=$(run_sql "SELECT team FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1")

if [ "${ON_CLOCK_TEAM}" != "${TEAM_NAME}" ]; then
    echo -e "${RED}⚠ WARNING: '${TEAM_NAME}' is NOT on the clock!${NC}"
    echo -e "${RED}  Current pick belongs to: '${ON_CLOCK_TEAM}'${NC}"
    echo -e "${RED}  That team's unfilled row sorts before '${TEAM_NAME}' (round=0, pick=0).${NC}"
    echo -e "${RED}  You may need to fill or remove that row first.${NC}"
    exit 1
fi

echo -e "${GREEN}✓${NC} '${TEAM_NAME}' is on the clock"
echo ""

# ============================================
# Step 5: ibl_draft_picks — UPDATE existing row
# ============================================
echo -e "${BLUE}Step 5: Ensuring draft pick ownership in ibl_draft_picks...${NC}"

# Check for exact match: same year, round=0, teampick=Jazz
EXACT_MATCH=$(run_sql "SELECT COUNT(*) FROM ibl_draft_picks WHERE year = '${ENDING_YEAR}' AND round = '0' AND teampick = '${TEAM_NAME}'")

if [ "${EXACT_MATCH}" -gt 0 ]; then
    # Exact row exists — just make sure ownerofpick is correct
    run_sql "UPDATE ibl_draft_picks SET ownerofpick = '${TEAM_NAME}' WHERE year = '${ENDING_YEAR}' AND round = '0' AND teampick = '${TEAM_NAME}' LIMIT 1"
    echo -e "${GREEN}✓${NC} Updated existing draft_picks row (year=${ENDING_YEAR}, round=0, teampick='${TEAM_NAME}')"
else
    # Try to find a row for this team in this year (any round)
    TEAM_PICK_ID=$(run_sql "SELECT pickid FROM ibl_draft_picks WHERE year = '${ENDING_YEAR}' AND teampick = '${TEAM_NAME}' ORDER BY pickid DESC LIMIT 1")

    if [ -n "${TEAM_PICK_ID}" ]; then
        run_sql "UPDATE ibl_draft_picks SET round = '0', ownerofpick = '${TEAM_NAME}' WHERE pickid = ${TEAM_PICK_ID}"
        echo -e "${GREEN}✓${NC} Updated draft_picks row (pickid=${TEAM_PICK_ID}) — set round=0, ownerofpick='${TEAM_NAME}'"
    else
        # Fallback: grab last pick row for this year from any team
        FALLBACK_PICK_ID=$(run_sql "SELECT pickid FROM ibl_draft_picks WHERE year = '${ENDING_YEAR}' ORDER BY pickid DESC LIMIT 1")

        if [ -n "${FALLBACK_PICK_ID}" ]; then
            run_sql "UPDATE ibl_draft_picks SET round = '0', teampick = '${TEAM_NAME}', ownerofpick = '${TEAM_NAME}' WHERE pickid = ${FALLBACK_PICK_ID}"
            echo -e "${YELLOW}⚠${NC} Modified another team's draft_picks row (pickid=${FALLBACK_PICK_ID}) — set to '${TEAM_NAME}'"
        else
            echo -e "${RED}ERROR: No rows found in ibl_draft_picks for year ${ENDING_YEAR}${NC}"
            echo -e "${RED}Cannot set up draft pick ownership.${NC}"
            exit 1
        fi
    fi
fi

echo ""

# ============================================
# Step 6: ibl_draft_class — INSERT if needed
# ============================================
echo -e "${BLUE}Step 6: Ensuring undrafted prospects exist in ibl_draft_class...${NC}"

UNDRAFTED_COUNT=$(run_sql "SELECT COUNT(*) FROM ibl_draft_class WHERE drafted = 0 OR drafted IS NULL")

if [ "${UNDRAFTED_COUNT}" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} ${UNDRAFTED_COUNT} undrafted prospect(s) already exist — skipping"
else
    # Check if Test Prospect exists but was drafted previously
    TEST_PROSPECT_EXISTS=$(run_sql "SELECT COUNT(*) FROM ibl_draft_class WHERE name = 'Test Prospect'")

    if [ "${TEST_PROSPECT_EXISTS}" -gt 0 ]; then
        run_sql "UPDATE ibl_draft_class SET drafted = 0, team = '' WHERE name = 'Test Prospect'"
        echo -e "${GREEN}✓${NC} Reset 'Test Prospect' to undrafted"
    else
        run_sql "INSERT INTO ibl_draft_class (name, pos, age, team, fga, fgp, fta, ftp, tga, tgp, orb, drb, ast, stl, tvr, blk, oo, \`do\`, po, \`to\`, od, dd, pd, td, talent, skill, intangibles, sta, ranking, drafted) VALUES ('Test Prospect', 'PG', 20, '', 65, 50, 55, 75, 60, 40, 20, 45, 70, 55, 50, 15, 60, 55, 35, 65, 50, 50, 30, 60, 70, 65, 60, 75, 85.5, 0)"
        echo -e "${GREEN}✓${NC} Inserted 'Test Prospect' into draft class"
    fi
fi

echo ""

# ============================================
# Step 7: Final Verification
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Final Verification${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

PASS_COUNT=0

# Condition 1: Jazz on the clock
VERIFY_CLOCK=$(run_sql "SELECT team FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1")
if [ "${VERIFY_CLOCK}" = "${TEAM_NAME}" ]; then
    echo -e "${GREEN}✓${NC} ibl_draft: '${TEAM_NAME}' is on the clock"
    PASS_COUNT=$((PASS_COUNT + 1))
else
    echo -e "${RED}✗${NC} ibl_draft: '${TEAM_NAME}' is NOT on the clock (got: '${VERIFY_CLOCK}')"
fi

# Condition 2: Jazz owns the pick
VERIFY_ROUND=$(run_sql "SELECT round FROM ibl_draft WHERE player = '' ORDER BY round ASC, pick ASC LIMIT 1")
VERIFY_OWNER=$(run_sql "SELECT ownerofpick FROM ibl_draft_picks WHERE year = '${ENDING_YEAR}' AND round = '${VERIFY_ROUND}' AND teampick = '${TEAM_NAME}' LIMIT 1")
if [ "${VERIFY_OWNER}" = "${TEAM_NAME}" ]; then
    echo -e "${GREEN}✓${NC} ibl_draft_picks: '${TEAM_NAME}' owns the pick (round=${VERIFY_ROUND})"
    PASS_COUNT=$((PASS_COUNT + 1))
else
    echo -e "${RED}✗${NC} ibl_draft_picks: pick owner is '${VERIFY_OWNER}' (expected '${TEAM_NAME}')"
fi

# Condition 3: Undrafted prospects exist
VERIFY_UNDRAFTED=$(run_sql "SELECT COUNT(*) FROM ibl_draft_class WHERE drafted = 0 OR drafted IS NULL")
if [ "${VERIFY_UNDRAFTED}" -gt 0 ]; then
    echo -e "${GREEN}✓${NC} ibl_draft_class: ${VERIFY_UNDRAFTED} undrafted prospect(s)"
    PASS_COUNT=$((PASS_COUNT + 1))
else
    echo -e "${RED}✗${NC} ibl_draft_class: no undrafted prospects found"
fi

echo ""

if [ "${PASS_COUNT}" -eq 3 ]; then
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  All 3 conditions passed!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo -e "Open the Draft module as A-Jay:"
    echo -e "  ${BLUE}http://localhost:8888/ibl5/modules.php?name=Draft${NC}"
else
    echo -e "${RED}========================================${NC}"
    echo -e "${RED}  ${PASS_COUNT}/3 conditions passed${NC}"
    echo -e "${RED}========================================${NC}"
fi
