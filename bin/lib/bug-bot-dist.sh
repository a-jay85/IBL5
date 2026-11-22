# shellcheck shell=bash
# bug-bot-dist.sh: one staleness test for the bug-bot build, shared by
# bin/bug-pipeline-check (reports it) and bin/bug-pipeline-tick (fixes it).
# Sharing it means the check and the fixer cannot disagree on "stale".
# Sourced, never executed. Sets no shell option and never calls exit.

# bug_bot_mtime <file> — mtime epoch. GNU form first (shell-portability rule).
bug_bot_mtime() {
    stat -c %Y "$1" 2>/dev/null || stat -f %m "$1" 2>/dev/null
}

# bug_bot_src_ct <repo-root> <git-bin> — commit time of the newest commit that
# touched ibl5/IBLbot/src/bug-bot. Prints nothing when git has no answer.
bug_bot_src_ct() {
    "$2" -C "$1" log -1 --format=%ct -- ibl5/IBLbot/src/bug-bot 2>/dev/null
}

# bug_bot_dist_newest <repo-root> — newest file mtime under
# ibl5/IBLbot/dist/bug-bot. Prints nothing when the dist is absent or empty.
bug_bot_dist_newest() {
    local dist="$1/ibl5/IBLbot/dist/bug-bot" newest="" m f
    [ -d "$dist" ] || return 0
    while IFS= read -r f; do
        m="$(bug_bot_mtime "$f")"
        case "$m" in ''|*[!0-9]*) continue ;; esac
        if [ -z "$newest" ] || [ "$m" -gt "$newest" ]; then newest="$m"; fi
    done < <(find "$dist" -type f 2>/dev/null)
    [ -n "$newest" ] && printf '%s\n' "$newest"
    return 0
}
