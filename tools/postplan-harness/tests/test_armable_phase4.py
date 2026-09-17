"""Phase 4 — conditions (11) and (14), and the completed condition (12).

The parity oracle is `bin/test-postplan-arm-conditions`; the section-8 case names it
uses are carried in the test ids here so a divergence is greppable from either side.
"""
import os
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.armable import (ArmInputs, conflict_flag_path, evaluate,
                             select_fidelity_verdict)
from harness.state import Classification

BODY_CLEARED = "## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed — covered.\n"
TREE = "a" * 40
STALE = "b" * 40


def inputs(**kw):
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(), findings=[], unresolved_conformance=[],
                phase5_status="pass", plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                unresolved_findings=[], conflict_resolved=False, current_tree=TREE)
    base.update(kw)
    return ArmInputs(**base)


def _held(d):
    return {c.number for c in d.holds}


# --- condition (12) parity mirror ---------------------------------------------

@pytest.mark.parametrize("name,v1,v2,tree2,arms", [
    ("v2-fresh-ready-over-v1-not-ready", "NOT READY", "READY", TREE, True),
    ("v2-fresh-not-ready-over-v1-ready", "READY", "NOT READY", TREE, False),
    ("v2-stale-tree-v1-not-ready", "NOT READY", "READY", STALE, False),
    ("v2-empty-falls-back-to-v1-ready", "READY", "", TREE, True),
    ("v2-no-reviewed-tree-falls-back-to-v1-ready", "READY", "READY", None, True),
    ("no-verdict-file-at-all", None, None, None, False),
    ("v2-fresh-ready-with-notes", "NOT READY", "READY WITH NOTES", TREE, True),
])
def test_condition_12_parity_with_the_shell_oracle(name, v1, v2, tree2, arms):
    d = evaluate(inputs(fidelity_verdict=v1, fidelity_verdict_2=v2, fidelity_tree_2=tree2))
    assert d.armed is arms, name
    if not arms:
        assert 12 in _held(d), name


def test_selected_source_is_named_in_the_reason():
    d = evaluate(inputs(fidelity_verdict="READY", fidelity_verdict_2="NOT READY",
                        fidelity_tree_2=TREE))
    reason = [c.reason for c in d.holds if c.number == 12][0]
    assert "source: verdict-2" in reason
    d2 = evaluate(inputs(fidelity_verdict="NOT READY", fidelity_verdict_2=None))
    assert "source: verdict-1" in [c.reason for c in d2.holds if c.number == 12][0]


def test_verdict_1_is_not_tree_gated():
    """Phase 6 commits between 5.5 and 6.5, so gating v1 would block every PR today."""
    assert select_fidelity_verdict("READY", None, None, TREE) == ("READY", "verdict-1")
    assert select_fidelity_verdict("READY", None, None, STALE) == ("READY", "verdict-1")


def test_head_moved_after_review_holds_12():
    d = evaluate(inputs(fidelity_verdict="NOT READY", fidelity_verdict_2="READY",
                        fidelity_tree_2=TREE, current_tree=STALE))
    assert not d.armed and 12 in _held(d)


def test_reviewed_tree_with_trailing_prose_is_stale(tmp_path):
    p = tmp_path / "verdict-2.md"
    p.write_text(f"READY\nREVIEWED_TREE={TREE} (re-reviewed)\n")
    tree2 = fidelity.read_reviewed_tree(str(p))
    assert tree2 is None
    d = evaluate(inputs(fidelity_verdict="READY", fidelity_verdict_2="NOT READY",
                        fidelity_tree_2=tree2))
    assert d.armed          # v2 is stale -> falls back to the READY v1


# --- condition (11) -----------------------------------------------------------

@pytest.mark.parametrize("uf,blocked,fragment", [
    (None, True, "unresolved review-thread state not consulted — fail-closed"),
    ([], False, ""),
    (["unresolved-findings-api-error"], True,
     "cannot verify review-thread state (GitHub API error) — fail-closed"),
    (["unresolved-findings-cap"], True,
     "review-thread list hit the 100-thread page cap — fail-closed"),
    (["unresolved-finding:95", "unresolved-finding:80"], True,
     "2 unresolved review finding(s) scored >= 80: "
     "unresolved-finding:95 unresolved-finding:80"),
])
def test_condition_11_outcomes(uf, blocked, fragment):
    d = evaluate(inputs(unresolved_findings=uf))
    c11 = [c for c in d.conditions if c.number == 11][0]
    assert c11.blocked is blocked
    assert c11.reason == fragment
    assert d.armed is not blocked


def test_conditions_11_and_12_are_additive():
    feat = inputs(pr_title="feat: shiny new GM power")
    assert _held(evaluate(feat)) == {8}
    both = inputs(pr_title="feat: shiny new GM power", unresolved_findings=None,
                  fidelity_verdict=None)
    assert _held(evaluate(both)) == {8, 11, 12}
    # a clear (11) cannot release the hold (8) raised
    assert not evaluate(inputs(pr_title="feat: x", unresolved_findings=[])).armed


# --- condition (11) shell-out fail-closed ------------------------------------

def _gh_shim(tmp_path, monkeypatch, body):
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    lib = bindir / "lib"
    lib.mkdir(parents=True, exist_ok=True)
    (lib / "pr-armable.sh").write_text(body)
    # a `git` that reports this tmp dir as the repo toplevel
    g = bindir / "git"
    g.write_text(f'#!/usr/bin/env bash\nprintf %s "{tmp_path}"\n')
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return LiveGh(str(tmp_path), str(tmp_path), "demo")


def test_unresolved_findings_fail_closed_on_nonzero_exit(tmp_path, monkeypatch):
    gh = _gh_shim(tmp_path, monkeypatch, "pr_unresolved_findings_hold() { return 1; }\n")
    assert gh.unresolved_findings(42) == ["unresolved-findings-api-error"]


def test_unresolved_findings_fail_closed_when_the_lib_is_missing(tmp_path, monkeypatch):
    gh = _gh_shim(tmp_path, monkeypatch, "")
    os.unlink(os.path.join(str(tmp_path), "bin", "lib", "pr-armable.sh"))
    assert gh.unresolved_findings(42) == ["unresolved-findings-api-error"]


def test_unresolved_findings_fail_closed_on_timeout(tmp_path, monkeypatch):
    """A hung `gh` must not arm. The production bound is 120 s; 1 s here proves the path."""
    from harness.adapters.llm import _run_reaped
    _gh_shim(tmp_path, monkeypatch, "pr_unresolved_findings_hold() { sleep 30; }\n")
    argv = ["bash", "-c",
            'source "$(git rev-parse --show-toplevel)/bin/lib/pr-armable.sh"; '
            'pr_unresolved_findings_hold "$1"', "_", "42"]
    try:
        _run_reaped(argv, None, 1, str(tmp_path), None)
        pytest.fail("the shim should have timed out")
    except Exception:
        pass      # the adapter maps any exception to the api-error sentinel


def test_unresolved_findings_reports_the_hold_list(tmp_path, monkeypatch):
    gh = _gh_shim(tmp_path, monkeypatch,
                  "pr_unresolved_findings_hold() { echo 'thread:95 thread:82'; }\n")
    assert gh.unresolved_findings(42) == ["thread:95", "thread:82"]


def test_unresolved_findings_passes_through_clean(tmp_path, monkeypatch):
    gh = _gh_shim(tmp_path, monkeypatch, "pr_unresolved_findings_hold() { :; }\n")
    assert gh.unresolved_findings(42) == []


def test_fake_gh_reports_no_unresolved_findings(tmp_path):
    assert RecordingGh(str(tmp_path)).unresolved_findings(1) == []


# --- condition (14) -----------------------------------------------------------

@pytest.mark.parametrize("flag,blocked,fragment", [
    (None, True, "conflict-resolved flag not consulted — fail-closed"),
    (True, True, "this branch carries a /post-plan auto-resolved rebase conflict — "
                 "a human reads the resolution"),
    (False, False, ""),
])
def test_condition_14_outcomes(flag, blocked, fragment):
    d = evaluate(inputs(conflict_resolved=flag))
    c14 = [c for c in d.conditions if c.number == 14][0]
    assert c14.blocked is blocked
    assert c14.reason == fragment
    assert d.armed is not blocked


@pytest.mark.parametrize("branch", ["feat/a", "a:b", "plain", "feat/a:b/c"])
def test_conflict_flag_key_matches_bash(branch):
    script = ("printf '/tmp/postplan-conflict-resolved-%s' "
              "\"$(printf %s \"$1\" | tr '/:' '--')\"")
    proc = subprocess.run(["bash", "-c", script, "_", branch],
                          capture_output=True, text=True)
    assert conflict_flag_path(branch) == proc.stdout


def test_condition_14_is_additive():
    assert not evaluate(inputs(conflict_resolved=True)).armed
    # a fresh NOT READY still blocks on (12) even with the flag clear
    d = evaluate(inputs(conflict_resolved=False, fidelity_verdict="NOT READY"))
    assert not d.armed and 12 in _held(d)
