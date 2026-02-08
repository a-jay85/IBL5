#!/bin/bash

# Rookie Option Test Data Setup Script
# Sets up local test data so user "A-Jay" (Jazz, tid=13) can exercise a rookie option.
# Ensures three conditions are met:
#   1. Season phase is "Free Agency", "Preseason", or "HEAT"
#   2. At least one eligible Jazz player exists for rookie option
#   3. Modified player (if any) is confirmed in the database

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
TEAM_ID=13
PHASE_CHANGED="no"
MODIFIED_PID=""

# ============================================
# Safety Checks
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Rookie Option Test Data Setup${NC}"
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
# Step 1: Read Season Phase
# ============================================
echo -e "${BLUE}Step 1: Reading current season phase...${NC}"

CURRENT_PHASE=$(run_sql "SELECT value FROM ibl_settings WHERE name = 'Current Season Phase' LIMIT 1")

if [ -z "${CURRENT_PHASE}" ]; then
    echo -e "${RED}ERROR: Could not find 'Current Season Phase' in ibl_settings${NC}"
    exit 1
fi

echo -e "  Current phase: ${CURRENT_PHASE}"

if [ "${CURRENT_PHASE}" = "Free Agency" ] || [ "${CURRENT_PHASE}" = "Preseason" ] || [ "${CURRENT_PHASE}" = "HEAT" ]; then
    echo -e "${GREEN}✓${NC} Phase '${CURRENT_PHASE}' is valid for rookie options"
else
    echo -e "${YELLOW}⚠${NC} Phase '${CURRENT_PHASE}' is not valid for rookie options — changing to 'Free Agency'"
    run_sql "UPDATE ibl_settings SET value = 'Free Agency' WHERE name = 'Current Season Phase'"
    CURRENT_PHASE="Free Agency"
    PHASE_CHANGED="yes"
    echo -e "${GREEN}✓${NC} Phase changed to 'Free Agency'"
fi

echo ""

# ============================================
# Step 2: Check for Already-Eligible Jazz Players
# ============================================
echo -e "${BLUE}Step 2: Checking for already-eligible ${TEAM_NAME} players...${NC}"

# Build eligibility query based on current phase
if [ "${CURRENT_PHASE}" = "Free Agency" ]; then
    ELIGIBILITY_SQL="
        SELECT pid, name, pos, draftround, exp, cy1, cy2, cy3, cy4
        FROM ibl_plr
        WHERE tid = ${TEAM_ID}
          AND retired = '0'
          AND (
            (draftround = 1 AND exp = 2 AND cy4 = 0 AND cy3 > 0)
            OR
            (draftround = 2 AND exp = 1 AND cy3 = 0 AND cy2 > 0)
          )
        LIMIT 10
    "
elif [ "${CURRENT_PHASE}" = "Preseason" ] || [ "${CURRENT_PHASE}" = "HEAT" ]; then
    ELIGIBILITY_SQL="
        SELECT pid, name, pos, draftround, exp, cy1, cy2, cy3, cy4
        FROM ibl_plr
        WHERE tid = ${TEAM_ID}
          AND retired = '0'
          AND (
            (draftround = 1 AND exp = 3 AND cy4 = 0 AND cy3 > 0)
            OR
            (draftround = 2 AND exp = 2 AND cy3 = 0 AND cy2 > 0)
          )
        LIMIT 10
    "
fi

ELIGIBLE_PLAYERS=$(run_sql "${ELIGIBILITY_SQL}")

if [ -n "${ELIGIBLE_PLAYERS}" ]; then
    echo -e "${GREEN}✓${NC} Found eligible ${TEAM_NAME} player(s):"
    echo ""
    echo -e "  ${BLUE}PID\tName\t\t\tPos\tRound\tExp\tCY1\tCY2\tCY3\tCY4${NC}"
    echo "  ---------------------------------------------------------------"
    while IFS=$'\t' read -r pid name pos round exp cy1 cy2 cy3 cy4; do
        printf "  %s\t%-20s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n" "$pid" "$name" "$pos" "$round" "$exp" "$cy1" "$cy2" "$cy3" "$cy4"
    done <<< "${ELIGIBLE_PLAYERS}"
    echo ""

    # Get the first eligible player's PID for the URL
    FIRST_PID=$(echo "${ELIGIBLE_PLAYERS}" | head -1 | cut -f1)

    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  No modifications needed!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    if [ "${PHASE_CHANGED}" = "yes" ]; then
        echo -e "${YELLOW}NOTE: Season phase was changed to 'Free Agency'.${NC}"
        echo ""
    fi
    echo -e "Open the Rookie Option page as A-Jay:"
    echo -e "  ${BLUE}http://localhost:8888/ibl5/modules.php?name=Player&pa=rookieoption&pid=${FIRST_PID}${NC}"
    exit 0
fi

echo -e "  No eligible players found — will modify one"
echo ""

# ============================================
# Step 3: Find Shortest-Contract Jazz Player
# ============================================
echo -e "${BLUE}Step 3: Finding shortest-contract ${TEAM_NAME} player to modify...${NC}"

CANDIDATE=$(run_sql "
    SELECT pid, name, pos, draftround, exp, cy1, cy2, cy3, cy4, cyt
    FROM ibl_plr
    WHERE tid = ${TEAM_ID}
      AND retired = '0'
      AND cyt > 0
    ORDER BY cyt ASC, pid ASC
    LIMIT 1
")

if [ -z "${CANDIDATE}" ]; then
    echo -e "${RED}ERROR: No active ${TEAM_NAME} players with cyt > 0 found${NC}"
    exit 1
fi

IFS=$'\t' read -r PID NAME POS OLD_ROUND OLD_EXP OLD_CY1 OLD_CY2 OLD_CY3 OLD_CY4 OLD_CYT <<< "${CANDIDATE}"

echo -e "${GREEN}✓${NC} Found candidate: ${NAME} (PID=${PID}, pos=${POS})"
echo -e "  Current: draftround=${OLD_ROUND}, exp=${OLD_EXP}, cy1=${OLD_CY1}, cy2=${OLD_CY2}, cy3=${OLD_CY3}, cy4=${OLD_CY4}, cyt=${OLD_CYT}"
echo ""

# ============================================
# Step 4: Modify Player for Eligibility
# ============================================
echo -e "${BLUE}Step 4: Modifying player for rookie option eligibility...${NC}"

# Target: Round 1, Free Agency rules → exp=2, cy4=0, cy3>0, draftround=1
# Only update fields that differ from target

UPDATES=""
CHANGES=""

# draftround → 1
if [ "${OLD_ROUND}" != "1" ]; then
    UPDATES="${UPDATES} draftround = 1,"
    CHANGES="${CHANGES}\n  draftround: ${OLD_ROUND} → 1"
fi

# exp → 2
if [ "${OLD_EXP}" != "2" ]; then
    UPDATES="${UPDATES} exp = 2,"
    CHANGES="${CHANGES}\n  exp: ${OLD_EXP} → 2"
fi

# cy4 → 0
if [ "${OLD_CY4}" != "0" ]; then
    UPDATES="${UPDATES} cy4 = 0,"
    CHANGES="${CHANGES}\n  cy4: ${OLD_CY4} → 0"
fi

# cy3 → must be > 0
if [ "${OLD_CY3}" = "0" ]; then
    # Use cy1 value if available, otherwise default to 500
    if [ "${OLD_CY1}" != "0" ] && [ -n "${OLD_CY1}" ]; then
        NEW_CY3="${OLD_CY1}"
    else
        NEW_CY3="500"
    fi
    UPDATES="${UPDATES} cy3 = ${NEW_CY3},"
    CHANGES="${CHANGES}\n  cy3: 0 → ${NEW_CY3}"
fi

if [ -z "${UPDATES}" ]; then
    echo -e "${GREEN}✓${NC} Player already matches target values — no changes needed"
else
    # Remove trailing comma
    UPDATES=$(echo "${UPDATES}" | sed 's/,$//')
    run_sql "UPDATE ibl_plr SET ${UPDATES} WHERE pid = ${PID}"
    echo -e "${GREEN}✓${NC} Updated player ${NAME} (PID=${PID}):"
    echo -e "${CHANGES}"
fi

MODIFIED_PID="${PID}"
echo ""

# ============================================
# Step 5: Final Verification
# ============================================
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Final Verification${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

PASS_COUNT=0

# Check 1: Season phase is valid
VERIFY_PHASE=$(run_sql "SELECT value FROM ibl_settings WHERE name = 'Current Season Phase' LIMIT 1")
if [ "${VERIFY_PHASE}" = "Free Agency" ] || [ "${VERIFY_PHASE}" = "Preseason" ] || [ "${VERIFY_PHASE}" = "HEAT" ]; then
    echo -e "${GREEN}✓${NC} Season phase: '${VERIFY_PHASE}' (valid for rookie options)"
    PASS_COUNT=$((PASS_COUNT + 1))
else
    echo -e "${RED}✗${NC} Season phase: '${VERIFY_PHASE}' (not valid for rookie options)"
fi

# Check 2: At least one eligible Jazz player exists
VERIFY_ELIGIBLE=$(run_sql "${ELIGIBILITY_SQL}" | head -1)
if [ -n "${VERIFY_ELIGIBLE}" ]; then
    VERIFY_PID=$(echo "${VERIFY_ELIGIBLE}" | cut -f1)
    VERIFY_NAME=$(echo "${VERIFY_ELIGIBLE}" | cut -f2)
    echo -e "${GREEN}✓${NC} Eligible player found: ${VERIFY_NAME} (PID=${VERIFY_PID})"
    PASS_COUNT=$((PASS_COUNT + 1))
else
    echo -e "${RED}✗${NC} No eligible ${TEAM_NAME} players found"
fi

# Check 3: Modified player confirmed in database
if [ -n "${MODIFIED_PID}" ]; then
    VERIFY_PLAYER=$(run_sql "SELECT draftround, exp, cy3, cy4 FROM ibl_plr WHERE pid = ${MODIFIED_PID}")
    if [ -n "${VERIFY_PLAYER}" ]; then
        IFS=$'\t' read -r V_ROUND V_EXP V_CY3 V_CY4 <<< "${VERIFY_PLAYER}"
        if [ "${V_ROUND}" = "1" ] && [ "${V_EXP}" = "2" ] && [ "${V_CY3}" != "0" ] && [ "${V_CY4}" = "0" ]; then
            echo -e "${GREEN}✓${NC} Modified player PID=${MODIFIED_PID} confirmed: draftround=${V_ROUND}, exp=${V_EXP}, cy3=${V_CY3}, cy4=${V_CY4}"
            PASS_COUNT=$((PASS_COUNT + 1))
        else
            echo -e "${RED}✗${NC} Modified player PID=${MODIFIED_PID} has unexpected values: draftround=${V_ROUND}, exp=${V_EXP}, cy3=${V_CY3}, cy4=${V_CY4}"
        fi
    else
        echo -e "${RED}✗${NC} Could not find modified player PID=${MODIFIED_PID} in database"
    fi
else
    # No modification was made (eligible player already existed) — skip this check
    echo -e "${GREEN}✓${NC} No modification needed (eligible player already existed)"
    PASS_COUNT=$((PASS_COUNT + 1))
fi

echo ""

if [ "${PASS_COUNT}" -eq 3 ]; then
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  All 3 checks passed!${NC}"
    echo -e "${GREEN}========================================${NC}"
else
    echo -e "${RED}========================================${NC}"
    echo -e "${RED}  ${PASS_COUNT}/3 checks passed${NC}"
    echo -e "${RED}========================================${NC}"
fi

echo ""

if [ "${PHASE_CHANGED}" = "yes" ]; then
    echo -e "${YELLOW}NOTE: Season phase was changed to 'Free Agency' (was '${CURRENT_PHASE}' before).${NC}"
    echo ""
fi

# Determine PID for URL
URL_PID="${MODIFIED_PID}"
if [ -z "${URL_PID}" ]; then
    URL_PID=$(run_sql "${ELIGIBILITY_SQL}" | head -1 | cut -f1)
fi

if [ -n "${URL_PID}" ]; then
    echo -e "Open the Rookie Option page as A-Jay:"
    echo -e "  ${BLUE}http://localhost:8888/ibl5/modules.php?name=Player&pa=rookieoption&pid=${URL_PID}${NC}"
fi
