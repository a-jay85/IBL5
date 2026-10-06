# shellcheck shell=bash
# Warm-standby worktree pool for bin/wt-new (backlog#24, ADR-0177).
#
# One spare worktree sits at <wt_parent>/_pool-N on branch wt-pool/N. A default-mode
# `bin/wt-new <slug>` on master claims it (branch rename + `git worktree move`);
# `bin/wt-new --pool-refill` builds the next one. Defines functions only; sourcing
# runs nothing. Must stay bash 3.2 safe and `set -u` clean (the refill runs from a
# launchd job with an almost empty environment).
#
# Usage: source "$(dirname "$0")/lib/wt-pool.sh"

POOL_LABEL="com.ibl5.wt-pool-refill"

# Canonical form of an existing path (macOS mktemp sits under a /var symlink).
_pool_canon() { (cd -P "$1" 2>/dev/null && pwd) || printf '%s\n' "$1"; }

_pool_gcd() { git -C "$1" rev-parse --path-format=absolute --git-common-dir; }

_pool_is_uint() { case "$1" in ''|*[!0-9]*) return 1 ;; *) return 0 ;; esac; }

_pool_bypass() { echo "wt-pool: bypass ($1)" >&2; }

# Every registered worktree path of <repo_root>, one per line.
_pool_worktree_paths() {
    git -C "$1" worktree list --porcelain 2>/dev/null | sed -n 's/^worktree //p'
}

# pool_next_index <repo_root> <wt_parent>
# Smallest N >= 1 free as a dir, a branch AND a git admin id. A claimed slot keeps
# admin id _pool-N after `git worktree move`, so the admin-dir test is what keeps a
# re-added _pool-N from colliding.
pool_next_index() {
    local repo_root="$1" wt_parent="$2" gcd n=1
    gcd="$(_pool_gcd "$repo_root")" || return 1
    while [ -e "$wt_parent/_pool-$n" ] \
        || git -C "$repo_root" show-ref --verify --quiet "refs/heads/wt-pool/$n" \
        || [ -e "$gcd/worktrees/_pool-$n" ]; do
        n=$((n + 1))
    done
    printf '%s\n' "$n"
}

# pool_is_slot <wt_parent> <path>
# 0 only for a registered worktree directly under <wt_parent>, named _pool-<digits>,
# with branch wt-pool/<digits> checked out. Guards every destructive pool step.
pool_is_slot() {
    local wt_parent="$1" path="$2" cpath base branch p registered=0
    [ -d "$path" ] || return 1
    cpath="$(_pool_canon "$path")"
    [ "$(_pool_canon "$(dirname "$cpath")")" = "$(_pool_canon "$wt_parent")" ] || return 1
    base="${cpath##*/}"
    case "$base" in _pool-*) ;; *) return 1 ;; esac
    _pool_is_uint "${base#_pool-}" || return 1
    while IFS= read -r p; do
        [ -n "$p" ] || continue
        if [ "$(_pool_canon "$p")" = "$cpath" ]; then registered=1; break; fi
    done <<< "$(_pool_worktree_paths "$cpath")"
    [ "$registered" = 1 ] || return 1
    branch="$(git -C "$cpath" symbolic-ref --short HEAD 2>/dev/null)" || return 1
    case "$branch" in wt-pool/*) ;; *) return 1 ;; esac
    _pool_is_uint "${branch#wt-pool/}"
}

# pool_mark_ready <path> — record the slot HEAD in its git admin dir. Refill's
# last step: a slot without a matching marker is never claimed.
pool_mark_ready() {
    local gd head
    gd="$(git -C "$1" rev-parse --absolute-git-dir)" || return 1
    head="$(git -C "$1" rev-parse HEAD)" || return 1
    printf '%s\n' "$head" > "$gd/wt-pool-ready"
}

# pool_is_ready <path> — 0 only when the marker exists and names the current HEAD.
pool_is_ready() {
    local gd head marker
    gd="$(git -C "$1" rev-parse --absolute-git-dir 2>/dev/null)" || return 1
    [ -f "$gd/wt-pool-ready" ] || return 1
    head="$(git -C "$1" rev-parse HEAD 2>/dev/null)" || return 1
    marker="$(cat "$gd/wt-pool-ready")"
    [ -n "$head" ] && [ "$marker" = "$head" ]
}

# pool_find_ready <repo_root> <wt_parent> — first ready slot path, or nothing.
pool_find_ready() {
    local repo_root="$1" wt_parent="$2" p
    while IFS= read -r p; do
        case "${p##*/}" in _pool-*) ;; *) continue ;; esac
        if pool_is_slot "$wt_parent" "$p" && pool_is_ready "$p"; then
            printf '%s\n' "$p"
            return 0
        fi
    done <<< "$(_pool_worktree_paths "$repo_root")"
    return 0
}

# --- Lock: mkdir under the git common dir (outside the tracked tree) ---------
# owner holds two lines: pid, then epoch seconds. Non-blocking by design.

_pool_lock_dir() {
    local gcd
    gcd="$(_pool_gcd "$1")" || return 1
    printf '%s/wt-pool.lock\n' "$gcd"
}

_pool_lock_write_owner() { printf '%s\n%s\n' "$$" "$(date +%s)" > "$1/owner"; }

# 0 when the lock at <dir> is stale: dead owner pid, owner epoch over 600s old,
# or no owner file in a dir over 10 minutes old.
_pool_lock_is_stale() {
    local lock="$1" pid="" epoch="" now
    now="$(date +%s)"
    if [ -f "$lock/owner" ]; then
        { read -r pid; read -r epoch; } < "$lock/owner" || true
        if _pool_is_uint "$pid"; then
            kill -0 "$pid" 2>/dev/null || return 0
            if _pool_is_uint "$epoch" && [ $((now - epoch)) -gt 600 ]; then return 0; fi
            return 1
        fi
    fi
    # No usable owner yet: the acquirer may sit between mkdir and the owner write.
    [ -n "$(find "$lock" -maxdepth 0 -mmin +10 2>/dev/null)" ]
}

# 0 when a lock dir exists and is not stale.
_pool_lock_live() {
    local lock
    lock="$(_pool_lock_dir "$1")" || return 1
    [ -d "$lock" ] && ! _pool_lock_is_stale "$lock"
}

pool_lock_acquire() {
    local lock
    lock="$(_pool_lock_dir "$1")" || return 1
    if mkdir "$lock" 2>/dev/null; then
        _pool_lock_write_owner "$lock"
        return 0
    fi
    if _pool_lock_is_stale "$lock"; then
        rm -rf "$lock"
        echo "wt-pool: recovered stale lock" >&2
        if mkdir "$lock" 2>/dev/null; then
            _pool_lock_write_owner "$lock"
            return 0
        fi
    fi
    return 1
}

pool_lock_release() {
    local lock pid=""
    lock="$(_pool_lock_dir "$1")" || return 0
    if [ -f "$lock/owner" ]; then read -r pid < "$lock/owner" || true; fi
    if [ "$pid" = "$$" ]; then rm -rf "$lock"; fi
    return 0
}

# _pool_remove_slot <path> — remove a slot worktree and its branch, refusing
# anything pool_is_slot rejects. Reads REPO_ROOT, WT_PARENT.
_pool_remove_slot() {
    local p="$1" br
    if ! pool_is_slot "$WT_PARENT" "$p"; then
        echo "wt-pool: refusing to remove non-slot $p" >&2
        return 1
    fi
    br="$(git -C "$p" symbolic-ref --short HEAD)" || return 1
    git -C "$REPO_ROOT" worktree remove --force "$p" || return 1
    git -C "$REPO_ROOT" branch -D "$br" >/dev/null || return 1
}

# pool_refill — build one ready slot when none exists. Reads REPO_ROOT, WT_PARENT,
# MAIN_IBL5. Called as `pool_refill; exit $?`, so the EXIT trap releases the lock.
pool_refill() {
    local p n slot
    if [ "${WT_POOL:-}" = 0 ]; then
        echo "wt-pool: disabled (WT_POOL=0)"
        return 0
    fi
    if ! pool_lock_acquire "$REPO_ROOT"; then
        echo "wt-pool: refill skipped, lock busy"
        return 0
    fi
    trap 'pool_lock_release "$REPO_ROOT"' EXIT
    # A slot never carries a session file: no Claude session owns it.
    unset CLAUDE_CODE_SESSION_ID

    git -C "$REPO_ROOT" worktree prune
    while IFS= read -r p; do
        case "${p##*/}" in _pool-*) ;; *) continue ;; esac
        if ! pool_is_slot "$WT_PARENT" "$p"; then
            echo "wt-pool: skipping non-slot $p" >&2
            continue
        fi
        if pool_is_ready "$p"; then continue; fi
        _pool_remove_slot "$p"
        echo "wt-pool: reaped half-built slot $p"
    done <<< "$(_pool_worktree_paths "$REPO_ROOT")"

    if [ -n "$(pool_find_ready "$REPO_ROOT" "$WT_PARENT")" ]; then
        echo "wt-pool: pool full"
        return 0
    fi

    wt_sync_base "$REPO_ROOT" master
    n="$(pool_next_index "$REPO_ROOT" "$WT_PARENT")"
    slot="$WT_PARENT/_pool-$n"
    echo "wt-pool: building warm slot $slot..."
    git -C "$REPO_ROOT" worktree add --quiet "$slot" -b "wt-pool/$n" master
    git -C "$slot" config "branch.wt-pool/$n.iblBase" master
    materialize_worktree_config "$slot/ibl5/config.php" "$MAIN_IBL5"
    materialize_worktree_config_local "$slot/ibl5/config.local.php" "$MAIN_IBL5"
    wt_link_shared "$slot/ibl5" "$MAIN_IBL5"
    wt_hide_compose "$slot"
    wt_build_css "$slot/ibl5" "$MAIN_IBL5"
    # Last step: the marker is what makes the slot claimable.
    pool_mark_ready "$slot"
    echo "wt-pool: slot ready at $slot"
}

# _pool_discard <slot> <reason> — remove a bad slot under the guard, release the
# lock, print the bypass.
_pool_discard() {
    _pool_remove_slot "$1" || echo "wt-pool: could not remove $1" >&2
    pool_lock_release "$REPO_ROOT"
    _pool_bypass "$2"
}

# pool_try_claim — claim the ready slot as $NAME at $WT_ROOT. Reads NAME,
# BASE_BRANCH, WT_ROOT, REPO_ROOT, WT_PARENT; sets POOL_CSS_STALE. Returns 0 only
# on a finished claim. Runs as an `if` condition, so `set -e` is off here and every
# step checks its own status.
pool_try_claim() {
    local slot old br st changed gd
    POOL_CSS_STALE=1
    if [ "${WT_POOL:-}" = 0 ]; then _pool_bypass "WT_POOL=0"; return 1; fi
    if [ "$BASE_BRANCH" != master ]; then _pool_bypass "--base $BASE_BRANCH"; return 1; fi
    if git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$NAME"; then
        _pool_bypass "branch exists"
        return 1
    fi
    if ! pool_lock_acquire "$REPO_ROOT"; then _pool_bypass "pool busy"; return 1; fi
    slot="$(pool_find_ready "$REPO_ROOT" "$WT_PARENT")"
    if [ -z "$slot" ]; then
        pool_lock_release "$REPO_ROOT"
        _pool_bypass "no ready slot"
        return 1
    fi

    # Pre-move validation. A failed check removes the slot and creates cold.
    if ! st="$(git -C "$slot" status --porcelain)" || [ -n "$st" ]; then
        _pool_discard "$slot" "slot dirty"
        return 1
    fi
    if ! old="$(git -C "$slot" rev-parse HEAD)" \
        || ! git -C "$REPO_ROOT" merge-base --is-ancestor "$old" master; then
        _pool_discard "$slot" "slot diverged"
        return 1
    fi
    if ! git -C "$slot" merge --ff-only --quiet master >/dev/null 2>&1; then
        _pool_discard "$slot" "fast-forward failed"
        return 1
    fi
    if ! changed="$(git -C "$slot" diff --name-only "$old"..HEAD -- ibl5 \
            ':!ibl5/tests' ':!ibl5/docs' ':!ibl5/migrations')" || [ -n "$changed" ]; then
        POOL_CSS_STALE=1
    else
        POOL_CSS_STALE=0
    fi

    # Rename first: undoing a rename is one `git branch -m`.
    br="$(git -C "$slot" symbolic-ref --short HEAD)"
    if ! git -C "$REPO_ROOT" branch -m "$br" "$NAME"; then
        pool_lock_release "$REPO_ROOT"
        _pool_bypass "rename failed"
        return 1
    fi
    if ! git -C "$REPO_ROOT" worktree move "$slot" "$WT_ROOT"; then
        git -C "$REPO_ROOT" branch -m "$NAME" "$br" \
            || echo "wt-pool: WARNING could not rename $NAME back to $br" >&2
        pool_lock_release "$REPO_ROOT"
        _pool_bypass "move failed"
        return 1
    fi
    if gd="$(git -C "$WT_ROOT" rev-parse --absolute-git-dir)"; then
        rm -f "$gd/wt-pool-ready"
    fi
    pool_lock_release "$REPO_ROOT"
    echo "Claimed warm slot ${slot##*/} as '$NAME'"
    return 0
}

# _pool_plist_write <dest> <repo_root> — the refill one-shot plist. <repo_root> is
# the canonical root, never a worktree, which can be removed before the job fires.
_pool_plist_write() {
    ljob_write_runner_plist "$1" "$POOL_LABEL" "'$2/bin/wt-new' --pool-refill" "$2" \
        "$HOME/Library/Logs/ibl5-wt-pool-refill.log"
}

# pool_plist_print <repo_root> — print the plist, then warn on stderr for each of
# git, bunx, bash that resolves outside the job PATH.
pool_plist_print() {
    local root="$1" tmp path_str bin dir
    tmp="$(mktemp "${TMPDIR:-/tmp}/wt-pool-plist.XXXXXX")" || return 1
    _pool_plist_write "$tmp" "$root"
    cat "$tmp"
    path_str="$(sed -n 's|.*<key>PATH</key><string>\(.*\)</string>.*|\1|p' "$tmp")"
    rm -f "$tmp"
    for bin in git bunx bash; do
        dir="$(command -v "$bin" 2>/dev/null)" || continue
        dir="${dir%/*}"
        case ":$path_str:" in
            *":$dir:"*) ;;
            *) echo "wt-pool: WARNING $bin at $dir is outside the job PATH" >&2 ;;
        esac
    done
    return 0
}

# pool_kick_refill — schedule a refill without blocking the caller. Reads REPO_ROOT
# and WT_POOL_KICK: none | inline (foreground, test seam) | print (plist to stdout)
# | unset/empty (launchd one-shot on macOS). Always returns 0: a kick problem never
# fails a worktree that already exists.
pool_kick_refill() {
    local kick="${WT_POOL_KICK:-}" agents plist
    case "$kick" in
        none) echo "wt-pool: refill kick disabled"; return 0 ;;
        inline)
            "$REPO_ROOT/bin/wt-new" --pool-refill || echo "wt-pool: inline refill failed" >&2
            return 0
            ;;
        print) pool_plist_print "$REPO_ROOT"; return 0 ;;
        '') ;;
        *) echo "wt-pool: unknown WT_POOL_KICK=$kick; refill not kicked" >&2; return 0 ;;
    esac
    if [ "$(uname -s)" != Darwin ]; then
        echo "wt-pool: refill not scheduled on this platform; run bin/wt-new --pool-refill"
        return 0
    fi
    if _pool_lock_live "$REPO_ROOT"; then
        echo "wt-pool: refill already running"
        return 0
    fi
    agents="$(ljob_agents_dir)"
    plist="$agents/$POOL_LABEL.plist"
    if mkdir -p "$agents" "$HOME/Library/Logs" \
        && _pool_plist_write "$plist" "$REPO_ROOT" \
        && ljob_bootstrap "$POOL_LABEL" "$plist"; then
        echo "wt-pool: refill scheduled ($POOL_LABEL)"
    else
        echo "wt-pool: WARNING could not schedule the refill; run bin/wt-new --pool-refill" >&2
    fi
    return 0
}
