#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
# Phase 5.9 — refresh the machine-generated files-changed block in the PR body.
#
# Runs in the CALLER'S cwd, deliberately: every Phase 0 path (ALREADY-IN-TARGET,
# post-EnterWorktree, WRONG-WORKTREE→re-enter) leaves cwd inside the target worktree,
# so a slug argument would only add a second source of truth that can disagree with
# the one that matters. The work-tree guard below is what makes that fail closed.
git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
  || { echo "STOP: not inside a git work tree — Phase 0 did not leave cwd in the worktree"; exit 1; }

# Six properties below are load-bearing. Each carries its measured rationale at the site;
# `bin/test-pr-ready-now` case 17 pins all six against ten body fixtures.

BEG='<!-- files-changed:begin -->'
END='<!-- files-changed:end -->'
IN=/tmp/pr-ready-body-in-$1.md
OUT=/tmp/pr-ready-body-out-$1.md
BLK=/tmp/pr-ready-fc-block-$1.md
NEWB=/tmp/pr-ready-fc-new-$1.txt
NEWS=/tmp/pr-ready-fc-newsort-$1.txt
OLDB=/tmp/pr-ready-fc-old-$1.txt

git fetch origin --quiet || { echo "STOP: git fetch origin failed"; exit 1; }

# PROPERTY 2 — `sed 's/\r$//'` on the fetched body. A body carrying CRLF makes `-Fx` miss
# BOTH markers, which silently routes a marked-up body onto the append path and DUPLICATES
# the block — the one outcome /post-plan's recipe forbids. Accepted side effect: such a body
# is rewritten with LF endings. Benign — GitHub normalises line endings on render — and it
# only happens to a body that already had CRLF.
gh pr view "$1" --json body --jq .body | sed 's/\r$//' > "$IN" \
  || { echo "STOP: could not read PR $1 body"; exit 1; }

# PROPERTY 4 — `$NF`, not `$2`, in the awk. `git diff --name-status` emits
# `R100<TAB>old<TAB>new` for renames; `$1` is the status and `$NF` is the surviving path.
git diff --name-status origin/master...HEAD \
  | awk -F'\t' 'NF > 1 { printf "- `%s` `%s`\n", $1, $NF }' > "$NEWB" \
  || { echo "STOP: git diff --name-status failed"; exit 1; }

# PROPERTY 5 — the block is emitted in native `git diff --name-status` order, NEVER sorted.
# /post-plan's recipe (.claude/skills/post-plan/SKILL.md § Files-changed block) writes one
# bullet per file straight from `git diff --name-status`, i.e. git's own path order. Sorting
# the *bullet lines* sorts on the STATUS LETTER first (- `A` `z` ahead of - `M` `a`), so the
# same file set yields a byte-different block; `cmp -s` would then differ on an already-current
# body and fire a body write on EVERY run, printing `REPLACED (n files; +0 -0)` — a body
# rewrite per invocation, on an artifact a human is reading. $BLK is therefore built from the
# unsorted $NEWB; the sorted $NEWS/$OLDB pair exists ONLY to feed `comm` for the +add -del
# counts, and uses LC_ALL=C sort so both sides collate identically regardless of locale.
{ printf '%s\n' "$BEG"; cat "$NEWB"; printf '%s\n' "$END"; } > "$BLK"
LC_ALL=C sort "$NEWB" > "$NEWS"

# PROPERTY 1 — `grep -cFx` / `grep -nFx`, never `-F` alone. The markers get quoted inside PR
# bodies that discuss this skill — including this PR's own. Exact-line matching is what stops
# an inline mention from being read as a marker.
# The `|| true` guards are required by `set -euo pipefail`: `grep -c` exits 1 when the count
# is 0 (it still PRINTS 0, so nb/ne stay correct), and the `grep -n` pipelines inherit that
# status under pipefail. Without them the no-marker and orphan-marker paths abort here.
nb=$(grep -cFx "$BEG" "$IN" || true)
ne=$(grep -cFx "$END" "$IN" || true)
lb=$(grep -nFx "$BEG" "$IN" | head -1 | cut -d: -f1 || true); lb=${lb:-0}
le=$(grep -nFx "$END" "$IN" | head -1 | cut -d: -f1 || true); le=${le:-0}
paired=0
if [ "$nb" -eq 1 ] && [ "$ne" -eq 1 ] && [ "$le" -gt "$lb" ]; then paired=1; fi

# PROPERTY 3 — the AMBIGUOUS arm writes NOTHING. With duplicate or out-of-order markers there
# is no safe splice: the head/tail splice on a reversed pair would swallow the body tail.
# Fail closed and let the fidelity review's 6d.4 report it.
if [ "$nb" -gt 1 ] || [ "$ne" -gt 1 ] || { [ "$nb" -eq 1 ] && [ "$ne" -eq 1 ] && [ "$paired" -eq 0 ]; }; then
  echo "FILES-CHANGED: AMBIGUOUS (nb=$nb ne=$ne lb=$lb le=$le) - body left untouched"
  exit 0
fi

: > "$OLDB"
if [ "$paired" -eq 1 ]; then
  if [ "$le" -gt "$((lb + 1))" ]; then sed -n "$((lb + 1)),$((le - 1))p" "$IN" | LC_ALL=C sort > "$OLDB"; fi
  : > "$OUT"
  # PROPERTY 6 — `head` is guarded by [ "$lb" -gt 1 ]. When the body STARTS with the begin
  # marker, lb is 1 and `head -n 0` on BSD/macOS head exits 1 with `illegal line count -- 0`
  # (confirmed on this platform) instead of emitting nothing the way GNU head does. Unguarded,
  # that aborts the splice and leaves $OUT truncated. The `: > "$OUT"` seed plus the guard
  # makes the empty-prefix case write nothing and carry on.
  if [ "$lb" -gt 1 ]; then head -n "$((lb - 1))" "$IN" >> "$OUT"; fi
  cat "$BLK" >> "$OUT"
  tail -n "+$((le + 1))" "$IN" >> "$OUT"
  MODE=REPLACED
else
  cp "$IN" "$OUT"
  printf '\n' >> "$OUT"
  cat "$BLK" >> "$OUT"
  MODE=APPENDED
fi

add=$(comm -13 "$OLDB" "$NEWS" | wc -l | tr -d ' ')
del=$(comm -23 "$OLDB" "$NEWS" | wc -l | tr -d ' ')
tot=$(wc -l < "$NEWB" | tr -d ' ')

if cmp -s "$IN" "$OUT"; then echo "FILES-CHANGED: UNCHANGED ($tot files; +0 -0)"; exit 0; fi

gh pr edit "$1" --body-file "$OUT" || { echo "FILES-CHANGED: EDIT FAILED"; exit 1; }
echo "FILES-CHANGED: $MODE ($tot files; +$add -$del)"
