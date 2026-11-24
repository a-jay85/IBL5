#!/usr/bin/env python3
"""Phase 5.5: a round that cannot clear a kept [unplanned-scope] blocker skips the re-review."""
from __future__ import annotations

import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, holdrepeat
from harness.adapters.ghad import RecordingGh
from harness.state import UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_rounds import (GIT_SHIM, TREE_1, TREE_2, TREE_3, TREE_4, _CountingGit,
                                   _ScriptedLlm, _cleanup)

GRID = "ibl5/design/components/negotiate.css"
KEEP_ITEM = (f"- [BLOCKING] [unplanned-scope] Remove or plan the button grid in {GRID}. "
             "Next action: move it.")
BODY_CLAIM_ITEM = "- [BLOCKING] The PR body claims a test that does not exist."
PR = 9961


def _review(*items):
    return "## FINDINGS\n" + "\n".join(items) + "\n\nNOT READY\n"


@pytest.fixture()
def loop_env(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    shim = bindir / "git"
    shim.write_text(GIT_SHIM)
    shim.chmod(shim.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setattr(runner.removed_guards, "run_detector", lambda *a: (True, []))
    calls = {"re_review": 0, "body_recheck": 0}

    def fake_re_review(*a, **kw):
        calls["re_review"] += 1
        return None, None, None

    def fake_body_recheck(*a, **kw):
        calls["body_recheck"] += 1
        return None, None, None, "stubbed"

    monkeypatch.setattr(fidelity, "re_review", fake_re_review)
    monkeypatch.setattr(fidelity, "body_recheck", fake_body_recheck)
    yield calls
    _cleanup(PR, f"{PR}-2", f"{PR}-3", f"{PR}-4")


def _drive(tmp_path, review, *, commit_returns, bodies, conformance=("MISSING: unrelated.php",)):
    gh = _BodySeqGh(str(tmp_path), bodies, fixture={"pr_number": PR})
    git = _CountingGit({"slug": "demo", "worktree_diff": "",
                        "diff": f"diff --git a/{GRID} b/{GRID}\n",
                        "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4]},
                       commit_returns=commit_returns)
    llm = _ScriptedLlm(UsageLedger(), {"plan-fidelity-review": [review],
                                       "fidelity-remediation": ["edited"]})
    res = types.SimpleNamespace(fidelity={}, reused_from={})
    plan = types.SimpleNamespace(found=False, path="", auto_merge_false=False)
    logs: list[str] = []
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, plan, "diff", "body", PR,
                         "dead" * 10, TREE_1, False, logs.append, res,
                         unresolved_conformance=list(conformance))
    return res, logs


def test_body_only_kept_scope_only_skips_rereview(tmp_path, loop_env):
    res, logs = _drive(tmp_path, _review(KEEP_ITEM), commit_returns=[""],
                       bodies=["BEFORE prose", "BEFORE prose", "AFTER prose", "AFTER prose"])
    rec = res.fidelity["rounds"][0]
    assert rec["remediation_sha"] == "body-only"
    assert rec["outcome"] == runner.KEPT_SCOPE_ONLY_OUTCOME == "kept-scope-owner-only"
    assert rec["review_kind"] == "skipped"
    assert loop_env == {"re_review": 0, "body_recheck": 0}
    assert any("re-review skipped" in m and "Waiting on owner approval" in m for m in logs)
    assert res.fidelity.get("verdict_2") is None
    assert len(res.fidelity["rounds"]) == 1
    # The skipped round still reads as NOT READY with kept-scope-only blockers, so the
    # holdrepeat record gets the owner-only kind for this very run.
    assert res.fidelity["review_items"][-1]["verdict"] == "NOT READY"
    assert holdrepeat.owner_only_kinds(
        [types.SimpleNamespace(number=12, name="plan-fidelity", blocked=True, reason="NOT READY")],
        res.fidelity) == [holdrepeat.KIND_KEPT_SCOPE]


def test_body_only_with_body_claim_still_rereviews(tmp_path, loop_env):
    res, _ = _drive(tmp_path, _review(KEEP_ITEM, BODY_CLAIM_ITEM), commit_returns=[""],
                    bodies=["BEFORE prose", "BEFORE prose", "AFTER prose", "AFTER prose"])
    assert res.fidelity["rounds"][0]["remediation_sha"] == "body-only"
    assert res.fidelity["rounds"][0]["outcome"] != runner.KEPT_SCOPE_ONLY_OUTCOME
    assert loop_env["re_review"] + loop_env["body_recheck"] >= 1


def test_committed_round_kept_scope_still_rereviews(tmp_path, loop_env):
    res, _ = _drive(tmp_path, _review(KEEP_ITEM), commit_returns=["c0ffee" * 6 + "abcd"],
                    bodies=["body"])
    assert res.fidelity["rounds"][0]["remediation_sha"] != "body-only"
    assert res.fidelity["rounds"][0]["outcome"] != runner.KEPT_SCOPE_ONLY_OUTCOME
    assert loop_env["re_review"] >= 1


def test_helper_fails_open_on_unreadable_verdict():
    assert runner._kept_scope_only_round("/nonexistent/verdict.md", [{"text": KEEP_ITEM}]) is False
    assert runner._kept_scope_only_round(None, None) is False


def test_helper_true_for_kept_only_verdict(tmp_path):
    path = tmp_path / "v.md"
    path.write_text(_review(KEEP_ITEM), encoding="utf-8")
    assert runner._kept_scope_only_round(str(path), [{"text": KEEP_ITEM}]) is True
    path.write_text(_review(KEEP_ITEM, BODY_CLAIM_ITEM), encoding="utf-8")
    assert runner._kept_scope_only_round(str(path), [{"text": KEEP_ITEM}]) is False


if __name__ == "__main__":
    # The plan matrix cell is this file path alone, so the file runs itself.
    import pytest
    sys.exit(pytest.main(["-q", __file__]))
