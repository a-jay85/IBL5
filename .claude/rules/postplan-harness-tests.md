---
description: Run only the post-plan harness test files you changed; CI runs the whole suite on every PR that touches the harness.
last_verified: 2026-11-12
paths:
  - "tools/postplan-harness/**"
  - "bin/post-plan-now"
---

# Post-Plan Harness Tests: Run Only the Files You Changed

Never run the whole harness suite on the Mac. That means `python3 -m pytest tests/`, `./run test`, or `tools/postplan-harness/tests/` from the repo root. A whole-suite run gets 8 xdist workers from `tests/parallel_default.py`, and many tests start real `bin/post-plan-now` scripts. On 2026-11-12 one such run pushed the load average past 95 while other sessions were busy.

CI's `Python tests (post-plan harness)` job in `.github/workflows/python-tests.yml` already runs the whole suite. It runs on every PR that touches `tools/postplan-harness/**`, `bin/post-plan-now`, or the other paths listed in that workflow.

Run only the test files you added or changed, from `tools/postplan-harness`:

```bash
python3 -m pytest -q tests/test_armable.py tests/test_runner_review_depth.py
```

A file or nodeid argument keeps the run serial, so it starts no worker pool. When a change touches shared code with no obvious test file, such as `conftest.py` or `harness/state.py`, push and let CI run the whole suite.
