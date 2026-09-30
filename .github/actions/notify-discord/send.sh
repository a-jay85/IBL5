#!/bin/bash
# send.sh — the shell logic of the notify-discord composite action.
#
# POSTs the message file to the #dev channel webhook. Lives in its own file so
# bin/test-discord-dm can run the exact bytes CI runs. Inputs arrive only through
# env (WEBHOOK_URL, MSG_FILE, PING, FAIL_ON_DELIVERY); NOTIFY_CURL_BIN is a test
# seam that no workflow sets.

set -uo pipefail

: "${WEBHOOK_URL:=}" "${MSG_FILE:=}" "${PING:=false}" "${FAIL_ON_DELIVERY:=true}"

die_or_warn() {
    if [ "$FAIL_ON_DELIVERY" = true ]; then
        echo "::error::$1"
        exit 1
    else
        echo "::warning::$1"
        exit 0
    fi
}

if [ -z "$WEBHOOK_URL" ]; then
    die_or_warn "webhook-url is empty (DISCORD_DEV_WEBHOOK_URL unset or fork PR)"
fi
echo "::add-mask::$WEBHOOK_URL"

# Same shape check as bin/discord-dm: https only, and no character that could
# break out of the curl config line below.
case "$WEBHOOK_URL" in
    https://*) ;;
    *) die_or_warn "webhook-url is malformed" ;;
esac
case "$WEBHOOK_URL" in
    *[!A-Za-z0-9:/._-]*) die_or_warn "webhook-url is malformed" ;;
esac

case "$PING" in
    true|false) ;;
    *) echo "::error::ping must be true or false"; exit 1 ;;
esac

MSG=$(cat "$MSG_FILE")
if [ "${#MSG}" -gt 1900 ]; then
    MSG="${MSG:0:1900}"$'\n…(truncated)'
fi

OWNER_ID=283183467804491776
if [ "$PING" = true ]; then
    content="<@$OWNER_ID> $MSG"
    am=$(jq -nc --arg id "$OWNER_ID" '{users: [$id]}')
else
    content="$MSG"
    am='{"parse":[]}'
fi

pf=$(mktemp "${TMPDIR:-/tmp}/notify-discord-payload.XXXXXX")
resp=$(mktemp "${TMPDIR:-/tmp}/notify-discord-resp.XXXXXX")
trap 'rm -f "$pf" "$resp"' EXIT
jq -n --arg c "$content" --argjson am "$am" \
    '{content: $c, flags: 4, allowed_mentions: $am}' > "$pf" || exit 1

code=""
for attempt in 1 2 3; do
    # The URL goes in on stdin as a curl config line so it never enters argv.
    code=$(printf 'url = "%s?wait=true"\n' "$WEBHOOK_URL" | "${NOTIFY_CURL_BIN:-curl}" \
        -s -m 15 -K - -o "$resp" -w '%{http_code}' -X POST \
        -H 'Content-Type: application/json' --data-binary "@$pf")
    case "$code" in
        2??)
            echo "delivered (HTTP $code)"
            exit 0 ;;
        429)
            [ "$attempt" -lt 3 ] || break
            ra=$(jq -r '.retry_after // 1' "$resp" 2>/dev/null)
            ra=$(awk -v r="$ra" 'BEGIN {
                if (r !~ /^[0-9]+(\.[0-9]+)?$/) { print 5; exit }
                c = int(r); if (c < r) c++
                if (c < 1) c = 1; if (c > 30) c = 30
                print c }')
            sleep "$ra" ;;
        *) break ;;
    esac
done

die_or_warn "Discord webhook POST failed (HTTP ${code:-none}): $(head -c 300 "$resp")"
