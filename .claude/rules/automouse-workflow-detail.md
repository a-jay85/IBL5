---
description: Automouse workflow detail. The plan autonomy contract (stop_condition and evidence frontmatter). Companion to automouse-workflow.md; attaches on the same paths.
last_verified: 2026-10-11
paths: "bin/automouse/**"
---

# Automouse Workflow Detail

Companion to `.claude/rules/automouse-workflow.md`.

## Plan frontmatter: the autonomy contract

Two **optional** line-1 fields let a plan declare *when it is done*, so `/post-plan` can hold a PR whose deliverable never landed. They are a **unit**: **both or neither**. Exactly one is a `bin/check-plan` `[K]` violation.

**`stop_condition:`** is a closed enum. Any other value, **including empty**, is rejected.

- `tests-green`: holds unless Phase 5 status is `pass` (`fail`, `skipped` and *no status recorded* all fail).
- `evidence-present`: no executable track (docs / tooling-only). Done when the declared artifacts are in the diff. No run-outcome check.

**`evidence:`** is a **single line** (not a YAML block list) of comma-separated repo-relative path tokens. Surrounding whitespace is ignored. It needs **one token minimum**, and each token must appear in the PR's changed-file list at post-plan time. Name the **deliverable artifacts**, never Verification Matrix rows.

**Neither field grants merge authority.** An unmet contract only **adds** a hold; a satisfied one never arms a PR. `auto_merge:`, plan gate 14 and `feat:` human-signoff remain that surface, untouched.

**Malformed values fail at authoring time**, before a run is spent. `bin/check-plan` `[K]` names the specific defect. Unknown keys stay **silently ignored**. This adds two *recognised* keys and no reject-unknown-key rule.

```yaml
stop_condition: tests-green
evidence: bin/lib/plan-autonomy-contract, bin/test-check-plan
```

Every parser is **line-1-anchored** (frontmatter only, to the closing `---`), so the example above never self-selects. Enforced by `bin/lib/plan-autonomy-contract`, `bin/check-plan` `[K]`, `/post-plan` Phase 5.0.
