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
