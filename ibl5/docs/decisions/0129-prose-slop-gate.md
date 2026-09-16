---
description: A mechanical gate blocks AI tell patterns (sycophant openers, em-dashes, buzzwords, etc.) in committed markdown and Claude's output, enforced by bin/check-prose on three surfaces.
last_verified: 2026-09-16
---

# ADR-0129: Prose slop gate

**Status:** Accepted
**Date:** 2026-09-16

## Context

Claude output carries a recognizable set of sentence shapes: sycophant openers, em-dash hangoffs,
buzzwords like "seamless" and "leverage", and a dozen others. These patterns reduce prose quality
and mark text as machine-generated. Manual rewrites were made when they appeared, but the same
patterns recurred the next session. A norm without enforcement is advice.

Two earlier attempts at documentation-level enforcement (output style instructions, memory
entries) both failed the recurrence test. The shapes returned the next time a diff or PR body
was composed from scratch.

## Decision

Enforce prose quality mechanically on three surfaces using a single script `bin/check-prose`:

1. **CI.** `pr-meta-checks.yml` runs `bin/check-prose --since=<base>` on every PR, blocking
   merge when added lines match a tell. The tell list lives only in the script; adding or tuning a
   pattern requires one edit and no cross-file coordination.
2. **Edit / Write hook.** Check 5 in `~/.claude/hooks/plan-gate-edit.sh` runs the same script
   on lines an Edit or Write would add, and denies the tool call on a match.
3. **Chat replies.** A Stop hook at `~/.claude/hooks/prose-gate-stop.py` scans the final
   assistant text and requests a rewrite on a match.

The gate is on-touch: it scans only added lines. Existing prose stays as-is until touched. Every
escape hatch is one-shot (a `<!-- slop-ok -->` inline marker or the `touch` command printed by
the deny message), so friction is bounded to one extra step per legitimate exception.

The tell list, fixtures, and `--self-test` mode are documented in `.claude/rules/prose-style.md`.

## Alternatives Considered

- **Memory entry.** Rejected: the same shapes recurred across sessions even with memory present,
  proving the pattern requires a hard deny.
- **Output-style instructions only.** Rejected: instructions are prose compliance; the failure
  mode is the model following them for a few turns and then drifting.
- **Repo-wide sweep.** Rejected: a sweep would produce a large diff with no user value and
  block unrelated PRs. The on-touch approach handles this without that cost.

## Consequences

- Positive: AI tell patterns cannot accumulate silently across sessions.
- Positive: the tell list has one home; a single script edit updates all three surfaces.
- Negative: a legitimate exception requires a one-shot escape-hatch step.

## References

- `bin/check-prose`
- `.claude/rules/prose-style.md`
- `.github/workflows/pr-meta-checks.yml`
