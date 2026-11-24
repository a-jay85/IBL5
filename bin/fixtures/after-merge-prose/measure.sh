#!/usr/bin/env bash
# Re-measures the owner-after-merge prose detector in bin/lib/post-merge-rows.sh.
# Usage (repo root): bash bin/fixtures/after-merge-prose/measure.sh prs|plans
[ "${BASH_VERSINFO[0]:-0}" -ge 4 ] || { printf '%s: bash 4+ required (this is bash %s). Run: brew install bash\n' "${0##*/}" "${BASH_VERSION:-?}" >&2; exit 2; }
set -uo pipefail
root="$(cd "$(dirname "$0")/../../.." && pwd)"
source "$root/bin/lib/post-merge-rows.sh"
d="$(mktemp -d)"; trap 'rm -rf "$d"' EXIT
h=0; t=0
case "${1-}" in
  prs)
    gh pr list --state merged --limit 250 --json number,body > "$d/prs.json" || exit 2
    for n in $(jq -r '.[].number' "$d/prs.json"); do
        jq -r --argjson n "$n" '.[] | select(.number == $n) | .body // ""' "$d/prs.json" > "$d/$n.txt"
    done
    for f in "$d"/*.txt; do
        t=$((t + 1))
        if pmr_prose_hits "$f" > "$d/hit"; then h=$((h + 1)); echo "HIT #$(basename "$f" .txt): $(head -1 "$d/hit")"; fi
    done
    echo "prs: hits=$h of $t" ;;
  plans)
    for f in "$HOME"/claude-plans/*.md; do
        t=$((t + 1)); out="$(pmr_plan_check "$f" "$PMR_BIN_DIR/post-merge-steps" "")"
        case "$out" in *'owner-after-merge prose'*) h=$((h + 1)); echo "PLAN $(basename "$f")" ;; esac
    done
    echo "plans: would-fail=$h of $t" ;;
  *) echo "usage: measure.sh prs|plans" >&2; exit 2 ;;
esac
