# shellcheck shell=bash
# bin/lib/automouse-queue-order.sh — automouse queue naming and run order.
# Source this file; do not execute it directly. No top-level side effects
# beyond one stat-flavor probe, so it is safe to source under `set -euo pipefail`.
#
# The queue directory owns run order. Each entry is named NNN-<slug>.md and
# order is the plain lexical `ls -1` order of those names. Only queue code
# renames entries, so rewriting a plan file in ~/claude-plans/ cannot move it.
# mtime is never an ordering key here: normalize_queue reads it exactly once,
# to convert a legacy bare-named queue into the order it already had.
#
# bin/automouse/lib-queue-order is a symlink to this file, so a runner copy
# that sources "$SELF_DIR/lib-queue-order" (the test fixtures do) loads it.
#
# Provides:
#   entry_to_slug <entry>          030-foo.md | foo.md | foo  -> foo
#   entry_to_name <entry>          030-foo.md -> foo.md (locks, sidecars, handoff, done/, skipped/)
#   slug_to_pattern <slug>         -> [0-9][0-9][0-9]-foo.md
#   queue_entry_for_slug <dir> <slug>  entry filename, or return 1 with no output
#   queue_entries [dir]            entry filenames in run order
#   queue_entries_ordered [dir]    full entry paths in run order
#   next_prefix [dir]              prefix for a new last entry; return 1 when exhausted
#   renumber_queue <dir> [entry…]  rename to 010, 020, … in the given (or current) order
#   normalize_queue [dir]          one-shot cutover of bare <slug>.md entries
#   queue_lock <dir> [tries]       take <dir>/.normalize.lock; return 1 after <tries> 1s waits
#   queue_unlock <dir>             release it
#   _renumber_queue_locked <dir> [entry…]  renumber_queue for a caller already holding the lock
#
# Every rename inside the queue holds .normalize.lock: normalize_queue,
# renumber_queue, the CLI's reorder, and an append. renumber_queue parks entries
# in the 900-999 band between its phases, and next_prefix skips that band, so an
# append that ran mid-renumber would reuse a prefix the renumber is about to give out.
#   lstat_mtime <path>             epoch mtime of the path itself (cutover only)

if stat -c '%Y' / >/dev/null 2>&1; then
    _LIB_STAT_FMT=gnu
else
    _LIB_STAT_FMT=bsd
fi

# lstat_mtime <path> — epoch of the path's own mtime, never dereferences.
lstat_mtime() {
    if [ "$_LIB_STAT_FMT" = gnu ]; then
        stat -c '%Y' "$1" 2>/dev/null
    else
        stat -f '%m' "$1" 2>/dev/null
    fi
}

# entry_to_slug <entry> — strip one leading NNN- (exactly three digits) and .md.
# A slug that merely starts with digits (2026-roster-audit.md) keeps them all.
entry_to_slug() {
    local n=${1##*/}
    n=${n%.md}
    case "$n" in
        [0-9][0-9][0-9]-*) n=${n#????} ;;
    esac
    printf '%s\n' "$n"
}

entry_to_name() {
    printf '%s.md\n' "$(entry_to_slug "$1")"
}

slug_to_pattern() {
    printf '[0-9][0-9][0-9]-%s.md\n' "$(entry_to_slug "$1")"
}

# _entry_prefix <entry> — numeric prefix of an entry; a bare entry counts as 0.
_entry_prefix() {
    case "$1" in
        [0-9][0-9][0-9]-*.md) printf '%d\n' "$((10#${1:0:3}))" ;;
        *) printf '0\n' ;;
    esac
}

# queue_entry_for_slug <dir> <slug> — the entry filename for <slug>, or return 1.
# Two entries for one slug should be unreachable; if it happens, the lexically
# first wins rather than aborting a nightly run. A bare <slug>.md that landed
# after normalize_queue ran (queue_entries lists it, so it can be claimed) is the
# fallback, so every membership check agrees with the claim path.
queue_entry_for_slug() {
    local dir=$1 slug f
    slug=$(entry_to_slug "$2")
    for f in "$dir"/[0-9][0-9][0-9]-"$slug".md "$dir/$slug.md"; do
        if [ -L "$f" ] || [ -e "$f" ]; then
            printf '%s\n' "${f##*/}"
            return 0
        fi
    done
    return 1
}

# queue_entries [dir] — entry filenames in run order. The one place order is read.
queue_entries() {
    local dir="${1:-$QUEUE_DIR}" f
    for f in "$dir"/*.md; do
        if [ -L "$f" ] || [ -e "$f" ]; then
            printf '%s\n' "${f##*/}"
        fi
    done | LC_ALL=C sort
}

# queue_entries_ordered [dir] — full paths, same order as queue_entries.
queue_entries_ordered() {
    local dir="${1:-$QUEUE_DIR}" e
    queue_entries "$dir" | while IFS= read -r e; do
        printf '%s/%s\n' "$dir" "$e"
    done
}

# next_prefix [dir] — the 3-digit prefix a newly appended entry gets: the highest
# prefix outside the reserved 900-999 staging band, plus 10. Returns 1 with no
# output past 890, so the caller compacts with renumber_queue and asks again.
next_prefix() {
    local dir="${1:-$QUEUE_DIR}" e p max=0
    while IFS= read -r e; do
        p=$(_entry_prefix "$e")
        if [ "$p" -lt 900 ] && [ "$p" -gt "$max" ]; then max=$p; fi
    done < <(queue_entries "$dir")
    if [ $((max + 10)) -gt 890 ]; then return 1; fi
    printf '%03d\n' $((max + 10))
}

# queue_lock <dir> [tries] — take <dir>/.normalize.lock, which sits outside the
# *.md glob. mkdir is atomic. A lock older than 60s belongs to a crashed holder
# and is removed. The default 65 tries outlasts that, so a killed holder never
# fails the next caller.
queue_lock() {
    local lock="$1/.normalize.lock" max=${2:-65} tries=0 now age
    while ! mkdir "$lock" 2>/dev/null; do
        now=$(date +%s)
        age=$(lstat_mtime "$lock") || age=$now
        if [ $((now - age)) -gt 60 ]; then
            rmdir "$lock" 2>/dev/null || true
            continue
        fi
        tries=$((tries + 1))
        if [ "$tries" -gt "$max" ]; then return 1; fi
        sleep 1
    done
}

queue_unlock() {
    rmdir "$1/.normalize.lock" 2>/dev/null || true
}

# renumber_queue <dir> [entry…] — rename the queue to 010, 020, … in the given
# order (default: the current order), holding the queue lock.
renumber_queue() {
    local dir=$1 rc=0
    if ! queue_lock "$dir"; then
        echo "renumber_queue: another queue rename holds $dir/.normalize.lock; nothing renamed." >&2
        return 1
    fi
    _renumber_queue_locked "$@" || rc=$?
    queue_unlock "$dir"
    return "$rc"
}

# _renumber_queue_locked <dir> [entry…] — the renumber itself; the caller holds
# the queue lock. Two phases through the reserved 900-999 band, so every
# intermediate state is a complete queue of *.md entries and no rename ever
# targets a name another entry holds.
_renumber_queue_locked() {
    local dir=$1 e slug i n
    shift
    local -a order=()
    if [ $# -gt 0 ]; then
        order=("$@")
    else
        while IFS= read -r e; do order+=("$e"); done < <(queue_entries "$dir")
    fi
    n=${#order[@]}
    if [ "$n" -eq 0 ]; then return 0; fi
    # 010…890 holds 89 entries. More would stage past 999 and number past 890,
    # into names entry_to_slug cannot strip, so refuse before renaming anything.
    if [ "$n" -gt 89 ]; then
        echo "renumber_queue: $n entries exceed the 89-entry prefix range; nothing renamed." >&2
        return 1
    fi
    # The runner may dispose an entry (move it out of the queue) while this
    # runs, so a vanished source is skipped, never fatal: aborting between the
    # phases would strand entries in the 900-999 band.
    local -a staged=()
    local name
    i=0
    for e in "${order[@]}"; do
        if [ ! -L "$dir/$e" ] && [ ! -e "$dir/$e" ]; then continue; fi
        slug=$(entry_to_slug "$e")
        name="$(printf '%03d' $((900 + i)))-$slug.md"
        if [ "$e" != "$name" ]; then
            if ! mv "$dir/$e" "$dir/$name"; then
                if [ -L "$dir/$e" ] || [ -e "$dir/$e" ]; then return 1; fi
                continue
            fi
        fi
        staged+=("$name")
        i=$((i + 1))
    done
    i=0
    for e in ${staged[@]+"${staged[@]}"}; do
        slug=$(entry_to_slug "$e")
        if ! mv "$dir/$e" "$dir/$(printf '%03d' $(((i + 1) * 10)))-$slug.md"; then
            if [ -L "$dir/$e" ] || [ -e "$dir/$e" ]; then return 1; fi
            continue
        fi
        i=$((i + 1))
    done
}

# normalize_queue [dir] — one-shot cutover. Prefixes every bare <slug>.md entry,
# keeping the order the pre-change code ran (oldest symlink lstat mtime first).
# Fast path: nothing bare means nothing to do. Serialized by the queue lock.
# Never fails the caller.
normalize_queue() {
    local dir="${1:-$QUEUE_DIR}" e
    _queue_has_bare "$dir" || return 0
    queue_lock "$dir" 10 || return 0
    if ! _queue_has_bare "$dir"; then
        queue_unlock "$dir"
        return 0
    fi
    local -a order=()
    # Prefixed entries keep their lexical order (an explicit reorder is not
    # undone by a late bare arrival); bare entries follow, oldest mtime first.
    while IFS= read -r e; do order+=("$e"); done < <(
        for e in "$dir"/*.md; do
            if [ -L "$e" ] || [ -e "$e" ]; then
                case "${e##*/}" in
                    ([0-9][0-9][0-9]-*.md) printf '0 0 %s\n' "${e##*/}" ;;
                    (*) printf '1 %s %s\n' "$(lstat_mtime "$e" || echo 0)" "${e##*/}" ;;
                esac
            fi
        done | LC_ALL=C sort -k1,1n -k2,2n -k3 | while IFS= read -r line; do
            printf '%s\n' "${line#* * }"
        done
    )
    _renumber_queue_locked "$dir" ${order[@]+"${order[@]}"} || true
    queue_unlock "$dir"
    return 0
}

_queue_has_bare() {
    local f
    for f in "$1"/*.md; do
        if [ -L "$f" ] || [ -e "$f" ]; then
            case "${f##*/}" in
                [0-9][0-9][0-9]-*.md) ;;
                *) return 0 ;;
            esac
        fi
    done
    return 1
}
