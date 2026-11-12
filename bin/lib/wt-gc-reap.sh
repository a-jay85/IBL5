# shellcheck shell=bash
# bin/lib/wt-gc-reap.sh — the reap steps bin/wt-gc-tick runs after
# `bin/cleanup --all` (ADR-0165 and its successor ADR, see bin/wt-gc-tick).
#
# This file is SOURCED by bin/wt-gc-tick only. It defines functions and sets
# no shell option; nothing runs at source time. Bash 3.2: no mapfile, no
# associative arrays, no ${var,,}.
#
# Globals read from the tick: DRY_RUN, TMP_STATUS, TMP_OUT, WT_GC_REPO_ROOT,
# and every WT_GC_* seam; log() writes the tick's log line.
#
# Safety contract:
#   - every destructive command goes through _gc_do, so --dry-run is inert;
#   - every predicate that gates a delete is re-proven at reap time and KEEPS
#     on any probe error (.claude/rules/shell-fail-open-probe.md);
#   - every branch/ref delete is preceded by a verified tips-file line and
#     done with `git update-ref -d <ref> <sha>` (compare-and-delete);
#   - the refs, stash, dirty-merged, detached and closed steps write a
#     keep-ref under refs/wt-gc-archive/ (git gc cannot collect it) before
#     each delete; a failed archive write keeps the item. The container step
#     archives a JSON file in the tips dir instead. The empty-worktree reap
#     (tip already in master) also writes a heads/<branch> keep-ref via
#     the tips record; the tmp-pack reap, the docker image and builder
#     prunes, and prune_archives itself write no archive.

# _gc_do <argv...> — single choke point for every destructive command.
_gc_do() {
    if [ "${DRY_RUN:-0}" = 1 ]; then
        log "DRY-RUN would: $*"
        return 0
    fi
    "$@"
}

# _gc_mtime <path> — epoch mtime (BSD+GNU, same fork as bin/wt-status
# file_mtime). Prints nothing and returns 1 on error.
_gc_mtime() {
    stat -c %Y "$1" 2>/dev/null || stat -f %m "$1" 2>/dev/null
}

# _gc_resolve_bin <name> — absolute path of <name>, searching PATH and then
# WT_GC_BIN_DIRS (appended, so a PATH entry wins). Prints nothing when absent.
_gc_resolve_bin() {
    local search="${PATH:-}" found
    if [ -n "${WT_GC_BIN_DIRS:-}" ]; then search="${search:+$search:}$WT_GC_BIN_DIRS"; fi
    [ -n "$search" ] || return 0
    found="$(PATH="$search" command -v "$1" 2>/dev/null)" || return 0
    case "$found" in /*) printf '%s\n' "$found" ;; esac
    return 0
}

# _gc_have <path> — the resolved binary seam names an executable file.
_gc_have() {
    [ -n "$1" ] && [ -f "$1" ] && [ -x "$1" ]
}

# _gc_epoch_days_ago <N> — epoch seconds N days before now.
_gc_epoch_days_ago() {
    printf '%s\n' "$(( $(date +%s) - $1 * 86400 ))"
}

# _gc_valid_days <VAR> — the named seam holds a non-empty integer (rewritten
# in place without leading zeros, so 010 means 10). Logs and
# returns 1 otherwise, so the calling step is skipped and the tick exits 1.
_gc_valid_days() {
    local name="$1" val
    eval "val=\"\${$name-}\""
    case "$val" in
        ''|*[!0-9]*)
            log "invalid $name '$val' — step skipped (fail-closed)"
            return 1 ;;
    esac
    # Strip leading zeros so bash arithmetic reads 010 as ten, not octal.
    val="${val#"${val%%[!0]*}"}"
    eval "$name=\"\${val:-0}\""
    return 0
}

# _gc_archive_stamp — sets GC_ARCHIVE_STAMP once per tick. WT_GC_ARCHIVE_STAMP
# (test seam) pins it; unset or empty means now.
_gc_archive_stamp() {
    [ -z "${GC_ARCHIVE_STAMP:-}" ] || return 0
    if [ -n "${WT_GC_ARCHIVE_STAMP:-}" ]; then
        if [[ ! "$WT_GC_ARCHIVE_STAMP" =~ ^[0-9]+$ ]]; then
            log "invalid WT_GC_ARCHIVE_STAMP '$WT_GC_ARCHIVE_STAMP' — archive refused (fail-closed)"
            return 1
        fi
        GC_ARCHIVE_STAMP="$WT_GC_ARCHIVE_STAMP"
    else
        GC_ARCHIVE_STAMP="$(date +%s)"
    fi
    return 0
}

# _gc_tips_record <sha> <ref> — write a gc-proof keep-ref
# refs/wt-gc-archive/<stamp>/<ref minus refs/>, then append "<sha> <ref>" to
# the dated tips file and re-read it. Returns 1 if the keep-ref, the mkdir,
# the append or the re-read fails; callers never delete when it does.
_gc_tips_record() {
    local sha="$1" ref="$2" file aref
    if [ "${DRY_RUN:-0}" = 1 ]; then
        log "DRY-RUN would: archive $sha as refs/wt-gc-archive/<stamp>/${ref#refs/}"
        return 0
    fi
    _gc_archive_stamp || return 1
    aref="refs/wt-gc-archive/$GC_ARCHIVE_STAMP/${ref#refs/}"
    if ! git -C "$WT_GC_REPO_ROOT" update-ref "$aref" "$sha" "" 2>/dev/null; then
        if [ "$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "$aref" 2>/dev/null)" != "$sha" ]; then
            log "archive failed $ref -> $aref"
            return 1
        fi
    fi
    file="$WT_GC_TIPS_DIR/tips-$(date +%F).txt"
    mkdir -p "$WT_GC_TIPS_DIR" 2>/dev/null || return 1
    printf '%s %s\n' "$sha" "$ref" >> "$file" 2>/dev/null || return 1
    grep -qxF -- "$sha $ref" "$file" 2>/dev/null || return 1
    return 0
}

# _gc_archive_batch <doomed> — keep-refs for a "<sha> <ref>" list in one
# update-ref transaction, then the tips lines. Returns 1 on any failure and the
# transaction leaves no keep-ref behind.
_gc_archive_batch() {
    local doomed="$1" sha ref n=0 batch="" file
    if [ "${DRY_RUN:-0}" = 1 ]; then
        while IFS=' ' read -r sha ref; do
            [ -n "$ref" ] && n=$((n + 1))
        done <<< "$doomed"
        log "DRY-RUN would: archive $n refs"
        return 0
    fi
    _gc_archive_stamp || return 1
    while IFS=' ' read -r sha ref; do
        [ -n "$ref" ] || continue
        batch="${batch}create refs/wt-gc-archive/$GC_ARCHIVE_STAMP/${ref#refs/} $sha"$'\n'
    done <<< "$doomed"
    [ -n "$batch" ] || return 0
    if ! git -C "$WT_GC_REPO_ROOT" update-ref --stdin <<< "${batch%$'\n'}" 2>/dev/null; then
        log "archive failed (batch) — no refs deleted"
        return 1
    fi
    file="$WT_GC_TIPS_DIR/tips-$(date +%F).txt"
    if mkdir -p "$WT_GC_TIPS_DIR" 2>/dev/null; then
        while IFS=' ' read -r sha ref; do
            [ -n "$ref" ] || continue
            printf '%s %s\n' "$sha" "$ref" >> "$file" 2>/dev/null || { n=1; break; }
            grep -qxF -- "$sha $ref" "$file" 2>/dev/null || { n=1; break; }
        done <<< "$doomed"
    else
        n=1
    fi
    if [ "$n" = 1 ]; then
        # The tips index failed: take the keep-refs back so nothing is half-written.
        local undo=""
        while IFS=' ' read -r sha ref; do
            [ -n "$ref" ] || continue
            undo="${undo}delete refs/wt-gc-archive/$GC_ARCHIVE_STAMP/${ref#refs/} $sha"$'\n'
        done <<< "$doomed"
        git -C "$WT_GC_REPO_ROOT" update-ref --stdin <<< "${undo%$'\n'}" >/dev/null 2>&1 || true
        return 1
    fi
    return 0
}

# _gc_snapshot_dirt <wt> <tip> <label> — prints a commit sha whose tree is the
# worktree's full content (tracked edits + untracked, non-ignored files) and
# whose parent is <tip>. Dry-run prints <tip> and writes no object.
_gc_snapshot_dirt() {
    local wt="$1" tip="$2" label="$3" idx tree snap
    if [ "${DRY_RUN:-0}" = 1 ]; then printf '%s\n' "$tip"; return 0; fi
    idx="$(mktemp "${TMPDIR:-/tmp}/wt-gc-idx.XXXXXX")" || return 1
    rm -f "$idx"
    if ! GIT_INDEX_FILE="$idx" git -C "$wt" read-tree "$tip" 2>/dev/null; then rm -f "$idx"; return 1; fi
    if ! GIT_INDEX_FILE="$idx" git -C "$wt" add -A -- . >/dev/null 2>&1; then rm -f "$idx"; return 1; fi
    if ! tree="$(GIT_INDEX_FILE="$idx" git -C "$wt" write-tree 2>/dev/null)"; then rm -f "$idx"; return 1; fi
    if ! snap="$(git -C "$wt" -c user.name=wt-gc -c user.email=wt-gc@localhost \
            commit-tree "$tree" -p "$tip" -m "wt-gc archive: $label" 2>/dev/null)"; then
        rm -f "$idx"; return 1
    fi
    rm -f "$idx"
    [[ "$snap" =~ ^[0-9a-f]{40}$ ]] || return 1
    printf '%s\n' "$snap"
}

# _gc_protected_name <refname> — 0 (deny) for refs no step may ever delete.
_gc_protected_name() {
    case "$1" in
        refs/t3/*|refs/remotes/origin/*|refs/heads/wt-pool/*) return 0 ;;
        refs/heads/master|refs/heads/main|refs/heads/production) return 0 ;;
    esac
    return 1
}

# _gc_checked_out — every refs/heads/<b> checked out in a worktree, one per
# line. Returns 1 when the worktree list fails.
_gc_checked_out() {
    local listing
    listing="$(git -C "$WT_GC_REPO_ROOT" worktree list --porcelain 2>/dev/null)" || return 1
    sed -n 's/^branch //p' <<< "$listing"
}

# _gc_protected_ref <refname> <checked-out-list> — _gc_protected_name, plus
# any branch in the list (from _gc_checked_out, read once per sweep).
_gc_protected_ref() {
    local ref="$1" checked_out="$2"
    _gc_protected_name "$ref" && return 0
    case "$ref" in
        refs/heads/*) grep -qxF -- "$ref" <<< "$checked_out" && return 0 ;;
    esac
    return 1
}

# _gc_live_work <slug> <branch> <wt_path> — 0 = live work (keep), 1 = no
# signal, 2 = probe error (keep). Probes in order: automouse queue, loaded
# launchd labels, Claude session registry, is_worktree_in_use.
_gc_live_work() {
    local slug="$1" branch="$2" wt="$3" listing entry labels label branches
    # 1. Queue. An absent dir is legal (created on first queue add).
    if [ -e "$WT_GC_QUEUE_DIR" ]; then
        if ! listing="$(ls -1A "$WT_GC_QUEUE_DIR" 2>/dev/null)"; then
            log "live-work probe error $slug: queue dir unlistable"
            return 2
        fi
        while IFS= read -r entry; do
            case "$entry" in
                "$slug.md"|"$slug.md."*) log "live-work $slug: queued ($entry)"; return 0 ;;
            esac
        done <<< "$listing"
    fi
    # 2. launchd. Slug-less plan-now-*/pr-cycle-* labels cannot be attributed
    # to a worktree, so they defer every worktree reap this sweep.
    if ! labels="$("$WT_GC_LAUNCHCTL" list 2>/dev/null)"; then
        log "live-work probe error $slug: launchctl list failed"
        return 2
    fi
    while IFS= read -r entry; do
        label="${entry##*[[:space:]]}"
        case "$label" in
            com.ibl5.plan-now-*|com.ibl5.pr-cycle-*)
                log "unattributable live job $label — worktree reaps deferred this sweep"
                return 0 ;;
            *"$slug"*)
                log "live-work $slug: launchd label $label"
                return 0 ;;
        esac
    done <<< "$labels"
    # 3. Session registry (~/.claude/hooks/session-registry.py).
    if [ ! -r "$WT_GC_SESSION_REGISTRY" ]; then
        log "live-work probe error $slug: session registry unreadable ($WT_GC_SESSION_REGISTRY)"
        return 2
    fi
    if ! command -v python3 >/dev/null 2>&1; then
        log "live-work probe error $slug: python3 missing"
        return 2
    fi
    if ! branches="$(python3 -I -c '
import json, sys
def walk(o):
    if isinstance(o, dict):
        for k, v in o.items():
            if k == "branch" and isinstance(v, str):
                print(v)
            else:
                walk(v)
    elif isinstance(o, list):
        for v in o:
            walk(v)
with open(sys.argv[1]) as fh:
    walk(json.load(fh))
' "$WT_GC_SESSION_REGISTRY" 2>/dev/null)"; then
        log "live-work probe error $slug: session registry unparseable"
        return 2
    fi
    if [ -n "$branch" ] && grep -qxF -- "$branch" <<< "$branches"; then
        log "live-work $slug: live Claude session on $branch"
        return 0
    fi
    # 4. Process cwd. is_worktree_in_use reads a missing lsof as "not in use"
    # and calls a bare `lsof`, so put the resolved one first on its PATH.
    if ! _gc_have "$WT_GC_LSOF"; then
        log "live-work probe error $slug: lsof not found ('$WT_GC_LSOF')"
        return 2
    fi
    if PATH="$(dirname "$WT_GC_LSOF"):$PATH" LAUNCHCTL_CMD="$WT_GC_LAUNCHCTL" is_worktree_in_use "$wt"; then
        log "live-work $slug: worktree in use"
        return 0
    fi
    return 1
}

# _gc_wt_age_days <wt_path> <branch> — whole days since the newer of the
# branch tip's commit time and the worktree dir mtime. Prints nothing and
# returns 1 when either source fails.
_gc_wt_age_days() {
    _gc_rev_age_days "$1" "refs/heads/$2"
}

# _gc_rev_age_days <wt_path> <rev> — as _gc_wt_age_days, for any rev.
_gc_rev_age_days() {
    local wt="$1" rev="$2" ct mt newest
    ct="$(git -C "$WT_GC_REPO_ROOT" log -1 --format=%ct "$rev" 2>/dev/null)" || return 1
    case "$ct" in ''|*[!0-9]*) return 1 ;; esac
    mt="$(_gc_mtime "$wt")" || return 1
    case "$mt" in ''|*[!0-9]*) return 1 ;; esac
    newest="$ct"
    [ "$mt" -gt "$newest" ] && newest="$mt"
    printf '%s\n' "$(( ($(date +%s) - newest) / 86400 ))"
}

# _gc_wt_parent — the IBL5-worktrees dir next to the main checkout.
_gc_wt_parent() {
    worktrees_parent_dir "$WT_GC_REPO_ROOT"
}

# _gc_wt_guard <tag> <name> <wt> — the predicates shared by the EMPTY and
# dirty-merged reaps: pool, detached HEAD, protected branch, live work.
# Sets GC_BRANCH and returns 0 when the worktree may proceed; logs
# "<tag> keep <name>: <reason>" and returns 1 otherwise. (A global, not
# stdout: log() writes to stdout.)
_gc_wt_guard() {
    local tag="$1" name="$2" wt="$3" branch lw=0
    GC_BRANCH=""
    case "$name" in
        _pool-*) log "$tag keep $name: pool worktree"; return 1 ;;
    esac
    if ! branch="$(git -C "$wt" symbolic-ref --short -q HEAD 2>/dev/null)" || [ -z "$branch" ]; then
        log "$tag keep $name: detached HEAD"
        return 1
    fi
    case "$branch" in
        wt-pool/*) log "$tag keep $name: pool branch"; return 1 ;;
    esac
    if _gc_protected_name "refs/heads/$branch"; then
        log "$tag keep $name: protected branch $branch"
        return 1
    fi
    _gc_live_work "$name" "$branch" "$wt" || lw=$?
    case "$lw" in
        0) log "$tag keep $name: live work"; return 1 ;;
        1) ;;
        *) log "$tag keep $name: probe error"; return 1 ;;
    esac
    GC_BRANCH="$branch"
}

# _gc_remove_wt_and_branch <tag> <name> <branch> <sha> [<path>] — tips line,
# then wt-remove (never --force), then a compare-and-delete of the branch.
# With <path>, a worktree outside IBL5-worktrees has no Docker stack, so
# `git worktree remove <path>` (never --force) replaces wt-remove.
_gc_remove_wt_and_branch() {
    local tag="$1" name="$2" branch="$3" sha="$4" path="${5:-}"
    GC_REMOVED=0
    if ! _gc_tips_record "$sha" "refs/heads/$branch"; then
        log "$tag keep $name: tips write failed"
        return 1
    fi
    if [ -n "$path" ]; then
        if ! _gc_do git -C "$WT_GC_REPO_ROOT" worktree remove "$path"; then
            log "$tag keep $name: git worktree remove refused"
            return 0
        fi
    elif ! _gc_do "$WT_GC_WT_REMOVE" "$name" --volumes; then
        log "$tag keep $name: wt-remove refused"
        return 0
    fi
    GC_REMOVED=1
    if ! _gc_do git -C "$WT_GC_REPO_ROOT" update-ref -d "refs/heads/$branch" "$sha"; then
        log "$tag branch delete failed $branch"
        return 1
    fi
    return 0
}

# ── Step: EMPTY worktrees ─────────────────────────────────────────────────────
reap_empty_worktrees() {
    _gc_valid_days WT_GC_EMPTY_DAYS || return 1
    log "step empty threshold=${WT_GC_EMPTY_DAYS}d"
    local names parent name rc=0
    names="$(awk '$1=="EMPTY" {print $2}' "$TMP_STATUS")"
    [ -n "$names" ] || return 0
    parent="$(_gc_wt_parent)" || return 1
    while IFS= read -r name <&3; do
        [ -n "$name" ] || continue
        _gc_reap_empty_one "$name" "$parent/$name" || rc=1
    done 3<<< "$names"
    return "$rc"
}

_gc_reap_empty_one() {
    local name="$1" wt="$2" branch age ahead dirt sha
    if [ ! -d "$wt" ]; then log "empty keep $name: no such worktree"; return 0; fi
    _gc_wt_guard empty "$name" "$wt" || return 0
    branch="$GC_BRANCH"
    age="$(_gc_wt_age_days "$wt" "$branch")" || age=""
    if [ -z "$age" ]; then log "empty keep $name: age unknown"; return 0; fi
    if [ "$age" -lt "$WT_GC_EMPTY_DAYS" ]; then log "empty keep $name: age=${age}d"; return 0; fi
    ahead="$(git -C "$wt" rev-list --count origin/master..HEAD 2>/dev/null)" || ahead=""
    if [ "$ahead" != 0 ]; then log "empty keep $name: ahead=${ahead:-?}"; return 0; fi
    if ! dirt="$(git -C "$wt" status --porcelain --untracked-files=all 2>/dev/null)"; then
        log "empty keep $name: status error"; return 0
    fi
    if [ -n "$dirt" ]; then log "empty keep $name: dirty"; return 0; fi
    if ! sha="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "refs/heads/$branch")"; then
        log "empty keep $name: branch unresolved"; return 0
    fi
    _gc_remove_wt_and_branch empty "$name" "$branch" "$sha" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    log "empty reaped $name branch=$branch age=${age}d"
}

# ── Step: T3 scheduled-run worktrees ──────────────────────────────────────────
# T3 Code starts every scheduled-task run in a new worktree named t3-<hex>
# directly under WT_GC_T3_ROOT, and nothing removes it after the run. Reap
# the ones older than WT_GC_T3_DAYS with no own commits and a clean tree,
# under the same guards as the EMPTY step.
reap_t3_worktrees() {
    _gc_valid_days WT_GC_T3_DAYS || return 1
    log "step t3 threshold=${WT_GC_T3_DAYS}d root=$WT_GC_T3_ROOT"
    local root listing paths wt real rc=0
    [ -d "$WT_GC_T3_ROOT" ] || return 0
    if ! root="$(cd "$WT_GC_T3_ROOT" && pwd -P)"; then
        log "t3 root unresolved: $WT_GC_T3_ROOT"
        return 1
    fi
    if ! listing="$(git -C "$WT_GC_REPO_ROOT" worktree list --porcelain 2>/dev/null)"; then
        log "t3 worktree list failed"
        return 1
    fi
    paths="$(sed -n 's/^worktree //p' <<< "$listing")"
    while IFS= read -r wt <&3; do
        [ -n "$wt" ] || continue
        case "${wt##*/}" in
            t3-*) ;;
            *) [ "${WT_GC_REAP_T3_MERGED:-1}" != 0 ] || continue ;;
        esac
        [ -d "$wt" ] || continue
        real="$(cd "$wt" && pwd -P)" || continue
        [ "${real%/*}" = "$root" ] || continue
        _gc_reap_t3_one "${wt##*/}" "$wt" || rc=1
    done 3<<< "$paths"
    return "$rc"
}

# _gc_t3_merged_check <name> <branch> <sha> — 0 when the branch has exactly
# one MERGED PR, no OPEN PR, and _gc_merge_proven accepts <sha> against that
# PR's head. Logs the keep reason and returns 1 otherwise (gh error included).
_gc_t3_merged_check() {
    local name="$1" branch="$2" sha="$3" oid prc=0
    if ! oid="$("$WT_GC_GH" pr list --repo a-jay85/IBL5 --head "$branch" --state all --limit 5 \
            --json state,headRefOid \
            --jq 'if any(.[]; .state=="OPEN") then "open" else ([.[] | select(.state=="MERGED")] | if length==1 then .[0].headRefOid else "none" end) end' \
            2>/dev/null)"; then
        log "t3 keep $name: gh error"; return 1
    fi
    if [ "$oid" = open ]; then log "t3 keep $name: open PR"; return 1; fi
    if [[ ! "$oid" =~ ^[0-9a-f]{40}$ ]]; then log "t3 keep $name: no merged PR"; return 1; fi
    _gc_merge_proven "$sha" "$oid" || prc=$?
    case "$prc" in
        0) GC_T3_PR_OID="$oid"; return 0 ;;
        1) log "t3 keep $name: merge not proven ($GC_PROOF_REASON)"; return 1 ;;
        *) log "t3 keep $name: proof error"; return 1 ;;
    esac
}

_gc_reap_t3_one() {
    local name="$1" wt="$2" branch age ahead dirt sha
    _gc_wt_guard t3 "$name" "$wt" || return 0
    branch="$GC_BRANCH"
    age="$(_gc_wt_age_days "$wt" "$branch")" || age=""
    if [ -z "$age" ]; then log "t3 keep $name: age unknown"; return 0; fi
    if [ "$age" -lt "$WT_GC_T3_DAYS" ]; then log "t3 keep $name: age=${age}d"; return 0; fi
    ahead="$(git -C "$wt" rev-list --count origin/master..HEAD 2>/dev/null)" || ahead=""
    case "$ahead" in ''|*[!0-9]*) log "t3 keep $name: ahead=?"; return 0 ;; esac
    local need_proof=0
    case "$name" in t3-*) [ "$ahead" = 0 ] || need_proof=1 ;; *) need_proof=1 ;; esac
    if [ "$need_proof" = 1 ] && [ "${WT_GC_REAP_T3_MERGED:-1}" = 0 ]; then
        log "t3 keep $name: ahead=$ahead"; return 0
    fi
    if ! dirt="$(git -C "$wt" status --porcelain --untracked-files=all 2>/dev/null)"; then
        log "t3 keep $name: status error"; return 0
    fi
    if [ -n "$dirt" ]; then log "t3 keep $name: dirty"; return 0; fi
    if ! sha="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "refs/heads/$branch")"; then
        log "t3 keep $name: branch unresolved"; return 0
    fi
    if [ "$need_proof" = 1 ]; then
        _gc_t3_merged_check "$name" "$branch" "$sha" || return 0
    fi
    _gc_remove_wt_and_branch t3 "$name" "$branch" "$sha" "$wt" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    if [ "$need_proof" = 1 ]; then
        log "t3 reaped-merged $name branch=$branch age=${age}d pr_head=$GC_T3_PR_OID"
    else
        log "t3 reaped $name branch=$branch age=${age}d"
    fi
}

# ── Step: content-landed worktrees ────────────────────────────────────────────
# Candidates: UNPUSHED rows. A clean branch whose merge into origin/master
# yields origin/master's own tree adds nothing: its commits landed under other
# SHAs. merge-tree exit 1 (conflict) or any other non-zero code is a keep.
reap_landed_worktrees() {
    _gc_valid_days WT_GC_LANDED_DAYS || return 1
    log "step landed threshold=${WT_GC_LANDED_DAYS}d"
    local names parent name master_tree rc=0
    names="$(awk '$1=="UNPUSHED" {print $2}' "$TMP_STATUS")"
    [ -n "$names" ] || return 0
    parent="$(_gc_wt_parent)" || return 1
    if ! master_tree="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q 'origin/master^{tree}')"; then
        log "landed master tree unresolved"; return 1
    fi
    while IFS= read -r name <&3; do
        [ -n "$name" ] || continue
        _gc_reap_landed_one "$name" "$parent/$name" "$master_tree" || rc=1
    done 3<<< "$names"
    return "$rc"
}

_gc_reap_landed_one() {
    local name="$1" wt="$2" master_tree="$3" branch age dirt sha out n mrc=0
    if [ ! -d "$wt" ]; then log "landed keep $name: no such worktree"; return 0; fi
    _gc_wt_guard landed "$name" "$wt" || return 0
    branch="$GC_BRANCH"
    age="$(_gc_wt_age_days "$wt" "$branch")" || age=""
    if [ -z "$age" ]; then log "landed keep $name: age unknown"; return 0; fi
    if [ "$age" -lt "$WT_GC_LANDED_DAYS" ]; then log "landed keep $name: age=${age}d"; return 0; fi
    if ! dirt="$(git -C "$wt" status --porcelain --untracked-files=all 2>/dev/null)"; then
        log "landed keep $name: status error"; return 0
    fi
    if [ -n "$dirt" ]; then log "landed keep $name: dirty"; return 0; fi
    if ! sha="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "refs/heads/$branch")"; then
        log "landed keep $name: branch unresolved"; return 0
    fi
    if ! n="$("$WT_GC_GH" pr list --repo a-jay85/IBL5 --head "$branch" --state open \
            --json number --jq length 2>/dev/null)"; then
        log "landed keep $name: gh error"; return 0
    fi
    if [ "$n" != 0 ]; then log "landed keep $name: open PR"; return 0; fi
    out="$(git -C "$WT_GC_REPO_ROOT" merge-tree --write-tree origin/master "$sha" 2>/dev/null)" || mrc=$?
    case "$mrc" in
        0) ;;
        1) log "landed keep $name: merge conflict"; return 0 ;;
        *) log "landed keep $name: merge-tree error rc=$mrc"; return 0 ;;
    esac
    if [ "${out%%$'\n'*}" != "$master_tree" ]; then log "landed keep $name: adds content"; return 0; fi
    _gc_remove_wt_and_branch landed "$name" "$branch" "$sha" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    log "landed reaped $name branch=$branch age=${age}d"
}

# ── Step: merged-but-dirty worktrees ──────────────────────────────────────────
# Candidates: MERGED rows that bin/cleanup skipped as dirty this sweep.
reap_dirty_merged_worktrees() {
    if [ "${WT_GC_REAP_DIRTY_ARCHIVE:-1}" != 0 ]; then _gc_valid_days WT_GC_DIRTY_ARCHIVE_DAYS || return 1; fi
    log "step dirty-merged"
    local names parent name rc=0
    names="$(awk '$1=="MERGED" {print $2}' "$TMP_STATUS")"
    [ -n "$names" ] || return 0
    parent="$(_gc_wt_parent)" || return 1
    while IFS= read -r name <&3; do
        [ -n "$name" ] || continue
        [ -d "$parent/$name" ] || continue
        if ! N="$name" awk '
                BEGIN { p = "  SKIP " ENVIRON["N"] ": has " }
                index($0, p) == 1 && (index($0, p "uncommitted changes") == 1 \
                    || index($0, p "discardable changes") == 1) { f = 1 }
                END { exit !f }' "$TMP_OUT"; then
            log "dirty keep $name: not dirty-skipped by cleanup"
            continue
        fi
        _gc_reap_dirty_one "$name" "$parent/$name" || rc=1
    done 3<<< "$names"
    return "$rc"
}

# _gc_dirt_identical <wt> — compare every dirty path with origin/master. Logs
# nothing. Sets GC_DIRT_REASON (the text after "dirty keep <name>: ") and
# GC_DIRT_PATHS. 0 = every path byte-identical to master; 1 = a content
# difference (archivable); 2 = a structural keep (never archived and reaped).
_gc_dirt_identical() {
    local wt="$1" porcelain line xy p n=0 drc=0
    GC_DIRT_REASON=""
    GC_DIRT_PATHS=0
    if ! porcelain="$(git -C "$wt" status --porcelain=v1 --untracked-files=all 2>/dev/null)"; then
        GC_DIRT_REASON="status error"; return 2
    fi
    while IFS= read -r line <&4; do
        [ -n "$line" ] || continue
        xy="${line:0:2}"
        p="${line:3}"
        case "$xy" in *R*|*C*|*U*|*T*) GC_DIRT_REASON="path $p rename/copy/conflict/type"; return 2 ;; esac
        case "$p" in '"'*) GC_DIRT_REASON="quoted path"; return 2 ;; esac
        if [ -L "$wt/$p" ]; then GC_DIRT_REASON="path $p is a symlink"; return 2; fi
        case "$(git -C "$wt" ls-files -s -- "$p" 2>/dev/null)" in
            160000*) GC_DIRT_REASON="path $p is a submodule"; return 2 ;;
        esac
        case "$xy" in
            '??'|' M'|'M '|'A ')
                if ! git -C "$wt" cat-file -e "origin/master:$p" 2>/dev/null; then
                    [ "$drc" = 1 ] || GC_DIRT_REASON="path $p absent on master"
                    drc=1
                elif ! git -C "$wt" show "origin/master:$p" 2>/dev/null | cmp -s - "$wt/$p"; then
                    [ "$drc" = 1 ] || GC_DIRT_REASON="path $p differs"
                    drc=1
                fi ;;
            ' D'|'D ')
                if git -C "$wt" cat-file -e "origin/master:$p" 2>/dev/null; then
                    [ "$drc" = 1 ] || GC_DIRT_REASON="path $p deleted but present on master"
                    drc=1
                fi ;;
            *) GC_DIRT_REASON="path $p status $xy"; return 2 ;;
        esac
        n=$((n + 1))
    done 4<<< "$porcelain"
    GC_DIRT_PATHS="$n"
    return "$drc"
}

# _gc_merge_proven <tip> <pr_oid> — 0 = every local commit reached master,
# 1 = not proven (GC_PROOF_REASON set), 2 = probe error. Proven when the tip is
# the PR head or an ancestor of it, or every path the branch changed matches
# origin/master byte for byte.
_gc_merge_proven() {
    local tip="$1" oid="$2" base paths p a b rc
    GC_PROOF_REASON=""
    [ "$tip" = "$oid" ] && return 0
    if [ -n "$oid" ] && git -C "$WT_GC_REPO_ROOT" cat-file -e "$oid^{commit}" 2>/dev/null; then
        rc=0
        git -C "$WT_GC_REPO_ROOT" merge-base --is-ancestor "$tip" "$oid" 2>/dev/null || rc=$?
        case "$rc" in
            0) return 0 ;;
            1) ;;
            *) return 2 ;;
        esac
    fi
    base="$(git -C "$WT_GC_REPO_ROOT" merge-base origin/master "$tip" 2>/dev/null)" || return 2
    # A temp file, not a variable: command substitution drops the NUL separators.
    paths="$(mktemp "${TMPDIR:-/tmp}/wt-gc-paths.XXXXXX")" || return 2
    if ! git -C "$WT_GC_REPO_ROOT" diff --name-only -z --no-renames "$base" "$tip" > "$paths" 2>/dev/null; then
        rm -f "$paths"; return 2
    fi
    rc=0
    while IFS= read -r -d '' p <&5; do
        if ! a="$(git -C "$WT_GC_REPO_ROOT" ls-tree "$tip" -- "$p" 2>/dev/null)" \
            || ! b="$(git -C "$WT_GC_REPO_ROOT" ls-tree origin/master -- "$p" 2>/dev/null)"; then
            rc=2; break
        fi
        if [ "$a" != "$b" ]; then
            GC_PROOF_REASON="path $p not in master"
            rc=1; break
        fi
    done 5< "$paths"
    rm -f "$paths"
    return "$rc"
}

_gc_reap_dirty_one() {
    local name="$1" wt="$2" branch tip drc=0 prc now merged days snap
    _gc_wt_guard dirty "$name" "$wt" || return 0
    branch="$GC_BRANCH"
    if ! tip="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "refs/heads/$branch")"; then
        log "dirty keep $name: branch unresolved"; return 0
    fi
    if ! _gc_have "$WT_GC_GH"; then
        log "dirty keep $name: gh not found ('$WT_GC_GH')"
        return 1
    fi
    GH_CMD="$WT_GC_GH" pr_info "$branch" "$wt" || true
    if [ "${PR_STATE:-}" != MERGED ]; then
        log "dirty keep $name: merge not proven (state=${PR_STATE:-?} oid=${PR_OID:-?})"
        return 0
    fi
    _gc_dirt_identical "$wt" || drc=$?
    if [ "${PR_OID:-}" = "$tip" ] && [ "$drc" = 0 ]; then
        _gc_remove_wt_and_branch dirty "$name" "$branch" "$tip" || return 1
        [ "$GC_REMOVED" = 1 ] || return 0
        log "dirty reaped $name paths=$GC_DIRT_PATHS"
        return 0
    fi
    if [ "$drc" = 2 ]; then
        log "dirty keep $name: $GC_DIRT_REASON"; return 0
    fi
    if [ "${WT_GC_REAP_DIRTY_ARCHIVE:-1}" = 0 ]; then
        if [ "${PR_OID:-}" != "$tip" ]; then
            log "dirty keep $name: merge not proven (state=${PR_STATE:-?} oid=${PR_OID:-?})"
        else
            log "dirty keep $name: $GC_DIRT_REASON"
        fi
        return 0
    fi
    prc=0
    _gc_merge_proven "$tip" "${PR_OID:-}" || prc=$?
    case "$prc" in
        0) ;;
        1) log "dirty keep $name: merge not proven (state=MERGED oid=${PR_OID:-?}: $GC_PROOF_REASON)"; return 0 ;;
        *) log "dirty keep $name: merge proof probe error"; return 0 ;;
    esac
    if ! [[ "${PR_NUM:-}" =~ ^[0-9]+$ ]] || ! merged="$(_gc_backup_merged_epoch "$PR_NUM")"; then
        log "dirty keep $name: merge age unknown"; return 0
    fi
    now="$(date +%s)"
    if [ $(( now - merged )) -lt $(( WT_GC_DIRTY_ARCHIVE_DAYS * 86400 )) ]; then
        log "dirty keep $name: merged <${WT_GC_DIRTY_ARCHIVE_DAYS}d ago"; return 0
    fi
    days=$(( (now - merged) / 86400 ))
    if ! snap="$(_gc_snapshot_dirt "$wt" "$tip" "wt/$name")"; then
        log "dirty keep $name: snapshot failed"; return 0
    fi
    if ! _gc_tips_record "$snap" "refs/wt/$name"; then
        log "dirty keep $name: archive failed"; return 0
    fi
    _gc_remove_wt_and_branch dirty "$name" "$branch" "$tip" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    if [ "${DRY_RUN:-0}" = 1 ]; then
        log "dirty archived-reaped $name paths=$GC_DIRT_PATHS merged=${days}d archive=dry-run"
    else
        log "dirty archived-reaped $name paths=$GC_DIRT_PATHS merged=${days}d archive=refs/wt-gc-archive/$GC_ARCHIVE_STAMP/wt/$name"
    fi
}

# ── Step: detached master snapshots ───────────────────────────────────────────
# bin/wt-status lists no row for a detached worktree, so this step reads
# `git worktree list --porcelain` itself. A snapshot whose HEAD is already on
# origin/master, clean and old enough, holds nothing that master lacks.
reap_detached_snapshots() {
    _gc_valid_days WT_GC_DETACHED_DAYS || return 1
    log "step detached threshold=${WT_GC_DETACHED_DAYS}d"
    local parent pphys listing rows sha path rc=0
    parent="$(_gc_wt_parent)" || return 1
    pphys="$(cd "$parent" 2>/dev/null && pwd -P)" || pphys="$parent"
    listing="$(git -C "$WT_GC_REPO_ROOT" worktree list --porcelain)" || return 1
    rows="$(awk '/^worktree /{p=substr($0,10); h=""} /^HEAD /{h=$2} /^detached$/{print h "\t" p}' <<< "$listing")"
    [ -n "$rows" ] || return 0
    while IFS=$'\t' read -r sha path <&3; do
        [ -n "$path" ] || continue
        case "$(dirname "$path")" in
            "$parent"|"$pphys") ;;
            *) continue ;;
        esac
        _gc_reap_detached_one "$(basename "$path")" "$path" "$sha" || rc=1
    done 3<<< "$rows"
    return "$rc"
}

_gc_reap_detached_one() {
    local name="$1" wt="$2" sha="$3" arc age dirt lw=0
    case "$name" in
        _pool-*) log "detached keep $name: pool worktree"; return 0 ;;
    esac
    if [ ! -d "$wt" ]; then log "detached keep $name: no such worktree"; return 0; fi
    if git -C "$wt" symbolic-ref -q HEAD >/dev/null 2>&1 \
        || [ "$(git -C "$wt" rev-parse --verify -q HEAD 2>/dev/null)" != "$sha" ]; then
        log "detached keep $name: HEAD moved"; return 0
    fi
    arc=0
    git -C "$WT_GC_REPO_ROOT" merge-base --is-ancestor "$sha" origin/master 2>/dev/null || arc=$?
    case "$arc" in
        0) ;;
        1) log "detached keep $name: not on master"; return 0 ;;
        *) log "detached keep $name: ancestry probe error"; return 0 ;;
    esac
    if ! dirt="$(git -C "$wt" status --porcelain --untracked-files=all 2>/dev/null)"; then
        log "detached keep $name: status error"; return 0
    fi
    if [ -n "$dirt" ]; then log "detached keep $name: dirty"; return 0; fi
    age="$(_gc_rev_age_days "$wt" "$sha")" || age=""
    if [ -z "$age" ]; then log "detached keep $name: age unknown"; return 0; fi
    if [ "$age" -lt "$WT_GC_DETACHED_DAYS" ]; then log "detached keep $name: age=${age}d"; return 0; fi
    _gc_live_work "$name" "" "$wt" || lw=$?
    case "$lw" in
        0) log "detached keep $name: live work"; return 0 ;;
        1) ;;
        *) log "detached keep $name: probe error"; return 0 ;;
    esac
    if ! _gc_tips_record "$sha" "refs/wt/$name"; then
        log "detached keep $name: archive failed"; return 0
    fi
    if ! _gc_do "$WT_GC_WT_REMOVE" "$name" --volumes; then
        log "detached keep $name: wt-remove refused"; return 0
    fi
    log "detached reaped $name sha=${sha:0:10} age=${age}d"
}

# ── Step: stale refs ──────────────────────────────────────────────────────────
# _gc_backup_merged_epoch <stem> — merge epoch of the PR the stem names.
# Prints only digits on success; anything else is a keep. Returns 2 when gh
# itself failed, 3 when gh answered and no PR carries the name (the only case
# the commit-age fallback may reap), and 1 when gh answered with a PR that is
# not MERGED or with more than one row (always a keep).
_gc_backup_merged_epoch() {
    local stem="$1" out
    if [[ "$stem" =~ ^p?[0-9]+$ ]]; then
        out="$("$WT_GC_GH" pr view "${stem#p}" --repo a-jay85/IBL5 --json state,mergedAt \
            --jq 'if .state=="MERGED" then (.mergedAt | fromdateiso8601) else "open" end' 2>/dev/null)" || return 2
    else
        out="$("$WT_GC_GH" pr list --repo a-jay85/IBL5 --head "$stem" --state all --limit 5 \
            --json state,mergedAt \
            --jq 'if length==0 then "none" elif length==1 then (.[0] | if .state=="MERGED" then (.mergedAt | fromdateiso8601) else "open" end) else "many" end' \
            2>/dev/null)" || return 2
    fi
    [ "$out" != none ] || return 3
    [[ "$out" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$out"
}

# _gc_pr_closed_epoch <num> — close epoch of a CLOSED (never merged) PR.
# Prints only digits on success. 1 = not closed / no date, 2 = gh failed.
_gc_pr_closed_epoch() {
    local out
    out="$("$WT_GC_GH" pr view "$1" --repo a-jay85/IBL5 --json state,closedAt \
        --jq 'select(.state=="CLOSED") | .closedAt | fromdateiso8601' 2>/dev/null)" || return 2
    [[ "$out" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$out"
}

# ── Step: closed-PR worktrees ─────────────────────────────────────────────────
# Candidates: cleanup skip lines "PR closed but remote gone". The tick never
# passes --discard-closed to cleanup; this step re-proves, archives, removes.
reap_closed_worktrees() {
    _gc_valid_days WT_GC_CLOSED_DAYS || return 1
    log "step closed threshold=${WT_GC_CLOSED_DAYS}d"
    local names parent name cls rc=0
    names="$(awk 'index($0, "  SKIP ") == 1 && index($0, ": PR closed but remote gone") > 0 {
        s = substr($0, 8); sub(/: PR closed but remote gone.*/, "", s); print s }' "$TMP_OUT")"
    [ -n "$names" ] || return 0
    parent="$(_gc_wt_parent)" || return 1
    while IFS= read -r name <&3; do
        [ -n "$name" ] || continue
        [ -d "$parent/$name" ] || continue
        cls="$(awk -v n="$name" '$2==n {print $1}' "$TMP_STATUS")"
        if [ -z "$cls" ]; then log "closed keep $name: no wt-status row"; continue; fi
        if [ "$cls" = OPEN-PR ]; then log "closed keep $name: wt-status class OPEN-PR"; continue; fi
        _gc_reap_closed_one "$name" "$parent/$name" || rc=1
    done 3<<< "$names"
    return "$rc"
}

_gc_reap_closed_one() {
    local name="$1" wt="$2" branch tip rl arc=0 cerr=0 closed now days snap dirc
    _gc_wt_guard closed "$name" "$wt" || return 0
    branch="$GC_BRANCH"
    if ! tip="$(git -C "$WT_GC_REPO_ROOT" rev-parse --verify -q "refs/heads/$branch")"; then
        log "closed keep $name: branch unresolved"; return 0
    fi
    if ! _gc_have "$WT_GC_GH"; then
        log "closed keep $name: gh not found ('$WT_GC_GH')"
        return 1
    fi
    GH_CMD="$WT_GC_GH" pr_info "$branch" "$wt" || true
    if [ "${PR_STATE:-}" != CLOSED ]; then
        log "closed keep $name: state=${PR_STATE:-?}"; return 0
    fi
    if ! [[ "${PR_NUM:-}" =~ ^[0-9]+$ ]]; then
        log "closed keep $name: pr number unknown"; return 0
    fi
    if ! rl="$(git -C "$WT_GC_REPO_ROOT" for-each-ref --format='%(refname)' "refs/remotes/origin/$branch")"; then
        log "closed keep $name: remote probe error"; return 0
    fi
    if grep -qxF -- "refs/remotes/origin/$branch" <<< "$rl"; then
        log "closed keep $name: remote branch present"; return 0
    fi
    if [ "$tip" != "${PR_OID:-}" ]; then
        if [ -n "${PR_OID:-}" ] && git -C "$WT_GC_REPO_ROOT" cat-file -e "$PR_OID^{commit}" 2>/dev/null; then
            git -C "$WT_GC_REPO_ROOT" merge-base --is-ancestor "$tip" "$PR_OID" 2>/dev/null || arc=$?
        else
            arc=1
        fi
        case "$arc" in
            0) ;;
            1) log "closed keep $name: local commits beyond PR head"; return 0 ;;
            *) log "closed keep $name: ancestry probe error"; return 0 ;;
        esac
    fi
    closed="$(_gc_pr_closed_epoch "$PR_NUM")" || cerr=$?
    if [ "$cerr" != 0 ]; then
        log "closed keep $name: close age unknown"; return 0
    fi
    now="$(date +%s)"
    if [ $(( now - closed )) -lt $(( WT_GC_CLOSED_DAYS * 86400 )) ]; then
        log "closed keep $name: closed <${WT_GC_CLOSED_DAYS}d ago"; return 0
    fi
    days=$(( (now - closed) / 86400 ))
    dirc=0
    _gc_dirt_identical "$wt" || dirc=$?
    if [ "$dirc" = 2 ]; then
        log "closed keep $name: $GC_DIRT_REASON"; return 0
    fi
    if ! snap="$(_gc_snapshot_dirt "$wt" "$tip" "wt/$name")"; then
        log "closed keep $name: snapshot failed"; return 0
    fi
    if ! _gc_tips_record "$snap" "refs/wt/$name"; then
        log "closed keep $name: archive failed"; return 0
    fi
    _gc_remove_wt_and_branch closed "$name" "$branch" "$tip" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    log "closed reaped $name pr=#$PR_NUM closed=${days}d"
}

# ── Step: orphan stashes ──────────────────────────────────────────────────────
# Matches bin/cleanup's "Stash candidate" rule, plus branch-gone stashes and
# stashes with an untracked part: the keep-ref holds both before the drop.

# _gc_stash_in_master <sha> — 0 = the stash patch reverse-applies cleanly to
# origin/master (so master already has it), 1 = not provable. A temp index
# keeps the main checkout's working tree out of the verdict.
_gc_stash_in_master() {
    local sha="$1" d rc=1
    d="$(mktemp -d "${TMPDIR:-/tmp}/wt-gc-stash.XXXXXX")" || return 1
    if git -C "$WT_GC_REPO_ROOT" stash show -p "$sha" > "$d/patch" 2>/dev/null \
        && [ -s "$d/patch" ] \
        && GIT_INDEX_FILE="$d/index" git -C "$WT_GC_REPO_ROOT" read-tree origin/master 2>/dev/null \
        && GIT_INDEX_FILE="$d/index" git -C "$WT_GC_REPO_ROOT" apply --cached --check -R "$d/patch" 2>/dev/null; then
        rc=0
    fi
    rm -rf "$d"
    return "$rc"
}

drop_orphan_stashes() {
    _gc_valid_days WT_GC_STASH_DAYS || return 1
    log "step stashes threshold=${WT_GC_STASH_DAYS}d"
    local rows doomed="" relist pairs sha ct subj s now age sbranch bl why meta N cur rc=0
    local re='^(WIP on|On) ([^:]+):'
    if ! rows="$(git -C "$WT_GC_REPO_ROOT" stash list --format='%H%x09%ct%x09%gs')"; then
        log "stash list failed — none dropped"; return 1
    fi
    [ -n "$rows" ] || return 0
    now="$(date +%s)"
    while IFS=$'\t' read -r sha ct subj <&3; do
        [ -n "$sha" ] || continue
        s="${sha:0:10}"
        case "$subj" in *nightly-autostash*) log "stash keep $s: nightly-autostash"; continue ;; esac
        case "$ct" in ''|*[!0-9]*) log "stash keep $s: no commit time"; continue ;; esac
        if ! [[ "$subj" =~ $re ]]; then log "stash keep $s: unparseable subject"; continue; fi
        sbranch="${BASH_REMATCH[2]}"
        if ! bl="$(git -C "$WT_GC_REPO_ROOT" for-each-ref --format='%(refname)' "refs/heads/$sbranch")"; then
            log "stash keep $s: branch probe error"; continue
        fi
        if grep -qxF -- "refs/heads/$sbranch" <<< "$bl"; then
            _gc_stash_in_master "$sha" || continue
            why="provably in master"
        else
            why="branch gone"
        fi
        age=$(( (now - ct) / 86400 ))
        if [ $(( now - ct )) -lt $(( WT_GC_STASH_DAYS * 86400 )) ]; then
            log "stash keep $s: age=${age}d"; continue
        fi
        if ! _gc_tips_record "$sha" "refs/stash/$sha"; then
            log "stash keep $s: archive failed"; continue
        fi
        doomed="${doomed}${sha}"$'\t'"${why}"$'\t'"${sbranch}"$'\t'"${age}"$'\n'
    done 3<<< "$rows"
    [ -n "$doomed" ] || return 0
    if ! relist="$(git -C "$WT_GC_REPO_ROOT" stash list --format='%gd%x09%H')"; then
        log "stash relist failed — none dropped"; return 1
    fi
    pairs=""
    while IFS=$'\t' read -r cur sha <&3; do
        [ -n "$sha" ] || continue
        grep -qF -- "$sha" <<< "$doomed" || continue
        N="${cur#stash@\{}"; N="${N%\}}"
        pairs="${pairs}${N}"$'\t'"${sha}"$'\n'
    done 3<<< "$relist"
    [ -n "$pairs" ] || return 0
    pairs="$(sort -t "$(printf '\t')" -k1,1nr <<< "${pairs%$'\n'}")"
    while IFS=$'\t' read -r N sha <&3; do
        [ -n "$sha" ] || continue
        s="${sha:0:10}"
        if [ "$(git -C "$WT_GC_REPO_ROOT" rev-parse -q --verify "stash@{$N}" 2>/dev/null)" != "$sha" ]; then
            log "stash keep $s: stash moved"; continue
        fi
        meta="$(awk -F'\t' -v s="$sha" '$1==s {print $2 "\t" $3 "\t" $4}' <<< "$doomed")"
        IFS=$'\t' read -r why sbranch age <<< "$meta"
        if _gc_do git -C "$WT_GC_REPO_ROOT" stash drop -q "stash@{$N}"; then
            log "stash dropped $s ($why) branch=$sbranch age=${age}d"
        else
            log "stash drop failed $s"; rc=1
        fi
    done 3<<< "$pairs"
    return "$rc"
}

# _gc_backup_shaped <branch-name> — 0 when the name looks like a backup: under
# backup/, or ending in a pre-rebase / presquash style tail. Pure, no I/O.
_gc_backup_shaped() {
    local name="$1"
    local re='-(pre-rebase|presquash|pre-squash|local-[0-9]{8}|backup-[0-9]{8})(-backup)?(-[0-9]{8})?$'
    [[ "$name" == backup/* ]] && return 0
    [[ "$name" =~ $re ]]
}

# _gc_backup_stem <branch-name> — the PR number or head-branch stem a backup
# name points at. Pure, no I/O. Strips backup/, then up to 4 passes of the
# first matching tail rule, then a trailing short sha.
_gc_backup_stem() {
    local s="${1#backup/}" pass=0
    local re_a='^(.+)/[0-9]{8}-[0-9]{6}(-.+)?$'
    local re_b='^(.+)-(pre-prep|pre-squash|pre-reset|pre-rebase)(-[0-9]{8})?$'
    local re_c='^(.+)-(presquash|prep|backup|local-[0-9]{8}|backup-[0-9]{8})(-[0-9]{8})?$'
    local re_d='^(.+)-[0-9]{8}$'
    local re_sha='^(.*)-[0-9a-f]{7,40}$'
    while [ "$pass" -lt 4 ]; do
        pass=$((pass + 1))
        if [[ "$s" =~ $re_a ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_b ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_c ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_d ]]; then s="${BASH_REMATCH[1]}"
        else break
        fi
    done
    if [[ "$s" =~ $re_sha ]]; then s="${BASH_REMATCH[1]}"; fi
    printf '%s\n' "$s"
}

# _gc_backup_stems <branch-name> — every stem _gc_backup_stem could land on,
# one per line, least stripped first (the last line equals _gc_backup_stem).
# A head branch whose own name ends in -backup, -prep or -<8 digits> is found
# before the strip loop eats its tail. Pure, no I/O.
_gc_backup_stems() {
    local s="${1#backup/}" pass=0 last=""
    local re_a='^(.+)/[0-9]{8}-[0-9]{6}(-.+)?$'
    local re_b='^(.+)-(pre-prep|pre-squash|pre-reset|pre-rebase)(-[0-9]{8})?$'
    local re_c='^(.+)-(presquash|prep|backup|local-[0-9]{8}|backup-[0-9]{8})(-[0-9]{8})?$'
    local re_d='^(.+)-[0-9]{8}$'
    local re_sha='^(.*)-[0-9a-f]{7,40}$'
    printf '%s\n' "$s"; last="$s"
    while [ "$pass" -lt 4 ]; do
        pass=$((pass + 1))
        # Date-only strip first, so "x-backup-20261020" yields "x-backup"
        # before the combined tail rule yields "x".
        if [[ "$s" =~ $re_d ]] && [ "${BASH_REMATCH[1]}" != "$last" ]; then
            last="${BASH_REMATCH[1]}"; printf '%s\n' "$last"
        fi
        if [[ "$s" =~ $re_a ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_b ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_c ]]; then s="${BASH_REMATCH[1]}"
        elif [[ "$s" =~ $re_d ]]; then s="${BASH_REMATCH[1]}"
        else break
        fi
        if [ "$s" != "$last" ]; then printf '%s\n' "$s"; last="$s"; fi
    done
    if [[ "$s" =~ $re_sha ]] && [ "${BASH_REMATCH[1]}" != "$last" ]; then
        printf '%s\n' "${BASH_REMATCH[1]}"
    fi
}

# ── Step: stale refs (reap) ───────────────────────────────────────────────────
reap_stale_refs() {
    _gc_valid_days WT_GC_BACKUP_DAYS || return 1
    _gc_valid_days WT_GC_REF_DAYS || return 1
    if [ "${WT_GC_REF_FALLBACK:-1}" != 0 ]; then _gc_valid_days WT_GC_BACKUP_FALLBACK_DAYS || return 1; fi
    log "refs threshold backup=${WT_GC_BACKUP_DAYS}d other=${WT_GC_REF_DAYS}d"
    local cand listing remotes remotes_ok=1 now line ref sha ct name stem rname merged mrc ct_ok
    local doomed="" rc=0 checked_out branches_ok=1 known
    now="$(date +%s)"
    listing="$(git -C "$WT_GC_REPO_ROOT" for-each-ref \
        --format='%(refname) %(objectname) %(committerdate:unix)' \
        refs/heads/ refs/tmp/ refs/wt-sync-backup/ refs/backup/ refs/remotes/)" || return 1
    remotes="$(git -C "$WT_GC_REPO_ROOT" remote 2>/dev/null)" || remotes_ok=0
    # A failed worktree list protects every branch (fail-closed).
    if ! checked_out="$(_gc_checked_out)"; then
        log "worktree list failed — branches kept"
        checked_out=""
        branches_ok=0
    fi
    while IFS=' ' read -r ref sha ct <&3; do
        [ -n "$ref" ] || continue
        case "$ref" in
            refs/remotes/*/HEAD) continue ;;
        esac
        if _gc_protected_ref "$ref" "$checked_out"; then continue; fi
        if [ "$branches_ok" = 0 ] && [[ "$ref" == refs/heads/* ]]; then continue; fi
        case "$ref" in
            refs/heads/*)
                name="${ref#refs/heads/}"
                if _gc_backup_shaped "$name"; then
                    stem="$(_gc_backup_stem "$name")"
                    if ! _gc_have "$WT_GC_GH"; then
                        log "ref keep $ref: gh not found ('$WT_GC_GH')"
                        rc=1
                        continue
                    fi
                    # Try the least-stripped stem first; the first one gh
                    # knows a PR for wins. Only "no PR under any stem" falls
                    # through to the age fallback.
                    mrc=3
                    while IFS= read -r cand <&4; do
                        [ -n "$cand" ] || continue
                        mrc=0
                        merged="$(_gc_backup_merged_epoch "$cand")" || mrc=$?
                        if [ "$mrc" != 3 ]; then stem="$cand"; break; fi
                    done 4< <(_gc_backup_stems "$name")
                    if [ "$mrc" = 2 ]; then
                        log "ref keep $ref: gh probe error for '$stem'"
                        continue
                    fi
                    if [ "$mrc" = 1 ]; then
                        log "ref keep $ref: PR for '$stem' is not merged or is ambiguous"
                        continue
                    fi
                    if [ "$mrc" = 3 ]; then
                        # gh answered: no PR carries this name. An old
                        # enough backup is still archived and reaped.
                        case "$ct" in ''|*[!0-9]*) ct_ok=0 ;; *) ct_ok=1 ;; esac
                        if [ "${WT_GC_REF_FALLBACK:-1}" != 0 ] && [ "$ct_ok" = 1 ] \
                            && [ $(( now - ct )) -ge $(( WT_GC_BACKUP_FALLBACK_DAYS * 86400 )) ]; then
                            log "ref fallback $ref: no merged PR for '$stem', newest commit $(( (now - ct) / 86400 ))d old"
                            doomed="${doomed}${sha} ${ref}"$'\n'
                        else
                            log "ref keep $ref: gh found no merged PR for '$stem'"
                        fi
                        continue
                    fi
                    if [ $(( now - merged )) -lt $(( WT_GC_BACKUP_DAYS * 86400 )) ]; then
                        log "ref keep $ref: PR merged <${WT_GC_BACKUP_DAYS}d ago"
                        continue
                    fi
                    doomed="${doomed}${sha} ${ref}"$'\n'
                fi ;;
            refs/tmp/*|refs/wt-sync-backup/*|refs/backup/*)
                case "$ct" in
                    ''|*[!0-9]*) log "ref keep $ref: no commit date"; continue ;;
                esac
                if [ $(( now - ct )) -lt $(( WT_GC_REF_DAYS * 86400 )) ]; then
                    log "ref keep $ref: younger than ${WT_GC_REF_DAYS}d"
                    continue
                fi
                doomed="${doomed}${sha} ${ref}"$'\n' ;;
            refs/remotes/*)
                if [ "$remotes_ok" = 0 ]; then
                    log "ref keep $ref: git remote failed"
                    continue
                fi
                # Remote names may contain '/', so match each configured name
                # as a prefix instead of cutting at the first slash.
                known=0
                while IFS= read -r rname; do
                    [ -n "$rname" ] || continue
                    case "$ref" in "refs/remotes/$rname/"*) known=1; break ;; esac
                done <<< "$remotes"
                if [ "$known" = 1 ]; then continue; fi
                doomed="${doomed}${sha} ${ref}"$'\n' ;;
        esac
    done 3<<< "$listing"
    [ -n "$doomed" ] || return "$rc"
    # Tips first: one failure deletes nothing.
    if ! _gc_archive_batch "$doomed"; then
        log "tips write failed — no refs deleted"
        return 1
    fi
    # One compare-and-delete transaction for the whole set (thousands of refs
    # under a dead remote). If it fails nothing was deleted, so the per-ref
    # loop below retries one at a time and isolates the bad ref.
    if [ "${DRY_RUN:-0}" != 1 ]; then
        local batch="" batch_ok=0
        while IFS=' ' read -r sha ref; do
            [ -n "$ref" ] || continue
            batch="${batch}delete ${ref} ${sha}"$'\n'
        done <<< "$doomed"
        if _gc_do git -C "$WT_GC_REPO_ROOT" update-ref --stdin <<< "$batch" 2>/dev/null; then
            batch_ok=1
        else
            log "batch ref delete failed — retrying one by one"
        fi
        if [ "$batch_ok" = 1 ]; then
            while IFS=' ' read -r sha ref; do
                [ -n "$ref" ] || continue
                log "ref deleted $ref $sha"
            done <<< "$doomed"
            return "$rc"
        fi
    fi
    while IFS=' ' read -r sha ref <&3; do
        [ -n "$ref" ] || continue
        if _gc_do git -C "$WT_GC_REPO_ROOT" update-ref -d "$ref" "$sha"; then
            [ "${DRY_RUN:-0}" = 1 ] || log "ref deleted $ref $sha"
        else
            log "ref delete failed $ref"
            rc=1
        fi
    done 3<<< "$doomed"
    return "$rc"
}

# ── Step: aborted-fetch tmp_pack_* files ──────────────────────────────────────
reap_tmp_packs() {
    _gc_valid_days WT_GC_TMP_PACK_DAYS || return 1
    log "step tmp-pack threshold=${WT_GC_TMP_PACK_DAYS}d"
    local common pack_dir files f mt now lrc errf err
    common="$(git -C "$WT_GC_REPO_ROOT" rev-parse --git-common-dir)" || return 1
    case "$common" in /*) ;; *) common="$WT_GC_REPO_ROOT/$common" ;; esac
    pack_dir="$common/objects/pack"
    [ -d "$pack_dir" ] || return 0
    files="$(find "$pack_dir" -maxdepth 1 -type f -name 'tmp_pack_*')" || return 1
    [ -n "$files" ] || return 0
    now="$(date +%s)"
    errf="$(mktemp "${TMPDIR:-/tmp}/wt-gc-lsof.XXXXXX")" || return 1
    while IFS= read -r f <&3; do
        [ -n "$f" ] || continue
        mt="$(_gc_mtime "$f")" || mt=""
        case "$mt" in ''|*[!0-9]*) log "tmp_pack keep $f: mtime unreadable"; continue ;; esac
        if [ $(( now - mt )) -lt $(( WT_GC_TMP_PACK_DAYS * 86400 )) ]; then
            log "tmp_pack keep $f: age"; continue
        fi
        if ! _gc_have "$WT_GC_LSOF"; then
            log "tmp_pack keep $f: lsof not found ('$WT_GC_LSOF')"; continue
        fi
        lrc=0
        "$WT_GC_LSOF" -F p -- "$f" >/dev/null 2>"$errf" || lrc=$?
        err="$(cat "$errf")"
        if [ "$lrc" -eq 0 ]; then log "tmp_pack keep $f: held open"; continue; fi
        if [ -n "$err" ]; then log "tmp_pack keep $f: probe error"; continue; fi
        if _gc_do rm -f -- "$f"; then
            [ "${DRY_RUN:-0}" = 1 ] || log "tmp_pack deleted $f"
        fi
    done 3<<< "$files"
    rm -f "$errf"
    return 0
}

# _gc_iso_epoch <rfc3339> — epoch seconds of a Docker timestamp such as
# 2026-10-01T12:34:56.123456789Z. Prints digits; returns 1 on any mismatch or
# a pre-1970 value (Docker's never-finished 0001-01-01 sentinel).
_gc_iso_epoch() {
    command -v python3 >/dev/null 2>&1 || return 1
    python3 -I -c '
import re, sys, datetime
m = re.match(r"^(\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d)(\.\d+)?(Z|[+-]\d\d:\d\d)$", sys.argv[1])
if not m:
    sys.exit(1)
try:
    dt = datetime.datetime.strptime(m.group(1), "%Y-%m-%dT%H:%M:%S")
except ValueError:
    sys.exit(1)
tz = m.group(3)
off = 0
if tz != "Z":
    sign = 1 if tz[0] == "+" else -1
    off = sign * (int(tz[1:3]) * 3600 + int(tz[4:6]) * 60)
epoch = int((dt - datetime.datetime(1970, 1, 1)).total_seconds()) - off
if epoch <= 0:
    sys.exit(1)
print(epoch)
' "$1" 2>/dev/null
}

# _gc_docker_containers — remove exited ibl5-* containers past the floor.
# `docker rm <id>` only: no -v, no -f, so volumes survive and a container that
# restarted since the listing is refused. The full inspect JSON is archived
# first under $WT_GC_TIPS_DIR.
_gc_docker_containers() {
    log "docker containers threshold=${WT_GC_CONTAINER_DAYS}d"
    local rows id cname fmt status finished exited now d f rc=0
    if ! rows="$("$WT_GC_DOCKER" ps -a --no-trunc --filter status=exited --filter name=ibl5- \
        --format '{{.ID}}{{"\t"}}{{.Names}}')"; then
        log "docker ps failed — no containers removed"
        return 1
    fi
    [ -n "$rows" ] || return 0
    now="$(date +%s)"
    while IFS=$'\t' read -r id cname <&3; do
        [ -n "$id" ] || continue
        case "$cname" in ibl5-*) ;; *) continue ;; esac
        if ! fmt="$("$WT_GC_DOCKER" inspect --format '{{.State.Status}}{{"\t"}}{{.State.FinishedAt}}' "$id" 2>/dev/null)"; then
            log "container keep $cname: inspect failed"; continue
        fi
        IFS=$'\t' read -r status finished <<< "$fmt"
        if [ "$status" != exited ]; then log "container keep $cname: not exited"; continue; fi
        if ! exited="$(_gc_iso_epoch "$finished")" || [[ ! "$exited" =~ ^[0-9]+$ ]]; then
            log "container keep $cname: exit time unknown"; continue
        fi
        d=$(( (now - exited) / 86400 ))
        if [ $(( now - exited )) -lt $(( WT_GC_CONTAINER_DAYS * 86400 )) ]; then
            log "container keep $cname: exited ${d}d ago"; continue
        fi
        if ! _gc_archive_stamp; then log "container keep $cname: archive failed"; continue; fi
        f="$WT_GC_TIPS_DIR/containers-${GC_ARCHIVE_STAMP}-${cname}.json"
        if [ "${DRY_RUN:-0}" = 1 ]; then
            log "DRY-RUN would: archive $cname to $f"
        else
            if ! mkdir -p "$WT_GC_TIPS_DIR" 2>/dev/null \
                || ! "$WT_GC_DOCKER" inspect "$id" > "$f.tmp" 2>/dev/null \
                || [ ! -s "$f.tmp" ] \
                || ! mv -f "$f.tmp" "$f"; then
                rm -f "$f.tmp"
                log "container keep $cname: archive failed"; continue
            fi
        fi
        if _gc_do "$WT_GC_DOCKER" rm "$id"; then
            log "container removed $cname exited=${d}d"
        else
            log "container rm failed $cname"; rc=1
        fi
    done 3<<< "$rows"
    return "$rc"
}

# ── Step: Docker image and build-cache prune (never volumes) ──────────────────
docker_prune() {
    if [[ ! "$WT_GC_DOCKER_UNTIL" =~ ^[0-9]+h$ ]]; then
        log "invalid WT_GC_DOCKER_UNTIL '$WT_GC_DOCKER_UNTIL' — step skipped (fail-closed)"
        return 1
    fi
    if [ "${WT_GC_DOCKER_CONTAINERS:-1}" != 0 ]; then _gc_valid_days WT_GC_CONTAINER_DAYS || return 1; fi
    log "step docker threshold=$WT_GC_DOCKER_UNTIL"
    if ! _gc_have "$WT_GC_DOCKER"; then
        log "docker not found ('$WT_GC_DOCKER') — nothing pruned"
        return 1
    fi
    if ! "$WT_GC_DOCKER" info >/dev/null 2>&1; then
        log "docker daemon unreachable — skipped"
        return 0
    fi
    local rc=0 prc=0
    if [ "${WT_GC_DOCKER_CONTAINERS:-1}" != 0 ]; then _gc_docker_containers || rc=1; fi
    _gc_do "$WT_GC_DOCKER" image prune -af --filter "until=$WT_GC_DOCKER_UNTIL" || prc=$?
    if [ "$prc" -ne 0 ]; then log "docker image prune failed (rc=$prc)"; rc=1; fi
    prc=0
    _gc_do "$WT_GC_DOCKER" builder prune -f --filter "until=$WT_GC_DOCKER_UNTIL" || prc=$?
    if [ "$prc" -ne 0 ]; then log "docker builder prune failed (rc=$prc)"; rc=1; fi
    return "$rc"
}

# ── Step: archive retention ───────────────────────────────────────────────────
# Keep-refs under refs/wt-gc-archive/<stamp>/ older than WT_GC_ARCHIVE_DAYS go.
# The stamp in the refname is the only clock; a malformed stamp keeps.
prune_archives() {
    _gc_valid_days WT_GC_ARCHIVE_DAYS || return 1
    log "step archive-prune threshold=${WT_GC_ARCHIVE_DAYS}d"
    local listing ref sha cutoff stamp rc=0
    listing="$(git -C "$WT_GC_REPO_ROOT" for-each-ref \
        --format='%(refname) %(objectname)' refs/wt-gc-archive/)" || return 1
    cutoff=$(( $(date +%s) - WT_GC_ARCHIVE_DAYS * 86400 ))
    while IFS=' ' read -r ref sha <&3; do
        [ -n "$ref" ] || continue
        if [[ ! "$ref" =~ ^refs/wt-gc-archive/([0-9]+)/ ]]; then
            log "archive keep $ref: malformed stamp"
            continue
        fi
        stamp="${BASH_REMATCH[1]}"
        if [ "$stamp" -lt "$cutoff" ]; then
            if _gc_do git -C "$WT_GC_REPO_ROOT" update-ref -d "$ref" "$sha"; then
                [ "${DRY_RUN:-0}" = 1 ] || log "archive pruned $ref"
            else
                log "archive prune failed $ref"
                rc=1
            fi
        fi
    done 3<<< "$listing"
    # Container inspect archives: the stamp in the file name is the clock.
    if [ -d "$WT_GC_TIPS_DIR" ]; then
        local files f
        if ! files="$(find "$WT_GC_TIPS_DIR" -maxdepth 1 -type f -name 'containers-*.json')"; then
            log "archive list of container files failed"
            return 1
        fi
        while IFS= read -r f <&3; do
            [ -n "$f" ] || continue
            if [[ ! "$(basename "$f")" =~ ^containers-([0-9]+)- ]]; then
                log "archive keep $f: malformed stamp"
                continue
            fi
            stamp="${BASH_REMATCH[1]}"
            if [ "$stamp" -lt "$cutoff" ]; then
                if _gc_do rm -f -- "$f"; then
                    [ "${DRY_RUN:-0}" = 1 ] || log "archive pruned $f"
                else
                    log "archive prune failed $f"
                    rc=1
                fi
            fi
        done 3<<< "$files"
    fi
    return "$rc"
}

# ── Step: STALLED / UNPUSHED list for the morning digest ──────────────────────
# Writes "# generated <date>" then name<TAB>class<TAB>age rows to
# $WT_GC_STALE_FILE (atomic mv). Unknown ages render "?" and are listed.
write_stale_list() {
    _gc_valid_days WT_GC_STALE_DAYS || return 1
    log "step stale-list threshold=${WT_GC_STALE_DAYS}d"
    local rows parent cls name wt branch age out="" n=0 tmp
    rows="$(awk '$1=="STALLED" || $1=="UNPUSHED" {print $1, $2}' "$TMP_STATUS")"
    parent="$(_gc_wt_parent)" || return 1
    while read -r cls name <&3; do
        [ -n "$name" ] || continue
        wt="$parent/$name"
        [ -d "$wt" ] || continue
        age=""
        if branch="$(git -C "$wt" symbolic-ref --short -q HEAD 2>/dev/null)" && [ -n "$branch" ]; then
            age="$(_gc_wt_age_days "$wt" "$branch")" || age=""
        fi
        if [ -n "$age" ] && [ "$age" -lt "$WT_GC_STALE_DAYS" ]; then continue; fi
        out="${out}${name}"$'\t'"${cls}"$'\t'"${age:-?}"$'\n'
        n=$((n + 1))
    done 3<<< "$rows"
    if [ "${DRY_RUN:-0}" = 1 ]; then
        log "DRY-RUN would: write stale list ($n rows)"
        return 0
    fi
    mkdir -p "$(dirname "$WT_GC_STALE_FILE")" || return 1
    tmp="$(mktemp "$WT_GC_STALE_FILE.XXXXXX")" || return 1
    { printf '# generated %s\n' "$(date '+%Y-%m-%d %H:%M')"; printf '%s' "$out"; } > "$tmp" || { rm -f "$tmp"; return 1; }
    mv -f "$tmp" "$WT_GC_STALE_FILE" || { rm -f "$tmp"; return 1; }
    log "stale list written ($n rows)"
}
