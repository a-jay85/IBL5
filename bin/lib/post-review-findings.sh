# Shared posting helper for code-review & security-audit findings — converts a
# JSON findings array into either resolvable inline GitHub review threads (on-diff
# lines) or a single fallback issue comment (out-of-diff lines).  Sourced by
# /post-plan Phase 4D, /pr-review Step 7, and /security-audit Step 7.
#
# Usage: source "$(git rev-parse --show-toplevel)/bin/lib/post-review-findings.sh"
#
# Public API:
#   post_review_findings     PR_NUMBER HEAD_SHA REVIEW_TITLE FINDINGS_FILE
#   post_review_summary      PR_NUMBER REVIEW_TITLE BODY
#   list_open_review_findings PR_NUMBER
#   resolve_review_finding   PR_NUMBER COMMENT_ID BODY
#
# The last two are the DISPOSITION half of the lifecycle: a finding posted as an
# inline thread stays open forever unless something replies in-thread and calls
# the resolveReviewThread mutation.  A top-level `gh pr comment` announcing the
# fix does NOT close the thread — GitHub never associates the two.
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
    # `trap ... RETURN` is bash-only.  This file is SOURCED, and /pr-review
    # sources it from a zsh shell, where RETURN is not a signal: the trap
    # command errors ("undefined signal: RETURN") and the temp dir leaks.  zsh
    # scopes an in-function EXIT trap to that function, which is the same
    # cleanup semantics bash gets from RETURN — so branch on the shell.
    # Keep this inline at each call site: factoring it into a helper would scope
    # the trap to the helper, firing it the moment the helper returns.
    # shellcheck disable=SC2064
    if [ -n "${ZSH_VERSION:-}" ]; then
        trap "rm -rf '$tmp'" EXIT
    else
        trap "rm -rf '$tmp'" RETURN
    fi

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
#   This is always the clean path, so the comment is wrapped in a collapsed
#   <details> (affirmative one-line summary) to save PR scroll space.
post_review_summary() {
    local pr="$1" title="$2" body="$3"
    local tmp; tmp="$(mktemp -d)"
    # Shell-branched cleanup — see post_review_findings for why RETURN alone leaks.
    # shellcheck disable=SC2064
    if [ -n "${ZSH_VERSION:-}" ]; then
        trap "rm -rf '$tmp'" EXIT
    else
        trap "rm -rf '$tmp'" RETURN
    fi

    local out="$tmp/summary.txt"
    printf '<details><summary>✅ %s — no issues found</summary>\n\n### %s\n\n%s\n\n%s\n\n</details>\n' \
        "$title" "$title" "$body" "$PRF_FOOTER" > "$out"
    "$GH_CMD" pr comment "$pr" --body-file "$out"
}

# ── Disposition half: reply in-thread and resolve ─────────────────────────────

# prf_review_threads PR_NUMBER
#   Emits one JSON object per review thread:
#     {id, isResolved, isOutdated, path, line, commentId, score, body}
#   `commentId` is the REST databaseId of the thread's FIRST comment — the id the
#   replies endpoint keys on.  `score` is parsed from the `<!-- score: N -->`
#   marker post_review_findings embeds, or null for a human-authored thread.
#   CAP: the first 100 threads only — no pagination.  A PR with more than 100
#   review threads silently truncates here; every caller inherits that cap.
#   A thread whose only comment was deleted yields commentId/body null, so the
#   `// ""` guards below are load-bearing (jq errors on `null | capture`).
prf_review_threads() {
    local pr="$1"
    local owner="${REPO_SLUG%%/*}" repo="${REPO_SLUG##*/}"
    "$GH_CMD" api graphql \
        -F owner="$owner" -F repo="$repo" -F pr="$pr" \
        -f query='
        query($owner:String!, $repo:String!, $pr:Int!) {
          repository(owner:$owner, name:$repo) {
            pullRequest(number:$pr) {
              reviewThreads(first:100) {
                nodes {
                  id isResolved isOutdated path line
                  comments(first:1) { nodes { databaseId body } }
                }
              }
            }
          }
        }' \
        --jq '.data.repository.pullRequest.reviewThreads.nodes[]
              | {id, isResolved, isOutdated, path, line,
                 commentId: .comments.nodes[0].databaseId,
                 score: ((.comments.nodes[0].body // "")
                         | capture("<!-- score: (?<s>[0-9]+) -->") // null
                         | if . == null then null else (.s|tonumber) end),
                 body: (.comments.nodes[0].body // "")}'
}

# list_open_review_findings PR_NUMBER
#   Human/agent-readable TSV of every UNRESOLVED review thread on the PR:
#     COMMENT_ID <TAB> SCORE <TAB> path:line <TAB> first line of the finding
#   Empty output = every thread is dispositioned.  Feed COMMENT_ID straight into
#   resolve_review_finding.  Threads whose sole comment was deleted have no
#   COMMENT_ID to reply to, so they are skipped rather than listed as "null".
list_open_review_findings() {
    local pr="$1"
    prf_review_threads "$pr" \
        | jq -r 'select(.isResolved == false and .commentId != null)
                 | [ (.commentId|tostring),
                     (if .score == null then "-" else (.score|tostring) end),
                     ((.path // "?") + ":" + (if .line == null then "?" else (.line|tostring) end)),
                     (.body | split("\n")[0] | .[0:100]) ]
                 | @tsv'
}

# resolve_review_finding PR_NUMBER COMMENT_ID BODY
#   Dispositions one review finding: posts BODY as a threaded REPLY to
#   COMMENT_ID, then marks the containing thread resolved.  BODY should say what
#   happened — "Fixed in <sha> — <what changed>" or why it is being declined.
#   Prints the thread's post-mutation isResolved value.  Returns 1 if COMMENT_ID
#   matches no thread on the PR, 2 on a malformed COMMENT_ID.
#
#   Use this INSTEAD of `gh pr comment` when announcing that a finding is
#   handled: a top-level comment leaves the thread open forever.
resolve_review_finding() {
    local pr="$1" cid="$2" body="$3"
    case "$cid" in
        ''|*[!0-9]*) echo "resolve_review_finding: COMMENT_ID must be numeric, got '$cid'" >&2; return 2 ;;
    esac

    local tid
    tid=$(prf_review_threads "$pr" | jq -r --argjson cid "$cid" \
        'select(.commentId == $cid) | .id' | head -1)
    if [ -z "$tid" ]; then
        echo "resolve_review_finding: comment ${cid} owns no review thread in the first 100 on PR #${pr}" >&2
        return 1
    fi

    # -f (raw-field) sends BODY as a literal string: no temp file, and no `@file`
    # expansion to escape from — a finding reply routinely starts with a backtick
    # or contains newlines.
    "$GH_CMD" api --method POST \
        "repos/${REPO_SLUG}/pulls/${pr}/comments/${cid}/replies" \
        -f body="$body" >/dev/null || return 1

    "$GH_CMD" api graphql -F tid="$tid" -f query='
        mutation($tid: ID!) {
          resolveReviewThread(input:{threadId:$tid}) { thread { id isResolved } }
        }' \
        --jq '.data.resolveReviewThread.thread.isResolved'
}
