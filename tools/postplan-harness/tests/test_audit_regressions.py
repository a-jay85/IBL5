"""Scratch-repo replays of the 2026-10-06 post-plan audit-log failures.

Each test drives the real LiveGit.rebase_onto() end to end: the merge, the conflict
inventory, the resolver, the merge commit, the marker sweep, the real lostwork.sh
proof and the manifest. Each docstring names the audit log it reproduces.
"""
import os
import shutil
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError
from test_gitad_stacked_rebase import _COLLAPSE, _LOSTWORK, _cleanup_full, _rev, _sh


class _MultiWritingLlm:
    """Stub LLM: writes resolutions[path] on each conflict-resolve:<path> call."""

    def __init__(self, worktree, resolutions, review_reply="CONFLICT-REVIEW=CLEAN\n"):
        self.calls = []
        self._worktree = worktree
        self._resolutions = resolutions
        self._review_reply = review_reply

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append(purpose)
        if purpose == "conflict-review":
            return self._review_reply
        path = purpose.split(":", 1)[1]
        with open(os.path.join(self._worktree, path), "w") as fh:
            fh.write(self._resolutions[path])
        return "RESOLVED"


def _apply(d, files, message):
    for path, content in files.items():
        full = os.path.join(d, path)
        if content is None:
            _sh(d, "rm", "-q", "--", path)
            continue
        os.makedirs(os.path.dirname(full) or d, exist_ok=True)
        with open(full, "w") as fh:
            fh.write(content)
        _sh(d, "add", "--", path)
    _sh(d, "commit", "-q", "-m", message)
    return _rev(d, "HEAD")


def _make_audit_repo(name, *, base_files, branch_commits, master_commits):
    """Returns (d, branch, branch_shas, master_sha). No iblBase, so --onto never runs."""
    branch = f"feat-{name}"
    d = tempfile.mkdtemp(prefix="postplan-audit-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    _apply(d, base_files, "base")
    scripts = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-q", "-m", "chore: proof scripts")
    fork = _rev(d, "HEAD")

    master_sha = fork
    for i, files in enumerate(master_commits):
        master_sha = _apply(d, files, f"chore: master {i}")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    _sh(d, "checkout", "-q", "-b", branch, fork)
    branch_shas = [_apply(d, files, f"feat: branch {i}")
                   for i, files in enumerate(branch_commits)]
    return d, branch, branch_shas, master_sha


def _merge_head(d):
    return _sh(d, "rev-parse", "-q", "--verify", "MERGE_HEAD", check=False).stdout.strip()


def _parents(d):
    return _sh(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]


def _notes(g):
    return open(g.last_conflict_resolution.notes_path).read()


_SCRIPT = "bin/migrate-backlog-to-issues"
_SCRIPT_BASE = "#!/usr/bin/env bash\necho one\necho two\necho three\n"
_SCRIPT_MASTER = "#!/usr/bin/env bash\necho one\necho TWO\necho three\n"
_TOOLS_DOC = "# Tools\n\nNothing here yet.\n"


def test_audit_modify_delete_branch_deletion_is_kept():
    """Reproduces live-harness-cleanup-2026-10-20261006-205229-26141: the branch
    deleted bin/migrate-backlog-to-issues while master edited it (stages [1, 2])."""
    d, branch, _shas, master_sha = _make_audit_repo(
        "md-kept",
        base_files={_SCRIPT: _SCRIPT_BASE, "docs/tools.md": _TOOLS_DOC},
        branch_commits=[{_SCRIPT: None}],
        master_commits=[{_SCRIPT: _SCRIPT_MASTER}],
    )
    pre = _rev(d, "HEAD")
    llm = _MultiWritingLlm(d, {})
    try:
        g = LiveGit(d, llm=llm)
        g.rebase_onto()
        assert _sh(d, "cat-file", "-e", f"HEAD:{_SCRIPT}", check=False).returncode != 0
        assert llm.calls == ["conflict-review"]
        assert g.last_conflict_resolution.resolved_files == (_SCRIPT,)
        assert _parents(d) == [pre, master_sha]
        assert "TREE-EQUIVALENT" in _notes(g)
    finally:
        _cleanup_full(branch, branch, d)


def test_audit_modify_delete_blocked_by_new_master_reference():
    """Negative path of the same class: master newly cites the deleted script."""
    d, branch, _shas, _master = _make_audit_repo(
        "md-blocked",
        base_files={_SCRIPT: _SCRIPT_BASE, "docs/tools.md": _TOOLS_DOC},
        branch_commits=[{_SCRIPT: None}],
        master_commits=[{_SCRIPT: _SCRIPT_MASTER,
                         "docs/tools.md": _TOOLS_DOC + f"see {_SCRIPT}\n"}],
    )
    pre = _rev(d, "HEAD")
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=_MultiWritingLlm(d, {})).rebase_onto()
        assert f"newly referenced on master: {_SCRIPT} <- docs/tools.md" in exc.value.detail
        assert _rev(d, "HEAD") == pre
        assert _merge_head(d) == ""
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(branch, branch, d)


def test_audit_three_commit_branch_two_conflicting_commits():
    """Reproduces live-css-font-size-scale-20261006-195213-53979 and
    live-records-hub-20261006-175220-44478: rebase --continue failed at stop 2/N."""
    d, branch, shas, _master = _make_audit_repo(
        "three-commit",
        base_files={"a.css": "a1\na2\na3\n", "b.css": "b1\nb2\nb3\n"},
        branch_commits=[{"a.css": "a1\na2-branch\na3\n"},
                        {"b.css": "b1\nb2-branch\nb3\n"},
                        {"c.txt": "c\n"}],
        master_commits=[{"a.css": "a1\na2-master\na3\n", "b.css": "b1\nb2-master\nb3\n"}],
    )
    llm = _MultiWritingLlm(d, {
        "a.css": "a1\na2-branch\na2-master\na3\n",
        "b.css": "b1\nb2-branch\nb2-master\nb3\n",
    })
    try:
        g = LiveGit(d, llm=llm)
        g.rebase_onto()
        resolves = [c for c in llm.calls if c.startswith("conflict-resolve:")]
        assert resolves == ["conflict-resolve:a.css", "conflict-resolve:b.css"]
        assert g.last_conflict_resolution.resolved_files == ("a.css", "b.css")
        for sha in shas:
            assert _sh(d, "merge-base", "--is-ancestor", sha, "HEAD",
                       check=False).returncode == 0
        assert _sh(d, "cat-file", "-e", "HEAD:c.txt", check=False).returncode == 0
        assert "TREE-EQUIVALENT" in _notes(g)
    finally:
        _cleanup_full(branch, branch, d)


_SKILL = ".claude/skills/post-plan/SKILL.md"


def _skill_doc(date, body):
    return f"---\ndescription: d\nlast_verified: {date}\n---\n{body}"


def _last_verified_repo(name):
    return _make_audit_repo(
        name,
        base_files={_SKILL: _skill_doc("2026-09-30", "A1\n")},
        # g.txt keeps the post-merge diff non-empty even when the resolution drops B1,
        # so the proof compares lines instead of reporting nothing compared.
        branch_commits=[{_SKILL: _skill_doc("2026-10-04", "A1\nB1\n"), "g.txt": "G\n"}],
        master_commits=[{_SKILL: _skill_doc("2026-10-05", "A1\nM1\n")}],
    )


def test_audit_last_verified_bumped_on_both_sides():
    """Reproduces live-post-plan-tick-manual-rows-20261006-195213-53998: both sides
    bumped last_verified and the proof flagged the branch's older date as lost."""
    d, branch, _shas, _master = _last_verified_repo("lv-both")
    llm = _MultiWritingLlm(d, {_SKILL: _skill_doc("2026-10-05", "A1\nB1\nM1\n")})
    try:
        g = LiveGit(d, llm=llm)
        g.rebase_onto()
        assert "TREE-EQUIVALENT" in _notes(g)
    finally:
        _cleanup_full(branch, branch, d)


def test_audit_tree_proof_still_fails_when_a_body_line_is_lost():
    """Guard for the Phase 1 exemption: only the date line is admitted."""
    d, branch, _shas, _master = _last_verified_repo("lv-lost")
    pre = _rev(d, "HEAD")
    llm = _MultiWritingLlm(d, {_SKILL: _skill_doc("2026-10-05", "A1\nM1\n")})
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).rebase_onto()
        assert "tree proof failed" in exc.value.detail
        assert f"LOST: {_SKILL}: +B1" in exc.value.detail
        assert _rev(d, "HEAD") == pre
        assert _merge_head(d) == ""
    finally:
        _cleanup_full(branch, branch, d)
