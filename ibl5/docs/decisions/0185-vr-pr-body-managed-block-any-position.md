---
description: The vr-new-screens managed block is found at any position in the PR body (own-line markers outside code fences); spliceBody replaces the first block in place and strips later stale blocks. Supersedes ADR-0076's offset-0-only clause.
last_verified: 2026-10-08
---

# ADR-0185: Find and replace the VR managed block at any position in the PR body

**Status:** Accepted
**Date:** 2026-10-08
**Supersedes (in part):** ADR-0076, the "leaves a marker found elsewhere in the body untouched" clause only.

## Lineage

- ADR-0076 introduced the offset-0 managed block. ADR-0181 (on the PR #2950 branch) added the changed-screens section and the agent-shot sub-block that live inside it.

## Context

ADR-0076 made `spliceBody` recognise a managed block only at offset 0. Any edit that moved the
block down the body (a human or tool prepending text, or the merge digest landing above it)
caused the next CI run to prepend a fresh block and leave the old one as a stale copy. PR #2950's
own body carried two vr-new-screens blocks with a merge-digest block between them. Agent shots
in a block below offset 0 were also invisible to `extractAgentShots`, so a refresh dropped them.

## Decision

- A managed block is an own-line BEGIN marker (trimmed) outside any fenced code block, through
  the next own-line END marker. Inline or backticked mentions, markers inside a fence, an
  unterminated BEGIN and a stray END are human text and are never edited.
- `spliceBody` replaces the first managed block in place and deletes every later managed block.
  It prepends at offset 0 only when the body has no managed block. The strip case removes all
  blocks.
- `extractAgentShots` and `upsertAgentShots` read and write the first managed block wherever it
  sits. Agent shots that exist only in a later stale block are dropped by design.
- A body with zero blocks, or one block at offset 0, produces byte-identical output to the
  ADR-0076 implementation. The corpus diff in the implementing PR checked this against real PR bodies.

## Alternatives Considered

- Keep offset-0-only and add a manual cleanup step. Rejected because stale blocks keep
  accumulating and every PR author has to notice them.
- Move the block to offset 0 on every run. Rejected because it rewrites the human text's order
  on every run, and a human who placed prose above the block would see it pushed down.
- Treat any marker occurrence as a block boundary. Rejected because a PR that documents the
  markers in prose or a code fence would have its text deleted.

## Consequences

- The clobber-safety boundary moves from "offset 0" to "own-line, outside fences". Prose that
  places a bare own-line marker pair outside a fence is now treated as a managed block.

## References

- `ibl5/tests/e2e/vr-pr-body.ts`: `findManagedBlocks`, `spliceBody`, `extractAgentShots`, `upsertAgentShots`.
- `ibl5/tests/ts-unit/vr-pr-body.test.ts`: tests 5d to 5j, 7c, 7d, 10g, 10h and 15a to 15f.
- `ibl5/docs/decisions/0076-vr-new-screens-in-pr-body.md`: the offset-0 clause this record supersedes.
- `ibl5/docs/decisions/0181-vr-pr-body-changed-screens.md`: the changed-screens section and agent-shot sub-block inside the block.
