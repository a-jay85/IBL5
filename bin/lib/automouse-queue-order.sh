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
# first wins rather than aborting a nightly run.
queue_entry_for_slug() {
    local dir=$1 slug f
    slug=$(entry_to_slug "$2")
    for f in "$dir"/[0-9][0-9][0-9]-"$slug".md; do
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

# renumber_queue <dir> [entry…] — rename the queue to 010, 020, … in the given
# order (default: the current order). Two phases through the reserved 900-999
# band, so every intermediate state is a complete queue of *.md entries and no
# rename ever targets a name another entry holds.
renumber_queue() {
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
    local -a staged=()
    i=0
    for e in "${order[@]}"; do
        slug=$(entry_to_slug "$e")
        staged[i]="$(printf '%03d' $((900 + i)))-$slug.md"
        if [ "$e" != "${staged[i]}" ]; then mv "$dir/$e" "$dir/${staged[i]}"; fi
        i=$((i + 1))
    done
    i=0
    for e in "${staged[@]}"; do
        slug=$(entry_to_slug "$e")
        mv "$dir/$e" "$dir/$(printf '%03d' $(((i + 1) * 10)))-$slug.md"
        i=$((i + 1))
    done
}

# normalize_queue [dir] — one-shot cutover. Prefixes every bare <slug>.md entry,
# keeping the order the pre-change code ran (oldest symlink lstat mtime first).
# Fast path: nothing bare means nothing to do. Serialized by .normalize.lock,
# which sits outside the *.md glob. Never fails the caller.
normalize_queue() {
    local dir="${1:-$QUEUE_DIR}" lock tries=0 now age e
    _queue_has_bare "$dir" || return 0
    lock="$dir/.normalize.lock"
    while ! mkdir "$lock" 2>/dev/null; do
        now=$(date +%s)
        age=$(lstat_mtime "$lock") || age=$now
        if [ $((now - age)) -gt 60 ]; then
            rmdir "$lock" 2>/dev/null || true
            continue
        fi
        tries=$((tries + 1))
        if [ "$tries" -gt 10 ]; then return 0; fi
        sleep 1
        _queue_has_bare "$dir" || return 0
    done
    if ! _queue_has_bare "$dir"; then
        rmdir "$lock" 2>/dev/null || true
        return 0
    fi
    local -a order=()
    while IFS= read -r e; do order+=("$e"); done < <(
        for e in "$dir"/*.md; do
            if [ -L "$e" ] || [ -e "$e" ]; then
                printf '%s %s\n' "$(lstat_mtime "$e" || echo 0)" "${e##*/}"
            fi
        done | LC_ALL=C sort -k1,1n -k2 | while IFS= read -r line; do
            printf '%s\n' "${line#* }"
        done
    )
    renumber_queue "$dir" ${order[@]+"${order[@]}"} || true
    rmdir "$lock" 2>/dev/null || true
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
