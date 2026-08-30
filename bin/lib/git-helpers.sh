# shellcheck shell=bash
# Shared git-layout helpers.
# Source this file from scripts that need canonical repo root resolution.
#
# Usage: source "$(dirname "$0")/lib/git-helpers.sh"

# Resolve the canonical (main checkout) repo root.
# In a worktree, .git is a file containing `gitdir: <main>/.git/worktrees/<name>`;
# traversing up three levels reaches the main repo root. In the main checkout,
# .git is a directory and the passed-in path is returned unchanged.
resolve_canonical_root() {
    local repo_root="$1"
    if [ -f "$repo_root/.git" ]; then
        local gitdir
        gitdir=$(awk '/^gitdir:/ {print $2}' "$repo_root/.git")
        if [ -n "$gitdir" ]; then
            (cd "$gitdir/../../.." && pwd)
            return
        fi
    fi
    echo "$repo_root"
}

# Return 0 if <repo_root> IS the main checkout (its canonical root resolves to
# itself), 1 if it is a linked worktree. Pure path logic — no `git`, no exit —
# so callers compose it in an `if` and own their own refusal message. Mirrors the
# discriminator in resolve_canonical_root: main checkout's .git is a directory,
# so canonical == passed-in root; a worktree's .git is a file resolving elsewhere.
is_main_checkout() {
    local repo_root="$1"
    local canonical
    canonical=$(resolve_canonical_root "$repo_root")
    [ "$repo_root" = "$canonical" ]
}

# Print <path> with each component's true on-disk case.
# macOS APFS is case-insensitive: launching from ~/github vs ~/GitHub yields the
# same files but different path strings, which splits worktree registration and
# Claude rule-loading across two keys. Rebuild the path from parent-directory
# listings so the result is always the canonical case, regardless of launch cwd.
canonicalize_case() {
    local input="${1%/}" parent="/" comp match
    local -a parts
    IFS='/' read -ra parts <<< "$input"
    for comp in ${parts[@]+"${parts[@]}"}; do
        [ -z "$comp" ] && continue
        # Find the entry in $parent matching $comp case-insensitively. Subshell
        # isolates nocasematch so it never leaks to the caller. Quoted RHS makes
        # the [[ ]] compare a literal (not a glob).
        match=$(
            shopt -s nocasematch
            for entry in "$parent"/* "$parent"/.*; do
                [ -e "$entry" ] || continue
                base=${entry##*/}
                { [ "$base" = "." ] || [ "$base" = ".." ]; } && continue
                if [[ "$base" == "$comp" ]]; then printf '%s' "$base"; break; fi
            done
        )
        [ -z "$match" ] && match="$comp"   # component not created yet — keep as-is
        if [ "$parent" = "/" ]; then parent="/$match"; else parent="$parent/$match"; fi
    done
    printf '%s\n' "$parent"
}

# Resolve the parent directory that holds this repo's worktrees.
# Worktrees live OUTSIDE the repo tree, as a canonical-case sibling
# (<parent-of-main>/IBL5-worktrees), so the repo-root .claude/rules is never a
# filesystem ancestor of a worktree file — which is what doubled rule injection
# when worktrees were nested. See ibl5/docs/decisions/0046-worktrees-outside-repo.md.
worktrees_parent_dir() {
    local canonical
    canonical="$(canonicalize_case "$(resolve_canonical_root "${1:-.}")")"
    printf '%s/IBL5-worktrees\n' "$(dirname "$canonical")"
}

# Return 0 if <dir> resides in a linked worktree (not the main checkout).
# A linked worktree's git-dir (.git/worktrees/<name>) differs from its
# git-common-dir (.git); in the main checkout they are identical. This is
# layout-independent — it works wherever the worktree physically lives.
is_in_worktree() {
    local dir="${1:-.}" gd gcd
    gd=$(git -C "$dir" rev-parse --absolute-git-dir 2>/dev/null) || return 1
    gcd=$(git -C "$dir" rev-parse --path-format=absolute --git-common-dir 2>/dev/null) || return 1
    [ "$gd" != "$gcd" ]
}

# Materialize a REAL config.php at <dest> from <main_ibl5_dir>.
# config.php can't be a symlink into a worktree: the absolute host target doesn't
# resolve inside Docker, so every request 500s (`Failed to open stream`). It's
# league-agnostic (DB host is env-injected), so a snapshot copy works everywhere.
# Removes any pre-existing symlink first. Prefers the real config.php; falls back
# to the tracked config.php.example (placeholders) with a warning; returns 1 if
# neither source exists. Callers pass the CANONICAL main ibl5 dir — never a
# worktree's own, or a from-worktree invocation would cp the file onto itself.
materialize_worktree_config() {
    local dest="$1" main_ibl5="$2"
    [ -L "$dest" ] && rm -f "$dest"
    if [ -s "$main_ibl5/config.php" ]; then
        cp "$main_ibl5/config.php" "$dest"
        # Announce the $dbname being propagated when no DB_NAME is in the
        # environment — that's exactly when the copied fallback becomes
        # load-bearing. A stale one silently fans out to every new worktree
        # otherwise (observed: 40 worktrees inheriting 'iblhoops_rehearsal').
        # Echo only, no policy: the name is league-agnostic by design.
        if [ -z "${DB_NAME:-}" ] && command -v php >/dev/null 2>&1; then
            local propagated_db
            propagated_db=$(php -r "error_reporting(0); include '$dest'; echo \$dbname;" 2>/dev/null)
            [ -n "$propagated_db" ] && echo "config.php: DB_NAME unset — worktree will use \$dbname fallback '$propagated_db'." >&2
        fi
    elif [ -s "$main_ibl5/config.php.example" ]; then
        echo "WARNING: $main_ibl5/config.php missing — copying config.php.example (placeholder values)." >&2
        cp "$main_ibl5/config.php.example" "$dest"
    else
        echo "Error: no config.php or config.php.example in $main_ibl5 — worktree will 500 on every request." >&2
        return 1
    fi
}

# Print the basename of every entry under <repo-relative-dir> as it exists on each
# remote-tracking ref under refs/remotes/origin/. Used by the number allocators so
# a number claimed on a pushed-but-unmerged branch is never handed out twice.
#
# STRICTLY OFFLINE. for-each-ref and ls-tree read objects already in .git; neither
# contacts the network. Never add fetch/ls-remote here — an allocator that needs
# the network becomes an allocator that fails on a plane. refs/remotes lives in the
# git common dir, so this returns the same set from a linked worktree as from the
# main checkout. Degrades open: no origin remote, or a ref with no such directory,
# yields nothing rather than an error.
scan_origin_refs() {
    local dir_path="${1%/}" repo="${2:-.}" ref
    while IFS= read -r ref; do
        git -C "$repo" ls-tree --name-only "$ref" "$dir_path/" 2>/dev/null
    done < <(git -C "$repo" for-each-ref --format='%(refname)' refs/remotes/origin/ 2>/dev/null) \
        | sed 's|.*/||'
    return 0
}

# Print the basename of every entry under <repo-relative-dir> in each registered
# worktree of this repo, including the main checkout. Reads the working tree on
# disk (not a ref), so it sees a number allocated in a sibling worktree that has
# not been committed yet. awk strips the "worktree " prefix without splitting on
# whitespace, so worktree paths containing spaces survive. Degrades open.
scan_worktrees() {
    local rel_path="${1%/}" repo="${2:-.}" wt
    while IFS= read -r wt; do
        [ -d "$wt/$rel_path" ] || continue
        ls -1 "$wt/$rel_path" 2>/dev/null
    done < <(git -C "$repo" worktree list --porcelain 2>/dev/null \
        | awk '/^worktree /{sub(/^worktree /, ""); print}')
    return 0
}
