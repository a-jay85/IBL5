---
name: pr-wt
description: "Switch this session's working directory into the worktree for a given PR number or branch name. Use when the user types /pr-wt <pr-number|branch-name>."
last_verified: 2026-09-04
---

# /pr-wt — enter a PR's worktree

`$ARGUMENTS` is a PR number (`1946`) or a branch name (`plan-architect-phase-drift-guard`).

Run these three steps and nothing else — do not read files, inspect the diff, or summarise the branch.

1. **Only if you called `EnterWorktree` earlier in this session**, call `ExitWorktree(action: "keep")` — `EnterWorktree` rejects a sibling-worktree switch made from inside a session-owned worktree, so that session must be exited first. **Skip step 1 otherwise.** `ExitWorktree` acts only on a worktree *this session* entered: with no such session it is a no-op that returns an error result, and a session merely **launched** inside a worktree (cwd is a worktree, but no `EnterWorktree` call) is not one it can exit either — so the filesystem cannot answer this, only the transcript can. **Never** pass `action: "remove"`: these are real worktrees holding in-flight work.
2. Bash: `bin/pr-wt-resolve $ARGUMENTS` — prints one absolute worktree path on stdout, or an actionable error on stderr.
3. `EnterWorktree(path: "<the path from step 2>")`.

If step 2 fails, print its stderr line verbatim, say where the session is now — step 1 moved it to `/Users/ajaynicolas/GitHub/IBL5` if it ran, otherwise the session has not moved — and stop.

If step 3 fails with `not under .../.claude/worktrees`, the session was launched inside a worktree rather than entering one, so step 1 was correctly skipped and nothing has moved the session. Report that `/pr-wt` needs a session launched from `/Users/ajaynicolas/GitHub/IBL5`, and stop.

On success, reply with a single line naming the branch and the path.
