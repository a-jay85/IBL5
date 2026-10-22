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
# significant changed lines, `B` marks it binary, `M<path>\t<mode>` carries the six-digit
# mode of an entry with a `new file mode` header (used by the delete arm to recognise a
# type-change pair). Exit 3 = git-quoted path, 4 = an entry
# whose paths cannot be read or a `new file mode` header that is not six octal digits. An entry with no ---/+++ lines (binary, empty new file,
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
  if (isnew && newmode != "") print "M" newp "\t" newmode
  if (bin) print "B"
}
/^diff --git / {
  finish()
  inent = 1; emitted = 0; latch = 0; bin = 0; isnew = 0; isdel = 0; seenold = 0
  oldp = ""; newp = ""; rfrom = ""; rto = ""; newmode = ""
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
/^new file mode / {
  isnew = 1; newmode = substr($0, 15)
  if (newmode !~ /^[0-7][0-7][0-7][0-7][0-7][0-7]$/) fail(4)
  next
}
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
# Pre-pass: paths this patch creates, with the mode the create header recorded. The delete
# half of a type-change pair runs before its create half, so the lookup is built up front.
CREATED="$TMPD/created"
LC_ALL=C grep '^M' "$REC" | cut -c2- > "$CREATED" || :
NFILES=0; NADD=0; NDEL=0; LOST=0
HAVE=0; OLDP=""; NEWP=""; BIN=0
lost() { echo "LOST: $*"; LOST=$((LOST + 1)); }
# Parser for ibl5/phpstan-baseline.neon, the one file the second exemption reads.
# `paths` prints each block's path; `split <file> <exempt-paths> <live-out> <dead-out>`
# copies every block whose path is listed in <exempt-paths> to <dead-out> and every
# other block to <live-out>. Grammar: `parameters:`, `\tignoreErrors:`, then 5-line
# blocks (`\t\t-`, rawMessage, identifier, count, path) each followed by a blank line or
# EOF. Anything else, or zero blocks, exits 1 and the caller keeps the file strict.
lw_neon() {
  local mode=$1 file=$2 exempt=${3:-/dev/null} live=${4:-/dev/null} dead=${5:-/dev/null}
  LC_ALL=C awk -v mode="$mode" -v live="$live" -v dead="$dead" '
    function bad() { rc = 1; exit 1 }
    FILENAME == ARGV[1] && mode == "split" { ex[$0] = 1; next }
    FNR == 1 { if ($0 != "parameters:") bad(); st = 1; next }
    st == 1 { if ($0 != "\tignoreErrors:") bad(); st = 2; next }
    st == 2 { if ($0 != "\t\t-") bad(); n = 1; blk[n] = $0; st = 3; next }
    st == 3 {
      if ($0 == "\t\t\trawMessage: '"'''"'") { blk[++n] = $0; st = 31; next }
      if ($0 !~ /^\t\t\trawMessage: ./) bad()
      blk[++n] = $0; st = 4; next }
    st == 31 {
      if ($0 == "\t\t\t'"'''"'") { blk[++n] = $0; st = 4; next }
      if ($0 !~ /^\t\t\t\t/) bad()
      blk[++n] = $0; next }
    st == 4 { if ($0 !~ /^\t\t\tidentifier: ./) bad(); blk[++n] = $0; st = 5; next }
    st == 5 { if ($0 !~ /^\t\t\tcount: [0-9]+$/) bad(); blk[++n] = $0; st = 6; next }
    st == 6 {
      if ($0 !~ /^\t\t\tpath: [A-Za-z0-9_][A-Za-z0-9_.\/-]*$/ || index($0, "..")) bad()
      p = substr($0, 10); blk[++n] = $0; nb++
      if (mode == "paths") pl[nb] = p
      else { out = (p in ex) ? dead : live; for (i = 1; i <= n; i++) print blk[i] > out }
      st = 7; next }
    st == 7 { if ($0 != "") bad(); if (mode == "split") print "" > out; st = 2; next }
    { bad() }
    END {
      if (rc) exit 1
      if (nb == 0 || (st != 2 && st != 7)) exit 1
      for (i = 1; i <= nb; i++) print pl[i] }
  ' "$exempt" "$file"
}
check_entry() {
  local rc L h m p HC MC cm hm
  [ "$HAVE" -eq 1 ] || return 0
  NFILES=$((NFILES + 1))
  if [ "$NEWP" = /dev/null ]; then
    if git cat-file -e "HEAD:$OLDP" 2>/dev/null; then
      # Type-change pair: `git diff` writes a regular-file<->symlink change as a delete of
      # the old path plus a create of the same path, so the delete half alone reads as a
      # lost deletion. It is honoured only when this patch also creates the path and
      # HEAD's mode equals the mode the create header recorded; the create entry's own
      # content check still runs. Any other mode, or no create entry, is a lost deletion.
      cm=$(LC_ALL=C awk -F'\t' -v p="$OLDP" '$1 == p { print $2; exit }' "$CREATED")
      hm=$(git ls-tree HEAD -- "$OLDP" 2>/dev/null | cut -d' ' -f1)
      if [ -z "$cm" ] || [ -z "$hm" ] || [ "$cm" != "$hm" ]; then
        lost "deletion lost, still at HEAD: $OLDP"
      fi
    fi
    return 0
  fi
  git cat-file -e "HEAD:$NEWP" 2>/dev/null || { lost "file missing at HEAD: $NEWP"; return 0; }
  [ "$BIN" -eq 0 ] || return 0
  git show "HEAD:$NEWP" > "$HF" 2>/dev/null || { lost "could not read HEAD:$NEWP"; return 0; }
  # First exemption, one key (the second is the baseline block below): an added `last_verified: YYYY-MM-DD` line is not lost when
  # HEAD's own frontmatter carries an equal or newer date (both sides bumped the doc and
  # the resolver kept the newer stamp). Only a file opening with `---` qualifies, only
  # the first `last_verified:` inside that frontmatter is read, and every other added
  # line stays strict.
  if [ -s "$ADDS" ] && [ "$(sed -n '1p' "$HF")" = "---" ]; then
    head_lv=$(LC_ALL=C sed -n '2,/^---$/{s/^last_verified: \([0-9]\{4\}-[0-9]\{2\}-[0-9]\{2\}\)$/\1/p;}' "$HF" | sed -n '1p')
    if [ -n "$head_lv" ]; then
      : > "$TMPD/adds-kept"
      while IFS= read -r L; do
        case "$L" in
          "last_verified: "[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9])
            pre_lv=${L#last_verified: }
            if [ "$pre_lv" \< "$head_lv" ] || [ "$pre_lv" = "$head_lv" ]; then continue; fi ;;
        esac
        printf '%s\n' "$L" >> "$TMPD/adds-kept"
      done < "$ADDS"
      mv "$TMPD/adds-kept" "$ADDS"
    fi
  fi
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
    # Second exemption, one key, one file: a deleted line of ibl5/phpstan-baseline.neon
    # whose block names a `path:` absent from the HEAD tree (git cat-file -e
    # HEAD:ibl5/<path>) is not lost; the ignore entry has nothing left to ignore. The
    # `ibl5/` prefix is load-bearing: a bare `HEAD:$p` is always absent and would exempt
    # every block. Both
    # copies must parse (lw_neon) or the file stays fully strict. Blocks for absent paths
    # are removed from both copies before counting, and each deleted line is charged to
    # the dead blocks' own lines first, so a `count: 1` shared with a dead block is not
    # read as a surviving copy of a live block's deletion. A live block's `path:` line is
    # never chargeable (its path exists), so a dropped live deletion still reports it.
    HC="$HF"; MC="$MF"
    if [ "$NEWP" = ibl5/phpstan-baseline.neon ] && [ "$OLDP" = ibl5/phpstan-baseline.neon ] && [ -s "$MF" ]; then
      if lw_neon paths "$HF" > "$TMPD/paths-h" && lw_neon paths "$MF" > "$TMPD/paths-m"; then
        : > "$TMPD/dead-paths"
        LC_ALL=C sort -u "$TMPD/paths-h" "$TMPD/paths-m" | while IFS= read -r p; do
          git cat-file -e "HEAD:ibl5/$p" 2>/dev/null || printf '%s\n' "$p" >> "$TMPD/dead-paths"
        done
        : > "$TMPD/head-live"; : > "$TMPD/head-dead"; : > "$TMPD/master-live"; : > "$TMPD/master-dead"
        if [ -s "$TMPD/dead-paths" ] \
          && lw_neon split "$HF" "$TMPD/dead-paths" "$TMPD/head-live" "$TMPD/head-dead" \
          && lw_neon split "$MF" "$TMPD/dead-paths" "$TMPD/master-live" "$TMPD/master-dead" \
          && LC_ALL=C awk '
               FILENAME == ARGV[1] { a[$0]++; next }
               FILENAME == ARGV[2] { b[$0]++; next }
               { bud = (a[$0] > b[$0]) ? a[$0] : b[$0]
                 if (used[$0] < bud) { used[$0]++; next }
                 print }' "$TMPD/master-dead" "$TMPD/head-dead" "$KEEP" > "$TMPD/dels-live"
        then
          mv "$TMPD/dels-live" "$KEEP"; HC="$TMPD/head-live"; MC="$TMPD/master-live"
        fi
      fi
    fi
    while IFS= read -r L; do
      h=$(LC_ALL=C grep -acxF -- "$L" "$HC" || :)
      m=$(LC_ALL=C grep -acxF -- "$L" "$MC" || :)
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
    M) ;;  # consumed by the pre-pass above
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
