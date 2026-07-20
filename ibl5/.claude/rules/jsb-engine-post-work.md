---
description: After JSB engine work ships, update the frontier memory and run /backlog-housekeep — both required before post-plan fires.
last_verified: 2026-07-20
---

# JSB Engine Post-Work Checklist

**Trigger.** Any worktree change that touches `engine/` code or closes / discovers a J-series backlog item.

Both steps are **required before `bin/post-plan-now --auto` fires**. Step 1 is also enforced by `.claude/rules/backlog-housekeep.md` and `/post-plan` Phase 2.5 — the overlap is intentional; engine work routinely surfaces new items and the double-trigger prevents "I'll update the backlog after the PR" drift.

## Step 1 — Backlog (`ibl5/docs/backlog/jsb-native-backlog.md`)

Run `/backlog-housekeep`. Flips status, archives done items, stamps new items, reconciles the README index. Do this inside the worktree (it ships with the PR).

## Step 2 — Frontier memory

**File:** `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/memory/project_jsb_engine_frontier.md`

Memory files live outside the repo — edit in place, no worktree. Do this **before tearing down the worktree** (need `git log` while the branch still exists).

### Required updates

1. **Capture hashes first:** run `git log --oneline -10 origin/master` after merge — grab every PR hash this work produced.

2. **SHIPPED block:** append one entry per merged PR:
   ```
   - **#<PR>** `<hash>` — what shipped and what it proved or ruled out.
   ```
   Include the git-verified `origin/master` hash. Bump the `(git-verified YYYY-MM-DD, origin/master HEAD \`<hash>\`)` line at the top of the SHIPPED block.

3. **NOT-A-LEVER block:** if this session proved a mechanism *cannot* move a target metric (measured A/B or exhaustive trace, not just reasoning), add a bullet. This is the trap-prevention record — the value comes from the empirical proof, so include the measurement or cite the artifact. Items that "might not help" don't belong here; items proven not to help do.

4. **OPEN block:** remove items this work closed; add brief "don't re-run blind" trap context for newly opened or newly constrained items. The authoritative open list is `ibl5/docs/backlog/jsb-native-backlog.md` — the frontier's OPEN block is the delta, not a copy.

### What to omit

- Measurement numbers likely to shift with future work (those belong in RE artifacts / ADRs).
- Items that add no trap-prevention value beyond what the backlog already says.
- Speculative "this might be the lever" notes — frontier memory records what is *proven*, not hypothesized.
