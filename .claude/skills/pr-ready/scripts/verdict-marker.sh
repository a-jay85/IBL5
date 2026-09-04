#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (verdict token) required — must be exactly READY, READY-WITH-NOTES or NOT-READY"; exit 1; }
case "$2" in
  READY|READY-WITH-NOTES|NOT-READY) ;;
  *) echo "STOP: arg2 must be exactly READY, READY-WITH-NOTES or NOT-READY — got '$2'"; exit 1 ;;
esac

URL_FILE="/tmp/pr-ready-commenturl-$1.txt"
url="$(grep -m1 '^https://' "$URL_FILE" 2>/dev/null || true)"
[ -n "$url" ] || url='-'

HOLDS_FILE="/tmp/pr-ready-holdsout-$1.txt"
if [ -f "$HOLDS_FILE" ] && [ -s "$HOLDS_FILE" ]; then
  holds="$(grep -v ': (clear)$' "$HOLDS_FILE" \
    | tr -d '\r|' \
    | tr '\n' ';' \
    | tr ' \t' '__' \
    | cut -c1-300)" || true
  [ -n "$holds" ] || holds='none'
else
  holds='unknown'
fi

line="PR-READY-VERDICT-MARKER-V1|pr=$1|verdict=$2|url=$url|holds=$holds"
printf '%s\n' "$line" > "/tmp/pr-ready-marker-$1.txt"
printf '%s\n' "$line"
