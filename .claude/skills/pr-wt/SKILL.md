---
name: pr-wt
description: "Switch this session's working directory into the worktree for a given PR number or branch name. Use when the user types /pr-wt <pr-number|branch-name>."
last_verified: 2026-09-05
---

# /pr-wt — enter a PR's worktree

`$ARGUMENTS` is a PR number (`1946`) or a branch name (`plan-architect-phase-drift-guard`).

Run these three steps and nothing else — do not read files, inspect the diff, or summarise the branch.

1. `ExitWorktree(action: "keep")` — **unconditionally**, before anything else. `EnterWorktree` accepts an arbitrary registered worktree only while the session's cwd is the **main checkout**, so the session has to be standing there before step 3. When this session never called `EnterWorktree`, the tool is a documented no-op: it reports that no worktree session is active and leaves the filesystem unchanged. That makes the call harmless, so do **not** try to decide from the transcript whether to skip it — that recall is exactly what a compaction eats, and guessing wrong strands the session. **Never** pass `action: "remove"`: these are real worktrees holding in-flight work.
2. Bash: `bin/pr-wt-resolve $ARGUMENTS` — prints one absolute worktree path on stdout, or an actionable error on stderr. It re-checks the session's own location first (`--git-dir` vs `--git-common-dir`), so step 1's outcome is **measured**, never inferred from the transcript or from pattern-matching an `EnterWorktree` error string.
3. `EnterWorktree(path: "<the path from step 2>")`.

If step 2 fails, print its stderr line verbatim and stop. **Exit 3** specifically means step 1 found nothing to exit: this session was **launched** inside a worktree rather than entering one, so it can never move (see Dead ends). Nothing has been touched; the fix is a new session launched from `/Users/ajaynicolas/GitHub/IBL5`.

If step 3 fails instead — a session launched in a worktree whose `bin/pr-wt-resolve` predates the exit-3 check, so step 2 returned a path — the diagnosis is identical: `EnterWorktree` rejects the switch with `not under .../.claude/worktrees` and nothing has moved. See Dead ends; relaunch from `/Users/ajaynicolas/GitHub/IBL5`.

On success, reply with a single line naming the branch and the path.

## Dead ends — tested 2026-09-05, do not retry

`EnterWorktree` gates on **cwd being a linked worktree**, not on whether this session owns one — a session merely launched in a worktree gets the same `Switching from this session is limited to…` rejection as one that entered a worktree itself. It also resolves the target through `realpath` before applying that check. Our worktrees live in `IBL5-worktrees/` (ADR-0046), never under Claude Code's own worktree directory, so from a worktree-launched session no target is reachable and both plausible workarounds are rejected by the tool:

- **Symlinking a worktree into Claude Code's worktree directory** — still rejected with `is not under …`, because the check runs on the resolved path, not the one passed in.
- **`EnterWorktree(path: "/Users/ajaynicolas/GitHub/IBL5")`** — rejected with `is the main working tree, not a linked worktree`. The `cd`-into-sibling-worktree Bash gate used to suggest this re-root; its message was corrected on 2026-09-04 and no longer does.

The only cure for a worktree-launched session is a **new session launched from `/Users/ajaynicolas/GitHub/IBL5`**.
