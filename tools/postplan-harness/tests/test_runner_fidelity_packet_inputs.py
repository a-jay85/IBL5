"""Runner helpers that feed the fidelity packet (backlog#1771)."""
import inspect
import os
import sys
import types
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conflict

import runner


def test_packet_conflict_paths_reads_run_list():
    suffix = uuid.uuid4().hex
    key = f"t1771-{suffix}"
    try:
        with open(conflict.autoresolved_list_path(key), "w", encoding="utf-8") as fh:
            fh.write("a/b.py\nc.py\n")
        assert runner._packet_conflict_paths(f"t1771/{suffix}") == ["a/b.py", "c.py"]
    finally:
        conflict.purge_autoresolved_list(key)


def test_packet_conflict_paths_none_without_slug():
    assert runner._packet_conflict_paths("") is None


class _FreshGit:
    def diff_vs_base(self, base="origin/master"):
        return "POST"


class _NoReadGit:
    def diff_vs_base(self, base="origin/master"):
        raise AssertionError("stacked review must reuse review_diff")


def test_fidelity_packet_diff_rereads_after_rebase():
    review = types.SimpleNamespace(parent=None, ref="origin/master")
    assert runner._fidelity_packet_diff(_FreshGit(), review, "PRE") == "POST"


def test_fidelity_packet_diff_stacked_reuses_review_diff():
    review = types.SimpleNamespace(parent="base", ref="abc")
    assert runner._fidelity_packet_diff(_NoReadGit(), review, "STACKED") == "STACKED"


def test_run_fidelity_passes_conflict_paths():
    src = inspect.getsource(runner._run_fidelity)
    assert "conflict_resolved_paths=_packet_conflict_paths(slug)" in src


def test_packet_diff_wired_through_run_pass():
    assert "packet_diff if packet_diff is not None else diff" in inspect.getsource(
        runner._run_fidelity)
    assert "packet_diff=_fidelity_packet_diff(git, review, review_diff)" in inspect.getsource(
        runner._run_pass)
