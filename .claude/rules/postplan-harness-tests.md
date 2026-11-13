---
description: Run only the post-plan harness test files you changed; CI runs the whole suite on every PR that touches the harness.
last_verified: 2026-11-13
paths:
  - "tools/postplan-harness/**"
  - "bin/post-plan-now"
  - "bin/lib/pr-armable.sh"
  - "bin/lib/post-review-findings.sh"
  - "bin/lib/branch-base.sh"
  - "bin/lib/usage-gate.sh"
  - "bin/lib/pr-sticky.sh"
  - "bin/lib/current-models"
  - "bin/normalize-manual-testing"
  - "bin/check-*"
  - "bin/gate-backtest"
  - ".claude/review-shared/scripts/lostwork.sh"
  - ".github/workflows/python-tests.yml"
---

# Post-Plan Harness Tests: Run Only the Files You Changed

Never run the whole harness suite on the Mac. That means `./run test`, a bare `python3 -m pytest` from `tools/postplan-harness`, or `python3 -m pytest tools/postplan-harness/tests/` from the repo root. A whole-suite run gets 8 xdist workers from `tests/parallel_default.py`, and many tests start real `bin/post-plan-now` scripts. On 2026-11-12 one such run took over 9 minutes while the Mac's load average sat at 96 and free memory fell to about 116 MB.

CI's `Python tests (post-plan harness)` job in `.github/workflows/python-tests.yml` already runs the whole suite. Its trigger paths are the `paths:` list above, so this rule loads whenever CI will run the suite for you.

Run only the test files you added or changed, from `tools/postplan-harness`:

```bash
python3 -m pytest -q tests/test_armable.py tests/test_runner_review_depth.py
```

`bin/lib/prefire-verify` runs the same targeted selection at the fire point through its `pytest` stage, so `bin/post-plan-now` exits 9 when a mapped harness test fails. Run the stage by hand with `bin/lib/prefire-verify --stage pytest`.

A file or nodeid argument keeps the run serial, so it starts no worker pool. When you change a shell script with no matching test file, find its tests with `grep -l <script-name> tests/*.py` and run those. When a change touches shared code such as `conftest.py` or `harness/state.py`, push and let CI run the whole suite.

A plan's `check:` row follows the same rule. `.claude/skills/plan/_architect-contract-detail.md` § Slow checks says why.
