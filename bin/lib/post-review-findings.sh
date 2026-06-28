# Shared posting helper for code-review & security-audit findings — converts a
# JSON findings array into either resolvable inline GitHub review threads (on-diff
# lines) or a single fallback issue comment (out-of-diff lines).  Sourced by
# /post-plan Phase 4D, /pr-review Step 7, and /security-audit Step 7.
#
# Usage: source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
#
# Public API:
#   post_review_findings PR_NUMBER HEAD_SHA REVIEW_TITLE FINDINGS_FILE
#   post_review_summary  PR_NUMBER REVIEW_TITLE BODY
#
# Test seam: GH_CMD (default `gh`) and REPO_SLUG (default `a-jay85/IBL5`) are
# overridable env-vars so the shim test can drive both without network calls.
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.

GH_CMD="${GH_CMD:-gh}"
REPO_SLUG="${REPO_SLUG:-a-jay85/IBL5}"

# Two-line footer appended by the helper envelope (callers must NOT add it).
PRF_FOOTER='Generated with [Claude Code](https://claude.ai/code)

<sub>If this was useful, react with thumbs-up. Otherwise, react with thumbs-down.</sub>'

# prf_diff_right_lines PR_NUMBER
#   Emits "path<TAB>line" for every right-side (context or added) line in the
#   PR diff.  Deleted lines (-) are skipped and do not advance the counter.
#   Handles both "@@ -a,b +c,d @@" and "@@ -a +c @@" (single-line) hunk forms.
prf_diff_right_lines() {
    local pr="$1"
    "$GH_CMD" pr diff "$pr" | awk '
        /^diff --git / { cur_file = "" }
        /^\+\+\+ b\// {
            sub(/^\+\+\+ b\//, "")
            cur_file = $0
        }
        /^@@ / {
            # Extract the +start from "+start" or "+start,count"
            match($0, /\+[0-9]+/)
            rline = substr($0, RSTART+1, RLENGTH-1) + 0
            next
        }
        cur_file != "" && /^\+/ && !/^\+\+\+/ {
            print cur_file "\t" rline
            rline++
            next
        }
        cur_file != "" && /^ / {
            print cur_file "\t" rline
            rline++
            next
        }
        cur_file != "" && /^-/ && !/^---/ {
            next
        }
    '
}

# post_review_findings PR_NUMBER HEAD_SHA REVIEW_TITLE FINDINGS_FILE
#   Partitions findings by whether their path:line is present in the PR diff.
#   On-diff  → one atomic batch review POST (resolvable inline threads).
#   Out-of-diff → one fallback gh pr comment (nothing is dropped).
#   Empty findings array → no-op.
post_review_findings() {
    local pr="$1" head_sha="$2" title="$3" findings_file="$4"
    local tmp; tmp="$(mktemp -d)"
    # shellcheck disable=SC2064
    trap "rm -rf '$tmp'" RETURN

    local count
    count=$(jq 'length' "$findings_file")
    [ "$count" -eq 0 ] && return 0

    # Build path:line set from the diff
    local lines_file="$tmp/lines.json"
    prf_diff_right_lines "$pr" \
        | jq -R 'split("\t") | .[0]+":"+.[1]' \
        | jq -s '.' > "$lines_file"

    # Partition: on-diff vs out-of-diff
    local on_file="$tmp/on.json" off_file="$tmp/off.json"
    jq --slurpfile lineset "$lines_file" \
        '[.[] | select( (.path+":"+(.line|tostring)) as $k | $lineset[0] | index($k) != null )]' \
        "$findings_file" > "$on_file"
    jq --slurpfile lineset "$lines_file" \
        '[.[] | select( (.path+":"+(.line|tostring)) as $k | $lineset[0] | index($k) == null )]' \
        "$findings_file" > "$off_file"

    local on_count off_count
    on_count=$(jq 'length' "$on_file")
    off_count=$(jq 'length' "$off_file")

    # On-diff: batch review POST
    if [ "$on_count" -gt 0 ]; then
        local envelope="### ${title}

Found ${on_count} issue(s). See inline threads below.

${PRF_FOOTER}"
        local payload_file="$tmp/payload.json"
        jq -n \
            --arg sha "$head_sha" \
            --arg ev "COMMENT" \
            --arg body "$envelope" \
            --argjson comments "$(jq '[.[] | {path, line, side:"RIGHT", body: (.body + "\n\n<!-- score: " + (.score|tostring) + " -->")}]' "$on_file")" \
            '{commit_id: $sha, event: $ev, body: $body, comments: $comments}' \
            > "$payload_file"
        "$GH_CMD" api --method POST \
            "repos/${REPO_SLUG}/pulls/${pr}/reviews" \
            --input "$payload_file"
    fi

    # Out-of-diff: fallback comment
    if [ "$off_count" -gt 0 ]; then
        local fallback_file="$tmp/fallback.txt"
        {
            printf '### %s\n\n' "$title"
            printf 'Found %d issue(s) on lines not present in the diff:\n\n' "$off_count"
            jq -r 'to_entries[] | "\(.key + 1). \(.value.body)\n\n<!-- score: \(.value.score) -->"' "$off_file"
            printf '\n%s\n' "$PRF_FOOTER"
        } > "$fallback_file"
        "$GH_CMD" pr comment "$pr" --body-file "$fallback_file"
    fi
}

# post_review_summary PR_NUMBER REVIEW_TITLE BODY
#   Posts a single issue comment for the no-issues path (no findings survived the
#   filter).  The heading and footer are emitted by this function; callers pass
#   only the evidence body (e.g. "No issues found. Architecture clean.").
post_review_summary() {
    local pr="$1" title="$2" body="$3"
    local tmp; tmp="$(mktemp -d)"
    # shellcheck disable=SC2064
    trap "rm -rf '$tmp'" RETURN

    local out="$tmp/summary.txt"
    printf '### %s\n\n%s\n\n%s\n' "$title" "$body" "$PRF_FOOTER" > "$out"
    "$GH_CMD" pr comment "$pr" --body-file "$out"
}
