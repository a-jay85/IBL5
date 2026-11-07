# shellcheck shell=bash
#
# bin/lib/ui-change-classifier.sh — single source of truth for "does this PR
# change the UI?". The human-signoff workflow sources it, and post-plan arming
# condition (8) runs its `--paths` CLI by subprocess. It holds no title, plan,
# or label logic.
#
# A PR is UI when a changed path matches UI_PATH_RE (minus UI_EXCLUDE_RE), or
# when its VR gallery shows a gating changed or new cell. When it cannot tell,
# the verdict holds.
#
# This file is SOURCED, not executed: no `set -euo pipefail` at file scope.
# It is sourced INTO a `set -euo pipefail` workflow step, so every function
# must be strict-mode safe.
#
# CLI: bin/lib/ui-change-classifier.sh --paths < paths
#   rc 0 UI (prints the matching paths), 1 not UI, 2 empty input.

UI_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
UI_GALLERY_ARTIFACT_PREFIX='vr-gallery-json-'
UI_PATH_RE='^ibl5/(design|themes|jslib|images|error-pages|blocks|includes|language|modules)/'
UI_PATH_RE+='|^ibl5/classes/(UI|PageLayout|Navigation)/'
UI_PATH_RE+='|^ibl5/classes/.+(View|Renderer|Formatter)\.php$'
UI_PATH_RE+='|^ibl5/(index|modules|block|demo-login)\.php$|^ibl5/favicon\.ico$'
UI_PATH_RE+='|-snapshots/'
UI_EXCLUDE_RE='\.md$'
UI_GALLERY_JQ='[(.changedCells // [])[] | select(.strict != true)] + (.newCells // []) | length'

# ui_paths_touch_ui: stdin one path per line. rc 0 UI (prints hits), 1 not UI, 2 empty input.
ui_paths_touch_ui() {
    local all hits
    all=$(grep -v '^[[:space:]]*$' || true)
    [ -n "$all" ] || return 2
    hits=$(printf '%s\n' "$all" | grep -vE "$UI_EXCLUDE_RE" | grep -E "$UI_PATH_RE" || true)
    [ -n "$hits" ] || return 1
    printf '%s\n' "$hits"
}

# ui_renderable <paths_file> <pr_author>: prints 1 when VR is expected to run, else 0.
# Mirrors the e2e-tests.yml `changes` job: bin/website-affecting on stdin, and
# Dependabot PRs always render.
ui_renderable() {
    if [ "$2" = 'dependabot[bot]' ]; then echo 1; return 0; fi
    local rc=0
    "$UI_LIB_DIR/../website-affecting" < "$1" >/dev/null 2>&1 || rc=$?
    if [ "$rc" = 1 ]; then echo 0; else echo 1; fi
}

# ui_gallery_count <gallery_json>: prints the gating cell count; rc 2 when malformed or nothing rendered.
ui_gallery_count() {
    local rendered
    rendered=$(jq -r '.renderedCells // 0' "$1" 2>/dev/null) || return 2
    [ "$rendered" -ge 1 ] 2>/dev/null || return 2
    jq -r "$UI_GALLERY_JQ" "$1" 2>/dev/null || return 2
}

# ui_decide <paths_file> <renderable 0|1> <gallery_json or "">: prints the verdict.
# Verdicts: ui-unknown ui-paths vr-missing ui-vr (rc 1, hold) | not-ui (rc 0).
ui_decide() {
    local paths="$1" renderable="$2" gallery="$3" rc=0 n
    ui_paths_touch_ui < "$paths" >/dev/null || rc=$?
    if [ "$rc" = 2 ]; then echo ui-unknown; return 1; fi
    if [ "$rc" = 0 ]; then echo ui-paths; return 1; fi
    if [ "$renderable" != 1 ]; then echo not-ui; return 0; fi
    if [ -z "$gallery" ] || [ ! -s "$gallery" ]; then echo vr-missing; return 1; fi
    n=$(ui_gallery_count "$gallery") || { echo vr-missing; return 1; }
    if [ "$n" -gt 0 ]; then echo ui-vr; return 1; fi
    echo "not-ui"
}

# ui_await_gallery <attempts> <sleep> <fetch_fn> <out_file>
# fetch_fn rc: 0 gallery written to out_file, 3 E2E run completed with no artifact, other = pending.
ui_await_gallery() {
    local attempts="$1" pause="$2" fetch="$3" out="$4" i=1 rc
    while [ "$i" -le "$attempts" ]; do
        rc=0; "$fetch" "$out" || rc=$?
        case "$rc" in
            0) return 0 ;;
            3) return 1 ;;
        esac
        i=$((i + 1))
        if [ "$i" -le "$attempts" ]; then sleep "$pause"; fi
    done
    return 1
}

# _ui_artifact_id: prints the newest unexpired gallery artifact id for UI_TREE,
# or nothing. rc 1 on a gh error.
_ui_artifact_id() {
    gh api "repos/$GH_REPO/actions/artifacts?name=${UI_GALLERY_ARTIFACT_PREFIX}$UI_TREE" \
        --jq '[.artifacts[] | select(.expired == false)] | sort_by(.created_at) | last | .id // empty'
}

# _ui_download_gallery <artifact_id> <out_file>: rc 0 when vr-gallery.json was
# extracted into out_file and is non-empty.
_ui_download_gallery() {
    local zip
    zip=$(mktemp) || return 1
    if ! gh api "repos/$GH_REPO/actions/artifacts/$1/zip" > "$zip"; then
        rm -f "$zip"; return 1
    fi
    if ! unzip -p "$zip" vr-gallery.json > "$2"; then
        rm -f "$zip"; return 1
    fi
    rm -f "$zip"
    [ -s "$2" ] || return 1
}

# ui_fetch_gallery_gh <out_file> — the production fetch for ui_await_gallery.
# Env: GH_REPO, UI_TREE, HEAD_SHA.
#   rc 0 gallery written, 3 every same-SHA E2E run completed with no artifact,
#   1 pending (also every gh error, so the bounded wait ends in a hold).
ui_fetch_gallery_gh() {
    local out="$1" id runs
    [ -n "${UI_TREE:-}" ] || return 1
    id=$(_ui_artifact_id) || return 1
    if [ -n "$id" ]; then
        _ui_download_gallery "$id" "$out" && return 0
        return 1
    fi
    runs=$(gh run list --repo "$GH_REPO" --workflow e2e-tests.yml --commit "$HEAD_SHA" \
        --event pull_request --json status \
        --jq 'if length > 0 and all(.[]; .status == "completed") then "finished" else "pending" end') || return 1
    [ "$runs" = finished ] || return 1
    id=$(_ui_artifact_id) || return 1
    if [ -n "$id" ]; then
        _ui_download_gallery "$id" "$out" && return 0
        return 1
    fi
    return 3
}

# ui_workflow_verdict <paths_file> <pr_author> <fetch_fn> — prints the verdict.
# Waits for the VR gallery only when no path is UI and VR is expected to run.
# rc follows ui_decide (0 not-ui, 1 hold).
ui_workflow_verdict() {
    local paths="$1" author="$2" fetch="$3" renderable rc=0 gallery="" tmp
    renderable=$(ui_renderable "$paths" "$author")
    ui_paths_touch_ui < "$paths" >/dev/null || rc=$?
    if [ "$rc" = 1 ] && [ "$renderable" = 1 ]; then
        tmp=$(mktemp) || tmp=""
        if [ -n "$tmp" ] && ui_await_gallery "${UI_VR_ATTEMPTS:-90}" "${UI_VR_SLEEP:-30}" "$fetch" "$tmp"; then
            gallery="$tmp"
        fi
    fi
    ui_decide "$paths" "$renderable" "$gallery"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    case "${1:-}" in
        --paths) ui_paths_touch_ui; exit $? ;;
        *) echo "usage: ui-change-classifier.sh --paths < paths" >&2; exit 64 ;;
    esac
fi
