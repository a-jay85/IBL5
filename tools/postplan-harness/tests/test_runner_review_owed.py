import inspect
import json
import os
import re
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm, MODEL_MAP
from harness.classify import FILES_CHANGED_BEGIN, name_status_text, numstat_text
from harness.state import HarnessError, TerminalState, UsageLedger

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
        "slug": "review-owed-test",
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
        "current_tree": TREE,
    }
    fx.update(over)
    return fx


def _actions(out):
    p = os.path.join(out, "actions.jsonl")
    if not os.path.exists(p):
        return []
    with open(p) as fh:
        return [json.loads(line) for line in fh if line.strip()]


from harness import fidelity

TREE = "a" * 40
CANNED_UNAVAILABLE = {"verdict": "unavailable", "reason": "script-unavailable",
                      "fired": False, "command": ""}


def _stub_prn(tmp_path, monkeypatch):
    """A pr-review-now stand-in that logs its argv. Dry-run must never reach it."""
    prn = tmp_path / "prn"
    prn.write_text('#!/usr/bin/env bash\nprintf "%s\\n" "$*" >> "$PRN_LOG"\n')
    prn.chmod(0o755)
    monkeypatch.setenv("REVIEW_OWED_PR_REVIEW_NOW", str(prn))
    monkeypatch.setenv("PRN_LOG", str(tmp_path / "fired.log"))
    return tmp_path / "fired.log"


def _run(fx):
    out = tempfile.mkdtemp(prefix="postplan-test-review-owed-")
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay", headless=True)
    return res, out


def _sticky_bodies(out):
    return [a["body"] for a in _actions(out) if a["action"] == "pr_sticky_verdict"]


def test_replay_runs_the_real_script_in_dry_run_and_launches_nothing(tmp_path, monkeypatch):
    fired = _stub_prn(tmp_path, monkeypatch)
    res, out = _run(_fixture())
    ro = res.fidelity["review_owed"]
    assert ro["verdict"] == "REVIEW-OWED"   # a harness sticky never carries REVIEW-COVERAGE: CURRENT
    assert ro["fired"] is False
    assert ro["command"].startswith("DRY-RUN: ")
    assert not fired.exists()
    with open(os.path.join(out, "review-owed-sticky.md")) as fh:
        assert fh.read() == _sticky_bodies(out)[-1]
    assert any("phase6.5 review-owed: REVIEW-OWED" in line for line in res.audit)


def test_decision_runs_after_the_sticky_post_and_before_arming(tmp_path, monkeypatch):
    _stub_prn(tmp_path, monkeypatch)
    order, seen = [], {}
    real_sticky = runner.RecordingGh.pr_sticky_verdict
    real_arm = runner.RecordingGh.pr_merge_auto
    real_fire = fidelity.fire_review_owed

    def sticky(self, pr, body):
        order.append("sticky"); seen["body"] = body
        return real_sticky(self, pr, body)

    def arm(self, pr):
        order.append("arm")
        return real_arm(self, pr)

    def fire(worktree, master_sha, pr_number, sticky_body, current_tree, out_dir, **kw):
        order.append("review-owed"); seen["fire_body"] = sticky_body; seen["tree"] = current_tree
        return real_fire(worktree, master_sha, pr_number, sticky_body, current_tree, out_dir, **kw)

    monkeypatch.setattr(runner.RecordingGh, "pr_sticky_verdict", sticky)
    monkeypatch.setattr(runner.RecordingGh, "pr_merge_auto", arm)
    monkeypatch.setattr(runner.fidelity, "fire_review_owed", fire)
    _run(_fixture())
    assert order[:2] == ["sticky", "review-owed"]
    assert seen["fire_body"] == seen["body"]
    assert seen["tree"] == TREE


def test_fixture_seam_replaces_the_script_in_replay(monkeypatch):
    def boom(*a, **k):
        raise AssertionError("script ran despite the review_owed seam")
    monkeypatch.setattr(runner.fidelity, "fire_review_owed", boom)
    res, out = _run(_fixture(review_owed=CANNED_UNAVAILABLE))
    assert res.fidelity["review_owed"] == CANNED_UNAVAILABLE
    assert any("phase6.5 review-owed: unavailable (script-unavailable)" in l for l in res.audit)
    assert res.terminal is not runner.TerminalState.FAILED


def test_seam_is_gated_on_not_live():
    src = inspect.getsource(runner.run)
    assert re.search(r'\.get\("review_owed"\)\s+if not live else None', src)


@pytest.mark.parametrize("canned", [
    {"verdict": "REVIEW-OWED", "reason": TREE, "fired": True, "command": "FIRED: pr-review-now 9999"},
    {"verdict": "REVIEW-OWED", "reason": TREE, "fired": False, "command": "FIRE-FAILED: rc=1"},
    CANNED_UNAVAILABLE,
    {"verdict": "REVIEW-CURRENT", "reason": TREE, "fired": False, "command": ""},
])
def test_arming_is_unchanged_by_every_review_owed_outcome(tmp_path, monkeypatch, canned):
    _stub_prn(tmp_path, monkeypatch)
    base, base_out = _run(_fixture())
    res, out = _run(_fixture(review_owed=canned))
    assert (res.arm.armed, [c.name for c in res.arm.holds]) == \
        (base.arm.armed, [c.name for c in base.arm.holds])
    # Sorted: ReviewPhase runs on a worker thread, so its pr_comment can land before or
    # after the main thread's pr_edit_body. Order between the two is not the invariant.
    assert sorted(a["action"] for a in _actions(out)) == \
        sorted(a["action"] for a in _actions(base_out))
    assert res.terminal == base.terminal


def test_no_pr_skips_the_decision(tmp_path, monkeypatch):
    fired = _stub_prn(tmp_path, monkeypatch)
    res, out = _run(_fixture(pr_number=None, pr_meta=None))
    assert "review_owed" not in res.fidelity
    assert not os.path.exists(os.path.join(out, "review-owed-sticky.md"))
    assert not fired.exists()


@pytest.mark.parametrize("bad", ["REVIEW-OWED", {"fired": True}, None])
def test_malformed_seam_falls_through_to_the_script(tmp_path, monkeypatch, bad):
    fired = _stub_prn(tmp_path, monkeypatch)
    res, out = _run(_fixture(review_owed=bad))
    assert res.fidelity["review_owed"]["verdict"] == "REVIEW-OWED"
    assert res.fidelity["review_owed"]["command"].startswith("DRY-RUN: ")
    assert not fired.exists()
