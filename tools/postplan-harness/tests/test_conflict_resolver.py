"""Unit and negative-path tests for conflict.py resolver, abort/restore, and verdict."""
from __future__ import annotations

import glob
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.conflict import (
    MAX_RESOLVE_ROUNDS,
    ConflictInventory,
    ConflictResolutionResult,
    abort_and_restore,
    inventory_conflicts,
    purge_verdict_artifacts,
    resolve_all,
    resolve_one,
    review_resolution,
)
from harness.state import HarnessError

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_COLLAPSE = os.path.join(
    _REPO_ROOT, ".claude", "skills", "pr-ready", "scripts", "collapse-guard.sh"
)
_LOSTWORK_EQUIV = (
    "#!/usr/bin/env bash\n"
    "echo 'TREE-EQUIVALENT'\n"
    "exit 0\n"
)


# ── Stub helpers ──────────────────────────────────────────────────────────────

class _StubLlm:
    """Records call_tooled kwargs; replies are driven by a list — strings or exceptions."""
    def __init__(self, replies):
        self.replies = list(replies)
        self.calls: list[dict] = []

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({
            "purpose": purpose, "model": model,
            "allowed_tools": allowed_tools, "denied_tools": denied_tools,
            "add_dirs": add_dirs,
        })
        if not self.replies:
            return "FAILED"
        reply = self.replies.pop(0)
        if isinstance(reply, BaseException):
            raise reply
        return reply


class _StubRun:
    """Minimal git-run stub for resolver unit tests."""
    def __init__(self, worktree: str, porcelain_before="", porcelain_after=""):
        self.worktree = worktree
        self._status_calls = 0
        self.porcelain_before = porcelain_before
        self.porcelain_after = porcelain_after
        self.calls: list[tuple] = []

    def __call__(self, *args, check=True) -> str:
        self.calls.append(args)
        first = args[0] if args else ""
        if first == "status":
            self._status_calls += 1
            return self.porcelain_after if self._status_calls > 1 else self.porcelain_before
        if first == "add":
            return ""
        if first == "ls-files":
            return ""
        if first == "show":
            return "base content\n"
        if first == "rev-parse":
            if "--git-path" in args:
                return "/nonexistent/path"
            return "abc1234"
        if first in ("rebase", "reset"):
            return ""
        return ""


class _WritingLlm:
    """Stub LLM that writes fixed content to a pre-configured path on resolve calls."""
    def __init__(self, worktree: str, resolve_path: str, *,
                 content: str = "merged content\n",
                 review_reply: str = "CONFLICT-REVIEW=CLEAN\n"):
        self.calls: list[dict] = []
        self._full_path = os.path.join(worktree, resolve_path)
        self._content = content
        self._review_reply = review_reply

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({"purpose": purpose})
        if purpose == "conflict-review":
            return self._review_reply
        with open(self._full_path, "w") as fh:
            fh.write(self._content)
        return "RESOLVED"


def _make_temp_file(worktree: str, path: str, content: str) -> None:
    full = os.path.join(worktree, path)
    os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "w") as fh:
        fh.write(content)


# ── Phase 2e: resolver unit tests ──────────────────────────────────────────────

def test_happy_path(tmp_path):
    """Resolver stub replying RESOLVED with clean content succeeds in one round."""
    worktree = str(tmp_path)
    path = "app/foo.py"
    _make_temp_file(worktree, path, "resolved content\n")

    llm = _StubLlm(["RESOLVED"])
    run = _StubRun(worktree)
    success, reason = resolve_one(llm, run, worktree=worktree, key="test-key", path=path)
    assert success is True
    assert reason == ""
    assert any(c[0] == "add" for c in run.calls)
    assert len(llm.calls) == 1


def test_round_cap(tmp_path):
    """Surviving conflict marker consumes all rounds then returns round cap exceeded."""
    worktree = str(tmp_path)
    path = "app/foo.py"
    _make_temp_file(worktree, path, "<<<<<<< HEAD\nours\n=======\ntheirs\n>>>>>>> branch\n")

    llm = _StubLlm(["RESOLVED"] * MAX_RESOLVE_ROUNDS)
    run = _StubRun(worktree)
    success, reason = resolve_one(llm, run, worktree=worktree, key="test-key", path=path)
    assert success is False
    assert reason.startswith(f"round cap exceeded: {path}")
    assert len(llm.calls) == MAX_RESOLVE_ROUNDS


def test_resolver_declines(tmp_path):
    """FAILED reply returns resolver declined: after exactly one call."""
    worktree = str(tmp_path)
    path = "app/foo.py"
    _make_temp_file(worktree, path, "content\n")

    llm = _StubLlm(["FAILED"])
    run = _StubRun(worktree)
    success, reason = resolve_one(llm, run, worktree=worktree, key="test-key", path=path)
    assert success is False
    assert reason == f"resolver declined: {path}"
    assert len(llm.calls) == 1


def test_blast_radius(tmp_path):
    """Stub dirtying a second path fails the blast-radius check."""
    worktree = str(tmp_path)
    path = "app/foo.py"
    other = "app/other.py"
    _make_temp_file(worktree, path, "resolved\n")

    run = _StubRun(worktree, porcelain_before="", porcelain_after=f" M {other}\n")
    llm = _StubLlm(["RESOLVED"] * MAX_RESOLVE_ROUNDS)
    success, reason = resolve_one(llm, run, worktree=worktree, key="test-key", path=path)
    assert success is False


def test_resolver_tool_allowlist(tmp_path):
    """Recorded kwargs carry allowed_tools == ('Read', 'Write') and a non-empty add_dirs."""
    worktree = str(tmp_path)
    path = "app/foo.py"
    _make_temp_file(worktree, path, "resolved\n")

    llm = _StubLlm(["RESOLVED"])
    run = _StubRun(worktree)
    resolve_one(llm, run, worktree=worktree, key="test-key", path=path)
    assert llm.calls[0]["allowed_tools"] == ("Read", "Write")
    assert len(llm.calls[0]["add_dirs"]) > 0


# ── Phase 3e: negative-path restore tests (real temp git repo) ──────────────

def _sh(d, *args, check=True):
    env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
    return subprocess.run(["git", "-C", d, *args], check=check,
                          capture_output=True, text=True, env=env)


def _make_conflict_repo():
    """Temp repo with a genuine rebase conflict. Returns (d, pre_sha, branch)."""
    d = tempfile.mkdtemp(prefix="conflict-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    _sh(d, "checkout", "-b", "feat")
    open(os.path.join(d, "a.txt"), "w").write("feature version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: change a.txt")
    pre_sha = _sh(d, "rev-parse", "HEAD").stdout.strip()

    _sh(d, "checkout", "master")
    open(os.path.join(d, "a.txt"), "w").write("master version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "master change")
    _sh(d, "update-ref", "refs/remotes/origin/master",
        _sh(d, "rev-parse", "HEAD").stdout.strip())
    _sh(d, "checkout", "feat")
    return d, pre_sha, "feat"


def _live_run(d):
    def run(*args, check=True):
        env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
               "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
        r = subprocess.run(["git", "-C", d, *args], capture_output=True,
                           text=True, errors="replace", env=env)
        if check and r.returncode != 0:
            raise HarnessError("git", r.stderr or r.stdout)
        return r.stdout
    return run


def _start_conflicting_rebase(d):
    env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
    subprocess.run(["git", "-C", d, "rebase", "origin/master"],
                   capture_output=True, text=True, env=env)  # expected rc!=0


def _sh_rev(d, ref):
    return subprocess.run(["git", "-C", d, "rev-parse", ref],
                          check=True, capture_output=True, text=True).stdout.strip()


def _make_migration_conflict_repo():
    """Temp repo with a rebase conflict on a SQL migration file.
    Returns (d, pre_sha, branch).
    """
    d = tempfile.mkdtemp(prefix="conflict-migration-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    # Base commit: just a.txt
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    # Feature branch: add ibl5/migrations/0001_x.sql
    _sh(d, "checkout", "-b", "feat")
    os.makedirs(os.path.join(d, "ibl5", "migrations"), exist_ok=True)
    open(os.path.join(d, "ibl5", "migrations", "0001_x.sql"), "w").write(
        "-- feature schema\n"
    )
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: add migration")
    pre_sha = _sh_rev(d, "HEAD")

    # Master: add same migration file with different content (add-add conflict)
    _sh(d, "checkout", "master")
    os.makedirs(os.path.join(d, "ibl5", "migrations"), exist_ok=True)
    open(os.path.join(d, "ibl5", "migrations", "0001_x.sql"), "w").write(
        "-- master schema\n"
    )
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "master: add migration")
    _sh(d, "update-ref", "refs/remotes/origin/master", _sh_rev(d, "HEAD"))
    _sh(d, "checkout", "feat")
    return d, pre_sha, "feat"


def _make_marker_planted_repo():
    """Squash-trap fixture where outside.txt has a committed conflict marker.

    feature.txt is the actual conflicted file; outside.txt is committed with a
    `<<<<<<< HEAD` marker line already in it. After auto-resolution and
    `rebase --continue`, the whole-tree sweep fires on the committed marker.
    Returns (d, key, branch).
    """
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-mp-{suffix}"
    key = branch

    d = tempfile.mkdtemp(prefix="postplan-mp-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    # Base: feature.txt and outside.txt
    open(os.path.join(d, "feature.txt"), "w").write("base content\n")
    open(os.path.join(d, "outside.txt"), "w").write("normal\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    # Proof scripts committed to master
    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    open(os.path.join(scripts_dir, "lostwork.sh"), "w").write(_LOSTWORK_EQUIV)
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    # Parent branch (squash-trap setup)
    _sh(d, "checkout", "-b", "parent")
    open(os.path.join(d, "parent.txt"), "w").write("step\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step")
    parent_tip = _sh_rev(d, "HEAD")

    # Squash merge parent; add conflict seed on master
    _sh(d, "checkout", "master")
    _sh(d, "merge", "--squash", "parent")
    _sh(d, "commit", "-m", "squash: parent")
    open(os.path.join(d, "feature.txt"), "w").write("master version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: master version of feature")
    master_sha = _sh_rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    # Feature branch from parent_tip: modifies feature.txt AND commits a marker in outside.txt
    _sh(d, "checkout", "-b", branch, parent_tip)
    open(os.path.join(d, "feature.txt"), "w").write("feature version\n")
    open(os.path.join(d, "outside.txt"), "w").write("<<<<<<< HEAD\npre-existing marker\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work with planted marker")
    _sh(d, "config", f"branch.{branch}.iblBase", parent_tip)

    return d, key, branch


def _cleanup_marker_repo(key: str, branch: str, d: str) -> None:
    from harness.armable import conflict_flag_path as _cfp
    from harness.conflict import purge_verdict_artifacts
    purge_verdict_artifacts(key)
    flag = _cfp(branch)
    if os.path.exists(flag):
        os.unlink(flag)
    for dpath in [
        f"/tmp/postplan-conflict-review-{key}",
        f"/tmp/postplan-conflict-stages-{key}",
    ]:
        if os.path.exists(dpath):
            shutil.rmtree(dpath, ignore_errors=True)
    for extra in [
        f"/tmp/postplan-conflict-files-{key}-autoresolved.txt",
        f"/tmp/postplan-conflict-files-{key}.txt",
        f"/tmp/pr-ready-diff-pre-{key}.patch",
        f"/tmp/postplan-lostwork-{key}.sh",
    ]:
        if os.path.exists(extra):
            os.unlink(extra)
    if d and os.path.exists(d):
        shutil.rmtree(d, ignore_errors=True)


def test_proof_failure_restores():
    """Proof failure restores pre_rebase_sha, leaves no rebase-merge dir."""
    d, pre_sha, branch = _make_conflict_repo()
    try:
        _start_conflicting_rebase(d)
        run = _live_run(d)
        with pytest.raises(HarnessError) as exc:
            abort_and_restore(run, worktree=d, pre_rebase_sha=pre_sha,
                              reason="tree proof failed: TREE DIVERGED")
        assert exc.value.kind == "rebase-conflict"
        assert _sh(d, "rev-parse", "HEAD").stdout.strip() == pre_sha
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_round_cap_restores():
    """Round-cap path restores pre_rebase_sha with empty porcelain and no rebase dir."""
    d, pre_sha, branch = _make_conflict_repo()
    try:
        _start_conflicting_rebase(d)
        run = _live_run(d)
        with pytest.raises(HarnessError) as exc:
            abort_and_restore(run, worktree=d, pre_rebase_sha=pre_sha,
                              reason="round cap exceeded: a.txt")
        assert exc.value.kind == "rebase-conflict"
        assert _sh(d, "rev-parse", "HEAD").stdout.strip() == pre_sha
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_unresolvable_class_restores():
    """Matrix row 18: a migration file in the conflict set raises and restores with zero LLM calls.

    The class gate in classify() fires before any model sees the file.
    """
    d, pre_sha, branch = _make_migration_conflict_repo()
    llm = _StubLlm([])
    try:
        _start_conflicting_rebase(d)
        run = _live_run(d)

        inventory = inventory_conflicts(run)
        assert inventory.unresolvable_reason is not None
        assert inventory.unresolvable_reason.startswith("migration file:")

        # gitad.py's branch verbatim: the class gate aborts BEFORE resolve_all is
        # ever reached, so the resolve_all call below is unreachable and the
        # zero-calls assertion is earned rather than vacuous.
        with pytest.raises(HarnessError) as exc:
            if inventory.unresolvable_reason:
                abort_and_restore(run, worktree=d, pre_rebase_sha=pre_sha,
                                  reason=inventory.unresolvable_reason)
            resolve_all(llm, run, worktree=d, key="k", inventory=inventory)
        assert exc.value.kind == "rebase-conflict"
        assert _sh(d, "rev-parse", "HEAD").stdout.strip() == pre_sha
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
        assert len(llm.calls) == 0
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_tree_marker_sweep():
    """Matrix row 19: a marker planted outside inventory.files aborts and restores.

    A `<<<<<<< HEAD` line in a COMMITTED file that is not in the conflict set must
    cause abort_and_restore() after rebase --continue, not silently pass.
    """
    d, key, branch = _make_marker_planted_repo()
    pre_head = subprocess.run(
        ["git", "-C", d, "rev-parse", "HEAD"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    llm = _WritingLlm(d, "feature.txt", content="merged content\n")
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert "conflict markers survive" in exc.value.detail
        assert _sh_rev(d, "HEAD") == pre_head
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        _cleanup_marker_repo(key, branch, d)


# ── Phase 5f: verdict tests ────────────────────────────────────────────────────

def _make_review_run(sha="abc1234"):
    def run(*args, check=True):
        if args[0] == "rev-parse":
            return sha
        return ""
    return run


def _cleanup_verdict(key: str, sha: str) -> None:
    for p in [f"/tmp/postplan-conflict-sha-{key}.txt",
              f"/tmp/postplan-conflict-verdict-{key}-{sha}.ok"]:
        if os.path.exists(p):
            os.unlink(p)


def test_verdict_clean(tmp_path):
    """Reply CONFLICT-REVIEW=CLEAN writes that exact literal as verdict line 1 plus SHA sidecar."""
    key = f"test-{uuid.uuid4().hex[:8]}"
    sha = "deadbeef"
    llm = _StubLlm(["CONFLICT-REVIEW=CLEAN\nLooked good.\n"])
    run = _make_review_run(sha)

    verdict = review_resolution(llm, run, worktree=str(tmp_path), key=key,
                                resolved_files=("app/foo.py",), proof_out="TREE-EQUIVALENT\n")
    assert verdict == "CONFLICT-REVIEW=CLEAN"
    verdict_file = f"/tmp/postplan-conflict-verdict-{key}-{sha}.ok"
    assert os.path.exists(verdict_file)
    assert open(verdict_file).readline().rstrip() == "CONFLICT-REVIEW=CLEAN"
    assert open(f"/tmp/postplan-conflict-sha-{key}.txt").read().strip() == sha
    _cleanup_verdict(key, sha)


def test_verdict_found_problem(tmp_path):
    """FOUND-PROBLEM is recorded verbatim, never silently upgraded."""
    key = f"test-{uuid.uuid4().hex[:8]}"
    sha = "cafebabe"
    llm = _StubLlm(["CONFLICT-REVIEW=FOUND-PROBLEM\ndropped hunk\n"])
    run = _make_review_run(sha)

    verdict = review_resolution(llm, run, worktree=str(tmp_path), key=key,
                                resolved_files=(), proof_out="")
    assert verdict == "CONFLICT-REVIEW=FOUND-PROBLEM"
    assert open(f"/tmp/postplan-conflict-verdict-{key}-{sha}.ok").readline().rstrip() == "CONFLICT-REVIEW=FOUND-PROBLEM"
    _cleanup_verdict(key, sha)


def test_verdict_malformed_is_absent(tmp_path):
    """Leading space, trailing suffix, and empty reply each record CONFLICT-REVIEW=ABSENT."""
    sha = "00000000"
    for bad in [" CONFLICT-REVIEW=CLEAN", "CONFLICT-REVIEW=CLEAN extra", "", "other"]:
        key = f"test-{uuid.uuid4().hex[:8]}"
        llm = _StubLlm([bad])
        run = _make_review_run(sha)
        verdict = review_resolution(llm, run, worktree=str(tmp_path), key=key,
                                    resolved_files=(), proof_out="")
        assert verdict == "CONFLICT-REVIEW=ABSENT", f"bad={bad!r}"
        _cleanup_verdict(key, sha)


def test_verdict_outage_is_absent(tmp_path):
    """call_tooled raising records ABSENT rather than propagating."""
    key = f"test-{uuid.uuid4().hex[:8]}"
    sha = "11111111"
    llm = _StubLlm([HarnessError("llm-tooled-error", "outage")])
    run = _make_review_run(sha)
    verdict = review_resolution(llm, run, worktree=str(tmp_path), key=key,
                                resolved_files=(), proof_out="")
    assert verdict == "CONFLICT-REVIEW=ABSENT"
    _cleanup_verdict(key, sha)


def test_reviewer_is_read_only(tmp_path):
    """Reviewer kwargs carry allowed_tools == ('Read',) with Write and Edit denied."""
    key = f"test-{uuid.uuid4().hex[:8]}"
    sha = "22222222"
    llm = _StubLlm(["CONFLICT-REVIEW=CLEAN\n"])
    run = _make_review_run(sha)
    review_resolution(llm, run, worktree=str(tmp_path), key=key,
                      resolved_files=(), proof_out="")
    assert llm.calls[0]["allowed_tools"] == ("Read",)
    assert "Write" in llm.calls[0]["denied_tools"]
    assert "Edit" in llm.calls[0]["denied_tools"]
    _cleanup_verdict(key, sha)


def test_flag_precedes_review(tmp_path):
    """The conflict flag file exists before review_resolution is called."""
    from harness.armable import conflict_flag_path
    key = f"test-{uuid.uuid4().hex[:8]}"
    branch = key
    flag = conflict_flag_path(branch)

    # Simulate _record_resolution writing the flag before calling review_resolution
    open(flag, "w").close()
    assert os.path.exists(flag)

    sha = "33333333"
    llm = _StubLlm(["CONFLICT-REVIEW=CLEAN\n"])
    run = _make_review_run(sha)
    review_resolution(llm, run, worktree=str(tmp_path), key=key,
                      resolved_files=(), proof_out="")
    assert os.path.exists(flag)
    os.unlink(flag)
    _cleanup_verdict(key, sha)


def test_purge_spares_flag(tmp_path):
    """purge_verdict_artifacts removes sidecar and verdict files but leaves the flag file."""
    from harness.armable import conflict_flag_path
    key = f"test-{uuid.uuid4().hex[:8]}"
    sha = "44444444"

    sidecar = f"/tmp/postplan-conflict-sha-{key}.txt"
    verdict_file = f"/tmp/postplan-conflict-verdict-{key}-{sha}.ok"
    flag = conflict_flag_path(key)

    open(sidecar, "w").write(sha)
    open(verdict_file, "w").write("CONFLICT-REVIEW=CLEAN\n")
    open(flag, "w").close()

    purge_verdict_artifacts(key)

    assert not os.path.exists(sidecar)
    assert not os.path.exists(verdict_file)
    assert os.path.exists(flag)
    os.unlink(flag)
