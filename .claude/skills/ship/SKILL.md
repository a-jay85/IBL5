---
name: ship
description: "Commit, push, and open a PR via /post-plan, which decides whether auto-merge arms; /ship never arms directly."
disable-model-invocation: true
last_verified: 2026-07-21
---

# /ship — Commit, push, PR via /post-plan

`/ship` is an **interactive-only** wrapper that hands commit/push/PR work to `/post-plan`. There is **one path**: it fires `bin/post-plan-now`, and `/post-plan` opens the PR, runs code review + security audit + verification, and decides at Phase 6.5 whether auto-merge arms or the PR waits for a human. The user's invocation text is available as `$ARGUMENTS`.

> ## Core invariant — `/ship` ONLY delegates; it NEVER arms auto-merge itself.
>
> Arming is owned exclusively by `/post-plan` Phase 6.5, which runs the teeth a chat-context wrapper structurally cannot supply: code-review scoring (condition (2)), Phase-5 verification (condition (4)), planned-test / planned-file completeness (condition (3)), CI-green-required, the realized-diff safety verdict (condition (9)), the `feat:` floor (condition (8)), and the independent `human-signoff` required GitHub check. A **reviewed-but-held PR** (open, a human merges it) is the **automatic** outcome whenever any Phase 6.5 condition holds — there is no token to request it. A routing bug **fails safe**: the worst case is a delayed ship, never an unreviewed merge.

(`/ship` previously carried merge-intent tokens and a routing step; both were removed per ADR-0067 — the held-PR outcome already emerges automatically when work is not arming-safe, and a flag the user never reaches for is dead weight. `/ship` now has a single behavior.)

## Step 1 — Precondition gate (refuse on ANY; before touching anything)

Evaluate all three before doing any work. On any hit, refuse with the one-line reason and stop.

1. **Current branch is `master` / `main` / `HEAD`** (`git rev-parse --abbrev-ref HEAD`) → refuse: work belongs in a worktree, not the reference/read-only main checkout (**ADR-0062**; see `.claude/rules/workflow-continuity.md`).
2. **`$CLAUDE_HEADLESS` = `1`** → refuse: the automouse/headless run owns post-plan; `/ship` is interactive-only.
3. **Clean tree AND no commits ahead of `origin/master`** (`git status --porcelain` empty **AND** `git log --oneline origin/master..HEAD` empty) → refuse: nothing to ship.

**Defense-in-depth note.** `bin/post-plan-now` independently enforces refusals (1)–(3): it `exit 1`s on `master`/`main`/`HEAD` and on a nothing-to-ship tree. `/ship`'s own gate still earns its place for earlier, clearer UX messaging.

## Step 2 — Fire post-plan (the single path)

First, a cheap **advisory prediction** — clearly label it *"advisory only; `/post-plan` Phase 6.5 makes the real call"*. Emit a warning for each that matches:

- PR/commit title matches `^feat(\(|!|:)` → Phase 6.5 condition **(8)** (the `feat:` floor) will **HOLD**.
- `git diff HEAD --name-only` includes `engine/internal/sim/testdata/golden.json` → condition **(5)** holds under headless (warns interactively).
- A plan at `~/claude-plans/<branch-slug>.md` whose **line-1** frontmatter has `auto_merge: false` → condition **(7)** will **HOLD**.

Then the actions:

- **Do NOT commit.** Leave the worktree **dirty** — `/post-plan` Phase 2 commits the uncommitted tree and opens the PR; committing first would change what ships (see `.claude/rules/workflow-continuity.md`).
- Fire **`bin/post-plan-now`** (bare, **not** `--auto`): a human invoking `/ship` IS the decision to ship; `--auto` only matters inside headless, which the Step 1 precondition already rejected.
- Report: post-plan fired (detached Sonnet 4.6, launchd-supervised); the PR / code review / CI / auto-merge progress all land on the PR; list any predicted holds from the advisory step.

## Decision-table recap

| Input | Outcome |
|-------|---------|
| Branch is `master`/`main`/`HEAD` | **Refuse** (Step 1.1 — worktree rule, ADR-0062) |
| `$CLAUDE_HEADLESS=1` | **Refuse** (Step 1.2 — interactive-only) |
| Clean tree AND 0 commits ahead of `origin/master` | **Refuse** (Step 1.3 — nothing to ship) |
| Otherwise | **Fire `bin/post-plan-now`** (Step 2 — dirty tree; post-plan opens the PR and decides arming) |

Arming is always delegated to `bin/post-plan-now` → `/post-plan` Phase 6.5. `/ship` never runs `gh pr merge --auto` or `--enable-auto-merge` itself.
