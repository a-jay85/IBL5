import inspect
import json
import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm, MODEL_MAP
from harness.classify import FILES_CHANGED_BEGIN, name_status_text, numstat_text
from harness.state import HarnessError, TerminalState, UsageLedger

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit", "summary_md": "## Summary\n- x\n"},
    "body-check": {"corrected_body": "## Summary\n- replay body\n", "findings": []},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _fixture(**over):
    fx = {
        "slug": "body-check-test",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9999,
        "pr_meta": {"number": 9999, "title": "fix: synthetic",
                    "body": "## Summary\n- 1 file changed\n\n## Manual Testing\n\nNone\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody.\n",
    }
    fx.update(over)
    return fx


def _actions(out):
    p = os.path.join(out, "actions.jsonl")
    if not os.path.exists(p):
        return []
    with open(p) as fh:
        return [json.loads(line) for line in fh if line.strip()]


class DegradingLlm(FixtureLlm):
    def __init__(self, ledger, canned, bad_purposes, kind="llm-invalid-output"):
        super().__init__(ledger, canned)
        self.bad, self.kind = set(bad_purposes), kind

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose in self.bad:
            raise HarnessError(self.kind, f"{purpose}: forced failure")
        return super().call(purpose, model, prompt, validate, max_retries, normalizer)


# ── classify.py helper tests ──────────────────────────────────────────────────

def test_name_status_text_matches_git_name_status_format():
    diff = (
        "diff --git a/ibl5/x.php b/ibl5/x.php\n"
        "new file mode 100644\n"
        "--- /dev/null\n"
        "+++ b/ibl5/x.php\n"
        "@@ -0,0 +1 @@\n"
        "+<?php\n"
    )
    out = name_status_text(diff)
    lines = [ln for ln in out.splitlines() if ln.strip()]
    assert len(lines) == 1
    parts = lines[0].split("\t")
    assert len(parts) == 2, f"expected tab-separated status\\tpath, got {lines[0]!r}"


def test_numstat_text_marks_binary_files_with_dashes():
    diff = (
        "diff --git a/ibl5/image.png b/ibl5/image.png\n"
        "Binary files a/ibl5/image.png and b/ibl5/image.png differ\n"
    )
    out = numstat_text(diff)
    assert out.startswith("-\t-\t"), f"expected binary placeholder, got {out!r}"


# ── runner integration tests ──────────────────────────────────────────────────

def test_body_check_skipped_when_pr_copy_degraded():
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"pr-copy"})
    res = runner.run(_fixture(head_subject="fix: real"), out, llm, mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert "body-check" not in res.degraded_agents
    assert "body-check" not in [c.purpose for c in res.ledger.calls]


def test_body_check_uses_pr_body_fresh_on_clean_rerun():
    """On the pr-copy skip path, _body_check must call pr_body_fresh() exactly once and
    must not call pr_body() before pr_body_fresh() (row 22 mutation guard)."""
    body_reads: list[str] = []

    class _TrackingGh(runner.RecordingGh):
        def pr_body(self) -> str:
            body_reads.append("direct")
            return super().pr_body()

        def pr_body_fresh(self):
            body_reads.append("fresh")
            self._body_override = None
            return (self.fixture.get("pr_meta") or {}).get("body") or ""

    out = tempfile.mkdtemp()
    canned = {k: v for k, v in CANNED.items() if k != "pr-copy"}
    fx = _fixture(clean_tree=True, head_subject="feat: widget")
    fx["pr_meta"] = dict(fx["pr_meta"], title="feat: widget")

    orig_gh = runner.RecordingGh
    try:
        runner.RecordingGh = _TrackingGh
        res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned), mode="replay")
    finally:
        runner.RecordingGh = orig_gh

    assert res.terminal != TerminalState.FAILED, res.error
    assert body_reads.count("fresh") == 1, (
        f"pr_body_fresh() must be called exactly once, got {body_reads.count('fresh')}")
    assert body_reads[0] == "fresh", (
        "_body_check must call pr_body_fresh() not pr_body(); "
        f"first body read was {body_reads[0]!r}")


def test_body_check_does_not_edit_live_body_on_skip_path():
    """On the clean-tree rerun, summary_md stays '' so body-check correction is not
    written back. Checks: (a) sentinel absent from pr_edit_body and (b) the commit
    message body is empty — both required to catch the row 23 named mutation."""
    sentinel = "99-files-changed-sentinel"
    commit_messages_log: list[str] = []

    class _TrackingGh(runner.RecordingGh):
        def pr_body_fresh(self):
            self._body_override = None
            return "## Summary\n- 1 file changed\n"

    class _TrackingGit(runner.ReplayGit):
        def commit_all(self, message: str) -> str:
            commit_messages_log.append(message)
            return super().commit_all(message)

    out = tempfile.mkdtemp()
    canned_with_correction = dict(CANNED)
    canned_with_correction["body-check"] = {
        "corrected_body": f"## Summary\n- {sentinel}\n",
        "findings": ["file count was wrong"],
    }
    canned_no_copy = {k: v for k, v in canned_with_correction.items() if k != "pr-copy"}
    fx = _fixture(clean_tree=True, head_subject="feat: widget")
    fx["pr_meta"] = dict(fx["pr_meta"], title="feat: widget")

    orig_gh = runner.RecordingGh
    orig_git = runner.ReplayGit
    try:
        runner.RecordingGh = _TrackingGh
        runner.ReplayGit = _TrackingGit
        res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned_no_copy), mode="replay")
    finally:
        runner.RecordingGh = orig_gh
        runner.ReplayGit = orig_git

    assert res.terminal != TerminalState.FAILED, res.error
    # (a) sentinel must NOT appear in any pr_edit_body action
    for act in _actions(out):
        if act.get("action") == "pr_edit_body":
            assert sentinel not in act.get("body", ""), (
                f"sentinel leaked into pr_edit_body: {act.get('body', '')[:200]!r}")
    # (b) commit message body must be empty — summary_md stayed ""
    assert commit_messages_log, "commit_all was never called"
    msg_body = (commit_messages_log[0].split("\n\n", 1)[1]
                if "\n\n" in commit_messages_log[0] else "")
    assert not msg_body.strip(), (
        f"commit message body is not empty — summary_md was written back: {msg_body[:100]!r}")


def test_body_check_degradation_joins_degraded_agents_and_holds_arming():
    """A body-check llm-invalid-output degrades the run and sets DEGRADED terminal."""
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"body-check"})
    res = runner.run(_fixture(), out, llm, mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert "body-check" in res.degraded_agents
    assert res.terminal == TerminalState.DEGRADED


def test_body_check_fixture_missing_degrades_instead_of_failing():
    """An uncanned body-check label degrades rather than raising."""
    out = tempfile.mkdtemp()
    canned = {k: v for k, v in CANNED.items() if k != "body-check"}
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), canned), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert "body-check" in res.degraded_agents


def test_corrected_body_reaches_pr_create():
    """The body-check's corrected_body is written into the PR body on the write-back path."""
    out = tempfile.mkdtemp()
    sentinel = "BODY-CHECK-APPLIED"
    canned = dict(CANNED)
    canned["body-check"] = {
        "corrected_body": f"## Summary\n- {sentinel}\n",
        "findings": [],
    }
    # No existing PR: pr_number absent → goes to pr_create path
    fx = _fixture()
    fx.pop("pr_number", None)
    fx["pr_meta"] = None
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    create_actions = [a for a in _actions(out) if a.get("action") == "pr_create"]
    assert create_actions, "pr_create not found in recorded actions"
    body = create_actions[0].get("body", "")
    assert sentinel in body, f"sentinel not found in pr_create body: {body[:200]!r}"
    assert FILES_CHANGED_BEGIN in body, (
        f"files-changed block not regenerated in pr_create body: {body[:200]!r}"
    )


def test_pr_copy_tier_is_sonnet_5_5():
    """MODEL_MAP['sonnet'] resolves to claude-sonnet-5-5, and _pr_copy uses 'sonnet'."""
    assert MODEL_MAP["sonnet"] == "claude-sonnet-5-5"
    src = inspect.getsource(runner._pr_copy)
    assert 'llm.call(schemas.PR_COPY_PURPOSE, "sonnet"' in src
    assert 'llm.call(schemas.PR_COPY_PURPOSE, "haiku"' not in src


# ── test-count claim verification ─────────────────────────────────────────────

_TC_PATH = "ibl5/tests/Foo/BarCountTest.php"
_TC_ADDED = ["    public function testA(): void {}",
             "    public function testB(): void {}",
             "    public function testC(): void {}"]


def _tc_diff(status="A"):
    head = f"diff --git a/{_TC_PATH} b/{_TC_PATH}\n"
    head += "new file mode 100644\n--- /dev/null\n" if status == "A" else f"--- a/{_TC_PATH}\n"
    return head + f"+++ b/{_TC_PATH}\n@@ -1,0 +1,3 @@\n" + "".join(f"+{ln}\n" for ln in _TC_ADDED)


class _TcGit:
    def __init__(self, diff):
        self._diff = diff

    def diff_vs_base(self):
        return self._diff


class _TcGh:
    def pr_body_fresh(self):
        return ""


def _tc_check(claim, degraded=False, diff=None, worktree=None):
    body = f"- `{_TC_PATH}` adds {claim} tests\n"
    canned = {"body-check": {"corrected_body": body, "findings": ["llm finding"]}}
    llm = (DegradingLlm(UsageLedger(), canned, {"body-check"}) if degraded
           else FixtureLlm(UsageLedger(), canned))
    copy = {"summary_md": body}
    return runner._body_check(
        llm, _TcGit(diff or _tc_diff()), _TcGh(), copy, False, None, lambda _m: None,
        worktree=worktree)


def test_body_check_appends_test_count_finding_on_llm_path():
    result, degraded = _tc_check(5)
    assert degraded is False
    assert f"5 tests [harness: measured 3 added / 3 total in `{_TC_PATH}`]" in result["corrected_body"]
    assert result["findings"][0] == "llm finding"
    assert any("test-count claim mismatch" in f and "body says 5" in f for f in result["findings"])


def test_body_check_test_count_finding_survives_degraded_llm():
    result, degraded = _tc_check(5, degraded=True)
    assert degraded is True
    assert "[harness: measured 3 added / 3 total" in result["corrected_body"]
    assert any("test-count claim mismatch" in f for f in result["findings"])


def test_body_check_matching_test_count_adds_no_finding():
    result, _ = _tc_check(3)
    assert result["corrected_body"] == f"- `{_TC_PATH}` adds 3 tests\n"
    assert result["findings"] == ["llm finding"]


def test_body_check_uses_worktree_for_head_total(tmp_path):
    """A claim of 9 against a 7-test file is flagged only when `worktree=worktree` reaches
    _body_check from the runner.py:364 call site and `read_file` reads the working tree."""
    target = tmp_path / _TC_PATH
    target.parent.mkdir(parents=True)
    target.write_text("\n".join(f"    public function testN{i}(): void {{}}" for i in range(7)))
    diff = _tc_diff(status="M")
    clean, _ = _tc_check(7, diff=diff, worktree=str(tmp_path))
    assert "[harness: measured" not in clean["corrected_body"]
    flagged, _ = _tc_check(9, diff=diff, worktree=str(tmp_path))
    assert f"[harness: measured 3 added / 7 total in `{_TC_PATH}`]" in flagged["corrected_body"]
