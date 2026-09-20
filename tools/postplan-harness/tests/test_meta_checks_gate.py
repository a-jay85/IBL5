"""Tests for run_meta_checks_local() — rows 15-18 of the verification matrix."""
from __future__ import annotations

import os
import sys
import tempfile

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner as _runner_mod
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger


def _load():
    import runner as r
    return r.run_meta_checks_local, r.HarnessError


# ---------------------------------------------------------------------------
# Row 15: ordering proof — gate runs before push
# ---------------------------------------------------------------------------

def test_row15_ordering():
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})
    assert git.pushes == 0

    import unittest.mock as mock
    import runner as r

    with mock.patch.object(r.subprocess, "run") as fake_run:
        fake_run.return_value = mock.MagicMock(returncode=0, stdout="", stderr="")
        result = run_meta_checks_local(git, "/fake/root", "origin/master", lambda m: None, live=True)

    assert result is True
    assert len(git.meta_checks_calls) == 1
    stage, push_count = git.meta_checks_calls[0]
    assert stage == "pre-push"
    assert push_count == 0, f"gate ran after push (count={push_count})"
    assert git.pushes == 0  # push hasn't happened yet — caller does it


def test_row15_ordering_through_run(monkeypatch, stub_ambient_git_show):
    """Row 15 (run-level): gate fires before push when verified through runner.run()."""
    captured = []

    class _CapturingGit(_runner_mod.ReplayGit):
        def __init__(self, fixture):
            super().__init__(fixture)
            captured.append(self)

    monkeypatch.setattr(_runner_mod, "ReplayGit", _CapturingGit)

    fx = {
        "slug": "meta-checks-ordering-test",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 8888,
        "pr_meta": {"number": 8888, "title": "chore: ordering test",
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": None, "phpstan": None, "go": None},
        "plan_content": "# Test plan\n\nNo matrix.\n",
    }
    canned = {
        "pr-copy": {"type": "chore", "title": "chore: ordering test",
                    "commit_subject": "chore: ordering test commit",
                    "summary_md": "## Summary\n- x\n"},
        "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
        "security-audit": [],
        "safety-verdict": {"holds": []},
        "manual-classify": [],
        "retrospective": {"save": False},
    }
    out = tempfile.mkdtemp(prefix="postplan-test-ordering-")
    llm = FixtureLlm(UsageLedger(), canned)
    _runner_mod.run(fx, out, llm, mode="replay", headless=True)

    assert len(captured) == 1
    git = captured[0]
    assert git.meta_checks_calls == [("pre-push", 0)], (
        f"gate did not record pre-push at push_count=0: {git.meta_checks_calls}"
    )
    assert git.pushes == 1, f"push did not happen after gate: pushes={git.pushes}"


# ---------------------------------------------------------------------------
# Regression guard (no matrix row): empty repo_root must not crash Phase 2
# ---------------------------------------------------------------------------

def test_empty_repo_root_skips_without_shelling_out():
    """A live call with no worktree path skips instead of raising FileNotFoundError.

    subprocess.run(cwd="") raises FileNotFoundError from chdir(""), which would abort
    Phase 2 before the push on every code path that has no worktree to offer.
    """
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})
    logged = []

    import unittest.mock as mock
    import runner as r

    with mock.patch.object(r.subprocess, "run") as fake_run:
        result = run_meta_checks_local(
            git, "", "origin/master", logged.append, live=True)

    assert result is True
    fake_run.assert_not_called()
    assert any("SKIPPED (no worktree path)" in m for m in logged), logged
    # Ordering is still recorded, so row 15's proof survives the skip.
    assert git.meta_checks_calls == [("pre-push", 0)]
    assert git.pushes == 0


# ---------------------------------------------------------------------------
# Row 16: negative path — rc=1 twice → False, flag file exists, push==1 (externally)
# ---------------------------------------------------------------------------

def test_row16_negative_flag_file(tmp_path):
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})

    import unittest.mock as mock
    import runner as r

    call_count = [0]

    def fake_run(argv, **kwargs):
        call_count[0] += 1
        return mock.MagicMock(
            returncode=1,
            stdout="META-CHECK-FAILED: check-docs-since\n",
            stderr="",
        )

    with mock.patch.object(r.subprocess, "run", fake_run), \
         mock.patch.object(r, "_remediate_doc_staleness", return_value=0):
        result = run_meta_checks_local(
            git, str(tmp_path), "origin/master", lambda m: None, live=True)

    assert result is False
    branch_slug = git.branch().replace("/", "-")
    flag = f"/tmp/ibl5-meta-checks-prepush-{branch_slug}.failed"
    assert os.path.exists(flag), "flag file should exist after failure"
    # cleanup
    try:
        os.unlink(flag)
    except FileNotFoundError:
        pass
    # run_meta_checks_local itself never pushes — the caller (runner.run) does.
    assert git.pushes == 0


# ---------------------------------------------------------------------------
# Row 17: fail-closed — rc=3 raises HarnessError, no push
# ---------------------------------------------------------------------------

def test_row17_failclosed_rc3():
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})

    import unittest.mock as mock
    import runner as r

    with mock.patch.object(r.subprocess, "run") as fake_run:
        fake_run.return_value = mock.MagicMock(
            returncode=3, stdout="", stderr="filter parse error")
        try:
            run_meta_checks_local(
                git, "/fake/root", "origin/master", lambda m: None, live=True)
            assert False, "should have raised HarnessError"
        except r.HarnessError as e:
            assert e.kind == "local-gate"

    assert git.pushes == 0


# ---------------------------------------------------------------------------
# Row 18: bypass and bounded-attempt boundary
# ---------------------------------------------------------------------------

def test_row18_bypass(monkeypatch):
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})

    monkeypatch.setenv("PRE_PUSH_META_CHECKS_SKIP", "1")
    import unittest.mock as mock
    import runner as r

    with mock.patch.object(r.subprocess, "run") as fake_run:
        result = run_meta_checks_local(
            git, "/fake/root", "origin/master", lambda m: None, live=True)
        assert result is True
        fake_run.assert_not_called()


def test_row18_bounded_attempt(tmp_path):
    """Stub failing then passing: result True, runner called exactly twice, commit recorded."""
    run_meta_checks_local, _ = _load()
    git = ReplayGit({"slug": "test/branch"})

    # Simulate a bin/wt-new worktree where .git is a file, not a directory.
    (tmp_path / ".git").write_text("gitdir: /fake/.git/worktrees/test-branch\n")

    import unittest.mock as mock
    import runner as r

    call_count = [0]

    def fake_run(argv, **kwargs):
        call_count[0] += 1
        rc = 1 if call_count[0] == 1 else 0
        return mock.MagicMock(
            returncode=rc,
            stdout="META-CHECK-FAILED: check-docs-since\n" if rc == 1 else "",
            stderr="",
        )

    with mock.patch.object(r.subprocess, "run", fake_run), \
         mock.patch.object(r, "_remediate_doc_staleness", return_value=1):
        result = run_meta_checks_local(
            git, str(tmp_path), "origin/master", lambda m: None, live=True)

    assert result is True
    assert call_count[0] == 2, f"expected 2 subprocess calls, got {call_count[0]}"
    assert git.commit_messages, "bounded attempt must commit the staged date bumps"


# ---------------------------------------------------------------------------
# Row 21: pre-push flag file present → evaluate() blocks condition 16
# ---------------------------------------------------------------------------

def test_row21_flag_blocks_even_with_clean_post_pr(tmp_path):
    from harness.armable import ArmInputs, evaluate, meta_checks_clearance
    from harness.state import Classification

    flag = tmp_path / "ibl5-meta-checks-prepush-test-branch.failed"
    flag.write_text("check-docs-since\n")

    status = meta_checks_clearance(str(flag), 0)  # post_pr_rc=0 (would be CLEARED without flag)
    assert status == "HELD", f"flag file should hold even with post_pr_rc=0, got {status}"

    cls = Classification(has_migration=False, golden_changed=False)
    inp = ArmInputs(
        pr_body="## Manual Testing\nNo manual testing needed",
        pr_title="chore: test",
        pr_labels=[],
        classification=cls,
        findings=[],
        unresolved_conformance=[],
        phase5_status="pass",
        plan_auto_merge_false=False,
        headless=False,
        dep_state_lookup=lambda n: "MERGED",
        fidelity_verdict="READY",
        current_tree="abc123",
        conflict_resolved=False,
        unresolved_findings=[],
        meta_checks_status=status,
    )
    decision = evaluate(inp)
    assert not decision.armed, "should not arm when meta-checks hold"
    blocked_names = [c.name for c in decision.conditions if c.blocked]
    assert "meta-checks" in blocked_names, f"condition 16 should be blocked, got: {blocked_names}"


# ---------------------------------------------------------------------------
# Row 22: boundary + fail-closed — UNKNOWN blocks; CLEARED does not
# ---------------------------------------------------------------------------

def test_row22_failclosed_unknown_and_cleared(tmp_path):
    from harness.armable import meta_checks_clearance

    missing_flag = str(tmp_path / "nonexistent.failed")

    # rc=3 (filter-parse failure) → UNKNOWN → blocks
    status_3 = meta_checks_clearance(missing_flag, 3)
    assert status_3 == "UNKNOWN", f"rc=3 should be UNKNOWN, got {status_3}"

    # rc=0 → CLEARED → does not block
    status_0 = meta_checks_clearance(missing_flag, 0)
    assert status_0 == "CLEARED", f"rc=0 should be CLEARED, got {status_0}"
