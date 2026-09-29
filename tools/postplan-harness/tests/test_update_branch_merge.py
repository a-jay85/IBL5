"""Tests for update-branch merge detection in reconcile_remote_head.

Phase 1: Characterization test (RED until Phase 3).
Phase 2: Fake-run_git unit tests for update_branch_merge_equivalent.
Phase 4: Negative-path and boundary scenario tests.
Phase 5: Runner-level replay tests via ciwatch.watch_or_reuse.
"""
from __future__ import annotations

import json
import os
import pathlib
import stat
import subprocess
import sys
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import gitutil, ciwatch
from harness.ciwatch import BackgroundWatch, _write_outcome


# ---------------------------------------------------------------------------
# Shared helpers (copied verbatim from test_patch_series_equivalence.py)
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


def _update_branch_merge(s, *, message="Merge branch 'master' into feat"):
    """Reproduce what .github/workflows/update-behind-prs.yml / the GitHub 'Update branch'
    button does: on the peer, check out origin/feat and merge origin/master with a real
    merge commit (never fast-forward), then push to origin/feat. Returns the merge sha."""
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("merge", "-q", "--no-ff", "--no-edit", "-m", message, "origin/master", cwd=s["peer"])
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    return _sh("rev-parse", "HEAD", cwd=s["peer"])


# ---------------------------------------------------------------------------
# Phase 1: Characterization test (RED until Phase 3)
# ---------------------------------------------------------------------------

def test_update_branch_merge_of_master_syncs(tmp_path):
    """GitHub 'Update branch' merged master into the PR head. Phase 3 makes this 'synced';
    before Phase 3 it returns 'diverged' (the 2026-09-28 exit-3 incident)."""
    s = _build_scenario(tmp_path)
    merge_sha = _update_branch_merge(s)
    # Sanity: the scenario really built a 2-parent merge whose first parent is old_head.
    parents = _sh("rev-list", "--parents", "-n", "1", merge_sha, cwd=s["peer"]).split()[1:]
    assert parents == [s["old_head"], s["m1"]]
    r = _reconcile(s, merge_sha, tmp_path)
    assert r.action == "synced"
    assert r.remote_sha == merge_sha
    assert "update-branch merge of master" in r.evidence
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == merge_sha


# ---------------------------------------------------------------------------
# Phase 2: Fake-run_git unit tests for update_branch_merge_equivalent
# ---------------------------------------------------------------------------


class _R:
    def __init__(self, rc=0, out="", err=""):
        self.returncode, self.stdout, self.stderr = rc, out, err


def _script_run_git(script):
    """run_git stub: script maps the git subcommand (args[0]) to a _R or an exception."""
    def _run(args, cwd):
        v = script.get(args[0], _R(1))
        if isinstance(v, BaseException):
            raise v
        return v
    return _run


def test_predicate_fetch_failure_fails_closed():
    run = _script_run_git({"fetch": _R(128, err="could not resolve host")})
    assert gitutil.update_branch_merge_equivalent("a" * 40, "b" * 40, "/nowhere", run_git=run) is False


def test_predicate_timeout_fails_closed():
    run = _script_run_git({"fetch": subprocess.TimeoutExpired(cmd="git", timeout=1)})
    assert gitutil.update_branch_merge_equivalent("a" * 40, "b" * 40, "/nowhere", run_git=run) is False


def test_predicate_empty_sha_fails_closed():
    calls = []
    def run(args, cwd):
        calls.append(args)
        return _R(0, "")
    assert gitutil.update_branch_merge_equivalent("", "b" * 40, "/nowhere", run_git=run) is False
    assert gitutil.update_branch_merge_equivalent("a" * 40, "", "/nowhere", run_git=run) is False
    assert calls == []   # short-circuits before any git call


# ---------------------------------------------------------------------------
# Phase 4: Negative-path and boundary scenario tests
# ---------------------------------------------------------------------------


def _sh_rc(*args, cwd=None):
    """Like _sh but never raises; returns the CompletedProcess."""
    return subprocess.run(["git", "-c", "user.email=t@t", "-c", "user.name=t",
                           "-c", "commit.gpgsign=false", *args],
                          cwd=cwd, capture_output=True, text=True)


def _assert_diverged(s, r):
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]


# Arm 5 (tree equality). Mutation: drop the merged_tree == remote_tree comparison.
def test_evil_merge_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _update_branch_merge(s)
    (pathlib.Path(s["peer"]) / "a.txt").write_text("tampered\n")
    _sh("add", "a.txt", cwd=s["peer"])
    _sh("commit", "-q", "--amend", "--no-edit", cwd=s["peer"])   # still 2 parents, p1 == old_head
    evil = _sh("push", "-q", "--force", "origin", "HEAD:refs/heads/feat", cwd=s["peer"]) or \
           _sh("rev-parse", "HEAD", cwd=s["peer"])
    r = _reconcile(s, evil, tmp_path)
    _assert_diverged(s, r)


# Arm 5 (merge-tree rc). Mutation: drop the `mt.returncode != 0` check.
def test_conflict_resolved_merge_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    # master gains its own a.txt → add/add conflict with feat's a.txt
    _sh("checkout", "-q", "master", cwd=s["peer"])
    _commit_file(s["peer"], "a.txt", "master-a\n", "M2 conflicting a.txt")
    _sh("push", "-q", "origin", "master", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    assert _sh_rc("merge", "--no-ff", "--no-edit", "origin/master", cwd=s["peer"]).returncode != 0
    _sh("checkout", "--ours", "a.txt", cwd=s["peer"])
    _sh("add", "a.txt", cwd=s["peer"])
    _sh("commit", "-q", "--no-edit", cwd=s["peer"])
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    resolved = _sh("rev-parse", "HEAD", cwd=s["peer"])
    r = _reconcile(s, resolved, tmp_path)
    _assert_diverged(s, r)


# Arm 3 (p1 == expected_sha), stacked case. Mutation: accept any ancestor chain of
# update-branch merges (e.g. walk first-parents until expected_sha).
def test_stacked_second_update_branch_merge_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _update_branch_merge(s)                                        # merge 1 (would sync alone)
    _sh("checkout", "-q", "master", cwd=s["peer"])
    _commit_file(s["peer"], "m2.txt", "m2\n", "M2")
    _sh("push", "-q", "origin", "master", cwd=s["peer"])
    merge2 = _update_branch_merge(s)                               # merge 2 on top of merge 1
    r = _reconcile(s, merge2, tmp_path)
    _assert_diverged(s, r)


# Arm 4 (p2 on master). Mutation: drop the merge-base --is-ancestor check.
def test_merge_of_non_master_branch_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("checkout", "-q", "-b", "other", s["m0"], cwd=s["peer"])
    _commit_file(s["peer"], "o.txt", "o\n", "other work")
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("merge", "-q", "--no-ff", "--no-edit", "other", cwd=s["peer"])
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    bad = _sh("rev-parse", "HEAD", cwd=s["peer"])
    r = _reconcile(s, bad, tmp_path)
    _assert_diverged(s, r)


# Arms 3+4 together (parent order). Mutation: a symmetric rewrite — `expected_sha in
# (p1, p2)` plus "either parent is an ancestor of master" — accepts this shape.
def test_reversed_parent_order_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("checkout", "-q", "master", cwd=s["peer"])                  # HEAD = m1
    _sh("merge", "-q", "--no-ff", "--no-edit", "origin/feat", cwd=s["peer"])   # p1 = m1, p2 = old_head
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    reversed_sha = _sh("rev-parse", "HEAD", cwd=s["peer"])
    r = _reconcile(s, reversed_sha, tmp_path)
    _assert_diverged(s, r)


# Arm 2 (exactly two parents), single-parent side. Mutation: relax `len(parts) != 3`
# to `len(parts) < 2` (also exercises that the unpack cannot raise out of the predicate).
def test_plain_commit_on_top_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("fetch", "-q", "origin", cwd=s["peer"])
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    extra = _commit_file(s["peer"], "d.txt", "d\n", "cd")
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    r = _reconcile(s, extra, tmp_path)
    _assert_diverged(s, r)


# Arm 2, octopus side. Mutation: relax `len(parts) != 3` to `len(parts) < 3`.
def test_octopus_merge_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("checkout", "-q", "-b", "other", s["m0"], cwd=s["peer"])
    _commit_file(s["peer"], "o.txt", "o\n", "other work")
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=s["peer"])
    _sh("merge", "-q", "--no-ff", "--no-edit", "origin/master", "other", cwd=s["peer"])
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    octo = _sh("rev-parse", "HEAD", cwd=s["peer"])
    assert len(_sh("rev-list", "--parents", "-n", "1", octo, cwd=s["peer"]).split()) == 4
    r = _reconcile(s, octo, tmp_path)
    _assert_diverged(s, r)


# Arm 1 (fetch must succeed), isolated on the predicate. Objects are fetched first so
# the only thing the broken URL changes is the fetch rc. Mutation: drop the fetch rc check.
def test_predicate_fetch_failure_on_real_repo_is_false(tmp_path):
    s = _build_scenario(tmp_path)
    merge_sha = _update_branch_merge(s)
    _sh("fetch", "-q", "origin", cwd=s["wt"])
    assert gitutil.update_branch_merge_equivalent(s["old_head"], merge_sha, s["wt"]) is True
    _sh("remote", "set-url", "origin", str(tmp_path / "does-not-exist.git"), cwd=s["wt"])
    assert gitutil.update_branch_merge_equivalent(s["old_head"], merge_sha, s["wt"]) is False


# Boundary accept: master advanced again AFTER the update-branch merge; p2 is an older
# master commit but still an ancestor. Mutation: replace --is-ancestor with `p2 == master tip`.
def test_master_advanced_after_merge_still_syncs(tmp_path):
    s = _build_scenario(tmp_path)
    merge_sha = _update_branch_merge(s)
    _sh("checkout", "-q", "master", cwd=s["peer"])
    _commit_file(s["peer"], "m2.txt", "m2\n", "M2 after merge")
    _sh("push", "-q", "origin", "master", cwd=s["peer"])
    r = _reconcile(s, merge_sha, tmp_path)
    assert r.action == "synced" and r.remote_sha == merge_sha


# Boundary accept: the predicate keys on expected_sha, not local_sha. Mutation: pass
# local_sha to the predicate in reconcile_remote_head.
def test_first_parent_must_equal_expected_sha_not_local_sha(tmp_path):
    s = _build_scenario(tmp_path)
    merge_sha = _update_branch_merge(s)
    gh_cmd = _fake_gh(tmp_path, [merge_sha])
    r = gitutil.reconcile_remote_head(42, s["old_head"], s["m0"], "feat", s["wt"], gh_cmd=gh_cmd)
    assert r.action == "synced" and r.remote_sha == merge_sha


# ---------------------------------------------------------------------------
# Phase 5: Runner-level replay tests via ciwatch.watch_or_reuse
# ---------------------------------------------------------------------------


def _seed_outcome(out_dir, sha, status="success"):
    bg = BackgroundWatch(sha=sha, pr=42, worktree="", path=os.path.join(out_dir, f"ci-{sha}.json"),
                         started=time.time(), verify_head=True)
    _write_outcome(bg, status, [], "seeded")


# Happy path at the phase7 seam. Mutation: delete the Phase 3 arm ⇒ outcome.diverged is
# True and head_sha is the merge sha with exit_code -1 (the 2026-09-28 incident replayed).
def test_watch_or_reuse_rekeys_to_update_branch_merge(tmp_path):
    s = _build_scenario(tmp_path)
    merge_sha = _update_branch_merge(s)
    out = str(tmp_path / "out"); os.makedirs(out)
    _seed_outcome(out, merge_sha)          # CI for the merge commit is green
    gh_cmd = _fake_gh(tmp_path, [merge_sha])
    outcome = ciwatch.watch_or_reuse(s["wt"], 42, s["old_head"], out, None,
                                     timeout=30, verify_head=True, gh_cmd=gh_cmd)
    assert outcome.diverged is False
    assert outcome.head_sha == merge_sha
    assert outcome.exit_code == 0
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == merge_sha
    # The seeded outcome for the OLD sha must not exist: CI was read under the merge sha.
    assert not os.path.exists(os.path.join(out, f"ci-{s['old_head']}.json"))


# Negative path at the same seam: an evil merge still surfaces as diverged so runner.py
# reaches _fail_closed_on_divergence(disarm=True). Mutation: drop the tree comparison
# in the predicate ⇒ diverged is False here.
def test_watch_or_reuse_evil_merge_still_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _update_branch_merge(s)
    (pathlib.Path(s["peer"]) / "a.txt").write_text("tampered\n")
    _sh("add", "a.txt", cwd=s["peer"])
    _sh("commit", "-q", "--amend", "--no-edit", cwd=s["peer"])
    _sh("push", "-q", "--force", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    evil = _sh("rev-parse", "HEAD", cwd=s["peer"])
    out = str(tmp_path / "out"); os.makedirs(out)
    _seed_outcome(out, evil)               # even a green CI on the evil sha must not be adopted
    gh_cmd = _fake_gh(tmp_path, [evil])
    outcome = ciwatch.watch_or_reuse(s["wt"], 42, s["old_head"], out, None,
                                     timeout=30, verify_head=True, gh_cmd=gh_cmd)
    assert outcome.diverged is True
    assert outcome.exit_code == -1
    assert outcome.head_sha == evil
    assert "diverged" in outcome.evidence
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["old_head"]   # no reset on diverged
