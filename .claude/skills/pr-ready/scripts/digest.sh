#!/usr/bin/env bash
# digest.sh — extract the `## DIGEST` section from a /pr-ready Phase 6 verdict file and
# emit exactly five labelled lines on stdout, for the Phase 7 sticky-comment composer.
#
# Contract: five lines, always, in this fixed order, each opening with its bold label —
#   **What changed:** / **Why:** / **Watch:** / **Touches:** / **Machine-authored fixes:**
# A sibling parser greps the `### Merge digest` heading and reads the five labels beneath
# it, so the SHAPE must be stable even when the content is not available. Anything the
# script cannot extract verbatim degrades to `<label> unavailable — <reason>` — never to
# a partial digest, never to fewer than five lines, never to a silent empty output.
#
# Usage: bash digest.sh /tmp/pr-ready-phase6-verdict-<N>.md

set -euo pipefail

LABELS=(
  '**What changed:**'
  '**Why:**'
  '**Watch:**'
  '**Touches:**'
  '**Machine-authored fixes:**'
)

# Five labelled placeholder lines, then exit 0. A missing or malformed digest is a
# legitimate runtime state (Phase 6 skipped, reviewer degraded), not a script failure:
# exiting non-zero here would make the caller's `&&` chain look like a broken materialise.
degrade() {
  local i
  for i in 0 1 2 3 4; do
    printf '%s unavailable — %s\n' "${LABELS[$i]}" "$1"
  done
  exit 0
}

# The ONE hard failure: no argument at all. That is a call-site bug (the <N> literal was
# not substituted), not a runtime state, and it must be loud — same shape as holds.sh.
[ -z "${1:-}" ] && { echo "STOP: arg1 (verdict file path) required — was the site rewritten with the literal?"; exit 1; }
VERDICT="$1"

[ -f "$VERDICT" ] || degrade "no Phase 6 verdict file at $VERDICT"
[ -s "$VERDICT" ] || degrade "Phase 6 verdict file is empty"

# Heading presence is checked separately from body extraction so the two failures get
# distinct reasons. Guarded by `if !` so the non-zero grep cannot trip `set -e`.
# `[[:space:]]*$` tolerates a trailing CR, since the verdict file is written by the
# agent's Write tool and may arrive CRLF.
if ! grep -qE '^## DIGEST[[:space:]]*$' "$VERDICT"; then
  degrade "no DIGEST section in verdict file"
fi

# Capture every non-blank line after the LAST `## DIGEST` heading. Last, not first: the
# contract puts the section at end of file, so an earlier occurrence can only be the
# reviewer quoting the heading inside its findings prose. String accumulation, not an
# awk array — `delete arr` is not portable to the BWK awk shipped on macOS.
#
# awk exits 0 whether or not it matched, so this assignment can never trip `pipefail`.
# Do NOT rewrite it as `grep -A5` or a `grep -c` pipeline: a zero-match `grep -c` exits 1
# and, under `set -euo pipefail`, would kill the script BEFORE degrade() prints anything —
# the caller would then see an empty file rather than five placeholder lines.
BODY="$(awk '
  { sub(/\r$/, "") }
  $0 == "## DIGEST" { out = ""; seen = 1; next }
  seen && $0 != ""  { out = out $0 "\n" }
  END               { printf "%s", out }
' "$VERDICT")"

[ -n "$BODY" ] || degrade "DIGEST section is empty (expected 5 labelled lines, found 0)"

n=0
LINES=()
while IFS= read -r line; do
  LINES[$n]="$line"
  n=$((n + 1))
done <<< "$BODY"

[ "$n" -eq 5 ] || degrade "DIGEST section malformed (expected 5 labelled lines, found $n)"

i=0
while [ "$i" -lt 5 ]; do
  if [ "${LINES[$i]:0:${#LABELS[$i]}}" != "${LABELS[$i]}" ]; then
    degrade "DIGEST line $((i + 1)) does not open with ${LABELS[$i]}"
  fi
  i=$((i + 1))
done

printf '%s\n' "${LINES[@]}"
