#!/usr/bin/env bash
# bin/hooks/wt-sync-session-start.sh — SessionStart hook: sync THIS worktree.
#
# Installed (as a symlink) into ~/.claude/hooks/ by bin/wt-sync-cron-setup --install-hook.
# Prints one wt-sync verdict line on stdout, which Claude Code injects as session
# context. Silent in the main checkout. NEVER exits non-zero: a hook that fails
# is a hook that blocks a session start. See ADR-0133.
set -u

GIT_BIN="$(command -v git || true)"
[ -n "$GIT_BIN" ] || exit 0

TARGET="${CLAUDE_PROJECT_DIR:-$PWD}"
ROOT="$("$GIT_BIN" -C "$TARGET" rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$ROOT" ] || exit 0

# Linked worktree iff the per-worktree git dir differs from the shared common dir.
GITDIR="$("$GIT_BIN" -C "$ROOT" rev-parse --absolute-git-dir 2>/dev/null || true)"
COMMON="$("$GIT_BIN" -C "$ROOT" rev-parse --git-common-dir 2>/dev/null || true)"
[ -n "$GITDIR" ] && [ -n "$COMMON" ] || exit 0
COMMON="$(cd "$ROOT" && cd "$COMMON" 2>/dev/null && pwd -P || true)"
[ -n "$COMMON" ] || exit 0
[ "$GITDIR" = "$COMMON" ] && exit 0      # main checkout — stay silent

# The common dir IS <main-checkout>/.git, so the durable tick lives one level up.
TICK="$(dirname "$COMMON")/bin/wt-sync-tick"
[ -x "$TICK" ] || exit 0

VERDICT="$("$TICK" --only "$ROOT" 2>/dev/null || true)"
[ -n "$VERDICT" ] && printf 'wt-sync: %s\n' "$VERDICT"
exit 0
