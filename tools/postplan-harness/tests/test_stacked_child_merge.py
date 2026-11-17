"""Tests for stacked-child merge detection in reconcile_remote_head.

Phase 1: stacked_child_merge_equivalent and the merged-child PR lookup.
Phase 2: the fourth adoption case in reconcile_remote_head.
"""
from __future__ import annotations

import json
import os
import pathlib
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import gitutil


@pytest.fixture(autouse=True)
def patch_sleep(monkeypatch):
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
    bare = str(tmp_path / "origin.git")
    wt = str(tmp_path / "wt")
    peer = str(tmp_path / "peer")
    _sh("init", "-q", "--bare", "-b", "master", bare)
    _sh("clone", "-q", bare, wt)
    _commit_file(wt, "root.txt", "root\n", "root")
    _commit_file(wt, "m0.txt", "m0\n", "M0")
    _sh("push", "-q", "origin", "master", cwd=wt)
    _sh("checkout", "-q", "-b", "feat", cwd=wt)
    c = [_commit_file(wt, f"{n}.txt", f"{n}\n", f"c{n}") for n in ("a", "b", "c")]
    _sh("push", "-q", "-u", "origin", "feat", cwd=wt)
    _sh("clone", "-q", bare, peer)
    _sh("checkout", "-q", "-B", "feat", "origin/feat", cwd=peer)
    return {"bare": bare, "wt": wt, "peer": peer, "expected": c[-1], "commits": c}


def _child_commit(s, name):
    """A commit a stacked child PR squash-merged into feat, pushed from the peer."""
    sha = _commit_file(s["peer"], f"{name}.txt", f"{name}\n", f"child {name} (#99)")
    _sh("push", "-q", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    return sha


def _merged(number, sha, base="feat", merged_at="2026-11-16T00:00:00Z"):
    return {"number": number, "merge_commit_sha": sha, "merged_at": merged_at,
            "base": {"ref": base}}


def _fake_gh_api(tmp_path, pulls_by_sha, rc=0, sleep_secs=0, raw="", head_sha=""):
    """Executable bash fake gh. `api .../commits/<sha>/pulls` prints raw (when set) or the
    JSON list stored for that sha ([] when absent), after sleeping, then exits rc.
    `pr view` prints head_sha and exits 0."""
    d = tmp_path / "pulls"
    d.mkdir(exist_ok=True)
    for sha, pulls in pulls_by_sha.items():
        (d / f"{sha}.json").write_text(json.dumps(pulls))
    (tmp_path / "raw.txt").write_text(raw)
    script = tmp_path / "gh"
    script.write_text(
        "#!/usr/bin/env bash\n"
        "if [ \"$1\" = \"pr\" ]; then\n"
        f"  echo '{head_sha}'\n"
        "  exit 0\n"
        "fi\n"
        f"sleep {sleep_secs}\n"
        "SHA=$(echo \"$2\" | sed -E 's#.*commits/([0-9a-f]+)/pulls.*#\\1#')\n"
        f"if [ -s \"{tmp_path}/raw.txt\" ]; then cat \"{tmp_path}/raw.txt\"\n"
        f"elif [ -f \"{d}/$SHA.json\" ]; then cat \"{d}/$SHA.json\"\n"
        "else echo '[]'; fi\n"
        f"exit {rc}\n"
    )
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    return [str(script)]


def _equiv(s, gh, remote, *, local=None):
    return gitutil.stacked_child_merge_equivalent(
        s["expected"], local or s["expected"], remote, "feat", s["wt"], gh_cmd=gh)


# --- Phase 1 ---------------------------------------------------------------

def test_one_merged_child_squash_adopted(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]})
    assert _equiv(s, gh, c1) == (41,)


def test_two_merged_children_adopted(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    c2 = _child_commit(s, "k2")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)], c2: [_merged(42, c2)]})
    assert _equiv(s, gh, c2) == (41, 42)


def test_plain_non_pr_commit_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: []})
    assert _equiv(s, gh, c1) == ()


def test_merged_pr_different_base_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1, base="master")]})
    assert _equiv(s, gh, c1) == ()


def test_open_pr_commit_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1, merged_at=None)]})
    assert _equiv(s, gh, c1) == ()


def test_merge_commit_sha_mismatch_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    other = "0" * 40
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, other)]})
    assert _equiv(s, gh, c1) == ()


def test_gh_lookup_failure_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]}, rc=1)
    assert _equiv(s, gh, c1) == ()


def test_gh_lookup_timeout_diverged(tmp_path, monkeypatch):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    monkeypatch.setattr(gitutil, "GH_TIMEOUT", 1)
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]}, sleep_secs=5)
    assert _equiv(s, gh, c1) == ()


def test_gh_unparseable_output_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {}, raw="Not Found")
    assert _equiv(s, gh, c1) == ()


def test_remote_not_descendant_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("reset", "-q", "--hard", s["commits"][1], cwd=s["peer"])
    rewritten = _commit_file(s["peer"], "z.txt", "z\n", "rewritten")
    _sh("push", "-q", "--force", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    gh = _fake_gh_api(tmp_path, {rewritten: [_merged(41, rewritten)]})
    assert _equiv(s, gh, rewritten) == ()


def test_local_ahead_of_expected_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    local = _commit_file(s["wt"], "unpushed.txt", "u\n", "unpushed local work")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]})
    assert _equiv(s, gh, c1, local=local) == ()


def test_chain_over_cap_diverged(tmp_path, monkeypatch):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    c2 = _child_commit(s, "k2")
    monkeypatch.setattr(gitutil, "STACKED_CHILD_CHAIN_CAP", 1)
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)], c2: [_merged(42, c2)]})
    assert _equiv(s, gh, c2) == ()


# --- Phase 2 ---------------------------------------------------------------

def _reconcile(s, gh, **kw):
    return gitutil.reconcile_remote_head(42, s["expected"], s["expected"], "feat", s["wt"],
                                         gh_cmd=gh, sleep=lambda x: None, **kw)


def test_reconcile_adopts_stacked_child(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]}, head_sha=c1)
    r = _reconcile(s, gh, allow_stacked_child=True)
    assert r.action == "stacked-child"
    assert r.child_prs == (41,)
    assert "#41" in r.evidence


def test_reconcile_syncs_worktree_to_remote(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]}, head_sha=c1)
    _reconcile(s, gh, allow_stacked_child=True)
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == c1


def test_default_flag_stays_diverged(tmp_path):
    s = _build_scenario(tmp_path)
    c1 = _child_commit(s, "k1")
    gh = _fake_gh_api(tmp_path, {c1: [_merged(41, c1)]}, head_sha=c1)
    r = _reconcile(s, gh)
    assert r.action == "diverged"
    assert _sh("rev-parse", "HEAD", cwd=s["wt"]) == s["expected"]


def test_existing_cases_take_precedence(tmp_path):
    s = _build_scenario(tmp_path)
    _sh("commit", "-q", "--amend", "-m", "reworded", cwd=s["peer"])
    _sh("push", "-q", "--force", "origin", "HEAD:refs/heads/feat", cwd=s["peer"])
    amended = _sh("rev-parse", "HEAD", cwd=s["peer"])
    gh = _fake_gh_api(tmp_path, {amended: [_merged(41, amended)]}, head_sha=amended)
    r = _reconcile(s, gh, allow_stacked_child=True)
    assert r.action == "synced"
