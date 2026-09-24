"""Scenario tests for patch-series equivalence detection in reconcile_remote_head.

Seven real-git scenarios covering the boundary between "rebased onto newer master
(synced)" and every diverged variant: extra commit, dropped commit, edited commit,
reordered commits, wrong base, and older base.

Test 1 (test_identical_series_rebased_onto_newer_master_syncs) is RED until Phase 2
production code lands — today reconcile_remote_head returns "diverged" because it only
compares single tree hashes, not patch-series equivalence.
"""
from __future__ import annotations

import os
import pathlib
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import gitutil


# ---------------------------------------------------------------------------
# Shared helpers
# ---------------------------------------------------------------------------

def _fake_gh(tmp_path, outputs, rc=0):
    """Write an executable script that appends one line per call and prints outputs[n]."""
    script = tmp_path / "gh"
    script.write_text(
        f"#!/usr/bin/env bash\n"
        f"echo \"$@\" >> \"{tmp_path}/gh.calls\"\n"
        f"N=$(wc -l < \"{tmp_path}/gh.calls\" 2>/dev/null || echo 0)\n"
        f"N=$((N - 1))\n"
        f"OUTPUTS=({' '.join(repr(str(o)) for o in outputs)})\n"
        f"IDX=$N\n"
        f"MAX=$(( {len(outputs)} - 1 ))\n"
        f"[ $IDX -gt $MAX ] && IDX=$MAX\n"
        f"echo \"${{OUTPUTS[$IDX]}}\"\n"
        f"exit {rc}\n"
    )
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    return [str(script)]


@pytest.fixture(autouse=True)
def patch_sleep(monkeypatch):
    """Neutralize all confirm waits."""
    monkeypatch.setattr(gitutil.time, "sleep", lambda s: None)


def _sh(*args, cwd=None):
    return subprocess.run(["git", "-c", "user.email=t@t", "-c", "user.name=t",
                           "-c", "commit.gpgsign=false", *args],
                          cwd=cwd, check=True, capture_output=True, text=True).stdout.strip()


def _commit_file(repo, name, content, msg):
    (pathlib.Path(repo) / name).write_text(content)
    _sh("add", name, cwd=repo)
    _sh("commit", "-q", "-m", msg, cwd=repo)
    return _sh("rev-parse", "HEAD", cwd=repo)


def _build_scenario(tmp_path):
    bare = str(tmp_path / "origin.git"); wt = str(tmp_path / "wt"); peer = str(tmp_path / "peer")
    _sh("init", "-q", "--bare", "-b", "master", bare)
    _sh("clone", "-q", bare, wt)
    m_minus1 = _commit_file(wt, "root.txt", "root\n", "root")          # M-1
    m0 = _commit_file(wt, "m0.txt", "m0\n", "M0")                       # M0 (branch point)
    _sh("push", "-q", "origin", "master", cwd=wt)
    _sh("checkout", "-q", "-b", "feat", cwd=wt)
    c = [_commit_file(wt, f"{n}.txt", f"{n}\n", f"c{n}") for n in ("a", "b", "c")]
    _sh("push", "-q", "-u", "origin", "feat", cwd=wt)
    old_head = c[-1]
    _sh("clone", "-q", bare, peer)                                      # peer = "someone on GitHub"
    m1 = _commit_file(peer, "m1.txt", "m1\n", "M1 (#2382)")             # master advances
    _sh("push", "-q", "origin", "master", cwd=peer)
    _sh("fetch", "-q", "origin", cwd=wt)                                # wt sees new origin/master, HEAD still old_head
    return {"bare": bare, "wt": wt, "peer": peer, "old_head": old_head,
            "m_minus1": m_minus1, "m0": m0, "m1": m1, "commits": c}


def _force_push_peer_head(s):
    _sh("push", "-q", "--force", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    return _sh("rev-parse", "HEAD", cwd=s["peer"])


def _reconcile(s, remote_sha, tmp_path):
    gh_cmd = _fake_gh(tmp_path, [remote_sha])
    return gitutil.reconcile_remote_head(42, s["old_head"], s["old_head"], "feat", s["wt"],
                                         gh_cmd=gh_cmd)          # run_git=None → real git


# ---------------------------------------------------------------------------
# Test 1: identical series rebased onto newer master syncs (RED until Phase 2)
# ---------------------------------------------------------------------------

def test_identical_series_rebased_onto_newer_master_syncs(tmp_path):
    """Peer rebases the same 3 commits onto the newer master tip; worktree should sync."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("rebase", "-q", "origin/master", cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "synced"
    assert r.remote_sha == remote_sha
    assert "patch-series-equivalent" in r.evidence
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == remote_sha


# ---------------------------------------------------------------------------
# Test 2: extra commit on rebased series stays diverged
# ---------------------------------------------------------------------------

def test_extra_commit_on_rebased_series_stays_diverged(tmp_path):
    """Peer adds an extra commit on top of the rebase; content differs → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("rebase", "-q", "origin/master", cwd=s["peer"])
    _commit_file(s["peer"], "d.txt", "d\n", "cd")
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# ---------------------------------------------------------------------------
# Test 3: dropped commit on rebased series stays diverged
# ---------------------------------------------------------------------------

def test_dropped_commit_on_rebased_series_stays_diverged(tmp_path):
    """Peer drops the last commit during rebase; one fewer patch → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("rebase", "-q", "origin/master", cwd=s["peer"])
    _sh("reset", "-q", "--hard", "HEAD~1", cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# ---------------------------------------------------------------------------
# Test 4: edited commit on rebased series stays diverged
# ---------------------------------------------------------------------------

def test_edited_commit_on_rebased_series_stays_diverged(tmp_path):
    """Peer edits the content of the second patch; same message, different diff → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("rebase", "-q", "origin/master", cwd=s["peer"])
    c3_rebased = _sh("rev-parse", "HEAD~0", cwd=s["peer"])
    _sh("reset", "-q", "--hard", "HEAD~2", cwd=s["peer"])
    _commit_file(s["peer"], "b.txt", "b-edited\n", "cb")
    _sh("cherry-pick", c3_rebased, cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# ---------------------------------------------------------------------------
# Test 5: reordered series stays diverged
# ---------------------------------------------------------------------------

def test_reordered_series_stays_diverged(tmp_path):
    """Peer cherry-picks the three commits in a different order → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/master", cwd=s["peer"])
    _sh("cherry-pick", s["commits"][2], cwd=s["peer"])
    _sh("cherry-pick", s["commits"][0], cwd=s["peer"])
    _sh("cherry-pick", s["commits"][1], cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# ---------------------------------------------------------------------------
# Test 6: series on base not on master stays diverged
# ---------------------------------------------------------------------------

def test_series_on_base_not_on_master_stays_diverged(tmp_path):
    """Peer rebases feat onto a side branch tip (not master) → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "side", s["m0"], cwd=s["peer"])
    _commit_file(s["peer"], "s.txt", "s\n", "cs")
    _sh("push", "-q", "origin", "side", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("rebase", "-q", "side", cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# ---------------------------------------------------------------------------
# Test 7: series rebased onto older master stays diverged
# ---------------------------------------------------------------------------

def test_series_rebased_onto_older_master_stays_diverged(tmp_path):
    """Peer rebases feat onto M-1 (one commit before the original branch point) → diverged."""
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", s["m_minus1"], cwd=s["peer"])
    _sh("cherry-pick", s["commits"][0], cwd=s["peer"])
    _sh("cherry-pick", s["commits"][1], cwd=s["peer"])
    _sh("cherry-pick", s["commits"][2], cwd=s["peer"])
    remote_sha = _force_push_peer_head(s)
    r = _reconcile(s, remote_sha, tmp_path)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]
