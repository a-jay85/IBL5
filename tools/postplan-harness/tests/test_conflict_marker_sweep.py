"""conflict_marker_sweep: open/close markers tree-wide, bare separators scoped (backlog#1614)."""

import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import (  # noqa: E402
    conflict_marker_sweep,
    _OPEN_CLOSE_MARKER_RE,
    _SEPARATOR_MARKER_RE,
)


def _repo(tmp_path, files):
    d = str(tmp_path / "r")
    subprocess.run(["git", "init", "-q", d], check=True)
    for rel, body in files.items():
        p = os.path.join(d, rel)
        os.makedirs(os.path.dirname(p), exist_ok=True)
        with open(p, "w") as fh:
            fh.write(body)
    subprocess.run(["git", "-C", d, "add", "-A"], check=True)
    subprocess.run(["git", "-C", d, "-c", "user.name=t", "-c", "user.email=t@t",
                    "commit", "-q", "-m", "seed"], check=True)
    return d


def _run_out(d, calls=None):
    def run_out(*args):
        if calls is not None:
            calls.append(args)
        p = subprocess.run(["git", "-C", d, *args], capture_output=True, text=True)
        return p.returncode, p.stdout + p.stderr
    return run_out


def test_sweep_ignores_setext_heading_outside_paths(tmp_path):
    d = _repo(tmp_path, {"README.md": "Title\n=======\n", "a.txt": "ok\n"})
    rc, out = conflict_marker_sweep(_run_out(d), ["a.txt"])
    assert rc == 1, out


def test_sweep_flags_bare_separator_in_scoped_path(tmp_path):
    d = _repo(tmp_path, {"a.txt": "x\n=======\ny\n"})
    rc, out = conflict_marker_sweep(_run_out(d), ["a.txt"])
    assert rc == 0, out
    assert "a.txt:2:=======" in out


def test_sweep_flags_diff3_base_marker_in_scoped_path(tmp_path):
    d = _repo(tmp_path, {"a.txt": "x\n||||||| base\ny\n"})
    rc, out = conflict_marker_sweep(_run_out(d), ["a.txt"])
    assert rc == 0, out


def test_sweep_flags_open_marker_outside_paths(tmp_path):
    d = _repo(tmp_path, {"outside.txt": "<<<<<<< HEAD\nx\n", "a.txt": "ok\n"})
    rc, out = conflict_marker_sweep(_run_out(d), ["a.txt"])
    assert rc == 0, out
    assert "outside.txt" in out


def test_sweep_propagates_grep_failure_without_scoped_pass():
    calls = []

    def run_out(*args):
        calls.append(args)
        return 129, "error: unknown option"

    rc, out = conflict_marker_sweep(run_out, ["a.txt"])
    assert rc == 129
    assert out == "error: unknown option"
    assert len(calls) == 1


def test_sweep_skips_scoped_pass_when_no_paths(tmp_path):
    d = _repo(tmp_path, {"README.md": "Title\n=======\n"})
    calls = []
    rc, out = conflict_marker_sweep(_run_out(d, calls), ())
    assert rc == 1, out
    assert len(calls) == 1


def test_sweep_scoped_path_is_literal(tmp_path):
    d = _repo(tmp_path, {"notes.md": "Title\n=======\n"})
    rc, out = conflict_marker_sweep(_run_out(d), ["*.md"])
    assert rc == 1, out


def test_sweep_ignores_near_miss_separator_lines(tmp_path):
    d = _repo(tmp_path, {
        "a.txt": "======= trailing\n========\n  =======\n<<<<<<<x\n>>>>>>>>\n||||||\n",
    })
    rc, out = conflict_marker_sweep(_run_out(d), ["a.txt"])
    assert rc == 1, out


def test_split_regexes_are_disjoint():
    lines = ["<<<<<<< HEAD", "<<<<<<<", ">>>>>>> topic", "=======", "||||||| base", "|||||||"]
    for line in lines:
        open_close = bool(re.search(_OPEN_CLOSE_MARKER_RE, line))
        separator = bool(re.search(_SEPARATOR_MARKER_RE, line))
        assert open_close != separator, f"{line!r} must match exactly one regex"
