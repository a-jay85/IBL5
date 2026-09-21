"""Live conflict-resolution tests using a genuine call_tooled invocation.

These tests are opt-in because they spin up a real Claude sub-agent session
and consume real tokens. Set:

    POSTPLAN_CONFLICT_LIVE_TEST=1 pytest tests/test_conflict_live.py -v

to run them.

Note: there is no existing opt-in skip pattern in this test suite to copy from;
the module-level pytestmark pattern below is the canonical form for this repo.
"""
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

pytestmark = pytest.mark.skipif(
    not os.environ.get("POSTPLAN_CONFLICT_LIVE_TEST"),
    reason="opt-in: set POSTPLAN_CONFLICT_LIVE_TEST=1 to run genuine call_tooled tests",
)

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


def _sh(d, *args):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t",
    }
    return subprocess.run(
        ["git", "-C", d] + list(args),
        check=True, capture_output=True, text=True, env=env,
    )


def _rev(d, ref):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _make_modify_conflict_repo():
    """Three-stage modify/modify conflict on feature.txt for live call_tooled testing."""
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-live-{suffix}"
    key = branch  # no / or :

    d = tempfile.mkdtemp(prefix="postplan-live-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    open(os.path.join(d, "a.txt"), "w").write("base\n")
    open(os.path.join(d, "feature.txt"), "w").write("base content\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    open(os.path.join(scripts_dir, "lostwork.sh"), "w").write(_LOSTWORK_EQUIV)
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    _sh(d, "checkout", "-b", "parent")
    open(os.path.join(d, "parent.txt"), "w").write("step1\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step1")
    open(os.path.join(d, "parent.txt"), "w").write("step2\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step2")
    parent_tip = _rev(d, "HEAD")

    _sh(d, "checkout", "master")
    _sh(d, "merge", "--squash", "parent")
    _sh(d, "commit", "-m", "squash: parent")
    open(os.path.join(d, "feature.txt"), "w").write(
        "master version: uses the new API format\n"
    )
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: master version")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    _sh(d, "checkout", "-b", branch, parent_tip)
    open(os.path.join(d, "feature.txt"), "w").write(
        "feature version: implements the new feature\n"
    )
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")
    _sh(d, "config", f"branch.{branch}.iblBase", parent_tip)

    return d, key, branch


def test_live_resolve_and_review():
    """Real call_tooled path: Claude resolves a simple modify/modify conflict end-to-end.

    Asserts only the observable contract: resolved=True, auto_resolved=True,
    flag written, verdict is one of the two valid strings.
    """
    from harness.adapters.gitad import LiveGit
    from harness.adapters.llm import ClaudeCli
    from harness.armable import conflict_flag_path, conflict_verdict_for
    from harness.conflict import purge_verdict_artifacts

    d, key, branch = _make_modify_conflict_repo()
    llm = ClaudeCli(worktree=d)
    try:
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True
        assert result.auto_resolved is True
        assert os.path.exists(conflict_flag_path(branch))
        verdict = conflict_verdict_for(branch)
        assert verdict in ("CONFLICT-REVIEW=CLEAN", "CONFLICT-REVIEW=FOUND-PROBLEM"), (
            f"unexpected verdict: {verdict!r}"
        )
    finally:
        purge_verdict_artifacts(key)
        flag = conflict_flag_path(branch)
        if os.path.exists(flag):
            os.unlink(flag)
        for fpath in [
            f"/tmp/postplan-conflict-files-{key}.txt",
            f"/tmp/postplan-conflict-files-{key}-autoresolved.txt",
            f"/tmp/postplan-conflict-resolution-{key}.md",
            f"/tmp/postplan-lostwork-{key}.sh",
            f"/tmp/postplan-collapse-guard-{key}.sh",
            f"/tmp/pr-ready-diff-pre-{key}.patch",
            f"/tmp/pr-ready-diff-post-{key}.patch",
            f"/tmp/pr-ready-numstat-pre-{key}.txt",
            f"/tmp/pr-ready-numstat-post-{key}.txt",
            f"/tmp/pr-ready-collapse-guard-{key}-{key}.meta",
        ]:
            if os.path.exists(fpath):
                os.unlink(fpath)
        for dpath in [
            f"/tmp/postplan-conflict-review-{key}",
            f"/tmp/postplan-conflict-stages-{key}",
        ]:
            if os.path.exists(dpath):
                shutil.rmtree(dpath, ignore_errors=True)
        shutil.rmtree(d, ignore_errors=True)
