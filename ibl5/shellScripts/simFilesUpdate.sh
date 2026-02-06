#!/bin/bash

# simFilesUpdate.sh
# Updates sim files on production server and merges production into master
# This script:
# 1. SSHes to production server
# 2. Stages and commits sim files (IBL5.plr, IBL5.sco, schedule, standings)
# 3. Pushes changes to origin/production
# 4. Pulls production and master branches locally
# 5. Merges production into master and pushes to origin

set -e  # Exit on any error

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

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Production server details
PROD_HOST="${PROD_SSH_HOST}"
PROD_PATH="www"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Sim Files Update Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# ============================================
# Part 1: Update sim files on production
# ============================================

echo -e "${YELLOW}Part 1: Updating sim files on production server...${NC}"
echo ""

# SSH to production server and execute commands
# Using here-document for multiple commands
ssh "${PROD_HOST}" << 'EOF'
  set -e  # Exit on any error

  echo -e "\033[1;33m[Production] Navigating to $PROD_PATH directory...\033[0m"
  cd www || exit 1

  echo -e "\033[1;33m[Production] Staging sim files...\033[0m"

  # Stage the sim data files
  git add --force ibl5/IBL5.plr || { echo -e "\033[0;31m[Production] Error: Failed to stage IBL5.plr\033[0m"; exit 1; }
  echo -e "\033[0;32m[Production] ✓ Staged ibl5/IBL5.plr\033[0m"

  git add --force ibl5/IBL5.sco || { echo -e "\033[0;31m[Production] Error: Failed to stage IBL5.sco\033[0m"; exit 1; }
  echo -e "\033[0;32m[Production] ✓ Staged ibl5/IBL5.sco\033[0m"

  git add --force ibl5/ibl/IBL/Schedule.htm || { echo -e "\033[0;31m[Production] Error: Failed to stage Schedule.htm\033[0m"; exit 1; }
  echo -e "\033[0;32m[Production] ✓ Staged ibl5/ibl/IBL/Schedule.htm\033[0m"

  git add --force ibl5/ibl/IBL/Standings.htm || { echo -e "\033[0;31m[Production] Error: Failed to stage Standings.htm\033[0m"; exit 1; }
  echo -e "\033[0;32m[Production] ✓ Staged ibl5/ibl/IBL/Standings.htm\033[0m"

  # Check if there are changes to commit
  echo -e "\033[1;33m[Production] Checking for changes to commit...\033[0m"
  if ! git diff --cached --quiet; then
    echo -e "\033[1;33m[Production] Committing changes...\033[0m"
    git commit -m "Update sim files" || { echo -e "\033[0;31m[Production] Error: Commit failed\033[0m"; exit 1; }
    echo -e "\033[0;32m[Production] ✓ Changes committed\033[0m"

    echo -e "\033[1;33m[Production] Pushing to origin...\033[0m"
    git push origin production || { echo -e "\033[0;31m[Production] Error: Push failed\033[0m"; exit 1; }
    echo -e "\033[0;32m[Production] ✓ Changes pushed to origin/production\033[0m"
  else
    echo -e "\033[1;33m[Production] No changes to commit\033[0m"
  fi

  echo -e "\033[0;32m[Production] ✓ Sim files update completed\033[0m"
EOF

if [ $? -ne 0 ]; then
  echo -e "${RED}Error: Failed to update sim files on production server${NC}"
  exit 1
fi

echo -e "${GREEN}✓ Production server update completed successfully${NC}"
echo ""

# ============================================
# Part 2: Local git operations
# ============================================

echo -e "${YELLOW}Part 2: Updating local git repositories...${NC}"
echo ""

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}Error: Not a git repository${NC}"
    exit 1
fi

# Fetch latest from origin
echo -e "${YELLOW}Fetching latest from origin...${NC}"
git fetch origin || { echo -e "${RED}Error: Failed to fetch from origin${NC}"; exit 1; }
echo -e "${GREEN}✓ Fetch completed${NC}"
echo ""

# Check if production branch exists
if ! git rev-parse origin/production > /dev/null 2>&1; then
    echo -e "${RED}Error: production branch not found on origin${NC}"
    exit 1
fi

# Check if master branch exists
if ! git rev-parse origin/master > /dev/null 2>&1; then
    echo -e "${RED}Error: master branch not found on origin${NC}"
    exit 1
fi

# Switch to production branch and pull latest changes
echo -e "${YELLOW}Switching to production branch and pulling latest changes...${NC}"
git checkout production || { echo -e "${RED}Error: Failed to checkout production branch${NC}"; exit 1; }
git pull origin production || { echo -e "${RED}Error: Failed to pull production branch${NC}"; exit 1; }
echo -e "${GREEN}✓ Production branch updated${NC}"
echo ""

# Switch to master branch and pull latest changes
echo -e "${YELLOW}Switching to master branch and pulling latest changes...${NC}"
git checkout master || { echo -e "${RED}Error: Failed to checkout master branch${NC}"; exit 1; }
git pull origin master || { echo -e "${RED}Error: Failed to pull master branch${NC}"; exit 1; }
echo -e "${GREEN}✓ Master branch updated${NC}"
echo ""

# ============================================
# Part 3: Merge production into master
# ============================================

echo -e "${YELLOW}Merging production into master...${NC}"

if git merge production --no-edit; then
    echo -e "${GREEN}✓ Successfully merged production into master${NC}"
else
    echo -e "${RED}Error: Merge conflict or merge failed${NC}"
    echo -e "${YELLOW}Please resolve conflicts manually and commit before trying again${NC}"
    exit 1
fi

echo ""

# Push master branch to origin
echo -e "${YELLOW}Pushing master branch to origin...${NC}"
if git push origin master; then
    echo -e "${GREEN}✓ Successfully pushed master branch to origin${NC}"
else
    echo -e "${RED}Error: Failed to push master branch${NC}"
    exit 1
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✓ Sim files update completed successfully!${NC}"
echo -e "${BLUE}========================================${NC}"
