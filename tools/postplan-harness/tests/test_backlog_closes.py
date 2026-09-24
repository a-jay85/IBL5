import os
import re
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.classify import BACKLOG_CLOSES_START, normalize_backlog_closes, _closing_ref_re
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


# ---------------------------------------------------------------------------
# Phase 2: normalize_backlog_closes
# ---------------------------------------------------------------------------

def test_normalize_appends_closes_line():
    out = normalize_backlog_closes("Summary", [11], [])
    assert out.count(f"Closes {REPO}#11") == 1
    assert BACKLOG_CLOSES_START in out


def test_normalize_skips_issue_already_closed_in_body():
    body = f"Fixes: {REPO}#11"
    out = normalize_backlog_closes(body, [11], [])
    assert BACKLOG_CLOSES_START not in out
    assert out.count(f"Closes {REPO}#11") == 0


def test_normalize_is_idempotent():
    body = "Summary"
    first = normalize_backlog_closes(body, [11], [12])
    second = normalize_backlog_closes(first, [11], [12])
    assert first == second


def test_normalize_replaces_stale_block():
    stale = f"Summary\n\n{BACKLOG_CLOSES_START}\nCloses {REPO}#7\n<!-- backlog-closes:end -->\n"
    out = normalize_backlog_closes(stale, [8], [])
    assert f"Closes {REPO}#7" not in out
    assert f"Closes {REPO}#8" in out
    assert out.count(BACKLOG_CLOSES_START) == 1


def test_normalize_strips_closing_keyword_for_refs():
    body = (
        f"Closes {REPO}#12\n"
        f"fixes: {REPO}#12\n"
        f"Resolved {REPO}#12\n"
    )
    out = normalize_backlog_closes(body, [], [12])
    assert _closing_ref_re(12).search(out) is None
    assert out.count(f"{REPO}#12") == 3


def test_normalize_refs_leaves_plain_mentions():
    body = f"Refs {REPO}#12"
    out = normalize_backlog_closes(body, [], [12])
    assert f"Refs {REPO}#12" in out


def test_normalize_refs_number_boundary():
    body = f"Closes {REPO}#12"
    out = normalize_backlog_closes(body, [], [1])
    assert f"Closes {REPO}#12" in out


def test_normalize_closes_wins_over_refs():
    out = normalize_backlog_closes("Summary", [5], [5])
    assert f"Closes {REPO}#5" in out


def test_normalize_never_emits_bare_hash():
    bare_kw_re = re.compile(
        r"(?i)\b(close[sd]?|fix(e[sd])?|resolve[sd]?)\s*:?\s+#\d")
    for body, closes, refs in [
        ("Summary", [11], []),
        (f"Fixes: {REPO}#11", [11], []),
        ("Summary", [], [12]),
        ("Summary", [5], [5]),
    ]:
        out = normalize_backlog_closes(body, closes, refs)
        assert bare_kw_re.search(out) is None, f"bare hash in output: {out!r}"


def test_normalize_empty_lists_passthrough():
    body = f"x\nCloses {REPO}#9"
    out = normalize_backlog_closes(body, [], [])
    assert out == body
