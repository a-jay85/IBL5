"""PRE-patch capture must be byte-faithful: CRLF and non-UTF-8 lines survive, and lostwork.sh still blocks a real loss."""
import os
import shutil
import subprocess
import sys
import tempfile
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_SCRIPTS = os.path.join(_REPO_ROOT, ".claude", "review-shared", "scripts")
_LOSTWORK = os.path.join(_SCRIPTS, "lostwork.sh")
_COLLAPSE = os.path.join(_SCRIPTS, "collapse-guard.sh")

CRLF_BASE = b"services:\r\n  php:\r\n    image: php:8\r\n"
LF_BASE = "# rule\n\nline one\n"


def _sh(d, *args, check=True):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
        "GIT_EDITOR": "true",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=check, capture_output=True, text=True, env=env
    )


def _rev(d, ref):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _cleanup_tmp(key):
    """Remove /tmp files written by the resolver and the proof scripts."""
    safe = key  # key already has / replaced by -
    patterns = [
        f"/tmp/postplan-conflict-files-{key}.txt",
        f"/tmp/postplan-conflict-resolution-{key}.md",
        f"/tmp/postplan-lostwork-{key}.sh",
        f"/tmp/postplan-collapse-guard-{key}.sh",
        f"/tmp/pr-ready-diff-pre-{key}.patch",
        f"/tmp/pr-ready-diff-post-{key}.patch",
        f"/tmp/pr-ready-numstat-pre-{key}.txt",
        f"/tmp/pr-ready-numstat-post-{key}.txt",
        f"/tmp/pr-ready-collapse-guard-{key}-{safe}.meta",
    ]
    for p in patterns:
        if os.path.exists(p):
            os.unlink(p)


@pytest.fixture(autouse=True)
def _no_editor(monkeypatch):
    # A conflicted-merge commit or `rebase --continue` can otherwise block on an editor.
    monkeypatch.setenv("GIT_EDITOR", "true")


def _write_bytes(d, name, data: bytes):
    with open(os.path.join(d, name), "wb") as fh:
        fh.write(data)


def _commit_proof_scripts(d):
    """Commit the real proof scripts so `git show <master_sha>:<path>` finds them."""
    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))


def _make_crlf_repo(branch_adds: bytes, lf_adds: str = "", move_master: bool = True):
    """master: ci.yml (CRLF) + rule.md (LF). feature: appends branch_adds to ci.yml
    and lf_adds to rule.md. master then moves ahead with an unrelated LF file so a
    rebase has work to do. Returns (d, key, branch, master_sha)."""
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-crlf-{suffix}"
    d = tempfile.mkdtemp(prefix="postplan-crlf-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "core.autocrlf", "false")
    _write_bytes(d, "ci.yml", CRLF_BASE)
    _write_bytes(d, "rule.md", LF_BASE.encode())
    _commit_proof_scripts(d)
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    _sh(d, "checkout", "-b", branch)
    _write_bytes(d, "ci.yml", CRLF_BASE + branch_adds)
    if lf_adds:
        _write_bytes(d, "rule.md", (LF_BASE + lf_adds).encode())
    _sh(d, "add", "-A")
    _sh(d, "commit", "--allow-empty", "-m", "feat: branch work")
    _sh(d, "checkout", "master")
    if move_master:
        _write_bytes(d, "other.txt", b"master moved\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "master: unrelated")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "checkout", branch)
    return d, branch, branch, master_sha


def _make_stacked_crlf_repo():
    """Squash-trap fixture whose feature commit appends CRLF lines to ci.yml.
    Returns (d, key, branch)."""
    suffix = uuid.uuid4().hex[:8]
    feature_branch = f"feature-crlf-stacked-{suffix}"
    key = feature_branch.replace("/", "-")
    d = tempfile.mkdtemp(prefix="postplan-crlf-sq-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    _sh(d, "config", "core.autocrlf", "false")

    _write_bytes(d, "a.txt", b"base\n")
    _write_bytes(d, "ci.yml", CRLF_BASE)
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    _commit_proof_scripts(d)
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    # Parent with two commits so the squash trap fires on a plain replay.
    _sh(d, "checkout", "-b", "parent")
    _write_bytes(d, "parent.txt", b"step1\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step1")
    _write_bytes(d, "parent.txt", b"step2\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step2")
    parent_tip = _rev(d, "HEAD")

    _sh(d, "checkout", "master")
    _sh(d, "merge", "--squash", "parent")
    _sh(d, "commit", "-m", "squash: parent")
    master_sha = _rev(d, "HEAD")

    _sh(d, "checkout", "-b", feature_branch, parent_tip)
    _write_bytes(d, "ci.yml", CRLF_BASE + b"  worker:\r\n    image: php:8-cli\r\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")

    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "config", f"branch.{feature_branch}.iblBase", parent_tip)
    _sh(d, "checkout", feature_branch)
    return d, key, feature_branch


_BRANCH_ADDS = b"  worker:\r\n    image: php:8-cli\r\n    command: run\r\n"


def _pre_path(key):
    return Path(f"/tmp/pr-ready-diff-pre-{key}.patch")


def test_crlf_added_lines_survive_capture_and_prove_tree_equivalent():
    d, key, branch, _ = _make_crlf_repo(_BRANCH_ADDS)
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is True
        g._rebase_onto("origin/master")
        pre = _pre_path(key).read_bytes()
        assert pre.count(b"\r\n") >= 3
        ok, out = g.prove_lostwork(key)
        assert ok, out
        assert "LOST:" not in out
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_capture_lostwork_pre_alone_writes_crlf_bytes():
    d, key, branch, _ = _make_crlf_repo(_BRANCH_ADDS)
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is True
        assert b"\r\n" in _pre_path(key).read_bytes()
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_truly_dropped_crlf_line_still_blocks():
    d, key, branch, _ = _make_crlf_repo(_BRANCH_ADDS)
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is True
        g._rebase_onto("origin/master")
        _write_bytes(d, "ci.yml", CRLF_BASE + b"  worker:\r\n    image: php:8-cli\r\n")
        _sh(d, "commit", "-am", "resolver dropped a line")
        ok, out = g.prove_lostwork(key)
        assert ok is False
        assert "LOST:" in out and "command: run" in out
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_empty_pre_patch_fails_closed():
    d, key, branch, _ = _make_crlf_repo(b"", move_master=False)
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is False
        assert not os.path.exists(f"/tmp/pr-ready-diff-pre-{key}.patch")
        ok, _ = g.prove_lostwork(key)
        assert ok is False
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_non_utf8_bytes_survive_capture():
    d, key, branch, _ = _make_crlf_repo(b"  env: caf\xe9 \xff\xfe\r\n")
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is True
        g._rebase_onto("origin/master")
        pre = _pre_path(key).read_bytes()
        assert b"caf\xe9 \xff\xfe" in pre
        assert b"\xef\xbf\xbd" not in pre
        ok, out = g.prove_lostwork(key)
        assert ok, out
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_reworded_lf_line_still_blocks():
    d, key, branch, _ = _make_crlf_repo(
        b"", lf_adds="AUTOMOUSE_CHECK_PLAN=/usr/bin/true run\n"
    )
    try:
        g = LiveGit(d)
        assert g.capture_lostwork_pre(key) is True
        g._rebase_onto("origin/master")
        _write_bytes(
            d, "rule.md",
            (LF_BASE + "AUTOMOUSE_CHECK_PLAN=/usr/bin/true \\\n  run\n").encode(),
        )
        _sh(d, "commit", "-am", "resolver reworded a line")
        ok, out = g.prove_lostwork(key)
        assert ok is False and "LOST:" in out
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_stacked_rebase_capture_keeps_crlf_bytes():
    d, key, branch = _make_stacked_crlf_repo()
    try:
        g = LiveGit(d)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert b"\r\n" in _pre_path(key).read_bytes()
        ok, out = g.prove_lostwork(key)
        assert ok, out
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_run_bytes_keeps_cr_and_raises_on_bad_ref():
    d, key, branch, master_sha = _make_crlf_repo(b"  x: y\r\n")
    try:
        g = LiveGit(d)
        out = g._run_bytes("diff", f"{master_sha}...HEAD")
        assert isinstance(out, bytes) and b"\r\n" in out
        with pytest.raises(HarnessError) as ei:
            g._run_bytes("diff", "no-such-ref-deadbeef...HEAD")
        assert ei.value.kind == "git"
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_rebase_onto_bad_base_writes_no_pre_patch():
    d, key, branch, _ = _make_crlf_repo(b"  x: y\r\n")
    try:
        g = LiveGit(d)
        with pytest.raises(HarnessError):
            g._rebase_onto("no-such-ref-deadbeef")
        assert not os.path.exists(f"/tmp/pr-ready-diff-pre-{key}.patch")
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
