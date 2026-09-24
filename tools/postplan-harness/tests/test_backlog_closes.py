import copy
import json
import os
import re
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.classify import BACKLOG_CLOSES_START, backlog_closes_mismatch, normalize_backlog_closes, _closing_ref_re
from harness.planfile import locate_plan, parse_backlog_issues
from harness.state import PlanInfo

# Repo root: tests/ -> harness/ parent -> tools/postplan-harness/ -> repo root
_TESTS_DIR = os.path.dirname(os.path.abspath(__file__))
_REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(_TESTS_DIR)))
_CLAIMS_DOC = os.path.join(_REPO_ROOT, ".claude", "skills", "post-plan", "_pr-body-claims.md")


def _snippet() -> str:
    """Extract the bash snippet from _pr-body-claims.md between the marker comments."""
    with open(_CLAIMS_DOC) as fh:
        content = fh.read()
    start = content.find("<!-- backlog-closes-snippet:start -->")
    end = content.find("<!-- backlog-closes-snippet:end -->")
    if start == -1 or end == -1:
        return ""
    block = content[start + len("<!-- backlog-closes-snippet:start -->"):end].strip()
    lines = block.splitlines()
    # strip the opening ```bash and closing ``` fence lines
    if lines and lines[0].startswith("```"):
        lines = lines[1:]
    if lines and lines[-1].strip() == "```":
        lines = lines[:-1]
    return "\n".join(lines)

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


# ---------------------------------------------------------------------------
# backlog_closes_mismatch
# ---------------------------------------------------------------------------

def test_mismatch_ok_when_github_links_all():
    msg = backlog_closes_mismatch([11], "master", [("a-jay85/IBL5-backlog", 11)])
    assert "self-check OK" in msg


def test_mismatch_warns_on_missing_issue():
    msg = backlog_closes_mismatch([11, 12], "master", [("a-jay85/IBL5-backlog", 11)])
    assert "WARN" in msg
    assert "a-jay85/IBL5-backlog#12" in msg
    assert "#11," not in msg


def test_mismatch_ignores_same_number_other_repo():
    msg = backlog_closes_mismatch([11], "master", [("a-jay85/IBL5", 11)])
    assert "WARN" in msg


def test_mismatch_skips_stacked_base():
    msg = backlog_closes_mismatch([11], "some-parent-branch", [])
    assert "SKIP" in msg
    assert "WARN" not in msg


# ---------------------------------------------------------------------------
# _check_backlog_closes helper
# ---------------------------------------------------------------------------

def test_check_backlog_closes_swallows_gh_error():
    import runner

    class _BrokenGh:
        def pr_closing_refs(self, pr):
            raise RuntimeError("network error")

    plan = PlanInfo(found=True, backlog_issues=[("closes", 11)])
    logged = []
    runner._check_backlog_closes(_BrokenGh(), 1, plan, logged.append)
    assert any("self-check skipped" in l for l in logged)


def test_check_backlog_closes_noop_without_closes():
    import runner

    class _CountingGh:
        def __init__(self):
            self.calls = 0
        def pr_closing_refs(self, pr):
            self.calls += 1
            return ("master", [])

    plan = PlanInfo(found=True, backlog_issues=[("refs", 11)])
    gh = _CountingGh()
    runner._check_backlog_closes(gh, 1, plan, lambda m: None)
    assert gh.calls == 0


# ---------------------------------------------------------------------------
# _apply_backlog_closes: plan-blind passthrough
# ---------------------------------------------------------------------------

def test_apply_backlog_closes_plan_blind_passthrough():
    import runner
    body = f"x\nCloses a-jay85/IBL5-backlog#9"
    plan = PlanInfo(found=False)
    result = runner._apply_backlog_closes(body, plan, lambda m: None)
    assert result == body


# ---------------------------------------------------------------------------
# Replay integration tests
# ---------------------------------------------------------------------------

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

_BACKLOG_PLAN = """\
# Backlog closes plan

## Backlog issues

- closes a-jay85/IBL5-backlog#11 — x
- refs a-jay85/IBL5-backlog#12 — y

## Verification Matrix

| # | Test | Test type | Timing | Planned tests |
|---|------|-----------|--------|---------------|
"""

_REPLAY_CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: backlog-closes-test",
                "commit_subject": "chore: backlog closes test",
                "summary_md": "## Summary\n- backlog closes test\n"},
    "body-check": {"corrected_body": "## Summary\n- backlog closes test\n", "findings": []},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}

_BACKLOG_FIXTURE = {
    "slug": "backlog-closes-replay",
    "diff": "diff --git a/ibl5/x.md b/ibl5/x.md\nnew file mode 100644\n--- /dev/null\n+++ b/ibl5/x.md\n@@ -0,0 +1 @@\n+# doc\n",
    "plan_content": _BACKLOG_PLAN,
    "pr_number": None,
    "pr_meta": None,
    "verify": {"phpunit": None, "phpstan": None, "go": None},
    "checks_outcome": {"exit": 0, "failed": []},
    "closing_refs": {"base": "master", "refs": [{"repo": "a-jay85/IBL5-backlog", "number": 11},
                                                 {"repo": "a-jay85/IBL5-backlog", "number": 12}]},
}


def _run_backlog_replay(fixture=None, canned=None):
    import runner
    from harness.adapters.llm import FixtureLlm
    from harness.state import UsageLedger
    fx = dict(_BACKLOG_FIXTURE if fixture is None else fixture)
    out = tempfile.mkdtemp(prefix="postplan-test-backlog-closes-")
    llm = FixtureLlm(UsageLedger(), copy.deepcopy(canned or _REPLAY_CANNED))
    res = runner.run(fx, out, llm, mode="replay", headless=True)
    from harness.adapters.ghad import RecordingGh
    gh = RecordingGh(out, fx)
    # Apply body_override from actions
    actions_path = os.path.join(out, "actions.jsonl")
    last_body = None
    if os.path.exists(actions_path):
        with open(actions_path) as fh:
            for line in fh:
                line = line.strip()
                if not line:
                    continue
                a = json.loads(line)
                if a.get("action") in ("pr_create", "pr_edit_body"):
                    last_body = a.get("body", "")
    return res, out, last_body


def test_replay_body_carries_closes_line():
    res, out, body = _run_backlog_replay()
    assert res.terminal is not None
    assert body is not None
    # Exactly one Closes line for #11
    assert body.count("Closes a-jay85/IBL5-backlog#11") == 1
    # No closing keyword before #12 (it's refs-kind)
    import re
    closing_re = re.compile(r"(?i)\b(close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s*:?\s+a-jay85/IBL5-backlog#12")
    assert not closing_re.search(body), f"Found closing keyword before #12 in body: {body!r}"


def test_replay_plan_blind_adds_no_closes():
    import runner
    from harness.adapters.llm import FixtureLlm
    from harness.state import UsageLedger
    fx = dict(_BACKLOG_FIXTURE)
    fx = {k: v for k, v in fx.items() if k != "plan_content"}
    out = tempfile.mkdtemp(prefix="postplan-test-backlog-blind-")
    llm = FixtureLlm(UsageLedger(), copy.deepcopy(_REPLAY_CANNED))
    res = runner.run(fx, out, llm, mode="replay", headless=True)
    # Read last body from actions
    actions_path = os.path.join(out, "actions.jsonl")
    last_body = None
    if os.path.exists(actions_path):
        with open(actions_path) as fh:
            for line in fh:
                line = line.strip()
                if not line:
                    continue
                a = json.loads(line)
                if a.get("action") in ("pr_create", "pr_edit_body"):
                    last_body = a.get("body", "")
    assert last_body is not None
    assert "backlog-closes:start" not in last_body


# ---------------------------------------------------------------------------
# Phase 5: skill snippet tests
# ---------------------------------------------------------------------------

def test_skill_snippet_markers_present():
    snippet = _snippet()
    assert snippet, "snippet between markers is empty — markers may be missing or deleted"
    assert "normalize_backlog_closes" in snippet


def test_skill_snippet_matches_harness():
    snippet = _snippet()
    assert snippet
    with tempfile.NamedTemporaryFile(mode="w", suffix=".md", delete=False) as pf:
        pf.write(
            "# Test\n\n## Backlog issues\n\n"
            "- closes a-jay85/IBL5-backlog#11 — x\n"
            "- refs a-jay85/IBL5-backlog#12 — y\n"
        )
        plan_path = pf.name
    original = "Summary\nFixes a-jay85/IBL5-backlog#12\n"
    with tempfile.NamedTemporaryFile(mode="w", suffix=".txt", delete=False) as bf:
        bf.write(original)
        body_path = bf.name
    try:
        env = {**os.environ, "PLAN": plan_path, "BODY_FILE": body_path}
        subprocess.run(["bash", "-c", snippet], cwd=_REPO_ROOT, env=env, check=True)
        with open(body_path) as fh:
            got = fh.read()
        expected = normalize_backlog_closes(original, [11], [12])
        assert got == expected
    finally:
        os.unlink(plan_path)
        os.unlink(body_path)


def test_skill_snippet_plan_blind_passthrough():
    snippet = _snippet()
    assert snippet
    original = "x\nCloses a-jay85/IBL5-backlog#9"
    with tempfile.NamedTemporaryFile(mode="w", suffix=".txt", delete=False) as bf:
        bf.write(original)
        body_path = bf.name
    try:
        env = {**os.environ, "PLAN": "/tmp/does-not-exist-plan.md", "BODY_FILE": body_path}
        subprocess.run(["bash", "-c", snippet], cwd=_REPO_ROOT, env=env, check=True)
        with open(body_path) as fh:
            got = fh.read()
        assert got == original
    finally:
        os.unlink(body_path)
