---
description: A hand-confirmed corpus of past held PRs scores the pr-second-reviewer seat offline, with holds frozen from the sticky comment, owner-outcome labels, a synthetic verdict key, and a second subcommand of bin/reviewer-eval.
last_verified: 2026-11-12
---

# ADR-0247: Second-reviewer eval corpus

**Status:** Accepted
**Date:** 2026-11-12
**Deciders:** a-jay85

## Context

ADR-0231 lets a blind Opus seat clear arming conditions 7, 10, 12 and 17. Nothing measured how often the seat cleared a PR the owner later held, or held a PR the owner merged as it was. The reviewer eval in ADR-0201 scores bug recall for the code reviewer, and its corpus and score line do not fit a yes-or-no merge verdict. Past held PRs already record the owner's answer in their labels, their later commits, and any fix that blamed them.

## Decision

`tools/postplan-harness/harness/second_review_eval.py` adds a corpus at `tools/postplan-harness/fixtures/second-review-eval/corpus.json` and four decisions about it.

- **Holds come from the sticky.** The miner reads the hold list from the `Auto-merge held:` block of the post-plan sticky comment, at the comment version whose reviewed tree the seat would have ruled on. It never recomputes the list.
- **The owner's outcome sets the label.** A later fix blamed on the PR within 14 days, a `needs-a-human` label, or a `human-approved` PR whose patch changed after the hold gives `hold`. A `human-approved` PR with an unchanged patch id gives `clear`. A seat-cleared PR that auto-merged clean gives `clear`. The first matching rule wins, and a person sets `status: confirmed` on each case after checking its evidence.
- **The seat writes to a synthetic verdict key.** Each run passes `eval-<id>` to the seat, so the verdict lands at `/tmp/postplan-second-review-eval-<id>.md` and never overwrites the verdict file of a real PR.
- **The eval lives under `bin/reviewer-eval second`.** The wrapper routes `second` to the new module. The existing subcommands and `reviewer_eval.py` stay unchanged.

The run prints `second-review-eval v=<surface> c=<corpus> missed_holds=a/b extra_holds=c/d degraded=n`. The two counts are never summed. A partial clear counts as held. Nothing gates on the score.

## Alternatives Considered

- **Recompute holds with `armable.evaluate`.** Rejected because the evaluator reads the live tree, labels, and check state, and today's answer can differ from the list the seat saw at arming.
- **Extend the code-reviewer corpus in `reviewer_eval.py`.** Rejected because its cases carry line ranges and a recall score, and the seat returns one word per PR.
- **Reuse the real PR number as the verdict key.** Rejected because a run would overwrite the verdict that arming reads for that PR.
- **A new `bin/` script.** Rejected under the extend-before-add bar, because `bin/reviewer-eval` already owns reviewer scoring.

## Consequences

- Positive: a change to the seat prompt, the agent definition, or the inline diff cap can be scored before and after on the same cases.
- Positive: the verdict cache under `tools/postplan-harness/out/second-review-eval/cache/` makes a repeat run on the same surface free.
- Negative: each uncached case costs one Opus seat call.
- Negative: labels rest on the owner's actions. An owner who merged a PR without reading it closely gives a `clear` label the seat may rightly dispute.

## References

- `ibl5/docs/decisions/0201-reviewer-eval-corpus.md`
- `ibl5/docs/decisions/0231-second-reviewer-clears-holds.md`
- `tools/postplan-harness/harness/second_review_eval.py`
- `tools/postplan-harness/harness/second_review.py`
- `tools/postplan-harness/fixtures/second-review-eval/corpus.json`
- `bin/reviewer-eval`
- `ibl5/docs/reviewer-eval.md`
