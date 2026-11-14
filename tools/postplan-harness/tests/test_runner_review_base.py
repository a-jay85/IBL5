"""runner routes reviewer-facing diffs through LiveGit.review_base(); arming reads stay on master."""
from __future__ import annotations

import os
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters import gitad
from harness.adapters.gitad import MASTER_REVIEW_BASE, ReviewBase
from test_review_cache_e2e import Harness, _pin_verifier_worktree  # noqa: F401
from test_review_cache_runner import _FakeGit, _plan, _section

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

SHA = "p" * 40
MASTER = "m" * 40


def _stacked_git(bases=None):
    bases = [] if bases is None else bases

    def changed_files(base="origin/master"):
        bases.append(base)
        return ["B.txt"] if base == SHA else ["A.txt", "B.txt"]

    return types.SimpleNamespace(
        review_base=lambda: ReviewBase(SHA, "parent"),
        diff_vs_base=lambda base="origin/master": "diff",
        changed_files=changed_files)


@pytest.fixture()
def recorded(monkeypatch):
    rec = {}
    monkeypatch.setattr(runner, "_detector_root", lambda *a: ".")
    monkeypatch.setattr(runner.removed_guards, "run_detector", lambda *a, **k: (True, []))

    def context_for(*args):
        rec["args"] = args
        return {}

    monkeypatch.setattr(runner.unplanned_scope, "context_for", context_for)
    return rec


def test_unplanned_ctx_stacked_passes_parent_base_sha(recorded):
    runner._unplanned_ctx(".", _stacked_git(), None, MASTER, "diff", [].append)
    assert recorded["args"][1] == SHA


def test_unplanned_ctx_stacked_passes_own_changed_paths(recorded):
    runner._unplanned_ctx(".", _stacked_git(), None, MASTER, "diff", [].append)
    assert recorded["args"][3] == ["B.txt"]


def test_unplanned_ctx_master_base_passes_master_sha(recorded):
    git = _stacked_git()
    git.review_base = lambda: MASTER_REVIEW_BASE
    runner._unplanned_ctx(".", git, None, MASTER, "diff", [].append)
    assert recorded["args"][1] == MASTER
    assert recorded["args"][3] == ["A.txt", "B.txt"]


def test_unplanned_ctx_stub_without_review_base_uses_master(recorded):
    git = types.SimpleNamespace(diff_vs_base=lambda: "diff",
                                changed_files=lambda: ["A.txt", "B.txt"])
    assert runner._unplanned_ctx(".", git, None, MASTER, "diff", [].append) == {}
    assert recorded["args"][1] == MASTER


def test_review_cache_context_keys_on_passed_diff():
    class _NoRead:
        def diff_vs_base(self):
            raise AssertionError("must not re-read the master diff")

    diff = _section("a.py", 1, "x")
    logs = []
    key, files = runner._review_cache_context(
        _NoRead(), "/wt", MASTER, {"title": "t"}, _plan(), runner.HARNESS_ROOT,
        logs.append, diff=diff)
    ref_key, ref_files = runner._review_cache_context(
        _FakeGit(diff), "/wt", MASTER, {"title": "t"}, _plan(), runner.HARNESS_ROOT,
        logs.append)
    assert key["diff_id"] == ref_key["diff_id"]
    assert sorted(files) == sorted(ref_files)


def test_review_base_logs_parent_line():
    logs = []
    runner._review_base(types.SimpleNamespace(review_base=lambda: ReviewBase(SHA, "parent")),
                        logs.append, "phase5.5")
    assert logs == [f"phase5.5: review base: parent parent@{SHA[:12]}"]


def test_review_base_logs_master_line():
    logs = []
    runner._review_base(types.SimpleNamespace(review_base=lambda: MASTER_REVIEW_BASE),
                        logs.append, "phase5.5")
    assert logs == ["phase5.5: review base: master"]


def test_run_passes_review_diff_to_fidelity(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    stacked = _section("B.txt", 1, "x")

    class StackedGit(gitad.ReplayGit):
        def review_base(self):
            return ReviewBase(SHA, "parent")

        def diff_vs_base(self, base="origin/master"):
            return stacked if base == SHA else super().diff_vs_base(base)

    seen = []
    wrapped = runner._run_fidelity

    def spy(*args, **kw):
        seen.append(args[6])
        return wrapped(*args, **kw)

    monkeypatch.setattr(runner, "ReplayGit", StackedGit)
    monkeypatch.setattr(runner, "_run_fidelity", spy)
    h.run("stacked")
    assert seen == [stacked]


def test_same_run_delta_review_reads_review_diff(monkeypatch):
    class _Stop(Exception):
        pass

    bases = []
    kwargs = {}

    def changed_files(base="origin/master"):
        bases.append(base)
        return ["B.txt"]

    git = types.SimpleNamespace(
        review_base=lambda: ReviewBase(SHA, "parent"),
        changed_files=changed_files,
        diff_vs_base=lambda base="origin/master": "OWN" if base == SHA else "MASTER",
        modified_files=lambda *a, **k: [])
    gh = types.SimpleNamespace(pr_meta=lambda: {"title": "t"})

    def stop(*args, **kw):
        kwargs.update(kw)
        raise _Stop

    monkeypatch.setattr(runner, "classify", lambda *a, **k: types.SimpleNamespace(filtered_diff=""))
    monkeypatch.setattr(runner, "_review_cache_context", stop)
    with pytest.raises(_Stop):
        runner._same_run_delta_review(None, {}, None, _plan(), git, gh, None, "", ".",
                                      MASTER, [].append)
    assert kwargs["diff"] == "OWN"
    assert bases == [SHA]
