"""Runner wiring for the closer arm: narrow-fixer skips, one session, one re-check."""
from __future__ import annotations

import ast
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner
from harness import closer
from harness.closer import CloserOutcome
from test_closer_ab_golden import _RebaseConflictGit, _go
from test_rearm_after_ci_fix import _armable_fixture, _held16_fixture, _merge_count
from test_runner_replay import _LiveShapedGit, _actions

_NARROW = {"gate-fix", "conformance-fix", "fidelity-remediation", "ci-fix", "prose-fix",
           "thread-remediation", "conflict-ladder-session", "conflict-fix-session"}
_RED = [{"exit": 8, "failed": ["Tests and Analysis"]}]
_GREEN = {"exit": 0, "failed": []}
_REPLY = ({"comment_id": 11, "body": "answered", "resolve": True},)


class _TrackedGit(_LiveShapedGit):
    instances: list = []

    def __init__(self, fixture):
        super().__init__(fixture)
        type(self).instances.append(self)


class _TrackedConflictGit(_RebaseConflictGit):
    instances: list = []

    def __init__(self, fixture):
        super().__init__(fixture)
        type(self).instances.append(self)


class Recorder:
    """Stands in for closer.run_session. `edit` dirties the replay tree."""

    def __init__(self, status="no-change", replies=(), edit=False, git_cls=_TrackedGit):
        self.calls = []
        self.status, self.replies, self.edit, self.git_cls = status, replies, edit, git_cls

    def __call__(self, llm, **kw):
        self.calls.append(kw)
        if self.edit:
            self.git_cls.instances[-1].extra_dirty = ["src/closer-edit.php"]
        return CloserOutcome(status=self.status, verdict="fixed", note="closer note",
                             thread_replies=tuple(self.replies), cost_usd=1.5,
                             pre_head="")


def _arm(monkeypatch, value="closer"):
    monkeypatch.setenv("POSTPLAN_AB_ARM", value)


def _closer_fixture(monkeypatch, tmp_path, **extra):
    extra.setdefault("closer", {})
    extra.setdefault("ci_fix_rewatch", _RED)
    return _held16_fixture(monkeypatch, tmp_path, **extra)[0]


def _install(monkeypatch, rec, git_cls=_TrackedGit):
    git_cls.instances.clear()
    monkeypatch.setattr(runner.closer, "run_session", rec)


def test_closer_cannot_arm_while_hard_gate_blocked(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path,
                         plan_content="---\nauto_merge: false\n---\n# plan\n")
    rec = Recorder(replies=_REPLY)
    _install(monkeypatch, rec)
    res, result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert len(rec.calls) == 1
    assert res.closer["ran"] is True
    assert res.closer["recheck"] == "held"
    assert res.arm.armed is False
    assert 7 in [c.number for c in res.arm.holds]
    assert _merge_count(out) == 0
    assert "pr_resolve_thread" in [a["action"] for a in _actions(out)]


def test_closer_module_has_no_arm_path():
    path = os.path.join(os.path.dirname(__file__), "..", "harness", "closer.py")
    with open(path) as fh:
        tree = ast.parse(fh.read())
    banned_attrs = {"pr_merge_auto", "pr_merge", "add_labels", "pr_add_label",
                    "pr_sticky_verdict", "evaluate"}
    for node in ast.walk(tree):
        if isinstance(node, ast.Attribute):
            assert node.attr not in banned_attrs, node.attr
        if isinstance(node, ast.Name):
            assert node.id not in banned_attrs, node.id
        if isinstance(node, ast.ImportFrom):
            assert "armable" not in (node.module or "")
            assert all(a.name != "armable" for a in node.names)
        if isinstance(node, ast.Import):
            assert all("armable" not in a.name for a in node.names)
        if isinstance(node, ast.Constant) and isinstance(node.value, str):
            assert "gh pr merge" not in node.value
            assert not ("verdict" in node.value and ".json" in node.value)


def test_closer_arm_skips_narrow_fixer_purposes(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    rec = Recorder()
    _install(monkeypatch, rec)
    res, result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit,
                           canned_extra={"ci-fix": ["edits made"]})
    purposes = {c["purpose"] for c in result["ledger"]["calls"]}
    assert not (purposes & _NARROW), purposes & _NARROW
    assert result["ab_arm"] == "closer"
    assert result["ab_source"] == "env"


def test_closer_runs_once_per_run(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    rec = Recorder()
    _install(monkeypatch, rec)
    res, _result, _out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert len(rec.calls) == 1
    assert res.arm.armed is False


def test_closer_tree_moved_defers_recheck(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    rec = Recorder(status="edited", edit=True)
    _install(monkeypatch, rec)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    git = _TrackedGit.instances[-1]
    closer_commits = [m for m in git.commit_messages
                      if m.startswith("fix: address post-plan hold (closer)")]
    assert len(closer_commits) == 1
    assert res.closer["recheck"] == "deferred-tree-moved"
    assert git.pushes + git.ff_pushes >= 1
    assert _merge_count(out) == 0


@pytest.mark.parametrize("status", ["cap-wall", "cap-budget"])
def test_closer_cap_keeps_original_terminal_and_exit(monkeypatch, tmp_path, status):
    _arm(monkeypatch, "harness")
    base_dir = tmp_path / "base"
    base_dir.mkdir()
    fx = _held16_fixture(monkeypatch, base_dir, ci_fix_rewatch=_RED)[0]
    base_res, base_result, _ = _go(monkeypatch, base_dir, fx,
                                   canned_extra={"ci-fix": ["edits made"]})
    _arm(monkeypatch)
    tmp2 = tmp_path / "closer"
    tmp2.mkdir()
    fx2 = _closer_fixture(monkeypatch, tmp2)
    rec = Recorder(status=status)
    _install(monkeypatch, rec)
    res, result, out = _go(monkeypatch, tmp2, fx2, git_cls=_TrackedGit)
    assert len(rec.calls) == 1
    assert result["terminal"] == base_result["terminal"]
    assert runner.exit_code_for(res) == runner.exit_code_for(base_res)
    git = _TrackedGit.instances[-1]
    assert not [m for m in git.commit_messages if "(closer)" in m]
    assert res.closer["recheck"] == "not-run"


def test_closer_per_pr_cap_stops_fourth_session(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    state = tmp_path / "state"
    state.mkdir()
    slug = fx["slug"]
    (state / "ab-enrollment.json").write_text(json.dumps({
        "v": 1, "cap": 20,
        "enrolled": {runner.statefile.safe_slug(slug): {
            "arm": "closer", "pr": 1, "enrolled_at": "x", "closer_sessions": 3}}}))
    rec = Recorder()
    _install(monkeypatch, rec)
    res, _result, _out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert rec.calls == []
    assert res.closer == {"ran": False, "reason": "per-pr-cap"}


def test_rebase_conflict_closer_keeps_exit_3(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _armable_fixture(monkeypatch, tmp_path, closer={})
    rec = Recorder(git_cls=_TrackedConflictGit)
    _install(monkeypatch, rec, git_cls=_TrackedConflictGit)
    res, result, _out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedConflictGit)
    assert len(rec.calls) == 1
    assert result["terminal"] == "failed"
    assert result["error_kind"] == "rebase-conflict"
    assert runner.exit_code_for(res) == 3


def test_closer_not_run_on_armed(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _armable_fixture(monkeypatch, tmp_path, closer={}, checks_outcome=_GREEN)
    rec = Recorder()
    _install(monkeypatch, rec)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert rec.calls == []
    assert _merge_count(out) == 1


def test_closer_not_run_on_hard_only_hold(monkeypatch, tmp_path):
    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path, checks_outcome=_GREEN)
    rec = Recorder()
    _install(monkeypatch, rec)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    # held on (16) only, CI green: a hard condition never triggers the session
    assert [c.number for c in res.arm.holds] == [16]
    assert rec.calls == []
    assert res.closer["ran"] is False
    assert res.closer["reason"].startswith("hard-only:")
    assert _merge_count(out) == 0
