#!/usr/bin/env bash
# Non-fatal by construction: uses set -uo pipefail WITHOUT -e; always exits 0.
set -uo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT" || exit 1

SLUG="$2"

# Step 1: abort if working tree is dirty to avoid destroying peer work
dirty=$(git -C "$WT" status --porcelain 2>&1) || true
if [ -n "$dirty" ]; then
  echo "BRINGUP: SKIP peer-dirty"
  printf '%s\n' "$dirty"
  echo "BRINGUP-COMPLETE"
  exit 0
fi

# Helper: true if both ibl5-php and ibl5-db containers report Running=true
stack_running() {
  local out count
  out=$(docker inspect -f '{{.State.Running}}' "ibl5-php-${SLUG}" "ibl5-db-${SLUG}" 2>/dev/null) || return 1
  count=$(printf '%s\n' "$out" | grep -c '^true$') || true
  [ "$count" -eq 2 ]
}

# Step 2: skip if already live — wt-up unconditionally tears down and purges the DB volume
if stack_running; then
  echo "BRINGUP: ALREADY-UP"
  echo "BRINGUP-COMPLETE"
  exit 0
fi

# Step 3: bring the stack up (--seed loads fixtures)
/Users/ajaynicolas/GitHub/IBL5/bin/wt-up "$SLUG" --seed || true

# Step 4: re-probe regardless of wt-up exit code — the exit code is not definitive
if stack_running; then
  # Step 5: poll for HTTP 200 on the /ibl5/ root — up to 12 × 5 s = 60 s
  i=0
  while [ "$i" -lt 12 ]; do
    code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "http://${SLUG}.localhost/ibl5/" 2>/dev/null) || code="000"
    if [ "$code" = "200" ]; then
      echo "BRINGUP: UP http://${SLUG}.localhost/ibl5/"
      echo "BRINGUP-COMPLETE"
      exit 0
    fi
    sleep 5
    i=$((i + 1))
  done
  echo "BRINGUP: NOT-READY"
else
  echo "BRINGUP: FAILED containers did not start"
fi

echo "BRINGUP-COMPLETE"
exit 0
