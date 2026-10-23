import inspect
import json
import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import schemas
from harness.adapters.llm import FixtureLlm, MODEL_MAP
from harness.classify import FILES_CHANGED_BEGIN, TESTS_CHANGED_BEGIN, name_status_text, numstat_text
from harness.state import HarnessError, TerminalState, UsageLedger

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit", "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _fixture(**over):
    fx = {
        "slug": "body-pass-test",
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

def test_body_pass_skipped_when_pr_copy_degraded():
    out = tempfile.mkdtemp()
    llm = DegradingLlm(UsageLedger(), CANNED, {"pr-copy"})
    res = runner.run(_fixture(head_subject="fix: real"), out, llm, mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert res.degraded_agents == [schemas.PR_COPY_PURPOSE]


def test_body_pass_uses_pr_body_fresh_on_clean_rerun():
    """On the pr-copy skip path, _mechanical_body_pass must call pr_body_fresh() exactly once and
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
        "_mechanical_body_pass must call pr_body_fresh() not pr_body(); "
        f"first body read was {body_reads[0]!r}")


def test_body_pass_does_not_edit_live_body_on_skip_path():
    """On the clean-tree rerun, summary_md stays '' so the body pass correction is
    not written back. Checks: (a) every pr_edit_body action carries the live PR body
    unchanged (the fixture body) and there is at least one, and (b) the commit message
    body is empty — both required to catch the named mutation."""
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
    canned_no_copy = {k: v for k, v in CANNED.items() if k != "pr-copy"}
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
    # (a) the live body is rewritten unchanged: the body pass's corrected text (which would
    # say "1 file changed") must never reach pr_edit_body
    live_body = fx["pr_meta"]["body"]
    edits = [a for a in _actions(out) if a.get("action") == "pr_edit_body"]
    assert edits, "expected the live body to be re-published unchanged; no pr_edit_body action"
    for act in edits:
        assert act.get("body") == live_body, (
            f"pr_edit_body altered the live body: {act.get('body', '')[:200]!r}")
    # (b) commit message body must be empty — summary_md stayed ""
    assert commit_messages_log, "commit_all was never called"
    msg_body = (commit_messages_log[0].split("\n\n", 1)[1]
                if "\n\n" in commit_messages_log[0] else "")
    assert not msg_body.strip(), (
        f"commit message body is not empty — summary_md was written back: {msg_body[:100]!r}")


def test_missing_merge_danger_line_holds_arming_under_merge_danger_label(monkeypatch):
    """A body with no Merge danger line degrades the run under its own label, and
    condition 9 holds with `review unavailable: merge-danger`."""
    from harness import merge_danger
    monkeypatch.setattr(merge_danger, "inject_merge_danger", lambda body, md: body)
    out = tempfile.mkdtemp()
    res = runner.run(_fixture(), out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    assert "merge-danger" in res.degraded_agents
    assert res.terminal == TerminalState.DEGRADED
    assert not res.arm.armed
    assert any("review unavailable: merge-danger" in (c.reason or "") for c in res.arm.holds)


def test_corrected_body_reaches_pr_create():
    """The mechanically corrected body is what pr create receives."""
    out = tempfile.mkdtemp()
    canned = dict(CANNED)
    canned["pr-copy"] = dict(CANNED["pr-copy"], summary_md="## Summary\n- 7 files changed\n")
    # No existing PR: pr_number absent -> goes to pr_create path
    fx = _fixture()
    fx.pop("pr_number", None)
    fx["pr_meta"] = None
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    create_actions = [a for a in _actions(out) if a.get("action") == "pr_create"]
    assert create_actions, "pr_create not found in recorded actions"
    body = create_actions[0].get("body", "")
    assert "7 files changed" not in body, f"seeded figure survived: {body[:200]!r}"
    assert "- 1 files changed\n" in body
    for marker in (FILES_CHANGED_BEGIN, TESTS_CHANGED_BEGIN):
        assert marker not in body, f"retired block written to pr_create body: {body[:200]!r}"


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


def _tc_check(claim, diff=None, worktree=None):
    body = f"- `{_TC_PATH}` adds {claim} tests\n"
    copy = {"summary_md": body}
    return runner._mechanical_body_pass(
        _TcGit(diff or _tc_diff()), _TcGh(), copy, False, lambda _m: None, worktree=worktree)


def test_body_pass_appends_test_count_finding():
    result = _tc_check(5)
    assert f"5 tests [harness: measured 3 added / 3 total in `{_TC_PATH}`]" in result["corrected_body"]
    assert any("test-count claim mismatch" in f and "body says 5" in f for f in result["findings"])


def test_body_pass_matching_test_count_adds_no_finding():
    result = _tc_check(3)
    assert result["corrected_body"] == f"- `{_TC_PATH}` adds 3 tests\n"
    assert result["findings"] == []


def test_body_pass_uses_worktree_for_head_total(tmp_path):
    """A claim of 9 against a 7-test file is flagged only when `worktree=worktree` reaches
    _mechanical_body_pass from the runner call site and `read_file` reads the working tree."""
    target = tmp_path / _TC_PATH
    target.parent.mkdir(parents=True)
    target.write_text("\n".join(f"    public function testN{i}(): void {{}}" for i in range(7)))
    diff = _tc_diff(status="M")
    clean = _tc_check(7, diff=diff, worktree=str(tmp_path))
    assert "[harness: measured" not in clean["corrected_body"]
    flagged = _tc_check(9, diff=diff, worktree=str(tmp_path))
    assert f"[harness: measured 3 added / 7 total in `{_TC_PATH}`]" in flagged["corrected_body"]


def test_body_pass_corrects_numbers_before_annotating_test_counts():
    """Order guard: correct_body_numbers must run before annotate_test_count_mismatches.

    The diff adds 3 test lines plus 4 other lines (7 added in total, 2 files). The body has a
    wrong file count (corrected to 2) and a wrong test-count claim (annotated with
    `3 added / 3 total`). If the passes were swapped, the inserted note's `3 added` would match
    the insertions rule and be rewritten to `7 added`.
    """
    other = "docs/notes.md"
    diff = (_tc_diff()
            + f"diff --git a/{other} b/{other}\nnew file mode 100644\n--- /dev/null\n"
            + f"+++ b/{other}\n@@ -1,0 +1,4 @@\n" + "+x\n" * 4)
    body = f"- `{_TC_PATH}` adds 5 tests\n- 9 files changed\n"
    result = runner._mechanical_body_pass(
        _TcGit(diff), _TcGh(), {"summary_md": body}, False, lambda _m: None)
    assert result["corrected_body"] == (
        f"- `{_TC_PATH}` adds 5 tests [harness: measured 3 added / 3 total in `{_TC_PATH}`]\n"
        "- 2 files changed\n")
