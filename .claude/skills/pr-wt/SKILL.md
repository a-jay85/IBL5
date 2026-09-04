---
name: pr-wt
description: "Switch this session's working directory into the worktree for a given PR number or branch name. Use when the user types /pr-wt <pr-number|branch-name>."
last_verified: 2026-09-03
---

# /pr-wt — enter a PR's worktree

`$ARGUMENTS` is a PR number (`1946`) or a branch name (`plan-architect-phase-drift-guard`).

Run these three steps and nothing else — do not read files, inspect the diff, or summarise the branch.

1. `ExitWorktree(action: "keep")` — always, unconditionally. It is a documented no-op outside a worktree session, and `EnterWorktree` rejects a sibling-worktree switch made from inside one. **Never** pass `action: "remove"`: these are real worktrees holding in-flight work.
2. Bash: `bin/pr-wt-resolve $ARGUMENTS` — prints one absolute worktree path on stdout, or an actionable error on stderr.
3. `EnterWorktree(path: "<the path from step 2>")`.

If step 2 fails, print its stderr line verbatim, add that step 1 already moved the session to `/Users/ajaynicolas/GitHub/IBL5`, and stop.

If step 3 fails with `not under .../.claude/worktrees`, the session was launched inside a worktree rather than entering one, so step 1 could not move it. Report that `/pr-wt` needs a session launched from `/Users/ajaynicolas/GitHub/IBL5`, and stop.

On success, reply with a single line naming the branch and the path.
