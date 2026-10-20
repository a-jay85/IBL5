---
description: Captured and rendered post-plan sticky comments used by the shell consumer tests, including bin/test-review-shared-skip, to run real readers against real bodies.
last_verified: 2026-10-16
---

# Review-shared fixtures

`post-plan-sticky-pr2892.json` is a verbatim capture of PR 2892's comments. It is the only committed capture of a live sticky body. Never edit it by hand. A re-capture replaces the whole file.

Capture command:

```bash
cd "$(git rev-parse --show-toplevel)" \
  && mkdir -p bin/fixtures/review-shared \
  && gh pr view 2892 --repo a-jay85/IBL5 --json comments,headRefName \
     > bin/fixtures/review-shared/post-plan-sticky-pr2892.json
```

The file holds two comments. The `## Code review` comment has no marker, so the script must ignore it. The other comment carries `<!-- pr-ready-verdict -->` and is the sticky.

The tests rely on three layout facts about the sticky:

1. The `**Reviewed tree:**` line sits inside the `<details><summary>Audit trail</summary>` block. Line 1 is the READY banner.
2. The terminal line, just above the marker, is `READY`.
3. The `<!-- pr-ready-verdict -->` marker is the last line.

`tools/postplan-harness/tests/test_fidelity_sticky.py::test_committed_fixture_matches_writer_tail_shape` checks that the live writer still produces this shape.

## PR 3267 layout pair

`post-plan-sticky-pr3267-v1.json` is the old sticky layout for a remediated run whose re-review returned READY WITH NOTES. It was rendered once from the renderer at master `0d7d98d8` and is frozen. `tools/postplan-harness/tests/test_sticky_layout_fixtures.py::test_pr3267_v1_fixture_is_frozen_old_layout` fails if it is regenerated. Its banner says READY WITH NOTES while its terminal line says `READY (re-review) ... re-reviewed clean`, which is the defect the layout change removed.

`post-plan-sticky-pr3267-v2.json` is the same run rendered by the current `compose_sticky`. It must equal what the renderer emits, and `tools/postplan-harness/tests/test_sticky_layout_fixtures.py::test_pr3267_v2_fixture_matches_renderer` fails on drift. Regenerate it with the Phase 5b command of the `pr-ready-verdict-consistency` plan when the layout changes on purpose.

Every shell consumer test (`bin/test-pr-cycle`, `bin/test-pr-triage`, `bin/test-digest-dm-build`, `bin/test-postplan-arm-conditions`, `bin/test-review-shared-skip`, `bin/test-post-plan-fleet`) runs one case per fixture and expects the same outcome from both.
