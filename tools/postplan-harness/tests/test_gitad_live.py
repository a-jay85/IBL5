import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.adapters.gitad as gitad
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError


@pytest.fixture()
def repo():
    d = tempfile.mkdtemp(prefix="postplan-git-test-")
    def sh(*a):
        subprocess.run(["git", "-C", d, *a], check=True, capture_output=True,
                       env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                            "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    subprocess.run(["git", "-C", d, "config", "user.email", "t@t"], check=True, capture_output=True)
    subprocess.run(["git", "-C", d, "config", "user.name", "t"], check=True, capture_output=True)
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    sh("add", "-A"); sh("commit", "-m", "base")
    return d


def test_dirty_commit_and_changed_files(repo):
    g = LiveGit(repo)
    assert g.branch() == "master"
    assert not g.is_dirty()
    open(os.path.join(repo, "b.php"), "w").write("<?php\n")
    assert g.is_dirty()
    assert g.changed_files(base="HEAD") == ["b.php"]
    sha = g.commit_all("chore: add b")
    assert sha and not g.is_dirty()
    assert g.commit_all("noop") == ""      # nothing staged -> no empty commit


def test_dirty_tree_ships_from_merge_base(repo):
    """post-plan-now fires on a DIRTY worktree: uncommitted + untracked changes
    must appear in diff_vs_base, or every run is a false nothing-to-ship."""
    g = LiveGit(repo)
    open(os.path.join(repo, "new.sh"), "w").write("#!/bin/bash\n")   # untracked
    open(os.path.join(repo, "a.txt"), "a").write("edit\n")            # unstaged
    g.stage_all()
    diff = g.diff_vs_base(base="HEAD")
    assert "new.sh" in diff and "edit" in diff
    assert set(g.changed_files(base="HEAD")) == {"a.txt", "new.sh"}
    assert g.modified_files(base="HEAD") == ["a.txt"]


def test_diff_vs_base_uses_merge_base_not_two_dot(repo):
    """base moving ahead must not reverse-bleed its commits into our diff."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "mine.txt"), "w").write("mine\n")
    g = LiveGit(repo)
    g.stage_all()
    g.commit_all("mine")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "master-only.txt"), "w").write("m\n")
    gm = LiveGit(repo)
    gm.stage_all()
    gm.commit_all("master moved")
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    diff = LiveGit(repo).diff_vs_base(base="master")
    assert "mine.txt" in diff and "master-only.txt" not in diff


def test_rebase_onto_moved_base(repo):
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "mine.txt"), "w").write("mine\n")
    g = LiveGit(repo)
    g.stage_all(); g.commit_all("mine")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "other.txt"), "w").write("m\n")
    gm = LiveGit(repo)
    gm.stage_all(); gm.commit_all("master moved")
    master_tip = gm.head()
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    g.rebase_onto(base="master")
    assert g._merge_base("master") == master_tip     # feature now sits on master tip


def test_rebase_conflict_aborts_and_types_failure(repo):
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "a.txt"), "w").write("feature version\n")
    g = LiveGit(repo)
    g.stage_all(); g.commit_all("feature edit")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "a.txt"), "w").write("master version\n")
    gm = LiveGit(repo)
    gm.stage_all(); gm.commit_all("conflicting master edit")
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    with pytest.raises(HarnessError) as e:
        g.rebase_onto(base="master")
    assert e.value.kind == "rebase-conflict"
    assert not g.is_dirty()                          # abort restored the tree


def test_push_disabled_is_typed_failure(repo):
    g = LiveGit(repo)   # no push_remote configured
    with pytest.raises(HarnessError) as e:
        g.push()
    assert e.value.kind == "push-disabled"


def test_push_after_rebase_uses_force_with_lease(repo):
    """push() must succeed after rebase_onto() rewrites SHAs (non-fast-forward scenario)."""
    bare = tempfile.mkdtemp(prefix="postplan-bare-")
    subprocess.run(["git", "clone", "--bare", repo, bare], check=True, capture_output=True)
    subprocess.run(["git", "-C", repo, "remote", "add", "origin", bare],
                   check=True, capture_output=True)

    # Create feature branch, push it to bare remote
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "feat.txt"), "w").write("feat\n")
    g = LiveGit(repo, push_remote="origin")
    g.stage_all(); g.commit_all("feat")
    subprocess.run(["git", "-C", repo, "push", "origin", "feature"],
                   check=True, capture_output=True)

    # Advance master in the bare repo so rebase is needed
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "other.txt"), "w").write("other\n")
    gm = LiveGit(repo, push_remote="origin")
    gm.stage_all(); gm.commit_all("master moved")
    subprocess.run(["git", "-C", repo, "push", "origin", "master"],
                   check=True, capture_output=True)

    # Back on feature: fetch + rebase rewrites SHAs → plain push would fail
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    subprocess.run(["git", "-C", repo, "fetch", "origin", "master"],
                   check=True, capture_output=True)
    g.rebase_onto(base="origin/master")

    # push() must succeed (--force-with-lease handles the diverged remote)
    g.push()  # raises HarnessError("git", ...) if plain push, passes if --force-with-lease


def _fake_proc(returncode, stdout="", stderr=""):
    class P:
        pass
    p = P()
    p.returncode, p.stdout, p.stderr = returncode, stdout, stderr
    return p


def test_push_gate_denial_is_kind_local_gate(monkeypatch):
    """pre-push-adr-hook denial must not be a generic 'git' failure: the kind is what
    drives the fail-closed exit 3 that skips the ~1M-token skill fallback."""
    monkeypatch.setattr(gitad.subprocess, "run", lambda *a, **k: _fake_proc(
        1, stderr="pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR."))
    with pytest.raises(HarnessError) as e:
        LiveGit("/tmp", push_remote="origin").push()
    assert e.value.kind == "local-gate"


def test_commit_gate_denial_on_stdout_is_detected(monkeypatch):
    """bin/pre-commit-hook runs its checks with 2>&1 and echoes to STDOUT, so
    stderr-only detection would miss every commit-path denial."""
    monkeypatch.setattr(gitad.subprocess, "run", lambda *a, **k: _fake_proc(
        1, stdout="FAIL  .claude/rules/x.md  16374 bytes  cap 16000\nOne or more checks failed:\n"))
    with pytest.raises(HarnessError) as e:
        LiveGit("/tmp")._run("commit", "-m", "x")
    assert e.value.kind == "local-gate"
    assert "One or more checks failed" in e.value.detail


def test_ordinary_git_failure_stays_kind_git(monkeypatch):
    """Negative path: transient/real git errors must still fall back to the skill."""
    monkeypatch.setattr(gitad.subprocess, "run", lambda *a, **k: _fake_proc(
        1, stderr="fatal: could not read from remote repository"))
    with pytest.raises(HarnessError) as e:
        LiveGit("/tmp")._run("fetch", "origin")
    assert e.value.kind == "git"


def test_diff_vs_base_non_utf8_bytes(repo):
    """Regression pin for UnicodeDecodeError: a file containing byte 0x9e must
    produce a str result (with U+FFFD replacements), not raise.

    Fails on master (text=True without errors="replace") and passes after Phase 1."""
    g = LiveGit(repo)
    bin_path = os.path.join(repo, "binary.bin")
    with open(bin_path, "wb") as f:
        f.write(b"binary\x9ebytes")
    subprocess.run(["git", "-C", repo, "add", "binary.bin"], check=True,
                   capture_output=True)
    subprocess.run(["git", "-C", repo, "commit", "-m", "add binary file"],
                   check=True, capture_output=True,
                   env={**os.environ,
                        "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})
    result = g.diff_vs_base(base="HEAD~1")
    assert isinstance(result, str), "diff_vs_base must return str, not raise"
    assert "�" in result, "U+FFFD replacement character must appear for byte 0x9e"


def _install_reject_hook(repo, body):
    """Write an executable pre-commit hook into the THROWAWAY fixture repo only."""
    hooks = os.path.join(repo, ".git", "hooks")
    os.makedirs(hooks, exist_ok=True)
    hook = os.path.join(hooks, "pre-commit")
    assert hook.startswith(tempfile.gettempdir()), f"refusing to write a hook at {hook}"
    with open(hook, "w") as fh:
        fh.write(body)
    os.chmod(hook, 0o755)
    return hook


def test_commit_rejected_by_an_unenumerated_hook_message_is_local_gate(repo):
    """THE Phase 1 defect: a hook message absent from _LOCAL_GATE_MARKERS is still a gate.

    `gofmt: unformatted Go files` is bin/pre-commit-hook's real wording and matches none
    of the four markers, so on master this commit types "git" -> exit 1 -> the 16-minute
    zombie skill session. The hook below lives in the THROWAWAY fixture repo.
    """
    _install_reject_hook(repo, "#!/bin/sh\n"
                               "echo 'gofmt: unformatted Go files (run gofmt -w):'\n"
                               "exit 1\n")
    g = LiveGit(repo)
    with open(os.path.join(repo, "b.txt"), "w") as fh:
        fh.write("blocked\n")
    with pytest.raises(HarnessError) as ei:
        g.commit_all("chore: should be rejected")
    assert ei.value.kind == "local-gate", f"kind was {ei.value.kind!r}"
    assert "gofmt" in ei.value.detail, "the gate's own reason must survive"


def test_commit_gate_detail_is_never_empty(repo):
    """Negative path -- a silent hook still produces a non-empty detail.

    A hook that prints nothing (an `exit 1` and no output) would give an empty
    stderr AND stdout; without the synthesized fallback, verdict_line would print
    `kind=local-gate: no detail` and the operator would have nothing to act on.
    """
    _install_reject_hook(repo, "#!/bin/sh\nexit 1\n")
    g = LiveGit(repo)
    with open(os.path.join(repo, "c.txt"), "w") as fh:
        fh.write("silent\n")
    with pytest.raises(HarnessError) as ei:
        g.commit_all("chore: silently rejected")
    assert ei.value.kind == "local-gate"
    assert ei.value.detail.strip(), "detail must never be empty"


def test_commit_all_still_returns_a_sha_when_no_hook_rejects(repo):
    """Preserve-current-behaviour -- the happy path is unchanged by the retyping."""
    g = LiveGit(repo)
    with open(os.path.join(repo, "d.txt"), "w") as fh:
        fh.write("ok\n")
    sha = g.commit_all("chore: accepted")
    assert len(sha) == 40, f"expected a full sha, got {sha!r}"
