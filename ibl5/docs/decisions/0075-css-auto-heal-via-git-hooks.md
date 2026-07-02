---
description: Git hooks (post-checkout/post-merge/post-rewrite) auto-heal stale compiled Tailwind CSS after bulk working-tree rewrites the watcher container misses.
last_verified: 2026-07-02
---

# ADR-0075: CSS auto-heal via git hooks

**Status:** Accepted
**Date:** 2026-07-02
**Deciders:** A-Jay Nicolas

## Context

The `ibl5-tailwind[-<slug>]` container runs `@tailwindcss/cli --watch=always`, reacting to filesystem events to keep `themes/IBL/style/style.css` in sync with `design/**` source. Editor saves propagate reliably (~1s rebuild). But git operations that bulk-rewrite the working tree — `checkout`, `merge`, `rebase` (via `post-rewrite`) — are not reliably delivered to `@parcel/watcher` through the macOS→Docker bind mount. The compiled stylesheet silently goes stale after a branch switch or merge, and the only prior recovery was a documented manual `bunx @tailwindcss/cli` step — easy to forget, and previously the "one sanctioned exception" in `.claude/rules/css-auto-rebuild.md`.

## Decision

`bin/install-git-hooks` injects a call to `bin/rebuild-css-if-source-changed` into the common git hooks dir's `post-checkout`, `post-merge`, and `post-rewrite`, right after each hook's shebang (so it runs before any branch-gated early exit). The script is mtime-gated (no-op when `design/**` is not newer than the compiled output) and runs the rebuild in the background so the git operation returns immediately; it prefers a host `bunx` and falls back to `docker exec` into the running `ibl5-tailwind[-<slug>]` container. Enforcement: `bin/rebuild-css-if-source-changed`, wired via `bin/install-git-hooks`.

## Alternatives Considered

- **Rely on the editor-save watcher only, document manual rebuild as recovery** — status quo. Rejected because: relies on developer memory and is invisible until a stale-CSS bug is noticed.
- **`core.hooksPath` pointing at a version-controlled hooks directory** — cleaner single source of truth. Rejected because: all-or-nothing across the shared common git dir; would orphan the existing untracked hooks (git-lfs pre-push, pre-commit's codebase-map + check-docs, post-merge wt-cleanup, post-checkout) that are not being migrated in this change.
- **Blocking (foreground) rebuild in the hook** — simpler control flow. Rejected because: would add multi-second latency to every checkout/merge/rebase; backgrounding keeps git operations instant while still healing before the next page load in practice.

## Consequences

- Positive: developers and agents no longer need to remember a manual CSS rebuild after switching branches or merging — the common failure mode from the bind-mount gap is closed at the source.
- Positive: idempotent and low-risk — mtime-gated no-op when output is already fresh, background execution, silent fallback path (host → container) logged to `~/.cache/ibl5-hooks/css-rebuild.log` for diagnosis.
- Negative: adds a small amount of injected shell to three hook files outside version control (the hooks themselves live in the shared common `.git` dir, not the repo) — `bin/install-git-hooks` must be re-run after any change to the injected block, and the sentinel-based idempotency check is the only guard against duplicate injection.

## References

- `bin/rebuild-css-if-source-changed`
- `bin/install-git-hooks`
- `.claude/rules/css-auto-rebuild.md`
