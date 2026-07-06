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

    local body
    body="$(printf 'Class: %s\nSeverity: %s\nReporter (discord_author_id): %s\nReport id: %s\nThread: %s\n\n---\n%s\n' \
        "$class" "$sev" "$author" "$report_id" "$thread_id" "$original_text")"

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
