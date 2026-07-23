import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.planfile import (frontmatter_auto_merge_false, locate_plan,
                              parse_critical_files, parse_matrix)

PLAN = """---
status: ready
auto_merge: false
---

# My Plan

## Security
CSRF token on the new POST endpoint via `CsrfGuard::validateSubmittedToken`.

## Verification Matrix

| Behavior | Test type | Test |
|---|---|---|
| saves row | PHPUnit | `ibl5/tests/Unit/EventLoggerTest.php` |
| page loads | E2E | `ibl5/tests/e2e/admin/events.spec.ts` |
| looks right | Truly-manual | eyeball the dashboard |

## Critical Files

- `ibl5/classes/EventLogger.php` — new logger class
- `ibl5/schema.sql` — reference only, do not edit
"""


def test_frontmatter_gate():
    assert frontmatter_auto_merge_false(PLAN)
    assert not frontmatter_auto_merge_false(PLAN.replace("auto_merge: false", "auto_merge: true"))
    # body mention without line-1 frontmatter must NOT self-select
    assert not frontmatter_auto_merge_false("# doc\nuse `auto_merge: false` in plans\n")


def test_matrix_and_critical_files():
    planned, manual = parse_matrix(PLAN)
    assert planned == ["ibl5/tests/Unit/EventLoggerTest.php", "ibl5/tests/e2e/admin/events.spec.ts"]
    assert len(manual) == 1 and "eyeball" in manual[0]
    cf = parse_critical_files(PLAN)
    assert cf[0][0] == "ibl5/classes/EventLogger.php" and not cf[0][2]
    assert cf[1][0] == "ibl5/schema.sql" and cf[1][2]  # "reference only" -> exempt


def test_locate_plan_missing_and_override():
    assert not locate_plan("no-such-slug", plans_dir="/nonexistent").found
    info = locate_plan("x", content_override=PLAN)
    assert info.found and info.auto_merge_false and info.has_matrix and info.has_security


def test_conformance():
    plan = locate_plan("x", content_override=PLAN)
    clean = conformance.check(plan, ["ibl5/tests/Unit/EventLoggerTest.php",
                                     "ibl5/tests/e2e/admin/events.spec.ts",
                                     "ibl5/classes/EventLogger.php"])
    assert clean == []
    missing = conformance.check(plan, ["ibl5/classes/EventLogger.php"])
    assert len(missing) == 2 and all(m.startswith("MISSING:") for m in missing)
    # exempt critical file (schema.sql) never demands a diff appearance
    assert not any("schema.sql" in m for m in missing)
