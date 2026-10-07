---
description: Captured post-plan sticky comment used by bin/test-review-shared-skip to run the real skip-review.sh against a real body.
last_verified: 2026-10-06
---

# Review-shared fixtures

`post-plan-sticky-pr2892.json` is a verbatim capture of PR 2892's comments. It is the only committed post-plan sticky body. Never edit it by hand. A re-capture replaces the whole file.

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
