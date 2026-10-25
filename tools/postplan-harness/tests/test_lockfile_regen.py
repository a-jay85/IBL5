"""Unit tests for harness/lockfile_regen.py — a tiny real git repo, no bun, no network."""
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import lockfile_regen
from harness.lockfile_regen import (
    LockfileRegenError,
    RegenLockfile,
    command_text,
    regen_lockfile,
    regenerable,
)

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
_LOCK = "ibl5/bun.lock"


def _git(d, *args):
    return subprocess.run(["git", "-C", str(d), *args], check=True,
                          capture_output=True, text=True, env=_ENV).stdout


@pytest.fixture
def repo(tmp_path):
    d = tmp_path / "wt"
    (d / "ibl5").mkdir(parents=True)
    _git(d, "init", "-q", "-b", "master")
    _git(d, "config", "commit.gpgsign", "false")
    (d / _LOCK).write_text("master lock\n")
    (d / "ibl5/package.json").write_text("{}\n")
    _git(d, "add", "-A")
    _git(d, "commit", "-qm", "base")
    return d, _git(d, "rev-parse", "HEAD").strip()


def _entry(argv):
    return RegenLockfile(_LOCK, "ibl5/package.json", tuple(argv), "ibl5")


def _call(repo, argv, **kwargs):
    d, sha = repo

    def run(*args, **_kw):
        return _git(d, *args)

    return regen_lockfile(_entry(argv), run=run, worktree=str(d), master_sha=sha, **kwargs)


def test_registry_bun_argv_ignores_scripts():
    argv = regenerable(_LOCK).argv
    assert "--ignore-scripts" in argv
    assert "--lockfile-only" in argv


def test_registry_composer_argv_disables_scripts_and_plugins():
    argv = regenerable("ibl5/composer.lock").argv
    for flag in ("--no-scripts", "--no-plugins", "--no-install", "--minimal-changes"):
        assert flag in argv


def test_regenerable_rejects_unregistered_locks():
    for path in ("ibl5/IBLbot/package-lock.json", "composer.lock", "ibl5/other.lock", "bun.lock"):
        assert regenerable(path) is None


def test_regen_nonzero_exit_names_command(repo):
    with pytest.raises(LockfileRegenError) as exc:
        _call(repo, ("bash", "-c", "echo boom >&2; exit 9"))
    assert str(exc.value).startswith(
        "lockfile: ibl5/bun.lock (regen failed: bash -c 'echo boom >&2; exit 9' exit 9: boom")


def test_regen_timeout_names_command(repo):
    with pytest.raises(LockfileRegenError) as exc:
        _call(repo, ("bash", "-c", "sleep 5"), timeout=1)
    assert "regen timed out after 1s: bash -c 'sleep 5'" in str(exc.value)
    assert str(exc.value).startswith("lockfile: ibl5/bun.lock (")


def test_regen_missing_binary_names_command(repo):
    with pytest.raises(LockfileRegenError) as exc:
        _call(repo, ("postplan-no-such-binary-xyz",))
    assert "command not found" in str(exc.value)
    assert "postplan-no-such-binary-xyz" in str(exc.value)


def test_regen_marker_output_rejected(repo):
    with pytest.raises(LockfileRegenError) as exc:
        _call(repo, ("bash", "-c", "printf '<<<<<<< ours\\nx\\n' > bun.lock"))
    assert "left conflict markers" in str(exc.value)


def test_regen_stray_write_rejected(repo):
    with pytest.raises(LockfileRegenError) as exc:
        _call(repo, ("bash", "-c", "echo new > bun.lock; echo x > stray.txt"))
    assert "regen touched unexpected paths: ibl5/stray.txt" in str(exc.value)


def test_regen_ignores_other_unmerged_lockfile(repo):
    d, _ = repo
    other = "ibl5/composer.lock"
    (d / other).write_text("master other\n")
    _git(d, "add", "-A")
    _git(d, "commit", "-qm", "other lock")
    sha = _git(d, "rev-parse", "HEAD").strip()
    (d / other).write_text("dirty, still unmerged\n")
    argv = ("bash", "-c", "echo regenerated > bun.lock")

    def run(*args, **_kw):
        return _git(d, *args)

    with pytest.raises(LockfileRegenError) as exc:
        regen_lockfile(_entry(argv), run=run, worktree=str(d), master_sha=sha)
    assert f"unexpected paths: {other}" in str(exc.value)

    (d / _LOCK).write_text("master lock\n")
    assert regen_lockfile(_entry(argv), run=run, worktree=str(d), master_sha=sha,
                          other_locks=(other,)) == command_text(_entry(argv))


def test_regen_env_inherits_path(repo):
    d, _ = repo
    seen = {}

    def recorder(args, **kwargs):
        seen.update(kwargs)
        (d / _LOCK).write_text("regenerated\n")
        return subprocess.CompletedProcess(args, 0, "", "")

    _call(repo, ("bun", "install"), runner=recorder)

    assert seen["env"]["PATH"] == os.environ["PATH"]
    assert seen["timeout"] == lockfile_regen.LOCKFILE_REGEN_TIMEOUT
    assert seen["cwd"].endswith("/ibl5")


def test_regen_success_stages_lock_and_returns_command(repo):
    d, _ = repo
    argv = ("bash", "-c", "echo regenerated > bun.lock")
    assert _call(repo, argv) == command_text(_entry(argv))
    assert _git(d, "diff", "--cached", "--name-only").split() == [_LOCK]
    assert _git(d, "diff", "--name-only").strip() == ""
