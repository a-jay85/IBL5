# /pr-ready runtime Phases 2–3 — rebase onto pinned master, then resolve conflicts

Purpose: the Phase 2 delegation packet (one `sonnet-4-6` sub-agent does the rebase chore against a pinned master SHA) and the Phase 3 three-way conflict-resolution procedure the orchestrator performs itself.

Read this file at runtime Phase 2 and follow it end-to-end before returning to the spine. Nothing in this file re-resolves `origin/master`.

**`<MASTER_SHA>` below means "substitute the literal SHA recorded in runtime Phase 1.3" — it is never a shell variable.** Each Bash call gets a fresh shell, so a `MASTER_SHA=` assigned in Phase 1 is empty in every command here; `git rev-list --count "$MASTER_SHA"..HEAD` would silently degrade to `git rev-list --count ..HEAD` (exit 0, prints `0`, skipping 2c's squash gate for every PR) and `git rebase -i "$MASTER_SHA"` would rebase onto an empty rev. The placeholder form fails **closed** instead: forget to substitute and git rejects the literal `<MASTER_SHA>` as a bad rev. Substitute every one before running or spawning.

**2a. Capture the pre-rebase tree diff first.** This is the input to Phase 4's lost-work proof, so it must be written *before* any history is rewritten:

```bash
git diff origin/master...HEAD > /tmp/pr-ready-diff-pre-<N>.patch
git rev-list --count <MASTER_SHA>..HEAD   # commit count drives the squash decision
```

**The filename is keyed to the PR number, deliberately — never `$$`.** Each Bash call gets a fresh shell, so `$$` differs between the call that writes this file and Phase 4's call that reads it; a PID-keyed name is guaranteed to miss, and Phase 4 would then report `TREE DIVERGED` on every run. The PR number is stable across calls and still unique per concurrent run. Substitute the real number before running — the same reason `<MASTER_SHA>` above is a literal rather than `"$MASTER_SHA"`.

**2b. The Phase 2 delegation packet.** Hand this to exactly one sub-agent. Substitute the real `<MASTER_SHA>`, branch name, and commit count before spawning — the delegate must never resolve them itself.

````
### Delegate — rebase onto pinned master
- **Tier:** Sonnet
- **Spawn:** `subagent_type: "sonnet-4-6"`, **omit `model`** — never `model: "sonnet"` (that alias resolves to Sonnet 5). Flat fan-out: this delegate must NOT spawn a sub-agent of its own.
- **Rules:** Read `.claude/rules/linear-history-squash-merge.md` first. It is path-scoped and its `paths:` list does NOT cover this skill's files, so it will not auto-attach — Read it by name.
- **First returned line, before any other work:** `pwd` and `git rev-parse --show-toplevel`. A mismatch against the expected worktree means the EnterWorktree switch did not take, and everything after it would rebase the wrong tree.
- **Inputs:** the pinned `<MASTER_SHA>` (use this literal SHA; do NOT resolve `origin/master` yourself, and do NOT reference it as a shell variable), the branch name, the commit count from 2a.
- **Recipe:** squash (best-effort) then rebase, per 2c/2d below.
- **Hard limits:** you may run `git rebase`, `git rebase --continue`, `git rebase --abort`, `git merge`, `git cherry`, `git diff`, `git show`. You may NOT run `git commit` or `git push` — `~/.claude/hooks/plan-gate-commit.sh` denies both for sub-agents and will hard-fail the call. Stop at the FIRST conflict; do not attempt to resolve it.
- **Report back (thin — pointers only, never pasted diffs or file bodies):** line 1 = `pwd` + toplevel; line 2 = the master SHA you rebased onto; line 3 = `clean` or a newline-separated list of conflicted paths from `git diff --name-only --diff-filter=U`; line 4 = `squashed` / `squash-skipped` / `squash-failed`.
````

**2c. Squash — hook-safe form, best-effort.** When `git rev-list --count <MASTER_SHA>..HEAD` is greater than 1, squash to one commit. The obvious recipe (`git reset --soft` + a commit) is **denied for the delegate** by the ship-overreach gate, which pattern-matches the commit/push subcommands. Use the non-interactive `rebase -i` form instead — the invoked binary is `git rebase`, which the gate allows, and the rebase machinery writes the commit without a commit call:

```bash
GIT_SEQUENCE_EDITOR="sed -i '' '2,$ s/^pick /squash /'" GIT_EDITOR=true \
  git rebase -i <MASTER_SHA>
```

The `2,$` range leaves the first `pick` alone and only rewrites lines that begin with `pick `, so the todo file's `#` comment block is untouched. `GIT_EDITOR=true` accepts the concatenated commit message unedited.

If this command fails for any reason, report `squash-failed` and continue to 2d with the un-squashed history. **The local squash is cosmetic** — master is squash/rebase-merge-only, so GitHub collapses the branch to one commit at merge time anyway. A failed squash is never a reason to stop the run.

**2d. Rebase onto the PINNED SHA.** Never run a bare `git rebase` against `origin/master` — a bare rebase replays a merged parent's already-squashed commits and fabricates conflicts (`.claude/rules/linear-history-squash-merge.md`, "The diagnostic trap this prevents"). Use the `--onto` form with the pinned SHA:

```bash
git rebase --onto <MASTER_SHA> <parent-tip-before-merge> <branch>
```

- For a branch based directly on master, `<parent-tip-before-merge>` is the merge base: run `git merge-base <MASTER_SHA> HEAD` as its own command and substitute the printed SHA. Do **not** inline it as `$(git merge-base …)` — the worktree-isolated session refuses command substitution outright (see the skill's invariants).
- For a **stacked** branch whose parent already merged, it is the last commit belonging to the parent. Confirm the parent's work is in master **by content** (files/diff present), never by `git branch --contains` — that shows a merged SHA as absent as a normal squash artifact.
- When 2c reported `squashed` **and the branch is not stacked**, the rebase is already done (2c's `rebase -i` targeted `<MASTER_SHA>`) — skip 2d and report `clean`.
- **If the branch IS stacked (its parent already merged), skip 2c entirely and run 2d instead.** 2c's `git rebase -i <MASTER_SHA>` uses the default upstream/merge-base replay range, which is exactly the trap the `--onto` form exists to avoid; the squash is cosmetic and not worth risking the wrong replay range.

**2e. Runtime Phase 3 — conflict resolution, on the orchestrator.** The delegate stops at the first conflict and returns; it never resolves. Everything below is the orchestrator's own work.

1. **Always resolve three-way.** For each conflicted path, read all three stages before editing — `git show :1:<path>` (base), `git show :2:<path>` (ours / the rebased-onto master side), `git show :3:<path>` (theirs / the branch side). Reconstruct the intent of both edits, then write the merged file.
2. **Never `git checkout --ours <path>` or `--theirs <path>`** unless *every* hunk in that file genuinely takes that side. A whole-file side-take is the single most common way real work disappears in this repo, and it is invisible in the resulting diff.
3. **`ibl5/docs/backlog/maintenance-backlog.md` is a special case.** Its per-axis counts are **additive** — a conflict where both sides added rows means the merged file keeps **both** sets of rows — while the `> ✅ resolved (N): …` / `> 🚫 declined (N): …` roll-up totals are **recomputed** from the merged row set, never taken from either side. Taking either side wholesale silently drops the other side's backlog items and leaves a total that does not match the rows, which `bin/check-docs` (`checkMaintenanceResolved`) will then contradict. Same rule for any other counted roll-up encountered, including the section counts in `ibl5/docs/backlog/archive/maintenance-backlog-archive.md`.
4. `git add <path>` each resolved file, then `git rebase --continue` (with `GIT_EDITOR=true` so it does not block on an editor). Repeat until the rebase finishes — or until `git rebase --abort` is the right call, in which case abort, report the conflict set, and stop rather than guessing.
5. **Record the resolved paths in a run note.** Phase 6 must name them explicitly, because conflict-resolution output is **new code that no code review has ever seen**.
