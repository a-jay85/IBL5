"""runner routes reviewer-facing diffs through LiveGit.review_base(); arming reads stay on master except the planned-landed filter, which also checks the review base."""
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


def _arming_spy(monkeypatch):
    """Wrap reviewer_arming_holds; returns the list of (args, kwargs) calls."""
    calls = []
    real = runner.fidelity.reviewer_arming_holds

    def spy(*args, **kw):
        calls.append((args, kw))
        return real(*args, **kw)

    monkeypatch.setattr(runner.fidelity, "reviewer_arming_holds", spy)
    return calls


def test_run_arming_master_base_reads_master_inputs(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    reads = []

    class RecordingGit(gitad.ReplayGit):
        def conformance_files(self, *a, **k):
            out = super().conformance_files(*a, **k)
            reads.append(("conformance_files", a, k, out))
            return out

        def diff_vs_base(self, *a, **k):
            out = super().diff_vs_base(*a, **k)
            reads.append(("diff_vs_base", a, k, out))
            return out

    calls = _arming_spy(monkeypatch)
    monkeypatch.setattr(runner, "ReplayGit", RecordingGit)
    h.run("stacked")
    assert len(calls) == 1
    args, _ = calls[0]
    files = [r[3] for r in reads if r[0] == "conformance_files" and not r[1] and not r[2]]
    diffs = [r[3] for r in reads if r[0] == "diff_vs_base" and not r[1] and not r[2]]
    assert args[1] in files
    assert args[2] in diffs


def test_landed_later_review_inputs_master_base_is_none():
    def boom(*a, **k):
        raise AssertionError("must not read git on the master base")

    git = types.SimpleNamespace(conformance_files=boom, diff_vs_base=boom)
    logs = []
    assert runner._landed_later_review_inputs(git, MASTER_REVIEW_BASE, logs.append) is None
    assert logs == []


def test_landed_later_review_inputs_stacked_reads_parent_ref():
    bases = []

    def files(base="origin/master"):
        bases.append(base)
        return ["child.py"]

    def diff(base="origin/master"):
        bases.append(base)
        return "<diff>"

    git = types.SimpleNamespace(conformance_files=files, diff_vs_base=diff)
    out = runner._landed_later_review_inputs(git, ReviewBase(SHA, "parent"), [].append)
    assert out == (["child.py"], "<diff>")
    assert bases == [SHA, SHA]


def test_landed_later_review_inputs_git_error_keeps_holds():
    def files(base="origin/master"):
        raise RuntimeError("boom")

    git = types.SimpleNamespace(conformance_files=files, diff_vs_base=lambda base="x": "")
    logs = []
    assert runner._landed_later_review_inputs(git, ReviewBase(SHA, "parent"), logs.append) == ((), "")
    assert len(logs) == 1 and "keeping planned-landed holds" in logs[0]


def test_run_arming_stacked_passes_review_base_inputs(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    stacked = _section("B.txt", 1, "x")

    class StackedGit(gitad.ReplayGit):
        def review_base(self):
            return ReviewBase(SHA, "parent")

        def diff_vs_base(self, base="origin/master"):
            return stacked if base == SHA else super().diff_vs_base(base)

        def conformance_files(self, base="origin/master"):
            return ["child.py"] if base == SHA else super().conformance_files(base)

    calls = _arming_spy(monkeypatch)
    monkeypatch.setattr(runner, "ReplayGit", StackedGit)
    h.run("stacked")
    assert len(calls) == 1
    args, kwargs = calls[0]
    assert kwargs["review_inputs"] == (["child.py"], stacked)
    assert args[1] != ["child.py"]
    assert args[2] != stacked


def test_run_arming_master_base_passes_no_review_inputs(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    calls = _arming_spy(monkeypatch)
    h.run("stacked")
    assert len(calls) == 1
    assert calls[0][1].get("review_inputs") is None


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


def test_same_run_delta_review_returns_master_classification(monkeypatch):
    """The returned cls feeds arming, so it covers the master diff even on a stacked PR."""
    git = types.SimpleNamespace(
        review_base=lambda: ReviewBase(SHA, "parent"),
        changed_files=lambda base="origin/master": ["B.txt"] if base == SHA else ["A.txt", "B.txt"],
        diff_vs_base=lambda base="origin/master": "OWN" if base == SHA else "MASTER",
        modified_files=lambda *a, **k: [])
    gh = types.SimpleNamespace(pr_meta=lambda: {"title": "t"})
    monkeypatch.setattr(runner, "classify",
                        lambda files, diff, mod: types.SimpleNamespace(files=files, filtered_diff=diff))
    monkeypatch.setattr(runner, "_review_cache_context", lambda *a, **k: ({}, {}))
    monkeypatch.setattr(runner.reviewcache, "subset_diff", lambda *a: "")
    _, cls, _, _, _ = runner._same_run_delta_review(None, {}, None, _plan(), git, gh, None, "x",
                                                     ".", MASTER, [].append)
    assert cls.files == ["A.txt", "B.txt"]
    assert cls.filtered_diff == "MASTER"
