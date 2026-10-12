---
description: The always-loaded doc-freshness rule keeps only its operative rules; rationale, history, and gate mechanics move to a Read-on-demand companion, doc-freshness-detail.md, whose paths entry deliberately never matches.
last_verified: 2026-10-01
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0157: Split Doc-Freshness Rationale Into a Read-on-Demand Companion

**Status:** Accepted
**Date:** 2026-10-01
**Deciders:** post-plan harness (auto-draft)

## Context

`.claude/rules/doc-freshness.md` carries `paths: "**/*.md"`, so it attaches to almost every session that touches a doc. Over time it had grown long paragraphs of history and evidence next to each rule: transcript findings behind the `paths:` residency constraints, the future-date and local-date mechanics of the on-touch gate, the false-green incident that led `--since` to read the working tree, the five false-positive suppressions in source-comment scanning, and why each retired-figure exemption exists. An agent needs that material only when it is debugging a `bin/check-docs` verdict or changing the rule. Every other session paid for it in context.

`bin/adr-check` flagged this PR because it adds a new agent rule file, `.claude/rules/doc-freshness-detail.md`.

## Decision

Keep the operative rules in `.claude/rules/doc-freshness.md` and move the rationale, history, and gate mechanics to `.claude/rules/doc-freshness-detail.md`. The parent states each rule in a few sentences and ends each section with a pointer of the form "Detail: `doc-freshness-detail.md` § <section>". The companion has four sections that match those pointers: `paths:` residency rationale, on-touch gate mechanics, source-comment scanning, and retired-figure rationale.

The companion is Read-on-demand. Its `paths:` names a single out-of-repo hook file. A rule with no `paths:` is always-loaded, and an out-of-repo entry never matches, so this keeps the file out of every system prompt. The companion's `description` says this outright, as the parent's own residency rule requires. No new gate enforces the split. `bin/check-docs` still enforces every rule the parent states.

## Alternatives Considered

- **Leave the rule whole.** Rejected because the rationale paragraphs loaded into every doc-touching session while being needed only in the rare session that debugs or edits the gate.
- **Delete the rationale.** Rejected because the history explains choices that look wrong in isolation, such as why `⚠` is excluded as a marker and why the older "bump one day higher" advice now fails. Without it, a later editor would likely undo those choices.
- **Glob the parent's path.** Rejected because the parent's own rule forbids it. Compaction restore counts as a touch, so the companion would re-attach every window and cancel the savings.
- **Move the rationale into an ADR.** Rejected because ADRs are append-only per `.claude/rules/adr-append-only.md`, and this material describes current gate behavior that will keep changing with `bin/check-docs`.

## Consequences

- Positive: sessions that touch markdown load a shorter rule, and every operative instruction stays in it.
- Positive: the split follows the parent-plus-companion pattern already used by `.claude/rules/work-triage-detail.md` and `.claude/rules/agent-tiering-detail.md`.
- Negative: an agent that needs the rationale must notice the pointer and Read the companion. If it skips the Read, it acts on the short form alone.
- Negative: the two files can drift apart. Each now needs its own `last_verified` bump when the gate changes.

## References

- `.claude/rules/doc-freshness.md`
- `.claude/rules/doc-freshness-detail.md`
- `.claude/rules/adr-append-only.md`
- `.claude/rules/work-triage-detail.md`
- `.claude/rules/agent-tiering-detail.md`
- `bin/check-docs`
- `bin/adr-check`
