"""A merge that bin/postplan-fix --headless committed locally is proved and reviewed by the
harness before it can be pushed. Real throwaway git repos; the only fake is the LLM."""
import glob
import json
import os
import shutil
import subprocess
import sys
import uuid

import pytest

_HARNESS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, _HARNESS)

from harness import premerge  # noqa: E402
from harness.adapters.gitad import LiveGit, lostwork_pre_path  # noqa: E402
from harness.armable import (  # noqa: E402
    FLAG_CARRIED, classify_conflict_flag, conflict_flag_path, conflict_verdict_for,
)
from harness.conflict import verdict_ok_path  # noqa: E402
from harness.state import HarnessError  # noqa: E402

_REPO_ROOT = os.path.dirname(os.path.dirname(_HARNESS))
_LOSTWORK = os.path.join(_REPO_ROOT, ".claude", "review-shared", "scripts", "lostwork.sh")
_ENV = {
    **os.environ,
    "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
    "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t",
}

BRANCH_LINE = "BRANCH_ONLY_LINE"


def _git(d, *args, check=True):
    return subprocess.run(["git", "-C", d, *args], check=check, capture_output=True,
                          text=True, env=_ENV)


def _rev(d, ref):
    return _git(d, "rev-parse", ref).stdout.strip()


def _write(d, name, text):
    with open(os.path.join(d, name), "w") as fh:
        fh.write(text)


class FakeLlm:
    def __init__(self, verdict="CONFLICT-REVIEW=CLEAN"):
        self.verdict = verdict
        self.calls = []

    def call_tooled(self, purpose, model, prompt, **kwargs):
        self.calls.append(purpose)
        return f"{self.verdict}\nlooks fine\n"


class Repo:
    def __init__(self, d, origin, branch, pre_sha):
        self.d, self.origin, self.branch, self.pre_sha = d, origin, branch, pre_sha
        self.key = branch.replace("/", "-")

    def merge_by_hand(self, text):
        """Merge origin/master (conflicts on f.txt), resolve with `text`, commit."""
        proc = _git(self.d, "merge", "--no-edit", "origin/master", check=False)
        assert proc.returncode != 0, "scenario must conflict"
        _write(self.d, "f.txt", text)
        _git(self.d, "add", "f.txt")
        _git(self.d, "commit", "--no-edit")
        return _rev(self.d, "HEAD")

    def rebase_by_hand(self, text):
        """What bin/postplan-fix --headless leaves: the branch squashed to one commit on
        top of origin/master, f.txt resolved to `text`. Linear, one parent."""
        _git(self.d, "reset", "-q", "--hard", "origin/master")
        _git(self.d, "checkout", self.pre_sha, "--", "own.txt")
        _write(self.d, "f.txt", text)
        _git(self.d, "add", "-A")
        _git(self.d, "commit", "-q", "-m", "branch: add line")
        return _rev(self.d, "HEAD")

    def record(self, merge_sha=None):
        return premerge.record(self.d, self.branch, self.pre_sha, ("f.txt",))

    def advance_master(self, text):
        _git(self.d, "checkout", "master")
        _write(self.d, "f.txt", text)
        _git(self.d, "commit", "-am", "master: newer")
        _git(self.d, "push", "origin", "master")
        _git(self.d, "checkout", self.branch)


@pytest.fixture
def make_repo(tmp_path):
    made = []

    def build(with_lostwork=True):
        branch = f"t-premerge-{uuid.uuid4().hex[:8]}"
        origin = str(tmp_path / f"origin-{branch}.git")
        d = str(tmp_path / f"work-{branch}")
        subprocess.run(["git", "init", "-q", "--bare", "-b", "master", origin], check=True)
        subprocess.run(["git", "init", "-q", "-b", "master", d], check=True)
        _git(d, "remote", "add", "origin", origin)
        _write(d, "f.txt", "line1\nline2\nline3\n")
        if with_lostwork:
            dest = os.path.join(d, ".claude", "review-shared", "scripts")
            os.makedirs(dest)
            shutil.copy(_LOSTWORK, dest)
        _git(d, "add", "-A")
        _git(d, "commit", "-m", "base")
        _git(d, "push", "origin", "master")
        _git(d, "checkout", "-b", branch)
        _write(d, "f.txt", f"line1\n{BRANCH_LINE}\nline3\n")
        _write(d, "own.txt", "branch-only file\n")
        _git(d, "add", "own.txt")
        _git(d, "commit", "-am", "branch: add line")
        _git(d, "push", "origin", branch)
        pre_sha = _rev(d, "HEAD")
        _git(d, "checkout", "master")
        _write(d, "f.txt", "line1\nMASTER_LINE\nline3\n")
        _git(d, "commit", "-am", "master: change")
        _git(d, "push", "origin", "master")
        _git(d, "checkout", branch)
        repo = Repo(d, origin, branch, pre_sha)
        made.append(repo)
        return repo

    yield build

    for repo in made:
        key = repo.key
        paths = [conflict_flag_path(repo.branch), premerge.manifest_path(key),
                 lostwork_pre_path(key), f"/tmp/postplan-lostwork-{key}.sh",
                 f"/tmp/postplan-conflict-sha-{key}.txt",
                 f"/tmp/postplan-conflict-files-{key}.txt",
                 f"/tmp/postplan-conflict-files-{key}-autoresolved.txt",
                 f"/tmp/postplan-conflict-resolution-{key}.md",
                 *glob.glob(f"/tmp/postplan-conflict-verdict-{key}-*.ok"),
                 *glob.glob(f"/tmp/pr-ready-*-{key}.*")]
        for p in paths:
            if os.path.exists(p):
                os.unlink(p)
        shutil.rmtree(f"/tmp/postplan-conflict-review-{key}", ignore_errors=True)


CLEAN = f"line1\nMASTER_LINE\n{BRANCH_LINE}\nline3\n"
DROPPED = "line1\nMASTER_LINE\nline3\n"


def test_premerged_dropped_branch_line_fails_proof(make_repo):
    r = make_repo()
    merge_sha = r.rebase_by_hand(DROPPED)
    r.record()
    with pytest.raises(HarnessError) as ei:
        LiveGit(r.d, llm=FakeLlm()).rebase_onto("origin/master")
    assert ei.value.kind == "rebase-conflict"
    assert ei.value.detail.startswith("tree proof failed:")
    assert "LOST:" in ei.value.detail and "f.txt" in ei.value.detail
    assert _rev(r.d, "HEAD") == merge_sha
    remote = subprocess.run(["git", "ls-remote", r.origin, r.branch], check=True,
                            capture_output=True, text=True).stdout.split()[0]
    assert remote == r.pre_sha
    assert merge_sha in open(conflict_flag_path(r.branch)).read()


def test_premerged_clean_resolution_passes_and_flags(make_repo):
    r = make_repo()
    merge_sha = r.rebase_by_hand(CLEAN)
    r.record()
    flag = conflict_flag_path(r.branch)
    # premerge.record writes the flag itself; the harness's later write must not mask a skip.
    assert merge_sha in open(flag).read()
    LiveGit(r.d, llm=None).rebase_onto("origin/master")
    state, _ = classify_conflict_flag(
        flag, "/nonexistent-notes",
        lambda sha: _git(r.d, "merge-base", "--is-ancestor", sha, "HEAD",
                         check=False).returncode == 0,
        lambda floor: False)
    assert state == FLAG_CARRIED
    assert merge_sha in open(flag).read()
    assert conflict_verdict_for(r.branch) is None


def test_premerged_review_runs_on_clean_resolution(make_repo):
    r = make_repo()
    merge_sha = r.rebase_by_hand(CLEAN)
    r.record()
    llm = FakeLlm()
    LiveGit(r.d, llm=llm).rebase_onto("origin/master")
    assert llm.calls == ["conflict-review"]
    listed = open(f"/tmp/postplan-conflict-review-{r.key}/resolved-files.txt").read()
    assert listed.split() == ["f.txt"]
    verdict = open(verdict_ok_path(r.key, merge_sha)).read()
    assert verdict.splitlines()[0] == "CONFLICT-REVIEW=CLEAN"


def test_premerged_manifest_ignored_when_head_moved(make_repo):
    r = make_repo()
    r.rebase_by_hand(CLEAN)
    r.record()
    _write(r.d, "g.txt", "later\n")
    _git(r.d, "add", "g.txt")
    _git(r.d, "commit", "-m", "later commit")
    llm = FakeLlm()
    LiveGit(r.d, llm=llm).rebase_onto("origin/master")
    expected = _git(r.d, "diff", "origin/master...HEAD").stdout
    assert open(lostwork_pre_path(r.key)).read() == expected
    assert llm.calls == []


def test_premerged_conflict_with_newer_master_fails_closed(make_repo):
    r = make_repo()
    merge_sha = r.rebase_by_hand(CLEAN)
    r.record()
    r.advance_master("line1\nMASTER_V2\nline3\n")
    llm = FakeLlm()
    with pytest.raises(HarnessError) as ei:
        LiveGit(r.d, llm=llm).rebase_onto("origin/master")
    assert ei.value.kind == "rebase-conflict"
    assert "premerged head is stale: origin/master moved after the fix" in ei.value.detail
    assert _rev(r.d, "HEAD") == merge_sha
    assert not os.path.exists(os.path.join(r.d, ".git", "MERGE_HEAD"))
    assert llm.calls == []


def test_premerged_missing_lostwork_fails_closed(make_repo):
    r = make_repo(with_lostwork=False)
    r.rebase_by_hand(CLEAN)
    r.record()
    with pytest.raises(HarnessError) as ei:
        LiveGit(r.d, llm=FakeLlm()).rebase_onto("origin/master")
    assert ei.value.kind == "rebase-conflict"
    assert "lostwork.sh missing" in ei.value.detail


def test_premerged_head_with_merge_commit_fails_closed(make_repo):
    """A manifest written by the old merge-based script binds a head that carries a merge."""
    r = make_repo()
    merge_sha = r.merge_by_hand(CLEAN)
    r.record()
    llm = FakeLlm()
    with pytest.raises(HarnessError) as ei:
        LiveGit(r.d, llm=llm).rebase_onto("origin/master")
    assert ei.value.kind == "rebase-conflict"
    assert "premerged head carries a merge commit; rerun bin/postplan-fix --headless" \
        in ei.value.detail
    assert _rev(r.d, "HEAD") == merge_sha
    assert llm.calls == []


def test_premerged_linear_head_runs_proof(make_repo, monkeypatch):
    r = make_repo()
    r.rebase_by_hand(CLEAN)
    r.record()
    calls = []

    def fake_prove(self, lostwork_path, key):
        calls.append(key)
        return True, "ok"

    monkeypatch.setattr(LiveGit, "_prove_tree_equivalent", fake_prove)
    LiveGit(r.d, llm=None).rebase_onto("origin/master")
    assert calls == [r.key]


def test_premerge_record_accepts_rebased_head(make_repo):
    r = make_repo()
    head = r.rebase_by_hand(CLEAN)
    proc = _cli(r, "f.txt\n")
    assert proc.returncode == 0, proc.stderr
    data = json.load(open(premerge.manifest_path(r.key)))
    assert data["sha"] == head and data["pre_sha"] == r.pre_sha


def _cli(r, files_text, *extra, pre_sha=None):
    files = os.path.join(os.path.dirname(r.d), f"files-{r.key}.txt")
    with open(files, "w") as fh:
        fh.write(files_text)
    return subprocess.run(
        [sys.executable, "-m", "harness.premerge", "record", "--worktree", r.d,
         "--branch", r.branch, "--pre-sha", pre_sha or r.pre_sha,
         "--files-from", files, *extra],
        cwd=_HARNESS, env={**os.environ, "PYTHONPATH": _HARNESS},
        capture_output=True, text=True)


def _nothing_written(r):
    return (not os.path.exists(premerge.manifest_path(r.key))
            and not os.path.exists(conflict_flag_path(r.branch)))


def test_premerge_record_rejects_non_merge_head(make_repo):
    r = make_repo()
    proc = _cli(r, "f.txt\n")
    assert proc.returncode == 3
    assert "premerge: HEAD is not a rebased commit or a two-parent merge" in proc.stderr
    assert _nothing_written(r)


def test_premerge_record_rejects_wrong_first_parent(make_repo):
    r = make_repo()
    r.merge_by_hand(CLEAN)
    proc = _cli(r, "f.txt\n", pre_sha=_rev(r.d, "origin/master"))
    assert proc.returncode == 3
    assert "first parent is not --pre-sha" in proc.stderr
    assert _nothing_written(r)


def test_premerge_record_rejects_empty_files(make_repo):
    r = make_repo()
    r.merge_by_hand(CLEAN)
    proc = _cli(r, "\n\n")
    assert proc.returncode == 3
    assert "no conflicted files" in proc.stderr
    assert _nothing_written(r)


def test_premerge_record_rejects_unknown_flag(make_repo):
    r = make_repo()
    r.merge_by_hand(CLEAN)
    proc = _cli(r, "f.txt\n", "--bogus")
    assert proc.returncode == 2
    assert _nothing_written(r)


def test_premerge_record_writes_manifest_and_flag(make_repo):
    r = make_repo()
    merge_sha = r.merge_by_hand(CLEAN)
    proc = _cli(r, "f.txt\n")
    assert proc.returncode == 0, proc.stderr
    data = json.load(open(premerge.manifest_path(r.key)))
    assert data == {"schema_version": 1, "sha": merge_sha, "pre_sha": r.pre_sha,
                    "files": ["f.txt"]}
    assert merge_sha in open(conflict_flag_path(r.branch)).read()


def test_premerge_load_ignores_malformed_manifest(make_repo, capsys):
    r = make_repo()
    with open(premerge.manifest_path(r.key), "w") as fh:
        fh.write('{"schema_version": 2}')
    assert premerge.load(r.key) is None
    assert "ignoring malformed manifest" in capsys.readouterr().err


@pytest.mark.parametrize("base", ["--upload-pack=x", "master", "origin/..", "origin/a..b", ""])
def test_record_rejects_non_origin_base(tmp_path, base):
    """--base is an origin/<branch> ref only: no option-shaped or range-shaped value."""
    with pytest.raises(premerge.PremergeError, match="origin/<branch>"):
        premerge.record(str(tmp_path), "feat", "a" * 40, ("f.txt",),
                        tmp_dir=str(tmp_path), base=base)
