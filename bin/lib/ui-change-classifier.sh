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
#
# One carve-out: ui_composer_exempt lets a Dependabot composer patch or minor
# bump skip the path hold. For a library, composer install runs no package
# code, so the gallery stays trustworthy. composer-* packages (plugins and
# installers), root composer.json scripts, and any other path still hold, and
# that PR still waits for and is judged by its VR gallery.
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
# _UI_COMPOSER_LOCK_JQ: $b and $h are the slurped base and head composer.lock,
# $jc is 1 when composer.json is in the diff. true only when every changed
# locked package is a strict patch or minor upgrade (0.x keeps its minor too).
# A two-part tag such as 0.2 reads as 0.2.0; PR #3848 bumped one from 0.2.
_UI_COMPOSER_LOCK_JQ='
def ver: if type == "string" and test("^v?[0-9]+\\.[0-9]+\\.[0-9]+$")
  then ltrimstr("v") | split(".") | map(tonumber)
  elif type == "string" and test("^v?[0-9]+\\.[0-9]+$")
  then ltrimstr("v") | split(".") | map(tonumber) | . + [0]
  else null end;
def bumpok($bv; $hv): $bv != null and $hv != null and $hv > $bv
  and $hv[0] == $bv[0] and ($bv[0] > 0 or $hv[1] == $bv[1]);
def host: if . == null then null elif type == "string"
  then ((capture("^https://(?<h>[^/@]+)/") | .h) // false) else false end;
def samehost($x; $y): ($x | host) as $p | ($y | host) as $q | $p == $q and $p != false;
def entryok($e; $f): ($e | type) == "object" and ($f | type) == "object"
  and $e.type == $f.type
  and (($f.type // "library") | (type == "string" and (startswith("composer-") | not)))
  and $e.source.type == $f.source.type and $e.dist.type == $f.dist.type
  and samehost($e.source.url; $f.source.url) and samehost($e.dist.url; $f.dist.url)
  and bumpok($e.version | ver; $f.version | ver);
def names($a): [$a[] | .name];
def sectok($bs; $hs): ($bs | type) == "array" and ($hs | type) == "array"
  and (names($bs) + names($hs) | all(type == "string"))
  and (names($bs) | sort) == (names($bs) | unique)
  and (names($bs) | sort) == (names($hs) | sort);
def changed($bs; $hs): ($bs | map({key: .name, value: .}) | from_entries) as $m
  | [$hs[] | select(. != $m[.name]) | {b: $m[.name], h: .}];
if ($b | length) != 1 or ($h | length) != 1 then false else
  $b[0] as $b0 | $h[0] as $h0
  | if ($b0 | type) != "object" or ($h0 | type) != "object" then false else
      ($b0 | del(.packages, ."packages-dev", ."content-hash"))
        == ($h0 | del(.packages, ."packages-dev", ."content-hash"))
      and ($jc == 1 or $b0["content-hash"] == $h0["content-hash"])
      and sectok($b0.packages; $h0.packages)
      and sectok($b0["packages-dev"]; $h0["packages-dev"])
      and ((names($h0.packages) + names($h0["packages-dev"])) | sort == unique)
      and ((changed($b0.packages; $h0.packages)
            + changed($b0["packages-dev"]; $h0["packages-dev"])) as $ch
           | ($ch | length) >= 1 and ($ch | all(entryok(.b; .h))))
    end
  end'
# _UI_COMPOSER_JSON_JQ: $b and $h are the slurped base and head composer.json.
# true only when nothing outside require/require-dev changed, platform keys
# (no "/" in the name: php, ext-*, lib-*) are equal, and each changed package
# constraint is ^a.b.c, ^a.b, ~a.b.c or a.b.c moving strictly up in the same major.
_UI_COMPOSER_JSON_JQ='
def cons: if type == "string"
  then ((capture("^(?<op>[\\^~]?)(?<v>[0-9]+\\.[0-9]+(\\.[0-9]+)?)$")) // null) else null end;
def nparts: .v | split(".") | length;
def parts: .v | split(".") | map(tonumber) | (. + [0, 0, 0])[0:3];
def consok($x; $y): ($x | cons) as $p | ($y | cons) as $q
  | if $p == null or $q == null or $p.op != $q.op then false
    elif $p.op != "^" and (($p | nparts) != 3 or ($q | nparts) != 3) then false
    else ($p | parts) as $bp | ($q | parts) as $hp
      | $hp > $bp and $hp[0] == $bp[0] and ($bp[0] > 0 or $hp[1] == $bp[1])
    end;
def sect($o): if $o == null then {} elif ($o | type) == "object" then $o else error("section") end;
def sectok($x; $y): sect($x) as $p | sect($y) as $q
  | ($p | keys) == ($q | keys)
    and ($p | to_entries | all(.key as $k | .value == $q[$k]
         or (($k | contains("/")) and consok(.value; $q[$k]))));
if ($b | length) != 1 or ($h | length) != 1 then false else
  $b[0] as $b0 | $h[0] as $h0
  | if ($b0 | type) != "object" or ($h0 | type) != "object" then false else
      ($b0 | del(.require, ."require-dev")) == ($h0 | del(.require, ."require-dev"))
      and sectok($b0.require; $h0.require)
      and sectok($b0["require-dev"]; $h0["require-dev"])
    end
  end'
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
# A specifier passes only when it is a semver range: comparators, versions, ||, and hyphen ranges.
UI_LOCK_SPEC_RE='^ *([|][|]|-|[~^<>=]* *v?(0|[1-9][0-9]*|[*xX])([.](0|[1-9][0-9]*|[*xX]))*(-[0-9A-Za-z.-]+)?)( +([|][|]|-|[~^<>=]* *v?(0|[1-9][0-9]*|[*xX])([.](0|[1-9][0-9]*|[*xX]))*(-[0-9A-Za-z.-]+)?))* *$'
UI_LOCK_ENTRY_JQ='
if type == "array" and length == 4
  and (.[0] | type == "string" and test($re))
  and .[1] == ""
  and (.[2] | type == "object")
  and ([.[2] | (.dependencies, .optionalDependencies, .peerDependencies) | select(. != null) | if type == "object" then .[] else error("dependency map is not an object") end | if type == "string" then . else error("specifier is not a string") end | select(test($spec) | not)] | length == 0)
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
# (empty second field), only semver-range specifiers in its dependency maps, and a
# sha512 integrity. rc 1 for any other line.
_ui_lock_entry_name() {
    local key entry name
    [[ $1 =~ $UI_LOCK_PKG_RE ]] || return 1
    key="${BASH_REMATCH[1]}"
    entry="${BASH_REMATCH[2]}"
    name=$(jq -er --arg re "$UI_LOCK_NAME_RE" --arg spec "$UI_LOCK_SPEC_RE" "$UI_LOCK_ENTRY_JQ" <<< "$entry" 2>/dev/null) || return 1
    printf '%s %s\n' "$key" "$name"
}

# _ui_lock_key_names <key> <name>: rc 0 when the lock key names its own package:
# the key equals the name, or the key is a nested path whose parent part ends in
# a whole package name. The parent part must be a chain of whole package
# names, so key "@evil/bar" (a bare scope) or "x@evil/bar" cannot pass for
# package "bar".
UI_LOCK_PARENT_RE='^((@[A-Za-z0-9._-]+/)?[A-Za-z0-9._-]+/)*(@[A-Za-z0-9._-]+/)?[A-Za-z0-9._-]+$'
_ui_lock_key_names() {
    local key="$1" name="$2" parent
    [ "$key" = "$name" ] && return 0
    [ "${key%/"$name"}" != "$key" ] || return 1
    parent="${key%/"$name"}"
    [[ $parent =~ $UI_LOCK_PARENT_RE ]] || return 1
    case "${parent##*/}" in @*) return 1 ;; esac
    return 0
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
            _ui_lock_key_names "$key" "$name" || return 1
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

# _ui_composer_regular <file>: rc 0 for a readable regular file that is no
# symlink and whose parent directory is no symlink.
_ui_composer_regular() { [ -f "$1" ] && [ ! -L "$1" ] && [ ! -L "${1%/*}" ] && [ -r "$1" ]; }

# _ui_composer_hits_only <paths_file>: rc 0 when every UI_PATH_RE hit is
# ibl5/composer.json or ibl5/composer.lock.
_ui_composer_hits_only() {
    local hits
    hits=$(ui_paths_touch_ui < "$1") || return 1
    ! grep -qvE '^ibl5/composer\.(json|lock)$' <<< "$hits"
}

# ui_composer_exempt <paths_file> <pr_author>: rc 0 when a Dependabot PR changes
# only ibl5/composer.lock (and maybe ibl5/composer.json) and every changed
# locked package is a patch or minor upgrade. A grouped PR is judged per
# member, so one major member holds the group. Env: UI_BASE_DIR (the trusted
# base checkout) and UI_HEAD_DIR (the PR head, read as data only). Every
# missing input, parse error, or unexpected shape returns 1, so the PR holds.
ui_composer_exempt() {
    local paths="$1" author="$2" base="${UI_BASE_DIR:-}" head="${UI_HEAD_DIR:-}" all p jc=0
    [ "$author" = 'dependabot[bot]' ] || return 1 # composer author gate
    if [ -z "$base" ] || [ -z "$head" ] || [ ! -d "$base" ] || [ ! -d "$head" ]; then return 1; fi
    command -v jq >/dev/null 2>&1 || return 1
    all=$(grep -v '^[[:space:]]*$' < "$paths" | sort -u) || return 1
    while IFS= read -r p; do
        case "$p" in
            ibl5/composer.lock) ;;
            ibl5/composer.json) jc=1 ;;
            *) return 1 ;; # any other path holds
        esac
    done <<< "$all"
    grep -qxF 'ibl5/composer.lock' <<< "$all" || return 1
    for p in ibl5/composer.lock ibl5/composer.json; do
        _ui_composer_regular "$base/$p" || return 1
        _ui_composer_regular "$head/$p" || return 1
    done
    if [ "$jc" = 0 ]; then
        cmp -s "$base/ibl5/composer.json" "$head/ibl5/composer.json" || return 1
    else
        jq -en --slurpfile b "$base/ibl5/composer.json" --slurpfile h "$head/ibl5/composer.json" \
            "$_UI_COMPOSER_JSON_JQ" >/dev/null 2>&1 || return 1
    fi
    jq -en --slurpfile b "$base/ibl5/composer.lock" --slurpfile h "$head/ibl5/composer.lock" \
        --argjson jc "$jc" "$_UI_COMPOSER_LOCK_JQ" >/dev/null 2>&1
}

# ui_decide <paths_file> <renderable 0|1> <gallery_json or ""> [dev_patch 0|1] [composer 0|1]: prints the verdict.
# Verdicts: ui-unknown ui-paths vr-missing ui-vr (rc 1, hold) | not-ui (rc 0).
# dev_patch=1 comes from ui_dev_patch_exempt. It skips the ui-paths verdict
# only when every UI path is ibl5/package.json or ibl5/bun.lock. The
# renderable and gallery checks below still run.
# composer=1 (from ui_composer_exempt) skips the ui-paths hold only when every
# UI path hit is a composer file; the renderable and gallery checks still run.
ui_decide() {
    local paths="$1" renderable="$2" gallery="$3" dev_patch="${4:-0}" rc=0 n hits
    hits=$(ui_paths_touch_ui < "$paths") || rc=$?
    if [ "$rc" = 2 ]; then echo ui-unknown; return 1; fi
    if [ "$rc" = 0 ] && [ "${5:-0}" = 1 ] && _ui_composer_hits_only "$paths"; then rc=1; fi
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
# new SHA. `_ui_memo_fallback` then reuses the gallery of an earlier same-PR
# run, but only when no file changed since that run matches `UI_PATH_RE` or the
# base-ref `.github/ci-memo/e2e.paths` list. Any doubt keeps `vr-missing`. A PR
# that edits a gallery producer holds on its paths (UI_PATH_RE).
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

# _ui_artifact_id <run_id> [sha]: prints the newest unexpired gallery artifact id
# that this run uploaded for <sha> (default HEAD_SHA), or nothing. rc 1 on a gh error.
_ui_artifact_id() {
    local want_sha="${2:-$HEAD_SHA}" json
    json=$(gh api "repos/$GH_REPO/actions/runs/$1/artifacts?per_page=100") || return 1
    jq -r --arg pre "$UI_GALLERY_ARTIFACT_PREFIX" --arg sha "$want_sha" --argjson rid "$1" '
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

_ui_memo_log() { printf 'UI memo fallback: %s\n' "$*" >&2; }

# Path match for one e2e.paths entry. rc 0 match, 1 no match, 2 unknown magic.
_ui_memo_pat_match() {
    case "$3" in
        ,glob,)
            # shellcheck disable=SC2053
            [[ $1 == $2 ]]; return ;;
        ,|,,|,top,|,literal,) ;;
        *) return 2 ;;
    esac
    [ "$1" = "$2" ] || [[ $1 == "$2"/* ]]
}

# rc 0 when the base-ref e2e.paths list covers <path> (any include, no exclude).
# Unknown magic counts as an include match and never as an exclude match.
_ui_memo_covered() {
    local path="$1" line magic pat m rc inc=0 exc=0
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in ''|'#'*) continue ;; esac
        magic=""; pat="$line"
        case "$line" in
            ':('*')'*) magic="${line#:(}"; magic="${magic%%)*}"; pat="${line#*)}" ;;
            ':!'*|':^'*) magic="exclude"; pat="${line#:?}" ;;
        esac
        m=",$magic,"; m="${m//,exclude,/,}"
        rc=0; _ui_memo_pat_match "$path" "$pat" "$m" || rc=$?
        case ",$magic," in
            *,exclude,*) if [ "$rc" = 0 ]; then exc=1; fi ;;
            *) if [ "$rc" != 1 ]; then inc=1; fi ;;
        esac
    done <"$UI_MEMO_PATHS_FILE"
    [ "$inc" = 1 ] && [ "$exc" = 0 ]
}

# rc 0 when the run's jobs look like a memo hit. This is a trigger, not proof.
_ui_memo_hit_shape() {
    local jobs
    jobs=$(gh api "repos/$GH_REPO/actions/runs/$1/jobs?per_page=100" 2>/dev/null) || { _ui_memo_log "jobs API unreadable for run $1"; return 1; }
    jq -e '[.jobs[]
        | select((.name == "E2E Memo Check" and .conclusion == "success")
              or (.name == "Visual Regression" and .conclusion == "skipped")
              or (.name == "E2E Tests" and .conclusion == "success"))
        | .name] | unique | length == 3' >/dev/null <<<"$jobs"
}

# Same-PR source candidates: "<id> <head_sha>", newest first, at most 5.
# The same-PR rule mirrors _ui_e2e_runs; keep the two in sync.
_ui_memo_source_runs() {
    gh api "repos/$GH_REPO/actions/workflows/e2e-tests.yml/runs?event=pull_request&branch=$HEAD_REF&per_page=50" 2>/dev/null \
        | jq -r --arg h "$HEAD_SHA" --arg wf ".github/workflows/e2e-tests.yml" \
            --argjson mpr "$PR" --arg mref "$HEAD_REF" --arg mrepo "$HEAD_REPO" '
        [.workflow_runs[]
          | select(.path == $wf and .event == "pull_request")
          | select(.status == "completed" and .conclusion == "success")
          | select(.head_sha != $h)
          | select((((.pull_requests // []) | map(.number) | index($mpr)) != null)
                   or (.head_branch == $mref and .head_repository.full_name == $mrepo))]
        | sort_by(.created_at, .id) | reverse | .[:5][] | "\(.id) \(.head_sha)"'
}

# rc 0 when every file changed between <src_sha> and HEAD_SHA is outside both
# UI_PATH_RE and the base-ref memo coverage list.
_ui_memo_neutral_diff() {
    local src="$1" cmp f
    cmp=$(gh api "repos/$GH_REPO/compare/$src...$HEAD_SHA" 2>/dev/null) || { _ui_memo_log "compare API unreadable for $src"; return 1; }
    jq -e '.status == "ahead" or .status == "identical"' >/dev/null <<<"$cmp" || { _ui_memo_log "compare status $(jq -r .status <<<"$cmp") for $src"; return 1; }
    jq -e '((.files // []) | length) < 300' >/dev/null <<<"$cmp" || { _ui_memo_log "compare file list may be truncated for $src"; return 1; }
    while IFS= read -r f; do
        [ -n "$f" ] || continue
        if command grep -Eq -- "$UI_PATH_RE" <<<"$f"; then _ui_memo_log "UI path changed since $src: $f"; return 1; fi
        if _ui_memo_covered "$f"; then _ui_memo_log "memo-covered path changed since $src: $f"; return 1; fi
    done <<<"$(jq -r '(.files // [])[] | .filename, (.previous_filename // empty)' <<<"$cmp")"
    return 0
}

# Called only when every trusted head run completed with no gallery artifact.
# UI_PR_PATHS is the PR path list file that ui_workflow_verdict scopes.
_ui_memo_fallback() {
    local out="$1" n_inc head_id cands src_id src_sha art p
    [ -n "${UI_MEMO_PATHS_FILE:-}" ] && [ -r "$UI_MEMO_PATHS_FILE" ] || { _ui_memo_log "no base-ref memo paths file"; return 1; }
    n_inc=$(command grep -cvE '^[[:space:]]*(#|$)|^:\([^)]*exclude|^:[!^]' "$UI_MEMO_PATHS_FILE" || true)
    [ "${n_inc:-0}" -gt 0 ] || { _ui_memo_log "memo paths file has no include patterns"; return 1; }
    [ -n "${UI_PR_PATHS+x}" ] || { _ui_memo_log "PR path list unknown"; return 1; }
    [ -r "$UI_PR_PATHS" ] || { _ui_memo_log "PR path list unreadable"; return 1; }
    while IFS= read -r p || [ -n "$p" ]; do
        case "$p" in
            bin/ci-memo|.github/ci-memo/*) _ui_memo_log "PR edits memo machinery: $p"; return 1 ;;
        esac
    done <"$UI_PR_PATHS"
    head_id=$(_ui_e2e_runs | awk 'NR==1{print $1}') || return 1
    [ -n "$head_id" ] || return 1
    _ui_memo_hit_shape "$head_id" || { _ui_memo_log "head run $head_id is not memo-hit shaped"; return 1; }
    cands=$(_ui_memo_source_runs) || { _ui_memo_log "source run list unreadable"; return 1; }
    while read -r src_id src_sha; do
        [ -n "$src_id" ] || continue
        art=$(_ui_artifact_id "$src_id" "$src_sha") || art=""
        [ -n "$art" ] || continue
        _ui_memo_neutral_diff "$src_sha" || continue
        _ui_download_gallery "$art" "$out" || continue
        _ui_memo_log "head run $head_id reused gallery of run $src_id (sha $src_sha)"
        return 0
    done <<<"$cands"
    _ui_memo_log "no same-PR source run with an unexpired gallery and unchanged covered paths"
    return 1
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
    [ "$finished" = 1 ] || return 1
    if _ui_memo_fallback "$out"; then return 0; fi
    return 3
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
# A Dependabot composer patch or minor bump (ui_composer_exempt) also waits.
ui_workflow_verdict() {
    local UI_PR_PATHS="$1"
    local paths="$1" author="$2" fetch="$3" renderable rc=0 gallery="" tmp dev_patch=0 composer=0
    renderable=$(ui_renderable "$paths" "$author")
    ui_paths_touch_ui < "$paths" >/dev/null || rc=$?
    if [ "$rc" = 0 ] && ui_dev_patch_exempt "$paths" "$author"; then dev_patch=1; fi
    if [ "$rc" = 0 ] && ui_composer_exempt "$paths" "$author"; then composer=1; fi
    if { [ "$rc" = 1 ] || [ "$dev_patch" = 1 ] || [ "$composer" = 1 ]; } && [ "$renderable" = 1 ] && ! ui_approving_event "${4:-}" "${5:-}"; then
        tmp=$(mktemp) || tmp=""
        if [ -n "$tmp" ] && ui_await_gallery "${UI_VR_ATTEMPTS:-90}" "${UI_VR_SLEEP:-30}" "$fetch" "$tmp"; then
            gallery="$tmp"
        fi
    fi
    ui_decide "$paths" "$renderable" "$gallery" "$dev_patch" "$composer"
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
