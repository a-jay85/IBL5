---
description: bin/test-rebuild-css-if-source-changed pins the docker paths of the CSS auto-heal hook (watcher restart, container rebuild fallback, mtime gate) and runs in CI harness-tests.
last_verified: 2026-10-06
---

# ADR-0173: Regression harness for the rebuild-css docker paths

**Status:** Accepted
**Date:** 2026-10-06
**Deciders:** A-Jay Nicolas

## Context

`bin/rebuild-css-if-source-changed` is the git-hook half of the CSS auto-heal (ADR-0075). After a rebuild it restarts the checkout's Tailwind watcher container. Without host `bunx` it falls back to a `docker exec` rebuild. An mtime gate skips all of this when the output is already fresh. None of those docker paths had a test. A wrong container name or a dropped restart would leave compiled CSS silently stale, and nothing would fail.

## Decision

Add `bin/test-rebuild-css-if-source-changed`, a shell regression harness. It builds a throwaway main repo plus a linked worktree, puts stub `docker` and `bunx` first on `PATH`, and asserts both the docker call log and the `css-rebuild.log` lines. Cases cover which watcher gets restarted (main vs. worktree slug), restart failure, the container rebuild fallback and its failures, the host `bunx` path, and the fresh-output skip. The `harness-tests` job in `.github/workflows/tests.yml` runs it on every PR.

## Alternatives Considered

- **Test against a real Docker stack.** Run the hook beside live containers. Rejected because: CI runners have no IBL5 compose stack, and a live run cannot force the failure branches.
- **Leave the docker paths untested.** Rely on the rule prose in `css-auto-rebuild.md`. Rejected because: the failure mode is silent stale CSS, so prose gives no signal when the script drifts.

## Consequences

- Positive: a change to container naming, the restart step, or the fallback order fails CI instead of shipping stale CSS.
- Positive: `REBUILD_CSS_SCRIPT` lets a mutated copy of the target run under the same cases, so the harness itself can be checked for blind spots.
- Negative: stubbed `docker` output must track the real CLI's `ps` format. If the hook starts parsing new fields, the stubs need updating too.

## References

- `bin/test-rebuild-css-if-source-changed`
- `bin/rebuild-css-if-source-changed`
- `.claude/rules/css-auto-rebuild.md`
- `.github/workflows/tests.yml`
- ADR-0075 (`ibl5/docs/decisions/0075-css-auto-heal-via-git-hooks.md`)
