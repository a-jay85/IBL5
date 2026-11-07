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
# The gallery producers run from the PR's own code on pull_request, so a PR
# that edits one could write a clean gallery. Those paths always hold: the
# workflow and its local actions, the gallery builder, the VR spec, and the
# fixtures, helpers and auth setup that run inside the VR Playwright process.
UI_PATH_RE+='|^\.github/workflows/e2e-tests\.yml$|^\.github/actions/|^bin/vr-build-gallery$'
UI_PATH_RE+='|^ibl5/tests/e2e/vr-[^/]*\.ts$|^ibl5/playwright[^/]*\.config\.ts$'
UI_PATH_RE+='|^ibl5/tests/e2e/smoke/visual-regression\.spec\.ts$'
UI_PATH_RE+='|^ibl5/tests/e2e/(fixtures|helpers)/|^ibl5/tests/e2e/[^/]*\.setup\.ts$'
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

# _ui_e2e_runs: prints "<run_id> <status>" per trusted E2E run, newest first.
# rc 1 on a gh error or a missing env value.
# Env: GH_REPO, HEAD_SHA, PR, HEAD_REF, HEAD_REPO.
# The gallery is trusted only from this PR's own E2E run on its exact head
# commit. The run API decides that, never the artifact name. The run must be
# of .github/workflows/e2e-tests.yml, from a pull_request event, on HEAD_SHA,
# for this PR. The PR matches by number in pull_requests[], or by head branch
# and head repo, because the API leaves pull_requests[] empty for a fork PR.
# A same-tree re-push gets no reuse. An E2E memo hit uploads nothing for the
# new SHA, so that PR holds as vr-missing. A PR that edits a gallery producer
# holds on its paths (UI_PATH_RE).
_ui_e2e_runs() {
    local json
    case "${HEAD_SHA:-}" in ''|*[!0-9a-f]*) return 1 ;; esac
    case "${PR:-}" in ''|*[!0-9]*) return 1 ;; esac
    json=$(gh api "repos/$GH_REPO/actions/workflows/e2e-tests.yml/runs?head_sha=$HEAD_SHA&event=pull_request&per_page=50") || return 1
    jq -r --arg sha "$HEAD_SHA" --argjson pr "$PR" \
        --arg ref "${HEAD_REF:-}" --arg repo "${HEAD_REPO:-}" '
        [.workflow_runs[]?
         | select(.path == ".github/workflows/e2e-tests.yml"
                  and .event == "pull_request" and .head_sha == $sha)
         | select(any(.pull_requests[]?; .number == $pr)
                  or ($ref != "" and $repo != "" and .head_branch == $ref
                      and .head_repository.full_name == $repo))]
        | sort_by(.created_at) | reverse | .[] | "\(.id) \(.status)"' <<< "$json" || return 1
}

# _ui_artifact_id <run_id>: prints the newest unexpired gallery artifact id that
# this run uploaded for HEAD_SHA, or nothing. rc 1 on a gh error.
_ui_artifact_id() {
    local json
    json=$(gh api "repos/$GH_REPO/actions/runs/$1/artifacts?per_page=100") || return 1
    jq -r --arg pre "$UI_GALLERY_ARTIFACT_PREFIX" --arg sha "$HEAD_SHA" --argjson rid "$1" '
        [.artifacts[]?
         | select(.expired == false and (.name | startswith($pre))
                  and .workflow_run.id == $rid and .workflow_run.head_sha == $sha)]
        | sort_by(.created_at) | last | .id // empty' <<< "$json" || return 1
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
# Env: see _ui_e2e_runs.
#   rc 0 gallery written, 3 every trusted E2E run completed with no artifact,
#   1 pending (also no run yet, and every gh error, so the bounded wait holds).
# The run list is read before each run's artifacts, so a run listed as
# completed has already uploaded any gallery it will upload.
ui_fetch_gallery_gh() {
    local out="$1" runs rid status id finished=1
    runs=$(_ui_e2e_runs) || return 1
    [ -n "$runs" ] || return 1
    while read -r rid status; do
        case "$rid" in ''|*[!0-9]*) return 1 ;; esac
        id=$(_ui_artifact_id "$rid") || return 1
        if [ -n "$id" ]; then
            _ui_download_gallery "$id" "$out" && return 0
            return 1
        fi
        [ "$status" = completed ] || finished=0
    done <<< "$runs"
    [ "$finished" = 1 ] && return 3
    return 1
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
