#!/usr/bin/env bash
# Canonical find-by-HTML-marker sticky PR comment helpers, sourced by bin/ scripts.
#
# Marker ownership stays with the CALLER: these functions never append the marker
# to the body. Pass a body that already contains it (bin/pr-canary-check builds it
# into render_body; bin/check-pr-collisions concatenates it at the call site).
#
# Honours $GH_CMD so test harnesses can substitute a stub gh.

pr_sticky_find() {   # <pr-number> <marker>  -> prints the comment id, or nothing
    local n="$1" marker="$2"
    "${GH_CMD:-gh}" api "repos/{owner}/{repo}/issues/$n/comments" --paginate \
        --jq ".[] | select(.body | contains(\"$marker\")) | .id" | head -1 || true
}

pr_sticky_upsert() { # <pr-number> <marker> <body>
    local n="$1" marker="$2" body="$3" tmpfile existing_id
    tmpfile="$(mktemp)"
    printf '%s\n' "$body" > "$tmpfile"
    existing_id="$(pr_sticky_find "$n" "$marker")"
    if [[ -n "$existing_id" ]]; then
        "${GH_CMD:-gh}" api --method PATCH "repos/{owner}/{repo}/issues/comments/$existing_id" \
            -F body=@"$tmpfile" >/dev/null || true
    else
        "${GH_CMD:-gh}" pr comment "$n" --body-file "$tmpfile" >/dev/null || true
    fi
    rm -f "$tmpfile"
}

pr_sticky_delete() { # <pr-number> <marker>  -- no-op when absent
    local n="$1" marker="$2" existing_id
    existing_id="$(pr_sticky_find "$n" "$marker")"
    [[ -n "$existing_id" ]] || return 0
    "${GH_CMD:-gh}" api --method DELETE "repos/{owner}/{repo}/issues/comments/$existing_id" \
        >/dev/null || true
}
