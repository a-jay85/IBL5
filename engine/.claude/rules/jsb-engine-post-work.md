---
description: After JSB engine work ships, run /backlog-housekeep — the backlog is the single source of truth; git is the authority for merged-PR hashes.
last_verified: 2026-07-22
---

# JSB Engine Post-Work Checklist

**Trigger.** Any worktree change that touches `engine/` code or closes / discovers a J-series backlog item.

This step is **required before `bin/post-plan-now --auto` fires**. It is also enforced by `.claude/rules/backlog-housekeep.md` and `/post-plan` Phase 2.5 — the overlap is intentional; engine work routinely surfaces new items and the double-trigger prevents "I'll update the backlog after the PR" drift.

## One source, one edit site

`engine/docs/backlog/jsb-native-backlog.md` is the **single source of truth** for J-series work: the OPEN list, each item's current state, AND its "Do NOT re-open / NOT-A-LEVER" trap list all live **in the backlog J-entry**, self-standing with each item's proof. There is no companion memory to keep in sync — that split-brain (a hand-maintained frontier hash ledger) was **eliminated 2026-07-21** because it drifted against git and against the backlog. Do not re-create it.

**Merged-PR commit hashes: git is the authority, not a stored ledger.** The backlog records PR **numbers**; the squash-merge commit carries that number in its title, so any hash is recoverable on demand:

```bash
git log --all --grep '#<PR>' --oneline    # resolve a PR number → its merged commit
```

Do NOT backfill hashes into the live backlog — that just relocates the cache you're eliminating. If a one-time provenance snapshot is ever wanted, it belongs in `ibl5/docs/backlog/archive/jsb-native-backlog-archive.md` (history, never maintained), not the live doc.

## Backlog housekeeping (the source of truth)

Run `/backlog-housekeep`. Flips status, archives done items, stamps new items, reconciles the README index. Do this inside the worktree (it ships with the PR). Beyond housekeeping, this is where the durable engine knowledge lands:

1. **Current state** of each touched J-entry (what shipped, the live blocker, the next lever). Dated measurement paragraphs that are now just history belong in `ibl5/docs/backlog/archive/jsb-native-backlog-archive.md` behind a dated pointer — keep the live entry to a single forward-looking current-state block.

2. **NOT-A-LEVER:** if this session proved a mechanism *cannot* move a target metric (measured A/B or exhaustive trace, not just reasoning), add it to the relevant J-entry's "Do NOT re-open" list with its **discriminating proof** (the measurement or the `jsb-native/re-artifacts/...` citation). Items that "might not help" don't belong; items proven not to help do.
