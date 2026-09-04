#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT"
BODY_FILE="/tmp/pr-ready-verdict-$1.md"
[ -s "$BODY_FILE" ] || { echo "STOP: $BODY_FILE is missing or empty — compose the verdict with Write first"; exit 1; }
ids="$(gh api "repos/{owner}/{repo}/issues/$1/comments" --paginate \
  --jq '.[] | select((.body // "") | contains("<!-- pr-ready-verdict -->")) | .id')" \
  || { echo "STOP: could not list comments for PR #$1 — not posting; a duplicate verdict is worse than none"; exit 1; }
id="$(printf '%s\n' "$ids" | head -1)"
URL_FILE="/tmp/pr-ready-commenturl-$1.txt"
rm -f "$URL_FILE"
if [ -n "$id" ]; then
  gh api --method PATCH "repos/{owner}/{repo}/issues/comments/$id" \
    -F body=@"$BODY_FILE" --jq .html_url | tee "$URL_FILE"
else
  gh pr comment "$1" --body-file "$BODY_FILE" | tee "$URL_FILE"
fi
