#!/usr/bin/env bash
# shellcheck shell=bash
#
# bin/lib/bug-pipeline-gh.sh — best-effort GitHub issue-tracking seam (§3f).
#
# A write-only projection of DB state to a PRIVATE tracking repo for root-cause
# analysis. Sourced by bin/bug-pipeline-tick; reused by PR #5b's hunter.
#
# LOAD-BEARING INVARIANTS:
#   1. Best-effort — every function logs a warning and returns 0 on any gh failure;
#      it NEVER aborts a tick, blocks a DB transition, or blocks a Discord post.
#   2. No-op when BUG_PIPELINE_ISSUE_REPO is empty or gh is unresolvable
#      (guard: bpgh_available). The DB, not the issue, is authoritative.
#   3. Idempotent creation — bpgh_ensure_issue creates only when issue_number IS NULL.
#   4. GM text lives ONLY in the private tracking repo; issue_title is always passed
#      as a single quoted argv arg to gh, never shell-interpolated.
#
# Seams: GH_BIN (default gh), BUG_PIPELINE_ISSUE_REPO. Tests stub GH_BIN + point
# BUG_PIPELINE_ISSUE_REPO at a throwaway repo.

: "${GH_BIN:=gh}"
: "${BUG_PIPELINE_ISSUE_REPO:=}"

# Prefer the driver's log(); fall back to a timestamped stderr line when sourced bare.
_bpgh_log() {
    if command -v log >/dev/null 2>&1; then
        log "$*"
    else
        printf '[%s] %s\n' "$(date '+%Y-%m-%dT%H:%M:%S')" "$*" >&2
    fi
}

# Returns 0 iff issue tracking is configured and gh is available.
bpgh_available() {
    [ -n "$BUG_PIPELINE_ISSUE_REPO" ] && command -v "$GH_BIN" >/dev/null 2>&1
}

# bpgh_ensure_issue <row_json>
# The create primitive. If the row already has a numeric issue_number, echoes it and
# makes zero gh calls (idempotent). Else creates the tracking issue and echoes the
# NEW number on success (nothing on failure). Reads issue_title/severity from
# caller-scope vars with safe defaults — so #5b's self-heal (no classifier output)
# still creates with a truncated-text title + severity:low. Never fails the caller.
bpgh_ensure_issue() {
    local row_json="$1"
    bpgh_available || return 0

    local existing
    existing="$(printf '%s' "$row_json" | jq -r '.issue_number // empty' 2>/dev/null)"
    if [ -n "$existing" ] && [ "$existing" != "null" ]; then
        printf '%s\n' "$existing"
        return 0
    fi

    local class report_id author original_text thread_id
    class="$(printf '%s' "$row_json" | jq -r '.class // "bug"' 2>/dev/null)"
    report_id="$(printf '%s' "$row_json" | jq -r '.id // "?"' 2>/dev/null)"
    author="$(printf '%s' "$row_json" | jq -r '.discord_author_id // "?"' 2>/dev/null)"
    thread_id="$(printf '%s' "$row_json" | jq -r '.thread_id // "(none)"' 2>/dev/null)"
    original_text="$(printf '%s' "$row_json" | jq -r '.original_text // ""' 2>/dev/null)"

    local class_label="bug"
    [ "$class" = "feature" ] && class_label="enhancement"

    # Advisory fields from caller scope (classifier output) with defaults.
    local title="${issue_title:-}"
    local sev="${severity:-low}"
    if [ -z "$title" ]; then
        # Truncated snapshot of the report text (newlines stripped, <= 70 chars).
        title="$(printf '%s' "$original_text" | tr '\n' ' ' | cut -c1-70)"
        [ -n "$title" ] || title="Bug pipeline report #${report_id}"
    fi

    # Attachment references, plain text only. Both the filename and the URL are
    # attacker-controlled, so emit `- name (type) — url`, never a `[name](url)`
    # markdown link (a crafted filename could close the link and inject markup).
    # Built here, then passed as ONE MORE printf %s ARGUMENT below — never into the
    # format string — so a filename containing `%s%n` cannot corrupt or crash printf.
    local att_block
    att_block="$(printf '%s' "$row_json" | jq -r '
        (.attachments // [])
        | if length > 0
          then "\n---\nAttachments:\n"
               + ( map("- " + (.filename // "(unnamed)")
                        + " (" + (.content_type // "unknown") + ") — "
                        + (.original_url // "(no url)")) | join("\n") )
          else "" end' 2>/dev/null)"

    local body
    body="$(printf 'Class: %s\nSeverity: %s\nReporter (discord_author_id): %s\nReport id: %s\nThread: %s\n\n---\n%s\n%s' \
        "$class" "$sev" "$author" "$report_id" "$thread_id" "$original_text" "$att_block")"

    local url
    if url="$("$GH_BIN" issue create --repo "$BUG_PIPELINE_ISSUE_REPO" \
            --title "$title" --body "$body" \
            --label "$class_label" --label "severity:$sev" 2>/dev/null)"; then
        # gh prints the issue URL; the trailing path segment is the number.
        printf '%s\n' "${url##*/}"
    else
        _bpgh_log "bpgh_ensure_issue: gh issue create failed for report ${report_id} (best-effort, continuing)"
    fi
    return 0
}

# bpgh_ensure_label <label> — create-on-demand for open vocabularies (no-op if exists).
bpgh_ensure_label() {
    local label="$1"
    bpgh_available || return 0
    "$GH_BIN" label create "$label" --repo "$BUG_PIPELINE_ISSUE_REPO" 2>/dev/null || true
    return 0
}

# bpgh_add_label <issue_number> <label>
bpgh_add_label() {
    local issue="$1" label="$2"
    bpgh_available || return 0
    [ -n "$issue" ] && [ "$issue" != "null" ] || return 0
    bpgh_ensure_label "$label"
    "$GH_BIN" issue edit "$issue" --repo "$BUG_PIPELINE_ISSUE_REPO" --add-label "$label" 2>/dev/null \
        || _bpgh_log "bpgh_add_label: failed adding $label to #$issue (best-effort)"
    return 0
}

# bpgh_comment <issue_number> <body>
bpgh_comment() {
    local issue="$1" body="$2"
    bpgh_available || return 0
    [ -n "$issue" ] && [ "$issue" != "null" ] || return 0
    "$GH_BIN" issue comment "$issue" --repo "$BUG_PIPELINE_ISSUE_REPO" --body "$body" 2>/dev/null \
        || _bpgh_log "bpgh_comment: failed commenting on #$issue (best-effort)"
    return 0
}

# bpgh_close <issue_number> — consumed by #5b's fixed-reconcile; defined here so both share the seam.
bpgh_close() {
    local issue="$1"
    bpgh_available || return 0
    [ -n "$issue" ] && [ "$issue" != "null" ] || return 0
    "$GH_BIN" issue close "$issue" --repo "$BUG_PIPELINE_ISSUE_REPO" --reason completed 2>/dev/null || true
    return 0
}

# bpgh_assign <issue_number> <login> — consumed by #5b's needs-human path.
bpgh_assign() {
    local issue="$1" login="$2"
    bpgh_available || return 0
    [ -n "$issue" ] && [ "$issue" != "null" ] || return 0
    "$GH_BIN" issue edit "$issue" --repo "$BUG_PIPELINE_ISSUE_REPO" --add-assignee "$login" 2>/dev/null || true
    return 0
}

# bpgh_code_repo_available — true when BUG_PIPELINE_CODE_REPO and gh are both present.
bpgh_code_repo_available() {
    [ -n "${BUG_PIPELINE_CODE_REPO:-}" ] && command -v "$GH_BIN" >/dev/null 2>&1
}

# bpgh_pr_comment <pr_number> <body> — post a comment on a code-repo PR (best-effort).
bpgh_pr_comment() {
    local pr="$1" body="$2"
    bpgh_code_repo_available || return 0
    [ -n "$pr" ] && [ "$pr" != "null" ] || return 0
    "$GH_BIN" pr comment "$pr" --repo "$BUG_PIPELINE_CODE_REPO" --body "$body" 2>/dev/null \
        || _bpgh_log "bpgh_pr_comment: failed commenting on PR #$pr (best-effort)"
    return 0
}

# bpgh_pr_failing_checks — emit one line per open, non-draft PR with settled red checks.
#
# Output format (one line per PR): NUM BRANCH HEAD_SHA failing_name,...
#   Names are LAST — GitHub check names may contain spaces.
#
# Skips PRs with CONFLICTING/DIRTY/empty merge state (fail-closed phantom-dispatch guard).
# Skips PRs with any PENDING check (not settled).
# Excludes human-signoff check (case-insensitive grep — not in jq; stub bypasses jq).
# No writes, no comments, no claude — detection only.
bpgh_pr_failing_checks() {
    bpgh_code_repo_available || return 0
    local prs
    prs="$("$GH_BIN" pr list --repo "$BUG_PIPELINE_CODE_REPO" --state open --limit 50 \
             --json number,headRefName,headRefOid,isDraft \
             --jq '.[] | select(.isDraft == false) | "\(.number) \(.headRefName) \(.headRefOid)"' \
             2>/dev/null)" || {
        _bpgh_log "bpgh_pr_failing_checks: pr list failed (best-effort)"; return 0; }
    [ -n "$prs" ] || return 0
    local num branch sha ms checks pending failing
    while IFS=' ' read -r num branch sha; do
        [ -n "${num:-}" ] || continue
        ms="$("$GH_BIN" pr view "$num" --repo "$BUG_PIPELINE_CODE_REPO" \
                --json mergeable,mergeStateStatus \
                --jq '[.mergeable,.mergeStateStatus]|@tsv' \
                2>/dev/null || true)"
        case "$ms" in
          *CONFLICTING*|*DIRTY*|"")
              _bpgh_log "bpgh_pr_failing_checks: PR #$num merge state '${ms:-unreadable}' — checks unreliable, skipping"
              continue ;;
        esac
        checks="$("$GH_BIN" pr checks "$num" --repo "$BUG_PIPELINE_CODE_REPO" \
                    --json name,state \
                    --jq '.[] | "\(.state) \(.name)"' 2>/dev/null || true)"
        [ -n "$checks" ] || continue
        # Exclude human-signoff at shell level (case-insensitive); stub bypasses jq
        checks="$(printf '%s\n' "$checks" | grep -viE 'human.?sign.?off' || true)"
        [ -n "$checks" ] || continue
        pending="$(printf '%s\n' "$checks" | grep -c '^PENDING ' 2>/dev/null || echo 0)"
        if [ "${pending:-0}" -gt 0 ]; then
            _bpgh_log "bpgh_pr_failing_checks: PR #$num has $pending pending check(s) — not settled, skipping"
            continue
        fi
        failing="$(printf '%s\n' "$checks" | sed -n 's/^FAILURE //p' | paste -sd, -)"
        [ -n "$failing" ] || continue
        printf '%s %s %s %s\n' "$num" "$branch" "$sha" "$failing"
    done <<INNER_EOF
$prs
INNER_EOF
    return 0
}
