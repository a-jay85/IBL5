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
if diff "$NPRE" "$NPOST"; then
  echo "TREE-EQUIVALENT"
else
  echo "TREE DIVERGED — inspect before pushing"
fi
