#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
# (?m) flag makes ^ match at the start of each line within the body string, not just the start of the whole string.
# #{1,6} matches any Markdown heading level (# through ######), so the detection is heading-level-agnostic.
gh api "repos/{owner}/{repo}/issues/$1/comments" --paginate \
  --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "comment\t\(.id)\t\(.user.login)\t\(.created_at)"' \
  || echo "PROBE-ERROR: issue-comments probe failed for PR #$1"
gh api "repos/{owner}/{repo}/pulls/$1/reviews" --paginate \
  --jq '.[] | select((.body // "") | test("(?m)^#{1,6} +Code review\\b")) | "review\t\(.id)\t\(.user.login)\t\(.submitted_at)"' \
  || echo "PROBE-ERROR: reviews probe failed for PR #$1"
echo "PROBE-COMPLETE"
