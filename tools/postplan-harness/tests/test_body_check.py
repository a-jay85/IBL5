import inspect
import json
import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm, MODEL_MAP
from harness.classify import name_status_text, numstat_text
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
    """On the pr-copy skip path, _body_check must call pr_body_fresh(), not pr_body() directly."""
    fresh_calls = []

    class _TrackingGh(runner.RecordingGh):
        def pr_body_fresh(self):
            fresh_calls.append(1)
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
    assert len(fresh_calls) >= 1, "pr_body_fresh() was not called"


def test_body_check_does_not_edit_live_body_on_skip_path():
    """On the clean-tree rerun, summary_md stays '' so body-check correction is not written back."""
    sentinel = "99-files-changed-sentinel"

    class _TrackingGh(runner.RecordingGh):
        def pr_body_fresh(self):
            self._body_override = None
            return "## Summary\n- 1 file changed\n"

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
    try:
        runner.RecordingGh = _TrackingGh
        res = runner.run(fx, out, FixtureLlm(UsageLedger(), canned_no_copy), mode="replay")
    finally:
        runner.RecordingGh = orig_gh

    assert res.terminal != TerminalState.FAILED, res.error
    # The sentinel from corrected_body must NOT appear in any pr_edit_body action
    for act in _actions(out):
        if act.get("action") == "pr_edit_body":
            assert sentinel not in act.get("body", ""), (
                f"sentinel leaked into pr_edit_body: {act.get('body', '')[:200]!r}")


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


def test_pr_copy_tier_is_sonnet_4_6():
    """MODEL_MAP['sonnet'] resolves to claude-sonnet-4-6, and _pr_copy uses 'sonnet'."""
    assert MODEL_MAP["sonnet"] == "claude-sonnet-4-6"
    src = inspect.getsource(runner._pr_copy)
    assert 'llm.call("pr-copy", "sonnet"' in src
    assert 'llm.call("pr-copy", "haiku"' not in src
