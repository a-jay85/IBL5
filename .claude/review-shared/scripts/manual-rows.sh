#!/usr/bin/env bash
set -euo pipefail
[ -z "${1:-}" ] && { echo "STOP: arg1 (PR number) required — was the site rewritten with the literal?"; exit 1; }
[ -z "${2:-}" ] && { echo "STOP: arg2 (worktree slug) required — was the site rewritten with the literal?"; exit 1; }
WT="/Users/ajaynicolas/GitHub/IBL5-worktrees/$2"
[ -d "$WT" ] || { echo "STOP: worktree $WT does not exist — wrong slug?"; exit 1; }
cd "$WT"

SLUG="$2"
TMPFILE="/tmp/pr-ready-manual-rows-${1}.txt"
CURL_BODY="/tmp/pr-ready-curl-body-${1}.tmp"
: > "$TMPFILE"

emit_row() {
  echo "$1"
  echo "$1" >> "$TMPFILE"
}

# Fetch body fresh
BODY=$(gh pr view "$1" --json body --jq '.body') || { echo "STOP: gh pr view failed for PR #$1"; exit 1; }

# Extract Manual Testing section (range includes the closing ## heading; checkbox grep filters it)
SECTION=$(printf '%s\n' "$BODY" | sed -n '/^## Manual Testing/,/^## /p')

while IFS= read -r row; do
  # Only process checkbox rows (both unticked and already-ticked)
  printf '%s' "$row" | grep -qE '^\- \[[ x]\] \*\*' || continue

  # Clear body buffer for this iteration
  : > "$CURL_BODY"

  # Extract id and text
  id=$(printf '%s' "$row" | sed 's/^- \[.\] \*\*\([^*]*\)\*\*.*/\1/')
  text=$(printf '%s' "$row" | sed 's/^- \[.\] \*\*[^*]*\*\* — //')

  # Branch 1: already ticked
  if printf '%s' "$row" | grep -q '^\- \[x\]'; then
    emit_row "ROW ${id} SKIP-DONE"
    continue
  fi

  # Branch 2: human-perception keywords win immediately
  if printf '%s' "$text" | grep -qiE 'look|feel|visual|spacing|layout|align|colou?r|readable|intuitive|confusing|aesthetic|ux|design'; then
    emit_row "ROW ${id} SKIP-HUMAN"
    continue
  fi

  # Branch 3: no /ibl5/... or *.php token in the text
  if ! printf '%s' "$text" | grep -qE '/ibl5/|[a-zA-Z0-9_-]+\.php'; then
    emit_row "ROW ${id} SKIP-NOURL"
    continue
  fi

  # Branch 4: no assertion keyword — unclassifiable rows are human-perception
  if ! printf '%s' "$text" | grep -qiE 'loads|returns|renders|shows|404|500|no PHP error'; then
    emit_row "ROW ${id} SKIP-HUMAN"
    continue
  fi

  # Extract URL — cover the same token set branch 3 accepted
  url=""
  if printf '%s' "$text" | grep -qE 'https?://'; then
    url=$(printf '%s' "$text" | grep -oE 'https?://[^ ]+' | head -1) || url=""
  elif printf '%s' "$text" | grep -qE '/ibl5/'; then
    url=$(printf '%s' "$text" | grep -oE '/ibl5/[^ ]+' | head -1) || url=""
  else
    # bare *.php token
    url=$(printf '%s' "$text" | grep -oE '[a-zA-Z0-9_-]+\.php[^ ]*' | head -1) || url=""
  fi

  # Build full URL
  if printf '%s' "$url" | grep -qE '^https?://'; then
    full_url="$url"
  elif printf '%s' "$url" | grep -q '^/'; then
    full_url="http://${SLUG}.localhost${url}"
  else
    # bare *.php — relative to /ibl5/
    full_url="http://${SLUG}.localhost/ibl5/${url}"
  fi

  # Determine asserted HTTP status (avoid \b — not reliable on Darwin BSD grep)
  expected="200"
  if printf '%s' "$text" | grep -qE '(^|[^0-9])404([^0-9]|$)'; then expected="404"; fi
  if printf '%s' "$text" | grep -qE '(^|[^0-9])500([^0-9]|$)'; then expected="500"; fi

  # Curl WITHOUT -L: redirects must stay visible, not masquerade as passes
  curl_out=$(curl -sS -o "$CURL_BODY" -w '%{http_code} %{redirect_url}' --max-time 15 "$full_url" 2>/dev/null) || curl_out="000 "
  http_code="${curl_out%% *}"
  redirect_url="${curl_out#* }"

  # Branch 5: 30x redirect whose Location names a login page
  if printf '%s' "$http_code" | grep -qE '^3'; then
    if printf '%s' "$redirect_url" | grep -qiE 'login|signin'; then
      emit_row "ROW ${id} SKIP-AUTH"
      continue
    fi
  fi

  # Branch 6: HTTP status does not match the asserted one
  if [ "$http_code" != "$expected" ]; then
    emit_row "ROW ${id} FAIL http-${http_code}"
    continue
  fi

  # Branch 7: PHP error signal in response body
  if [ -s "$CURL_BODY" ] && grep -qE 'Fatal error|Parse error|Warning:|Notice:' "$CURL_BODY"; then
    emit_row "ROW ${id} FAIL php-error"
    continue
  fi

  # Branch 8: quoted literal check — grep -qF against response body
  literal=$(printf '%s' "$text" | grep -oE '"[^"]+"' | head -1) || literal=""
  if [ -n "$literal" ]; then
    literal_text="${literal//\"/}"
    if [ -s "$CURL_BODY" ] && ! grep -qF "$literal_text" "$CURL_BODY"; then
      emit_row "ROW ${id} FAIL missing-literal"
      continue
    fi
  fi

  # Branch 9: all checks passed — only branch that ticks
  emit_row "ROW ${id} PASS"

done <<< "$SECTION"

echo "MANUAL-ROWS-COMPLETE"
