#!/bin/bash

# Merges master into production and pushes both branches to origin.
# All git output is suppressed; only errors or the final summary are printed.

set -e

fail() { echo "FAILED: $1"; exit 1; }

git rev-parse --git-dir > /dev/null 2>&1 || fail "Not a git repository"
git fetch origin -q
git rev-parse origin/master > /dev/null 2>&1 || fail "master branch not found on origin"
git rev-parse origin/production > /dev/null 2>&1 || fail "production branch not found on origin"

git checkout master -q
git pull origin master -q
git push origin master -q 2>/dev/null || fail "push master"

git checkout production -q
git pull origin production -q
git merge origin/master --no-edit -q || fail "merge master into production (conflicts?)"
git push origin production -q 2>/dev/null || fail "push production"

git checkout master -q

echo "Done. Pushed master and production."
