---
description: After JSB engine work ships, run /backlog-housekeep — the backlog is the single source of truth; git is the authority for merged-PR hashes.
last_verified: 2026-08-11
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

## Read the leverage report before the next RE lever

The AutoResearch harness (ADR-0087, shipped as J14 in PR #1545) ranks every registered stand-in by measured leverage over the archive corpus. Per **ADR-0087 §3 it NEVER auto-commits** — its only deliverable is the leverage table, and a human must read it before choosing what to touch next.

**Before picking the next J-series RE lever**, read the most recent report:

```bash
ls -t jsb-native/re-artifacts/leverage-*.txt | head -1   # newest dated leverage report
```

Prefer the highest-|Delta| ABOVE-NOISE stand-in as the next lever. A lever the table ranks below the noise floor is not a lever: per the ADR-0085 precedent a better corpus fit can mask two cancelling fidelity bugs, so **leverage rank — not corpus loss — drives the pick**. If no current report exists, regenerate one with `make research` (`engine/Makefile`) before deciding.

## Content-anchored citations into jsb-native/ docs

Citations into `jsb-native/` docs (especially `00_MASTER_REFERENCE.md`) must use a
grep-able unique token — `§ Heading`, a distinctive string like `+0xDD0`, or an explicit
`grep -n '<phrase>'` command. Never use `path:line` anchors.

`jsb-native/` is git-excluded from IBL5 (ADR-0105). Edits land on disk with no diff and no
attribution, so line numbers shift invisibly. Empirical basis (2026-07-29): PR #1740's
stat showed only `jsb-native-backlog.md | 181 +++-`; the concurrent master-ref edits
shifted the four line anchors in `jsb-j7-param-recovery.md` by 18–38 lines each. The
same plan's grep-anchored citations did not drift.

**Prefer:** `§ Section heading`, `FUN_004d7a40`, `CEngine +0x68A8`,
`grep -n 'leagueSTL48' jsb-native/jsb_560/decompiled/00_MASTER_REFERENCE.md`
**Never:** `00_MASTER_REFERENCE.md:462`
