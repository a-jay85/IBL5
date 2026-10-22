#!/usr/bin/env bash
# Backtest the post-plan TREE DIVERGED change-level proof (lostwork.sh) against real
# aborted runs. For each key K it reconstructs the post-rebase state the real run saw
# (master as of the run's timestamp plus the saved post patch), then runs the proof with
# the saved pre-rebase patch. Reports only; the exit code is 0 whenever the loop completes.
#
# Usage: lostwork-backtest.sh [--keep] [--script <path>] <key>...
set -euo pipefail

usage() {
  echo "usage: lostwork-backtest.sh [--keep] [--script <path>] <key>..." >&2
  exit 2
}

HERE=$(cd "$(dirname "$0")" && pwd)
REPO=$(git -C "$HERE" rev-parse --show-toplevel)
SCRIPT="$(cd "$(dirname "$0")/../../.." && pwd)/.claude/review-shared/scripts/lostwork.sh"
KEEP=0

while [ $# -gt 0 ]; do
  case "$1" in
    --keep) KEEP=1; shift ;;
    --script) [ $# -ge 2 ] || usage; SCRIPT="$2"; shift 2 ;;
    -*) usage ;;
    *) break ;;
  esac
done
[ $# -gt 0 ] || usage
[ -f "$SCRIPT" ] || { echo "proof script not found: $SCRIPT" >&2; exit 2; }
SCRIPT="$(cd "$(dirname "$SCRIPT")" && pwd)/$(basename "$SCRIPT")"

SRC=$(git -C "$REPO" rev-parse --path-format=absolute --git-common-dir)
MAIN_CHECKOUT=$(dirname "$SRC")
OUT_DIRS="$REPO/tools/postplan-harness/out
$MAIN_CHECKOUT/tools/postplan-harness/out"

git -C "$REPO" fetch -q origin

BT=$(mktemp -d /tmp/lostwork-backtest.XXXXXX)
cleanup_bt() { [ "$KEEP" -eq 1 ] || rm -rf "$BT"; }
trap cleanup_bt EXIT

TABLE=""
PASS=0
BLOCK=0
UNREC=0
TOTAL=0

# Newest live-K-<ts>-<pid> dir whose audit.log has TREE DIVERGED; searches each out/ in order.
find_run_dir() {
  local key="$1" base d name rest found
  while IFS= read -r base; do
    [ -d "$base" ] || continue
    found=""
    for d in "$base"/live-"$key"-*/; do
      [ -d "$d" ] || continue
      d=${d%/}
      name=${d##*/}
      rest=${name#live-"$key"-}
      [[ $rest =~ ^[0-9]{8}-[0-9]{6}- ]] || continue
      grep -q 'TREE DIVERGED' "$d/audit.log" 2>/dev/null || continue
      found="$found$d
"
    done
    if [ -n "$found" ]; then
      printf '%s' "$found" | sort | tail -1
      return 0
    fi
  done <<EOF
$OUT_DIRS
EOF
  return 0
}

# Run the proof inside clone $1 with proof key $2; sets PROOF_RC and PROOF_OUT.
run_proof() {
  PROOF_RC=0
  PROOF_OUT=$(cd "$1" && bash "$SCRIPT" "$2" 2>&1) || PROOF_RC=$?
}

verdict_of() {
  if [ "$PROOF_RC" -eq 0 ] && printf '%s\n' "$PROOF_OUT" | grep -q 'TREE-EQUIVALENT'; then
    echo PASS
  else
    echo BLOCK
  fi
}

print_lost() {
  printf '%s\n' "$PROOF_OUT" | grep '^LOST:' | sed 's/^/    /' || true
}

cleanup_key() {
  local key="$1"
  rm -f "/tmp/pr-ready-diff-pre-bt-$key.patch" "/tmp/pr-ready-diff-post-bt-$key.patch" \
        "/tmp/pr-ready-diff-pre-btm-$key.patch" "/tmp/pr-ready-diff-post-btm-$key.patch" \
        /tmp/pr-ready-numstat-*-bt*-"$key".txt
  rm -rf /tmp/postplan-lostwork-bt*-"$key".*
}

add_row() { TABLE="$TABLE$1
"; }

unrec() {
  local key="$1" why="$2"
  echo "UNRECONSTRUCTIBLE $key: $why"
  add_row "$key | - | UNRECONSTRUCTIBLE | - | - | -"
  UNREC=$((UNREC + 1))
}

for K in "$@"; do
  TOTAL=$((TOTAL + 1))
  PRE="/tmp/pr-ready-diff-pre-$K.patch"
  POST="/tmp/pr-ready-diff-post-$K.patch"

  # 1. Inputs
  if [ ! -s "$PRE" ] || [ ! -s "$POST" ]; then
    unrec "$K" "missing pre/post patch"
    continue
  fi

  # 2. Run directory
  RUN_DIR=$(find_run_dir "$K")
  if [ -z "$RUN_DIR" ]; then
    unrec "$K" "no diverged run dir"
    continue
  fi
  NAME=${RUN_DIR##*/}
  REST=${NAME#live-"$K"-}
  TS=${REST:0:15}
  STAMP="${TS:0:4}-${TS:4:2}-${TS:6:2} ${TS:9:2}:${TS:11:2}:${TS:13:2}"

  # 3. Master at run time
  SHA=$(git -C "$REPO" rev-list -1 --before="$STAMP" origin/master)
  if [ -z "$SHA" ]; then
    unrec "$K" "no master commit before $STAMP"
    continue
  fi
  echo "$K: master@run=$SHA"

  # 4. Scratch clone with the post-rebase state
  CL="$BT/$K"
  git clone -q --shared --no-checkout "$SRC" "$CL"
  git -C "$CL" update-ref refs/remotes/origin/master "$SHA"
  git -C "$CL" checkout -q --detach "$SHA"
  if ! git -C "$CL" apply --index "$POST" 2>/dev/null; then
    unrec "$K" "post patch does not apply at $SHA"
    continue
  fi
  git -C "$CL" -c user.email=bt@local -c user.name=bt commit -q -m "backtest post state $K"

  # 5. Run the proof
  cp "$PRE" "/tmp/pr-ready-diff-pre-bt-$K.patch"
  run_proof "$CL" "bt-$K"
  VERDICT=$(verdict_of)
  LOSTN=$(printf '%s\n' "$PROOF_OUT" | grep -c '^LOST:' || true)
  echo "$K: $VERDICT"
  print_lost
  if [ "$VERDICT" = PASS ]; then PASS=$((PASS + 1)); else BLOCK=$((BLOCK + 1)); fi

  # 6. Merge cross-check
  PRINFO=$(gh pr list --repo a-jay85/IBL5 --state all --head "$K" --json number,state,mergeCommit \
    --jq '.[0] | if . == null then "" else "\(.number) \(.state) \(.mergeCommit.oid // "")" end' 2>/dev/null || true)
  PRCOL="none"
  MERGECOL="-"
  if [ -z "$PRINFO" ]; then
    echo "MERGE-CHECK $K: no PR"
  else
    PRNUM=${PRINFO%% *}
    PRREST=${PRINFO#* }
    PRSTATE=${PRREST%% *}
    MCOMMIT=${PRREST#* }
    [ "$MCOMMIT" = "$PRREST" ] && MCOMMIT=""
    PRCOL="#$PRNUM $PRSTATE"
    if [ "$PRSTATE" != MERGED ]; then
      echo "MERGE-CHECK $K: PR #$PRNUM $PRSTATE"
    elif [ -z "$MCOMMIT" ] \
      || ! git -C "$CL" update-ref refs/remotes/origin/master "$MCOMMIT^" 2>/dev/null \
      || ! git -C "$CL" checkout -q --detach "$MCOMMIT" 2>/dev/null; then
      echo "MERGE-CHECK $K: PR #$PRNUM merged, merge commit unavailable in clone"
      MERGECOL="unavailable"
    else
      cp "$PRE" "/tmp/pr-ready-diff-pre-btm-$K.patch"
      run_proof "$CL" "btm-$K"
      MERGECOL=$(verdict_of)
      echo "MERGE-CHECK $K: PR #$PRNUM merged, proof-at-merge=$MERGECOL"
      print_lost
    fi
  fi

  add_row "$K | $SHA | $VERDICT | $LOSTN | $PRCOL | $MERGECOL"

  # 7. Cleanup
  cleanup_key "$K"
done

echo
echo "KEY | master@run | verdict | lost-lines | PR | proof-at-merge"
printf '%s' "$TABLE"
echo "SUMMARY: pass=$PASS block=$BLOCK unreconstructible=$UNREC of $TOTAL"
exit 0
