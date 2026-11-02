"""POSTPLAN_FIRED_BY attribution: the label is logged and stored, never used in a decision."""
from __future__ import annotations

import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger


def test_fired_by_unset_is_unknown(monkeypatch):
    monkeypatch.delenv("POSTPLAN_FIRED_BY", raising=False)
    assert runner._fired_by() == "unknown"


def test_fired_by_empty_is_unknown(monkeypatch):
    monkeypatch.setenv("POSTPLAN_FIRED_BY", "")
    assert runner._fired_by() == "unknown"


@pytest.mark.parametrize("label", ["post-plan-now", "post-plan-fleet", "pr-cycle-tick", "automouse"])
def test_fired_by_accepts_caller_labels(monkeypatch, label):
    monkeypatch.setenv("POSTPLAN_FIRED_BY", label)
    assert runner._fired_by() == label


@pytest.mark.parametrize("value", [
    "PR-CYCLE",        # uppercase
    "pr cycle",        # space
    "pr-cycle;rm",     # shell metachar
    "-lead",           # leading dash
    "a" * 41,          # one over the 40-char cap
])
def test_fired_by_rejects_malformed(monkeypatch, value):
    monkeypatch.setenv("POSTPLAN_FIRED_BY", value)
    assert runner._fired_by() == "unknown"


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_fired_by_lands_in_result_json_and_audit_log(monkeypatch, tmp_path):
    monkeypatch.setenv("POSTPLAN_FIRED_BY", "pr-cycle-tick")
    fx = {
        "slug": "fired-by-test",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9996,
        "pr_meta": {"number": 9996, "title": "fix: synthetic", "body": "", "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody.\n",
    }
    canned = {
        "pr-copy": {"type": "chore", "title": "chore: fired-by-test",
                    "commit_subject": "chore: test commit", "summary_md": "## Summary\n- x\n"},
        "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
        "security-audit": [],
        "safety-verdict": {"holds": []},
        "manual-classify": [],
        "retrospective": {"save": False},
    }
    out = tmp_path / "out"
    runner.run(fx, str(out), FixtureLlm(UsageLedger(), canned), mode="replay")
    assert json.loads((out / "result.json").read_text())["fired_by"] == "pr-cycle-tick"
    assert "postplan: fired-by=pr-cycle-tick" in (out / "audit.log").read_text()
