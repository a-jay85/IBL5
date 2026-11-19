"""`bin/check-prose --since=<ref>` on stacked and stale branches.

Each test builds a throwaway git repo and runs the real `bin/check-prose` in it.
The stacked layout is M0 -> parent P -> child C, where master squash-merges P
and then fixes a tell on `docs/rules.md` line 3. The child never touches
`docs/rules.md` or `docs/parent.md`, so a hit on either is a false flag.
"""
from __future__ import annotations

import subprocess
import sys
from pathlib import Path

REAL_CHECK_PROSE = Path(__file__).resolve().parents[3] / "bin" / "check-prose"

TELL_LINE = "The cache warms fast — the pool stays small."
CLEAN_LINE = "The cache warms fast. The pool stays small."


def _git(repo, *args):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True,
                          text=True, check=True).stdout.strip()


def _prose(repo, ref):
    r = subprocess.run([sys.executable, str(REAL_CHECK_PROSE), f"--since={ref}"],
                       cwd=repo, capture_output=True, text=True)
    return r.returncode, r.stdout + r.stderr


def _commit(repo, msg):
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", msg)
    return _git(repo, "rev-parse", "HEAD")


def _init(tmp_path):
    repo = tmp_path / "repo"
    (repo / "docs").mkdir(parents=True)
    _git(repo, "init", "-q", "-b", "master")
    _git(repo, "config", "user.email", "t@t")
    _git(repo, "config", "user.name", "t")
    _git(repo, "config", "commit.gpgsign", "false")
    return repo


def _stacked(tmp_path, *, child_merges_master=False, child_tell=False):
    """M0 -> parent P (adds docs/parent.md with a tell) -> child C (adds docs/child.md).
    master squash-merges P, then fixes the tell on docs/rules.md line 3.
    Returns (repo, {"m0": sha, "master": sha, "parent": sha})."""
    repo = _init(tmp_path)
    (repo / "docs" / "rules.md").write_text(f"# Rules\n\n{TELL_LINE}\n")
    m0 = _commit(repo, "m0")
    _git(repo, "checkout", "-q", "-b", "parent")
    (repo / "docs" / "parent.md").write_text(f"{TELL_LINE}\n")
    parent = _commit(repo, "parent")
    _git(repo, "checkout", "-q", "-b", "child")
    child_text = f"{CLEAN_LINE}\n"
    if child_tell:
        child_text += f"{TELL_LINE}\n"
    (repo / "docs" / "child.md").write_text(child_text)
    _commit(repo, "child")
    _git(repo, "checkout", "-q", "master")
    _git(repo, "merge", "-q", "--squash", "parent")
    _commit(repo, "squash parent")
    (repo / "docs" / "rules.md").write_text(f"# Rules\n\n{CLEAN_LINE}\n")
    master = _commit(repo, "fix rules tell")
    _git(repo, "checkout", "-q", "child")
    if child_merges_master:
        _git(repo, "merge", "-q", "--no-edit", "master")
    return repo, {"m0": m0, "master": master, "parent": parent}


def test_master_fixed_line_not_flagged_on_stale_child(tmp_path):
    repo, _ = _stacked(tmp_path)
    rc, out = _prose(repo, "master")
    print(out)
    assert "docs/rules.md:3:" not in out
    assert "docs/parent.md" not in out
    assert rc == 0


def test_stacked_unmerged_child_ignores_squashed_parent_file(tmp_path):
    repo, _ = _stacked(tmp_path)
    rc, out = _prose(repo, "master")
    print(out)
    assert "docs/parent.md" not in out


def test_merge_base_ref_flags_parent_file_today(tmp_path):
    repo, shas = _stacked(tmp_path)
    rc, out = _prose(repo, shas["m0"])
    print(out)
    assert rc == 1
    assert "docs/parent.md:1:" in out


def test_child_own_tell_still_fails(tmp_path):
    repo, _ = _stacked(tmp_path, child_tell=True)
    rc, out = _prose(repo, "master")
    print(out)
    assert rc == 1
    assert "docs/child.md:2:" in out


def _hits(out):
    return sorted(l.split(" ", 1)[0] for l in out.splitlines() if l.startswith("docs/"))


def test_child_tell_still_fails_after_merging_master(tmp_path):
    repo, _ = _stacked(tmp_path, child_merges_master=True, child_tell=True)
    rc, out = _prose(repo, "master")
    print(out)
    assert rc == 1
    assert _hits(out) == ["docs/child.md:2:"]


def test_uncommitted_child_edit_is_scanned(tmp_path):
    repo, _ = _stacked(tmp_path)
    with open(repo / "docs" / "child.md", "a") as fh:
        fh.write(f"{TELL_LINE}\n")
    rc, out = _prose(repo, "master")
    print(out)
    assert rc == 1
    assert _hits(out) == ["docs/child.md:2:"]


def test_ancestor_ref_matches_two_dot_result(tmp_path):
    repo = _init(tmp_path)
    (repo / "docs" / "note.md").write_text(f"# Note\n\n{CLEAN_LINE}\n")
    _commit(repo, "base")
    _git(repo, "checkout", "-q", "-b", "feature")
    with open(repo / "docs" / "note.md", "a") as fh:
        fh.write(f"{TELL_LINE}\n")
    _commit(repo, "tell")
    base = _git(repo, "merge-base", "master", "HEAD")
    rc_base, out_base = _prose(repo, base)
    rc_master, out_master = _prose(repo, "master")
    print(out_base, out_master)
    assert _hits(out_base) == _hits(out_master) == ["docs/note.md:4:"]
    assert rc_base == rc_master == 1


def test_unrelated_ref_falls_back_to_tip_diff(tmp_path):
    repo, _ = _stacked(tmp_path, child_tell=True)
    _git(repo, "checkout", "-q", "--orphan", "other")
    _git(repo, "rm", "-rq", "--cached", ".")
    for f in (repo / "docs").iterdir():
        f.unlink()
    (repo / "docs" / "x.md").write_text(f"{CLEAN_LINE}\n")
    _commit(repo, "orphan")
    _git(repo, "checkout", "-q", "-f", "child")
    rc, out = _prose(repo, "other")
    print(out)
    assert rc == 1
    assert "docs/child.md:2:" in out
