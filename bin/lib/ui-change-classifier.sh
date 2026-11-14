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
# that edits one could write a clean gallery. Every PR-head file the VR job
# runs before the vr-gallery-json upload always holds: the workflow, the one
# local action the e2e job runs (setup-docker-e2e), the bin/ scripts they
# call, the specs the VR and manual-row configs match (their testMatch is
# unanchored, so any directory), the fixtures, helpers and auth setup inside
# the Playwright process, the package and lock files that `bun install` and
# `composer install` run, and the Docker files that start the containers.
# The other local actions (setup-php-env, notify-discord, setup-ssh,
# lighthouse-setup) never run in the VR job, so they stay off this list. A PR
# that wires another action into the VR job must edit e2e-tests.yml or
# setup-docker-e2e, and both hold. The app under test (PHP, SQL, CSS) is
# what the gallery measures, so it is out of scope here.
UI_PATH_RE+='|^\.github/workflows/e2e-tests\.yml$|^\.github/actions/setup-docker-e2e/|^bin/vr-build-gallery$'
UI_PATH_RE+='|^bin/vr-review-comment$|^bin/vr-changed-coverage$|^bin/playwright-image-tag$'
UI_PATH_RE+='|^ibl5/bin/run-migrations-ci$'
UI_PATH_RE+='|^ibl5/tests/e2e/vr-[^/]*\.ts$|^ibl5/playwright[^/]*\.config\.ts$'
UI_PATH_RE+='|^ibl5/tests/e2e/visual/'
UI_PATH_RE+='|^ibl5/tests/e2e/.*visual-regression\.spec\.ts$|^ibl5/tests/e2e/.*manual-rows\.spec\.ts$'
UI_PATH_RE+='|^ibl5/tests/e2e/(fixtures|helpers)/|^ibl5/tests/e2e/.*\.setup\.ts$'
UI_PATH_RE+='|^ibl5/package\.json$|^ibl5/bun\.lockb?$|^ibl5/bunfig\.toml$|^ibl5/\.npmrc$'
UI_PATH_RE+='|^ibl5/composer\.(json|lock)$|^ibl5/tsconfig\.json$'
UI_PATH_RE+='|^docker-compose\.ci\.yml$|^Dockerfile$|^docker/'
UI_EXCLUDE_RE='\.md$'
UI_GALLERY_JQ='[(.changedCells // [])[] | select(.strict != true)] + (.newCells // []) | length'
# Dependabot dev-dependency patch exemption (ADR-0062 Update 2026-11-13).
# ui_dev_patch_exempt compares ibl5/package.json and ibl5/bun.lock between the
# base checkout (UI_BASE_DIR) and the PR head checkout (UI_HEAD_DIR). One regex
# validates and splits a version: an optional ^ or ~, then three numbers.
UI_DEVPATCH_SEMVER='^(?<p>[~^]?)(?<a>0|[1-9][0-9]{0,8})[.](?<i>0|[1-9][0-9]{0,8})[.](?<c>0|[1-9][0-9]{0,8})$'
UI_DEVPATCH_JQ='
if ($b | length) != 1 or ($h | length) != 1 then error("not one JSON document") else . end
| $b[0] as $B | $h[0] as $H
| if ($B | type) != "object" or ($H | type) != "object" then error("not an object") else . end
| if ($B | del(.devDependencies)) != ($H | del(.devDependencies)) then error("a key outside devDependencies changed") else . end
| $B.devDependencies as $bd | $H.devDependencies as $hd
| if ($bd | type) != "object" or ($hd | type) != "object" then error("devDependencies is not an object") else . end
| if ($bd | keys) != ($hd | keys) then error("a package was added or removed") else . end
| [$hd | keys[] | select($bd[.] != $hd[.])] as $ch
| if ($ch | length) == 0 then error("no version changed") else . end
| $ch[] as $k | $bd[$k] as $o | $hd[$k] as $n
| if ($o | type) != "string" or ($n | type) != "string" then error("version is not a string: \($k)") else . end
| if ($o | test($re) | not) or ($n | test($re) | not) then error("unparseable version: \($k)") else . end
| ($o | capture($re)) as $x | ($n | capture($re)) as $y
| if $x.p == $y.p and $x.a == $y.a and $x.i == $y.i and ($y.c | tonumber) > ($x.c | tonumber) then $k else error("not a patch bump: \($k)") end
'
UI_PKG_DEP_RE='^    "[^"]+": "[^"]+",?$'
UI_LOCK_WS_RE='^        "([^"]+)": "([^"]+)",?$'
UI_LOCK_PKG_RE='^    "([@A-Za-z0-9._/-]+)": (\[.*\]),?$'
UI_LOCK_NAME_RE='^(@[A-Za-z0-9._-]+/)?[A-Za-z0-9._-]+@(0|[1-9][0-9]*)[.](0|[1-9][0-9]*)[.](0|[1-9][0-9]*)(-[0-9A-Za-z.-]+)?$'
UI_LOCK_ENTRY_JQ='
if type == "array" and length == 4
  and (.[0] | type == "string" and test($re))
  and .[1] == ""
  and (.[2] | type == "object")
  and ([.[2] | (.dependencies, .optionalDependencies, .peerDependencies) | select(. != null) | if type == "object" then .[] else error("dependency map is not an object") end | if type == "string" then . else error("specifier is not a string") end | select(test(":"))] | length == 0)
  and (.[3] | type == "string" and test("^sha512-[A-Za-z0-9+/]{86}==$"))
then .[0] | sub("@[^@]*$"; "") else error("lock entry outside the registry shape") end'

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

# _ui_regular_file <path>: rc 0 for a regular file that is not a symlink.
_ui_regular_file() { [ -f "$1" ] && [ ! -L "$1" ]; }

# _ui_toolchain <package>: rc 0 when the package runs inside the Playwright
# process that measures the VR gallery.
_ui_toolchain() {
    case "$1" in
        pixelmatch|pngjs|*playwright*) return 0 ;;
    esac
    return 1
}

# _ui_dev_patch_hits_only <hits>: rc 0 when every UI path hit is
# ibl5/package.json or ibl5/bun.lock.
_ui_dev_patch_hits_only() {
    local line
    while IFS= read -r line; do
        case "$line" in ibl5/package.json|ibl5/bun.lock) ;; *) return 1 ;; esac
    done <<< "$1"
}

# _ui_diff_changes <base_file> <head_file>: prints each changed line of a
# normal-format diff with its "< " or "> " prefix. rc 1 when diff fails or
# prints a line of any other kind.
_ui_diff_changes() {
    local out rc=0 line
    out=$(diff "$1" "$2") || rc=$?
    if [ "$rc" = 0 ]; then return 0; fi
    if [ "$rc" != 1 ]; then return 1; fi
    while IFS= read -r line; do
        case "$line" in
            '< '*|'> '*) printf '%s\n' "$line" ;;
            ---) ;;
            *) [[ $line =~ ^[0-9]+(,[0-9]+)?[acd][0-9]+(,[0-9]+)?$ ]] || return 1 ;;
        esac
    done <<< "$out"
}

# _ui_pkg_lines_ok <changes> <count>: rc 0 when the package.json diff holds
# exactly <count> removed and <count> added lines, each one "name": "version".
_ui_pkg_lines_ok() {
    local line adds=0 rems=0
    while IFS= read -r line; do
        [[ ${line:2} =~ $UI_PKG_DEP_RE ]] || return 1
        case "$line" in '<'*) rems=$((rems + 1)) ;; *) adds=$((adds + 1)) ;; esac
    done <<< "$1"
    [ "$adds" = "$2" ] && [ "$rems" = "$2" ]
}

# _ui_lock_entry_name <line>: prints "<key> <name>" when <line> is one registry
# entry of bun.lock: four fields, a plain name@version, the default registry
# (empty second field), no ":" specifier among its dependency maps, and a
# sha512 integrity. rc 1 for any other line.
_ui_lock_entry_name() {
    local key entry name
    [[ $1 =~ $UI_LOCK_PKG_RE ]] || return 1
    key="${BASH_REMATCH[1]}"
    entry="${BASH_REMATCH[2]}"
    name=$(jq -er --arg re "$UI_LOCK_NAME_RE" "$UI_LOCK_ENTRY_JQ" <<< "$entry" 2>/dev/null) || return 1
    printf '%s %s\n' "$key" "$name"
}

# _ui_lock_ok <base_root> <head_root>: rc 0 when every changed line of
# ibl5/bun.lock is a registry entry for a package outside the gallery
# toolchain, or a workspace devDependencies line that matches its own side's
# ibl5/package.json. rc 1 for any other change.
_ui_lock_ok() {
    local bl="$1/ibl5/bun.lock" hl="$2/ibl5/bun.lock" changes line body side key spec kn name
    { _ui_regular_file "$bl" && _ui_regular_file "$hl"; } || return 1
    changes=$(_ui_diff_changes "$bl" "$hl") || return 1
    [ -n "$changes" ] || return 0
    while IFS= read -r line; do
        body="${line:2}"
        case "$line" in '<'*) side="$1" ;; *) side="$2" ;; esac
        if [[ $body =~ $UI_LOCK_WS_RE ]]; then
            key="${BASH_REMATCH[1]}"
            spec="${BASH_REMATCH[2]}"
            if _ui_toolchain "$key"; then return 1; fi
            jq -e --arg k "$key" --arg v "$spec" '.devDependencies[$k] == $v' "$side/ibl5/package.json" >/dev/null 2>&1 || return 1
        elif kn=$(_ui_lock_entry_name "$body"); then
            read -r key name <<< "$kn"
            { [ "$key" = "$name" ] || [ "${key%/"$name"}" != "$key" ]; } || return 1
            if _ui_toolchain "$key" || _ui_toolchain "$name"; then return 1; fi
        else return 1  # any other lock line holds
        fi
    done <<< "$changes"
}

# ui_dev_patch_exempt <paths_file> <pr_author>: rc 0 when the PR is a Dependabot
# patch bump of ibl5 devDependencies that may skip the ui-paths verdict.
# Env: UI_BASE_DIR (trusted base checkout root), UI_HEAD_DIR (PR head root).
# The head tree is data only. rc 1 on every other input and on any failure.
ui_dev_patch_exempt() {
    local paths="$1" author="$2" base="${UI_BASE_DIR:-}" head="${UI_HEAD_DIR:-}"
    local line has_pkg=0 names name n=0 changes
    [ "$author" = 'dependabot[bot]' ] || return 1
    { [ -n "$base" ] && [ -n "$head" ] && [ -d "$base" ] && [ -d "$head" ]; } || return 1
    [ -f "$paths" ] || return 1
    while IFS= read -r line; do
        case "$line" in
            '') ;;
            ibl5/package.json) has_pkg=1 ;;
            ibl5/bun.lock) ;;
            *) return 1 ;;  # any other changed file holds
        esac
    done < "$paths"
    [ "$has_pkg" = 1 ] || return 1
    { _ui_regular_file "$base/ibl5/package.json" && _ui_regular_file "$head/ibl5/package.json"; } || return 1
    names=$(jq -nr --arg re "$UI_DEVPATCH_SEMVER" --slurpfile b "$base/ibl5/package.json" \
        --slurpfile h "$head/ibl5/package.json" "$UI_DEVPATCH_JQ" 2>/dev/null) || return 1
    [ -n "$names" ] || return 1
    while IFS= read -r name; do
        if _ui_toolchain "$name"; then return 1; fi
        n=$((n + 1))
    done <<< "$names"
    changes=$(_ui_diff_changes "$base/ibl5/package.json" "$head/ibl5/package.json") || return 1
    _ui_pkg_lines_ok "$changes" "$n" || return 1
    _ui_lock_ok "$base" "$head"
}

# ui_decide <paths_file> <renderable 0|1> <gallery_json or ""> [dev_patch 0|1]: prints the verdict.
# Verdicts: ui-unknown ui-paths vr-missing ui-vr (rc 1, hold) | not-ui (rc 0).
# dev_patch=1 comes from ui_dev_patch_exempt. It skips the ui-paths verdict
# only when every UI path is ibl5/package.json or ibl5/bun.lock. The
# renderable and gallery checks below still run.
ui_decide() {
    local paths="$1" renderable="$2" gallery="$3" dev_patch="${4:-0}" rc=0 n hits
    hits=$(ui_paths_touch_ui < "$paths") || rc=$?
    if [ "$rc" = 2 ]; then echo ui-unknown; return 1; fi
    if [ "$rc" = 0 ] && ! { [ "$dev_patch" = 1 ] && _ui_dev_patch_hits_only "$hits"; }; then echo ui-paths; return 1; fi
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

# ui_approving_event <action> <event_label>: rc 0 for the `labeled human-approved` event.
ui_approving_event() {
    [ "${1:-}" = labeled ] && [ "${2:-}" = human-approved ]
}

# ui_workflow_verdict <paths_file> <pr_author> <fetch_fn> [action] [event_label]
# Prints the verdict. Waits for the VR gallery only when no path is UI and VR
# is expected to run. The approving `labeled human-approved` run never waits:
# the label clears the hold whatever the gallery shows, and a sync run gives up
# on the approval record after HS_AWAIT_ATTEMPTS x HS_AWAIT_SLEEP. That run
# decides with no gallery, so it holds as vr-missing and records its
# fingerprint at once. Every other event waits as before.
# rc follows ui_decide (0 not-ui, 1 hold).
# A Dependabot dev-dependency patch bump (ui_dev_patch_exempt) waits on the gallery like a non-UI PR.
ui_workflow_verdict() {
    local paths="$1" author="$2" fetch="$3" renderable rc=0 gallery="" tmp dev_patch=0
    renderable=$(ui_renderable "$paths" "$author")
    ui_paths_touch_ui < "$paths" >/dev/null || rc=$?
    if [ "$rc" = 0 ] && ui_dev_patch_exempt "$paths" "$author"; then dev_patch=1; fi
    if { [ "$rc" = 1 ] || [ "$dev_patch" = 1 ]; } && [ "$renderable" = 1 ] && ! ui_approving_event "${4:-}" "${5:-}"; then
        tmp=$(mktemp) || tmp=""
        if [ -n "$tmp" ] && ui_await_gallery "${UI_VR_ATTEMPTS:-90}" "${UI_VR_SLEEP:-30}" "$fetch" "$tmp"; then
            gallery="$tmp"
        fi
    fi
    ui_decide "$paths" "$renderable" "$gallery" "$dev_patch"
}

# ui_fetch_pr_gh — the production fetch for ui_live_pr_state. Prints the live
# REST pulls/N object. Env: GH_REPO, PR.
ui_fetch_pr_gh() {
    gh api "repos/$GH_REPO/pulls/$PR"
}

# ui_live_pr_state <expected_head_sha> <fetch_fn>
# The gallery wait can last UI_VR_ATTEMPTS x UI_VR_SLEEP seconds, and the event
# payload's labels and head SHA are frozen at trigger time. This re-reads the
# PR after the wait. It prints the live labels as a comma list.
#   rc 0 the head is still <expected_head_sha>
#   rc 3 the head moved: the run must post no verdict for the old head
#   rc 1 the read or parse failed, so the caller must hold
ui_live_pr_state() {
    local want="$1" fetch="$2" json head labels
    json=$("$fetch") || return 1
    head=$(jq -er '.head.sha | select(type == "string" and test("^[0-9a-f]{40}$"))' <<< "$json" 2>/dev/null) || return 1
    labels=$(jq -er '.labels | select(type == "array") | map(.name | strings) | join(",")' <<< "$json" 2>/dev/null) || return 1
    [ "$head" = "$want" ] || return 3
    printf '%s\n' "$labels"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    case "${1:-}" in
        --paths) ui_paths_touch_ui; exit $? ;;
        *) echo "usage: ui-change-classifier.sh --paths < paths" >&2; exit 64 ;;
    esac
fi
