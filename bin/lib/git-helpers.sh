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

# Print the absolute path of the main checkout for the repo containing <dir>
# (default: the current directory). Answers from the main checkout, a linked
# worktree, or any subdirectory of either, and from any cwd when <dir> is
# given explicitly. Git-based: resolve_canonical_root is path-only and needs a
# worktree top-level, so it cannot answer from a subdir.
# Output is physical (pwd -P), matching the first `git worktree list
# --porcelain` entry, so macOS /var vs /private/var never splits the two.
# Prints nothing and returns 1 when <dir> is not inside a git repo.
main_checkout_root() {
    local dir="${1:-.}" gcd rel
    gcd=$(git -C "$dir" rev-parse --path-format=absolute --git-common-dir 2>/dev/null) || gcd=""
    if [ "${gcd#/}" = "$gcd" ]; then
        # git < 2.31 lacks --path-format and echoes the flag back; fall back to
        # the relative common dir resolved from <dir>.
        rel=$(git -C "$dir" rev-parse --git-common-dir 2>/dev/null) || return 1
        [ -n "$rel" ] || return 1
        gcd=$(cd "$dir" 2>/dev/null && cd "$rel" 2>/dev/null && pwd -P) || return 1
    fi
    (cd "$(dirname "$gcd")" 2>/dev/null && pwd -P) || return 1
}

# Materialize a REAL config.php at <dest> from <main_ibl5_dir>.
# config.php can't be a symlink into a worktree: the absolute host target doesn't
# resolve inside Docker, so every request 500s (`Failed to open stream`). It's
# league-agnostic (DB credentials come from config.local.php, materialized alongside), so a snapshot copy works everywhere.
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

# Materialize config.local.php (the gitignored DB-credential file config.php
# requires) at <dest> from <main_ibl5_dir>. Same shape as
# materialize_worktree_config: a real copy, never a symlink (the absolute host
# target does not resolve inside Docker). Prefers the main checkout's
# config.local.php; falls back to the tracked config.local.php.example, whose
# values are the docker-compose.yml stack defaults, so the fallback is a working
# worktree stack and only announces itself. Returns 1 when neither exists:
# config.php dies on a missing config.local.php, so a worktree without one
# 500s on every request.
materialize_worktree_config_local() {
    local dest="$1" main_ibl5="$2"
    [ -L "$dest" ] && rm -f "$dest"
    if [ -s "$main_ibl5/config.local.php" ]; then
        cp "$main_ibl5/config.local.php" "$dest"
    elif [ -s "$main_ibl5/config.local.php.example" ]; then
        echo "config.local.php: none in $main_ibl5 — copying config.local.php.example (Docker stack defaults)." >&2
        cp "$main_ibl5/config.local.php.example" "$dest"
    else
        echo "Error: no config.local.php or config.local.php.example in $main_ibl5 — config.php will die on every request." >&2
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

# --- bin/wt-new tail, shared with the warm pool (bin/lib/wt-pool.sh) ---------

# Record which Claude session created this branch. ~/.claude/hooks/auto-commit-reminder.sh
# compares it to the Stop payload's session_id: the creating session owns the whole
# tree, so its ad-hoc work ships by default; any later session holds. Skipped for a
# human run (var unset or empty).
wt_write_session() {
    local wt_root="$1"
    if [ -n "${CLAUDE_CODE_SESSION_ID:-}" ]; then
        printf '%s\n' "$CLAUDE_CODE_SESSION_ID" \
            > "$(git -C "$wt_root" rev-parse --absolute-git-dir)/wt-new-session"
    fi
}

# Link the shared, gitignored state from the main checkout. -n matters: without it,
# re-linking over an existing symlink to a directory creates the link INSIDE main's
# directory (main/ibl5/vendor/vendor), which a warm-slot claim would otherwise hit.
wt_link_shared() {
    local wt_ibl5="$1" main_ibl5="$2"
    ln -sfn "$main_ibl5/.env" "$wt_ibl5/.env"
    ln -sfn "$main_ibl5/.env.test" "$wt_ibl5/.env.test"
    ln -sfn "$main_ibl5/vendor" "$wt_ibl5/vendor"
    ln -sfn "$main_ibl5/node_modules" "$wt_ibl5/node_modules"
}

# Hide the main docker-compose.yml from the worktree so `docker compose up`
# can't accidentally be run here (replacing main containers with broken mounts).
# Worktree Docker uses bin/wt-up which reads docker/worktree-compose.yml instead.
# --skip-worktree tells git to ignore the deletion so it won't appear in diffs.
wt_hide_compose() {
    local wt_root="$1"
    git -C "$wt_root" update-index --skip-worktree docker-compose.yml
    rm -f "$wt_root/docker-compose.yml"
}

# Build the Tailwind stylesheet; on failure copy the main checkout's.
wt_build_css() {
    local wt_ibl5="$1" main_ibl5="$2"
    mkdir -p "$wt_ibl5/themes/IBL/style"
    (cd "$wt_ibl5" && bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css 2>/dev/null) || {
        echo "CSS build failed — copying from main repo..."
        cp "$main_ibl5/themes/IBL/style/style.css" "$wt_ibl5/themes/IBL/style/style.css" 2>/dev/null || true
    }
}

# Sync local <base> with origin before branching, so a worktree never forks from a
# stale tip. An unreachable origin only warns.
wt_sync_base() {
    local repo_root="$1" base="$2" behind checked_out
    echo "Fetching origin/$base..."
    if git -C "$repo_root" fetch origin "$base" --quiet 2>/dev/null; then
        behind=$(git -C "$repo_root" rev-list --count "$base..origin/$base" 2>/dev/null || echo 0)
        if [ "$behind" -gt 0 ]; then
            echo "Fast-forwarding local $base ($behind commit(s) behind origin)..."
            checked_out="$(git -C "$repo_root" rev-parse --abbrev-ref HEAD 2>/dev/null)"
            if [ "$checked_out" = "$base" ]; then
                # <base> is checked out in the main checkout — git refuses to fetch
                # into a checked-out ref, so merge --ff-only is the only safe path here.
                git -C "$repo_root" merge --ff-only "origin/$base" --quiet
            else
                # <base> is NOT checked out — use fetch refspec to fast-forward the
                # local ref directly without needing it to be checked out.
                git -C "$repo_root" fetch origin "$base:$base" --quiet
            fi
        fi
    else
        echo "Warning: could not reach origin; creating worktree from local $base." >&2
    fi
}
