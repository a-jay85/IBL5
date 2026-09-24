import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.planfile import locate_plan, parse_backlog_issues

REPO = "a-jay85/IBL5-backlog"

PLAN_WITH_CLOSES_AND_REFS = f"""# Test Plan

## Backlog issues

- closes {REPO}#11 — fully resolves this
- refs {REPO}#12 — partially addresses this

## Out of Scope

- nothing
"""

PLAN_NO_SECTION = """# Test Plan

## Approach

Just some text.
"""

PLAN_MALFORMED = f"""# Test Plan

## Backlog issues

- closes #13 — bare hash
- closes other/repo#14 — wrong repo
- fixes {REPO}#15 — wrong keyword
- this mentions {REPO}#16 in prose

## Notes
"""

PLAN_FENCED = f"""# Test Plan

## Backlog issues

```
- closes {REPO}#17 — in a fence
```

## Notes
"""

PLAN_STOPS_AT_HEADING = f"""# Test Plan

## Backlog issues

## Out of Scope

- closes {REPO}#18 — under wrong heading
"""

PLAN_DEDUPE = f"""# Test Plan

## Backlog issues

- refs {REPO}#20 — first
- closes {REPO}#20 — wins
- closes {REPO}#20 — duplicate

## Notes
"""

PLAN_SINGLE_CLOSES = f"""# Test Plan

## Backlog issues

- closes {REPO}#11 — only one
"""


def test_parse_backlog_issues_closes_and_refs():
    result = parse_backlog_issues(PLAN_WITH_CLOSES_AND_REFS)
    assert result == [("closes", 11), ("refs", 12)]


def test_parse_backlog_issues_absent_section_returns_empty():
    result = parse_backlog_issues(PLAN_NO_SECTION)
    assert result == []


def test_parse_backlog_issues_ignores_malformed_lines():
    result = parse_backlog_issues(PLAN_MALFORMED)
    assert result == []


def test_parse_backlog_issues_skips_fenced_examples():
    result = parse_backlog_issues(PLAN_FENCED)
    assert result == []


def test_parse_backlog_issues_stops_at_next_heading():
    result = parse_backlog_issues(PLAN_STOPS_AT_HEADING)
    assert result == []


def test_parse_backlog_issues_closes_wins_and_dedupes():
    result = parse_backlog_issues(PLAN_DEDUPE)
    assert result == [("closes", 20)]


def test_locate_plan_populates_backlog_issues():
    with tempfile.TemporaryDirectory() as tmpdir:
        plan_path = os.path.join(tmpdir, "my-branch.md")
        with open(plan_path, "w") as f:
            f.write(PLAN_SINGLE_CLOSES)
        info = locate_plan("my-branch", plans_dir=tmpdir)
    assert info.backlog_issues == [("closes", 11)]
