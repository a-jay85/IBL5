#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
# The guards are the point: this proof must fail closed. A script that silently exits 0
# on a degraded input (empty file, missing file, unparseable patch) gives a false
# TREE-EQUIVALENT and lets a diverged tree through. Every exit path that cannot reach the
# diff comparison emits the stop word instead.
# pipefail (from the shared header) is why the `||` guards below catch
# `git apply`'s status rather than `sort`'s.
PRE="/tmp/pr-ready-diff-pre-$1.patch"
POST="/tmp/pr-ready-diff-post-$1.patch"
NPRE="/tmp/pr-ready-numstat-pre-$1.txt"
NPOST="/tmp/pr-ready-numstat-post-$1.txt"
git diff origin/master...HEAD > "$POST" || { echo "TREE DIVERGED — could not capture the post-rebase diff"; exit 1; }
for f in "$PRE" "$POST"; do
  [ -s "$f" ] || { echo "TREE DIVERGED — $f is missing or empty; nothing was compared"; exit 1; }
done
git apply --numstat "$PRE"  | sort > "$NPRE"  || { echo "TREE DIVERGED — git apply --numstat failed on $PRE"; exit 1; }
git apply --numstat "$POST" | sort > "$NPOST" || { echo "TREE DIVERGED — git apply --numstat failed on $POST"; exit 1; }
[ -s "$NPRE" ] || { echo "TREE DIVERGED — numstat of $PRE is empty"; exit 1; }
# Change-level proof. The numstat calls above are parse guards only. Every line the
# branch changed before the rebase must still be in the post-rebase TREE at HEAD. The
# tree is read, never the post diff, so a branch hunk master also landed (gone from
# origin/master...HEAD, still in the tree) proves for free. Anything this cannot prove
# prints a LOST: line and blocks. Only lines holding a letter or digit are checked: a
# `}` or blank line appears all over any file, so it is no evidence either way.
TMPD=$(mktemp -d "/tmp/postplan-lostwork-$1.XXXXXX") || { echo "TREE DIVERGED — could not create a temp dir"; exit 1; }
trap 'rm -rf "$TMPD"' EXIT
REC="$TMPD/records"; HF="$TMPD/head"; HFP="$TMPD/head-patterns"; MF="$TMPD/master"
ADDS="$TMPD/adds"; DELS="$TMPD/dels"; MISS="$TMPD/missing"; KEEP="$TMPD/dels-checked"
# One pass over $PRE. Records: `F<old>\t<new>` opens an entry, `+<line>`/`-<line>` are its
# significant changed lines, `B` marks it binary. Exit 3 = git-quoted path, 4 = an entry
# whose paths cannot be read. An entry with no ---/+++ lines (binary, empty new file,
# mode-only, pure rename) takes its paths from the rename/copy headers or from a
# `diff --git a/P b/P` line whose two halves match; anything else is unparseable.
AWKRC=0
LC_ALL=C awk '
function fail(code) { dead = code; exit code }
function strip(p) { sub(/\t$/, "", p); return p }
function hdr(p, pfx) {
  if (p == "/dev/null") return p
  if (substr(p, 1, 1) == "\"") fail(3)
  if (substr(p, 1, 2) != pfx) fail(4)
  return strip(substr(p, 3))
}
function finish(   n, k) {
  if (!inent) return
  if (!emitted) {
    if (seenold) fail(4)
    if (rfrom != "" && rto != "") { oldp = rfrom; newp = rto }
    else {
      n = length(dg); k = (n - 5) / 2
      if (n < 7 || k != int(k) || substr(dg, 1, 2) != "a/" || substr(dg, k + 3, 3) != " b/" || substr(dg, 3, k) != substr(dg, k + 6)) fail(4)
      oldp = substr(dg, 3, k); newp = oldp
      if (isnew) oldp = "/dev/null"
      if (isdel) newp = "/dev/null"
    }
    print "F" oldp "\t" newp
  }
  if (bin) print "B"
}
/^diff --git / {
  finish()
  inent = 1; emitted = 0; latch = 0; bin = 0; isnew = 0; isdel = 0; seenold = 0
  oldp = ""; newp = ""; rfrom = ""; rto = ""
  dg = substr($0, 12)
  if (substr(dg, 1, 1) == "\"") fail(3)
  next
}
!inent { next }
latch {
  c = substr($0, 1, 1)
  if ((c == "+" || c == "-") && substr($0, 2) ~ /[[:alnum:]]/) print
  next
}
/^@@ / { if (!emitted) fail(4); latch = 1; next }
/^--- / { oldp = hdr(substr($0, 5), "a/"); seenold = 1; next }
/^\+\+\+ / {
  if (!seenold) fail(4)
  newp = hdr(substr($0, 5), "b/")
  print "F" oldp "\t" newp; emitted = 1; next
}
/^(rename|copy) from / { p = $0; sub(/^(rename|copy) from /, "", p); if (substr(p, 1, 1) == "\"") fail(3); rfrom = p; next }
/^(rename|copy) to / { p = $0; sub(/^(rename|copy) to /, "", p); if (substr(p, 1, 1) == "\"") fail(3); rto = p; next }
/^new file mode / { isnew = 1; next }
/^deleted file mode / { isdel = 1; next }
/^Binary files / || /^GIT binary patch/ { bin = 1; next }
END { if (dead) exit dead; finish() }
' "$PRE" > "$REC" || AWKRC=$?
case "$AWKRC" in
  0) ;;
  3) echo "TREE DIVERGED — quoted path in $PRE, cannot compare"; exit 1 ;;
  4) echo "TREE DIVERGED — unparseable entry in $PRE"; exit 1 ;;
  *) echo "TREE DIVERGED — could not parse $PRE"; exit 1 ;;
esac
NFILES=0; NADD=0; NDEL=0; LOST=0
HAVE=0; OLDP=""; NEWP=""; BIN=0
lost() { echo "LOST: $*"; LOST=$((LOST + 1)); }
check_entry() {
  local rc L h m
  [ "$HAVE" -eq 1 ] || return 0
  NFILES=$((NFILES + 1))
  if [ "$NEWP" = /dev/null ]; then
    if git cat-file -e "HEAD:$OLDP" 2>/dev/null; then lost "deletion lost, still at HEAD: $OLDP"; fi
    return 0
  fi
  git cat-file -e "HEAD:$NEWP" 2>/dev/null || { lost "file missing at HEAD: $NEWP"; return 0; }
  [ "$BIN" -eq 0 ] || return 0
  git show "HEAD:$NEWP" > "$HF" 2>/dev/null || { lost "could not read HEAD:$NEWP"; return 0; }
  # Added lines: each must be a whole line of HEAD's copy. The pattern file holds only
  # significant lines, so no empty pattern can match everything.
  if [ -s "$ADDS" ]; then
    rc=0; LC_ALL=C grep -a '[[:alnum:]]' "$HF" > "$HFP" || rc=$?
    [ "$rc" -le 1 ] || { lost "could not scan HEAD:$NEWP"; return 0; }
    rc=0
    LC_ALL=C grep -avxF -f "$HFP" -- "$ADDS" > "$MISS" || rc=$?
    [ "$rc" -le 1 ] || { lost "could not compare added lines of $NEWP"; return 0; }
    while IFS= read -r L; do lost "$NEWP: +$L"; done < "$MISS"
  fi
  # Deleted lines: a line the entry also adds was moved, not deleted. Otherwise the
  # deletion is lost when HEAD still holds the line at least as often as master does.
  if [ -s "$DELS" ]; then
    git show "origin/master:$OLDP" > "$MF" 2>/dev/null || : > "$MF"
    rc=0
    if [ -s "$ADDS" ]; then
      LC_ALL=C grep -avxF -f "$ADDS" -- "$DELS" > "$KEEP" || rc=$?
    else
      cp "$DELS" "$KEEP" || rc=2
    fi
    [ "$rc" -le 1 ] || { lost "could not compare deleted lines of $OLDP"; return 0; }
    while IFS= read -r L; do
      h=$(LC_ALL=C grep -acxF -- "$L" "$HF" || :)
      m=$(LC_ALL=C grep -acxF -- "$L" "$MF" || :)
      case "$h$m" in ''|*[!0-9]*) lost "could not count $OLDP: -$L"; continue ;; esac
      if [ "$h" -gt 0 ] && [ "$h" -ge "$m" ]; then lost "$NEWP: -$L"; fi
    done < "$KEEP"
  fi
  return 0
}
while IFS= read -r rec; do
  kind=${rec:0:1}; body=${rec:1}
  case "$kind" in
    F) check_entry
       OLDP=${body%%$'\t'*}; NEWP=${body#*$'\t'}; BIN=0; HAVE=1
       : > "$ADDS"; : > "$DELS" ;;
    B) BIN=1 ;;
    +) printf '%s\n' "$body" >> "$ADDS"; NADD=$((NADD + 1)) ;;
    -) printf '%s\n' "$body" >> "$DELS"; NDEL=$((NDEL + 1)) ;;
    *) echo "TREE DIVERGED — unexpected record from the $PRE parser"; exit 1 ;;
  esac
done < "$REC"
check_entry
echo "CHECKED: files=$NFILES added=$NADD deleted=$NDEL"
[ "$NFILES" -gt 0 ] || { echo "TREE DIVERGED — no file entries parsed from $PRE"; exit 1; }
if [ "$LOST" -eq 0 ]; then
  echo "TREE-EQUIVALENT"
else
  echo "TREE DIVERGED — inspect before pushing"
fi
