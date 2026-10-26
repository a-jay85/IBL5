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
#     done with `git update-ref -d <ref> <sha>` (compare-and-delete).

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

# _gc_tips_record <sha> <ref> — append "<sha> <ref>" to the dated tips file
# and re-read it. Returns 1 if the mkdir, the append or the re-read fails;
# callers never delete when it does.
_gc_tips_record() {
    local sha="$1" ref="$2" file
    if [ "${DRY_RUN:-0}" = 1 ]; then return 0; fi
    file="$WT_GC_TIPS_DIR/tips-$(date +%F).txt"
    mkdir -p "$WT_GC_TIPS_DIR" 2>/dev/null || return 1
    printf '%s %s\n' "$sha" "$ref" >> "$file" 2>/dev/null || return 1
    grep -qxF -- "$sha $ref" "$file" 2>/dev/null || return 1
    return 0
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
    local wt="$1" branch="$2" ct mt newest
    ct="$(git -C "$WT_GC_REPO_ROOT" log -1 --format=%ct "refs/heads/$branch" 2>/dev/null)" || return 1
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

# _gc_remove_wt_and_branch <tag> <name> <branch> <sha> — tips line, then
# wt-remove (never --force), then a compare-and-delete of the branch.
_gc_remove_wt_and_branch() {
    local tag="$1" name="$2" branch="$3" sha="$4"
    GC_REMOVED=0
    if ! _gc_tips_record "$sha" "refs/heads/$branch"; then
        log "$tag keep $name: tips write failed"
        return 1
    fi
    if ! _gc_do "$WT_GC_WT_REMOVE" "$name" --volumes; then
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

# ── Step: merged-but-dirty worktrees ──────────────────────────────────────────
# Candidates: MERGED rows that bin/cleanup skipped as dirty this sweep.
reap_dirty_merged_worktrees() {
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

_gc_reap_dirty_one() {
    local name="$1" wt="$2" branch tip porcelain line xy p n=0
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
    if [ "${PR_STATE:-}" != MERGED ] || [ -z "${PR_OID:-}" ] || [ "$PR_OID" != "$tip" ]; then
        log "dirty keep $name: merge not proven (state=${PR_STATE:-?} oid=${PR_OID:-?})"
        return 0
    fi
    if ! porcelain="$(git -C "$wt" status --porcelain=v1 --untracked-files=all 2>/dev/null)"; then
        log "dirty keep $name: status error"; return 0
    fi
    while IFS= read -r line <&3; do
        [ -n "$line" ] || continue
        xy="${line:0:2}"
        p="${line:3}"
        case "$xy" in *R*|*C*|*U*|*T*) log "dirty keep $name: path $p rename/copy/conflict/type"; return 0 ;; esac
        case "$p" in '"'*) log "dirty keep $name: quoted path"; return 0 ;; esac
        if [ -L "$wt/$p" ]; then log "dirty keep $name: path $p is a symlink"; return 0; fi
        case "$(git -C "$wt" ls-files -s -- "$p" 2>/dev/null)" in
            160000*) log "dirty keep $name: path $p is a submodule"; return 0 ;;
        esac
        case "$xy" in
            '??'|' M'|'M '|'A ')
                if ! git -C "$wt" cat-file -e "origin/master:$p" 2>/dev/null; then
                    log "dirty keep $name: path $p absent on master"; return 0
                fi
                if ! git -C "$wt" show "origin/master:$p" 2>/dev/null | cmp -s - "$wt/$p"; then
                    log "dirty keep $name: path $p differs"; return 0
                fi ;;
            ' D'|'D ')
                if git -C "$wt" cat-file -e "origin/master:$p" 2>/dev/null; then
                    log "dirty keep $name: path $p deleted but present on master"; return 0
                fi ;;
            *) log "dirty keep $name: path $p status $xy"; return 0 ;;
        esac
        n=$((n + 1))
    done 3<<< "$porcelain"
    _gc_remove_wt_and_branch dirty "$name" "$branch" "$tip" || return 1
    [ "$GC_REMOVED" = 1 ] || return 0
    log "dirty reaped $name paths=$n"
}

# ── Step: stale refs ──────────────────────────────────────────────────────────
# _gc_backup_merged_epoch <stem> — merge epoch of the PR the stem names.
# Prints only digits on success; anything else is a keep.
_gc_backup_merged_epoch() {
    local stem="$1" out
    if [[ "$stem" =~ ^p?[0-9]+$ ]]; then
        out="$("$WT_GC_GH" pr view "${stem#p}" --repo a-jay85/IBL5 --json state,mergedAt \
            --jq 'select(.state=="MERGED") | .mergedAt | fromdateiso8601' 2>/dev/null)" || return 1
    else
        out="$("$WT_GC_GH" pr list --repo a-jay85/IBL5 --head "$stem" --state all --limit 5 \
            --json state,mergedAt \
            --jq 'if length==1 then (.[0] | select(.state=="MERGED") | .mergedAt | fromdateiso8601) else empty end' \
            2>/dev/null)" || return 1
    fi
    [[ "$out" =~ ^[0-9]+$ ]] || return 1
    printf '%s\n' "$out"
}

reap_stale_refs() {
    _gc_valid_days WT_GC_BACKUP_DAYS || return 1
    _gc_valid_days WT_GC_REF_DAYS || return 1
    log "refs threshold backup=${WT_GC_BACKUP_DAYS}d other=${WT_GC_REF_DAYS}d"
    local listing remotes remotes_ok=1 now line ref sha ct name stem rname merged
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
                if [[ "$name" == backup/* ]] || [[ "$name" =~ -(pre-rebase|presquash|local-[0-9]{8}|backup-[0-9]{8})(-[0-9]{8})?$ ]]; then
                    stem="${name#backup/}"
                    if [[ "$stem" =~ ^(.*)-(pre-rebase|presquash|local-[0-9]{8}|backup-[0-9]{8})(-[0-9]{8})?$ ]]; then
                        stem="${BASH_REMATCH[1]}"
                    fi
                    if [[ "$stem" =~ ^(.*)-[0-9a-f]{7,40}$ ]]; then
                        stem="${BASH_REMATCH[1]}"
                    fi
                    if ! _gc_have "$WT_GC_GH"; then
                        log "ref keep $ref: gh not found ('$WT_GC_GH')"
                        rc=1
                        continue
                    fi
                    if ! merged="$(_gc_backup_merged_epoch "$stem")"; then
                        log "ref keep $ref: gh found no merged PR for '$stem'"
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
    while IFS=' ' read -r sha ref; do
        [ -n "$ref" ] || continue
        if ! _gc_tips_record "$sha" "$ref"; then
            log "tips write failed — no refs deleted"
            return 1
        fi
    done <<< "$doomed"
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

# ── Step: Docker image and build-cache prune (never volumes) ──────────────────
docker_prune() {
    if [[ ! "$WT_GC_DOCKER_UNTIL" =~ ^[0-9]+h$ ]]; then
        log "invalid WT_GC_DOCKER_UNTIL '$WT_GC_DOCKER_UNTIL' — step skipped (fail-closed)"
        return 1
    fi
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
    _gc_do "$WT_GC_DOCKER" image prune -af --filter "until=$WT_GC_DOCKER_UNTIL" || prc=$?
    if [ "$prc" -ne 0 ]; then log "docker image prune failed (rc=$prc)"; rc=1; fi
    prc=0
    _gc_do "$WT_GC_DOCKER" builder prune -f --filter "until=$WT_GC_DOCKER_UNTIL" || prc=$?
    if [ "$prc" -ne 0 ]; then log "docker builder prune failed (rc=$prc)"; rc=1; fi
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
