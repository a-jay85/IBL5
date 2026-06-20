---
description: "Commit, push, and open a PR; optionally arm gated auto-merge by delegating to /post-plan (never arms directly)."
last_verified: 2026-06-20
---

# /ship — Commit, push, PR, optionally arm gated auto-merge

`/ship` is an **interactive-only** wrapper around `/commit-commands:commit-push-pr`. It routes commit/push/PR work down one of two paths: a plain **no-merge** path that opens a PR and leaves it open, or a gated **auto-merge** path that fires `bin/post-plan-now` and lets `/post-plan` Phase 6.5 decide whether auto-merge actually arms. The user's invocation text is available as `$ARGUMENTS`.

> ## Core invariant — `/ship` ONLY routes; it NEVER arms auto-merge itself.
>
> Arming is owned exclusively by `/post-plan` Phase 6.5. That phase runs the teeth this wrapper structurally cannot supply from conversation context:
> - code review (any finding scored **≥ 80 blocks** — condition **(2)**),
> - Phase-5 local verification status (condition **(4)**),
> - planned-test / planned-file completeness (condition **(3)**),
> - CI-green-required, and
> - the independent `human-signoff` required GitHub check.
>
> The merge path therefore **delegates rather than reimplements**: conditions (2)/(3)/(4) plus CI-green and the `human-signoff` check are produced by post-plan phases (review scoring, Phase-5 verification, CI watch) that a chat-context wrapper cannot reproduce. A routing bug **fails safe** — the worst case is the wrong path is taken, never an unreviewed merge.

## Step 1 — Precondition gate (refuse on ANY; before touching anything)

Evaluate all three before doing any work. On any hit, refuse with the one-line reason and stop.

1. **Current branch is `master` / `main` / `HEAD`** (`git rev-parse --abbrev-ref HEAD`) → refuse: work belongs in a worktree, not the reference/read-only main checkout (**ADR-0062**; see `.claude/rules/workflow-continuity.md`).
2. **`$CLAUDE_HEADLESS` = `1`** → refuse: the automouse/headless run owns post-plan; `/ship` is interactive-only.
3. **Clean tree AND no commits ahead of `origin/master`** (`git status --porcelain` empty **AND** `git log --oneline origin/master..HEAD` empty) → refuse: nothing to ship.

**Defense-in-depth note (the gate is NOT redundant).** `bin/post-plan-now` independently enforces refusals (1)–(3): it `exit 1`s on `master`/`main`/`HEAD` and on a nothing-to-ship tree, and its headless skip is gated behind `--auto` (which `/ship` does not pass). `/ship`'s own gate still earns its place for two reasons: (a) earlier, clearer UX messaging, and (b) the **`--no-merge` path never touches `post-plan-now` at all** and would otherwise be unguarded.

## Step 2 — Resolve merge intent (precedence order, exactly)

1. An explicit `--merge` or `--no-merge` token in `$ARGUMENTS` **wins outright**.
2. Else, if THIS conversation explicitly asked to auto-merge / ship-and-merge / "merge when green" → treat as `--merge`.
3. Else call **`AskUserQuestion`** — **default to ASK, never silently assume**. Arming auto-merge is outward-facing and hard to reverse, so an ambiguous intent must be confirmed, not guessed.

## Step 3a — No-merge path

Run `/commit-commands:commit-push-pr`: commit, push, open the PR, and **leave it OPEN**. Report the PR URL. Do **not** fire `bin/post-plan-now`.

## Step 3b — Merge-wanted path (delegate the gated arming, NEVER arm directly)

First, a cheap **advisory prediction** — clearly label it *"advisory only; `/post-plan` Phase 6.5 makes the real call"*. Emit a warning for each that matches:

- PR/commit title matches `^feat(\(|!|:)` → Phase 6.5 condition **(8)** (the `feat:` floor) will **HOLD**.
- `git diff HEAD --name-only` includes `engine/internal/sim/testdata/golden.json` → condition **(5)** holds under headless.
- A plan at `~/.claude/plans/<branch-slug>.md` whose **line-1** frontmatter has `auto_merge: false` → condition **(7)** will **HOLD**.

Then the actions:

- **Do NOT commit.** Leave the worktree **dirty** — `/post-plan` Phase 2 commits the uncommitted tree and opens the PR; committing first would change what ships (see `.claude/rules/workflow-continuity.md`).
- Fire **`bin/post-plan-now`** (bare, **not** `--auto`): a human invoking `/ship --merge` IS the decision to ship; `--auto` only matters inside headless, which the Step 1 precondition already rejected.
- Report: post-plan fired (detached Sonnet 4.6, launchd-supervised); the PR / code review / CI / auto-merge progress all land on the PR; list any predicted holds from the advisory step.

## Decision-table recap

| Input | Outcome |
|-------|---------|
| Branch is `master`/`main`/`HEAD` | **Refuse** (Step 1.1 — worktree rule, ADR-0062) |
| `$CLAUDE_HEADLESS=1` | **Refuse** (Step 1.2 — interactive-only) |
| Clean tree AND 0 commits ahead of `origin/master` | **Refuse** (Step 1.3 — nothing to ship) |
| Intent resolves `--no-merge` | **No-merge path** (Step 3a — open PR, leave OPEN) |
| Intent resolves `--merge` | **Merge path** (Step 3b — dirty tree, fire `bin/post-plan-now`) |
| Intent ambiguous | **`AskUserQuestion`** (Step 2.3 — never assume merge) |

Arming is always delegated to `bin/post-plan-now` → `/post-plan` Phase 6.5. `/ship` never runs `gh pr merge --auto` or `--enable-auto-merge` itself.
