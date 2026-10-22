---
description: wt-new claims a pre-built spare worktree by branch rename and worktree move, then refills the spare through a launchd one-shot.
last_verified: 2026-10-06
---

# ADR-0177: Warm-standby worktree pool for bin/wt-new

**Status:** Accepted
**Date:** 2026-10-06

## Context

Every task starts with `bin/wt-new`, which measured 14.3s on 2026-10-06. The Tailwind CSS build takes 9.7s of that, and `git worktree add` takes 3-27s under load. Backlog issue 24 proposed warm Docker stacks. But `bin/wt-up` derives the container name, Traefik route, bind mount and DB volume from the worktree directory name, and container labels and bind mounts cannot change on a running container. A worktree carries its identity in three places: the directory basename, the branch name, and the per-branch `branch.<name>.*` config (iblBase per ADR-0117).

## Decision

Keep one spare worktree at `IBL5-worktrees/_pool-N` on branch `wt-pool/N`. `bin/wt-new --pool-refill` builds it and writes a readiness marker last, inside the slot's git admin dir. A default-mode `bin/wt-new <slug>` on `master` validates the spare: it must be clean, an ancestor of master, and fast-forwardable. It then renames the branch (`git branch -m` carries the whole config section) and moves the directory (`git worktree move`). The identity tail re-runs: iblBase, wt-new-session, and config materialization. CSS is reused unless the fast-forward touched `ibl5/` outside tests, docs and migrations. Any failed check falls back to the unchanged cold path, and each bypass prints `wt-pool: bypass (<reason>)`. Slot numbers skip any `N` used as a dir, a branch or a git admin id, because a moved worktree keeps admin id `_pool-N`. Every removal of a slot first passes `pool_is_slot`, which checks the parent dir, the `_pool-N` basename, the registration and the `wt-pool/N` branch. Refill runs out of band as a launchd RunAtLoad one-shot (`com.ibl5.wt-pool-refill`, built with `bin/lib/launchd-job.sh`), since a backgrounded child of a Claude Bash tool dies with the tool shell. `WT_POOL=0` turns the pool off, and `WT_POOL_KICK=none|inline|print` overrides the kick. The pool holds worktrees only. `bin/test-wt-pool` covers the claim, refill and kick paths in CI.

## Alternatives Considered

- **Warm Docker stacks.** Keep a running stack per spare. Rejected because labels, bind mounts and the per-slug DB volume are fixed at creation, and `bin/wt-up` purges the DB on every run, so a claimed stack would need a rebuild anyway.
- **A `nohup` refill.** Spawn the refill in the background from wt-new. Rejected because a backgrounded child of a Claude Bash tool dies with the tool shell, which would leave a half-built slot on most automouse runs.
- **A separate pool script.** Add a new `bin/` entry point for the pool. Rejected because refill needs every wt-new helper, so the script would duplicate the tail or source wt-new. A flag keeps one entry point.
- **Move then rename.** The opposite claim order. Rejected because undoing a rename is one `git branch -m`, while undoing a move after a failed rename needs a second move on a half-claimed path.

## Consequences

- Positive: claim cost drops to a fetch plus a rename plus a move.
- Positive: a stale or dirty spare costs one cold create, and the next refill reaps it.
- Positive: consumers (`bin/cleanup`, `bin/wt-status`, `bin/wt-sync-tick`, `bin/wt-rebase`) see a registered, clean, zero-commit branch with no upstream, and need no change.
- Negative: one extra worktree sits on disk at all times. Linux has no scheduler for the refill, so it runs by hand there.
- Negative: the launchd firing itself is only checked on the Mac host. CI covers the refill logic, the kick dispatch and the generated plist.

## References

- `bin/wt-new`: claim call, `--pool-refill` flag and refill kick.
- `bin/lib/wt-pool.sh`: slot naming, guard, lock, marker, refill, claim and kick.
- `bin/lib/git-helpers.sh`: the shared wt-new tail helpers.
- `bin/lib/launchd-job.sh`: plist writer and bootstrap reused by the kick.
- `bin/test-wt-pool`: sandbox harness for every pool behavior.
- `ibl5/docs/decisions/0117-stacked-pr-base-resolution.md`: the iblBase config the claim rewrites.
