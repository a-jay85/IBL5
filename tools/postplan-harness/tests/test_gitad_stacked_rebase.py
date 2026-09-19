import glob
import os
import re
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_LOSTWORK = os.path.join(
    _REPO_ROOT, ".claude", "skills", "pr-ready", "scripts", "lostwork.sh"
)
_COLLAPSE = os.path.join(
    _REPO_ROOT, ".claude", "skills", "pr-ready", "scripts", "collapse-guard.sh"
)


def _sh(d, *args, check=True):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=check, capture_output=True, text=True, env=env
    )


def _rev(d, ref):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _make_squash_repo(
    suffix=None,
    feature_branch=None,
    include_lostwork=True,
    lostwork_script=None,
    include_collapse_guard=True,
):
    """Build a squash-trap fixture. Returns (d, parent_tip, master_sha, key, branch)."""
    if suffix is None:
        suffix = uuid.uuid4().hex[:8]
    if feature_branch is None:
        feature_branch = f"feature-{suffix}"
    key = feature_branch.replace("/", "-")

    d = tempfile.mkdtemp(prefix="postplan-sq-test-")

    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    # Step 1: base commit
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    # Step 2: commit real scripts so git show <master_sha>:<path> exercises them
    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    if include_lostwork:
        if lostwork_script is not None:
            open(os.path.join(scripts_dir, "lostwork.sh"), "w").write(lostwork_script)
        else:
            shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    if include_collapse_guard:
        shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    # Step 3: parent branch with two commits so the squash-trap fires.
    # P1 adds parent.txt at "step1"; P2 rewrites it to "step2".
    # The squash nets parent.txt="step2", but replaying P1 against that
    # squash sees base=<absent>, ours="step2", theirs="step1" -> ADD/ADD conflict.
    _sh(d, "checkout", "-b", "parent")
    open(os.path.join(d, "parent.txt"), "w").write("step1\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step1")
    open(os.path.join(d, "parent.txt"), "w").write("step2\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step2")
    parent_tip = _rev(d, "HEAD")

    # Step 4: squash merge parent into master
    _sh(d, "checkout", "master")
    _sh(d, "merge", "--squash", "parent")
    _sh(d, "commit", "-m", "squash: parent")
    master_sha = _rev(d, "HEAD")

    # Step 5: feature branch from parent_tip
    _sh(d, "checkout", "-b", feature_branch, parent_tip)
    open(os.path.join(d, "feature.txt"), "w").write("feature content\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")

    # Step 6: wire origin/master ref and iblBase config
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "config", f"branch.{feature_branch}.iblBase", parent_tip)

    # Step 7: ensure HEAD is on the feature branch
    _sh(d, "checkout", feature_branch)
    return d, parent_tip, master_sha, key, feature_branch


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


@pytest.fixture()
def squash_repo():
    d, parent_tip, master_sha, key, branch = _make_squash_repo()
    yield d, parent_tip, master_sha, key, branch
    _cleanup_tmp(key)
    shutil.rmtree(d, ignore_errors=True)


def test_plain_rebase_conflicts_on_the_squash_fixture(squash_repo):
    """Characterization: plain rebase_onto raises rebase-conflict and leaves a clean tree."""
    d, parent_tip, master_sha, key, branch = squash_repo
    g = LiveGit(d)
    with pytest.raises(HarnessError) as exc:
        g.rebase_onto()
    assert exc.value.kind == "rebase-conflict"
    assert not g.is_dirty()
    assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    assert not os.path.exists(os.path.join(d, ".git", "rebase-apply"))


def test_stacked_conflict_auto_resolves_and_proves_tree_equivalent(squash_repo):
    """Happy path: resolved=True, base_sha == parent_tip, only the feature commit above master."""
    d, parent_tip, master_sha, key, branch = squash_repo
    g = LiveGit(d)
    pre_head = g.head()
    result = g.autoresolve_stacked_rebase()
    assert result.resolved is True
    assert result.reason == ""
    assert result.base_sha == parent_tip
    assert len(result.post_resolution_sha) == 40
    assert result.post_resolution_sha != pre_head
    log_out = _sh(d, "log", "--format=%s", "origin/master..HEAD").stdout.strip()
    assert log_out == "feat: feature work"


def test_pre_side_patch_is_captured_against_ibl_base(squash_repo):
    """Pre-side patch uses iblBase, not origin/master, so TREE-EQUIVALENT does not false-diverge."""
    d, parent_tip, master_sha, key, branch = squash_repo
    g = LiveGit(d)
    # Capture expected pre-side before autoresolve moves HEAD
    expected_pre = subprocess.run(
        ["git", "-C", d, "diff", f"{parent_tip}...HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout
    result = g.autoresolve_stacked_rebase()
    assert result.resolved is True
    pre_patch_path = f"/tmp/pr-ready-diff-pre-{key}.patch"
    assert os.path.exists(pre_patch_path)
    assert open(pre_patch_path).read() == expected_pre


def test_resolution_keeps_both_sides_of_the_tree(squash_repo):
    """After resolution, a.txt, parent.txt and feature.txt all exist with expected contents."""
    d, parent_tip, master_sha, key, branch = squash_repo
    g = LiveGit(d)
    g.autoresolve_stacked_rebase()
    assert open(os.path.join(d, "a.txt")).read() == "base\n"
    assert open(os.path.join(d, "parent.txt")).read() == "step2\n"
    assert open(os.path.join(d, "feature.txt")).read() == "feature content\n"


def test_diverged_proof_declines_and_writes_no_manifest():
    """A proof that outputs TREE DIVERGED and exits 0 is still a decline -- the gate is conjunctive."""
    DIVERGED_STUB = (
        "#!/usr/bin/env bash\n"
        "echo 'TREE DIVERGED -- inspect before pushing'\n"
        "exit 0\n"
    )
    d, parent_tip, master_sha, key, branch = _make_squash_repo(lostwork_script=DIVERGED_STUB)
    manifest = f"/tmp/postplan-conflict-files-{key}.txt"
    if os.path.exists(manifest):
        os.unlink(manifest)
    try:
        result = LiveGit(d).autoresolve_stacked_rebase()
        assert result.resolved is False
        assert "TREE DIVERGED" in result.reason
        assert not os.path.exists(manifest)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_missing_proof_script_declines():
    """If lostwork.sh is absent from master, the resolver declines loudly."""
    d, parent_tip, master_sha, key, branch = _make_squash_repo(include_lostwork=False)
    manifest = f"/tmp/postplan-conflict-files-{key}.txt"
    notes = f"/tmp/postplan-conflict-resolution-{key}.md"
    for p in (manifest, notes):
        if os.path.exists(p):
            os.unlink(p)
    try:
        result = LiveGit(d).autoresolve_stacked_rebase()
        assert result.resolved is False
        assert not os.path.exists(manifest)
        assert not os.path.exists(notes)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_onto_rebase_that_still_conflicts_declines_and_leaves_history_untouched():
    """A genuine content conflict after --onto: declined, history untouched, tree clean."""
    d, parent_tip, master_sha, key, branch = _make_squash_repo()
    try:
        g = LiveGit(d)
        pre_head = g.head()
        # Add a conflicting commit on master: feature.txt with different content
        _sh(d, "checkout", "master")
        open(os.path.join(d, "feature.txt"), "w").write("master version\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: conflict seed")
        new_master = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", new_master)
        _sh(d, "checkout", branch)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert "still conflicts" in result.reason
        assert g.head() == pre_head
        assert not g.is_dirty()
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_tmp_state_shape_matches_what_the_reviewer_reads():
    """Branch with / in name: paths use safe key, manifest is exactly ['feature.txt']."""
    suffix = uuid.uuid4().hex[:8]
    feature_branch = f"feat/stacked-thing-{suffix}"
    d, parent_tip, master_sha, key, branch = _make_squash_repo(
        suffix=suffix, feature_branch=feature_branch
    )
    try:
        result = LiveGit(d).autoresolve_stacked_rebase()
        assert result.resolved is True
        expected_key = feature_branch.replace("/", "-")
        assert result.manifest_path == f"/tmp/postplan-conflict-files-{expected_key}.txt"
        assert result.notes_path == f"/tmp/postplan-conflict-resolution-{expected_key}.md"
        assert os.path.exists(result.manifest_path)
        assert os.path.exists(result.notes_path)
        manifest_lines = open(result.manifest_path).read().strip().splitlines()
        assert manifest_lines == ["feature.txt"]
        notes_body = open(result.notes_path).read()
        assert parent_tip in notes_body
        assert "TREE-EQUIVALENT" in notes_body
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_tmp_templates_match_the_skill_engine():
    """The /tmp path templates in gitad.py are byte-identical to those in _phase-2-conflict-resolution.md."""
    gitad_path = os.path.join(
        _REPO_ROOT, "tools", "postplan-harness", "harness", "adapters", "gitad.py"
    )
    skill_path = os.path.join(
        _REPO_ROOT, ".claude", "skills", "post-plan", "_phase-2-conflict-resolution.md"
    )
    gitad_content = open(gitad_path).read()
    skill_content = open(skill_path).read()
    # Extract /tmp/postplan-conflict-* prefix strings (everything before {key} or <KEY>)
    gitad_files_prefix = re.search(r'(/tmp/postplan-conflict-files-)', gitad_content)
    gitad_notes_prefix = re.search(r'(/tmp/postplan-conflict-resolution-)', gitad_content)
    skill_files_prefix = re.search(r'(/tmp/postplan-conflict-files-)', skill_content)
    skill_notes_prefix = re.search(r'(/tmp/postplan-conflict-resolution-)', skill_content)
    assert gitad_files_prefix is not None, "gitad.py missing /tmp/postplan-conflict-files- template"
    assert gitad_notes_prefix is not None, "gitad.py missing /tmp/postplan-conflict-resolution- template"
    assert skill_files_prefix is not None, "_phase-2-conflict-resolution.md missing files template"
    assert skill_notes_prefix is not None, "_phase-2-conflict-resolution.md missing notes template"
    assert gitad_files_prefix.group(1) == skill_files_prefix.group(1)
    assert gitad_notes_prefix.group(1) == skill_notes_prefix.group(1)


def test_collapse_guard_warn_is_carried_into_the_notes():
    """COLLAPSE-GUARD: WARN reaches collapse_warn and the notes body; absent on a clean run."""
    d, parent_tip, master_sha, key, branch = _make_squash_repo()
    try:
        # First pass: no prior rewrite in reflog -> warn empty
        result1 = LiveGit(d).autoresolve_stacked_rebase()
        assert result1.resolved is True
        assert result1.collapse_warn == ""
        assert "COLLAPSE-GUARD" not in open(result1.notes_path).read()

        # Set up WARN: create a second feature branch, add a commit, reset it, so
        # the reflog has a "reset:" rewrite entry with a partially-lost prior tip.
        suffix2 = uuid.uuid4().hex[:8]
        branch2 = f"feature-warn-{suffix2}"
        key2 = branch2.replace("/", "-")
        _cleanup_tmp(key2)
        # Branch from parent_tip (still has the squash trap since origin/master unchanged)
        _sh(d, "checkout", "-b", branch2, parent_tip)
        open(os.path.join(d, "feature.txt"), "w").write("feature content\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "feat: feature work")
        # Second commit adds extra.txt only (does NOT modify feature.txt)
        open(os.path.join(d, "extra.txt"), "w").write("extra\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "feat: add extra")
        # Reset to first commit: "reset:" in reflog; extra.txt lost, feature.txt survives -> WARN
        _sh(d, "reset", "--hard", "HEAD^")
        _sh(d, "config", f"branch.{branch2}.iblBase", parent_tip)
        result2 = LiveGit(d).autoresolve_stacked_rebase()
        assert result2.resolved is True
        assert "COLLAPSE-GUARD: WARN" in result2.collapse_warn
        assert "COLLAPSE-GUARD" in open(result2.notes_path).read()
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
        for p in glob.glob("/tmp/postplan-conflict-*feature-warn-*.txt"):
            os.unlink(p)
        for p in glob.glob("/tmp/postplan-conflict-*feature-warn-*.md"):
            os.unlink(p)
        for p in glob.glob("/tmp/pr-ready-*feature-warn-*"):
            os.unlink(p)
        for p in glob.glob("/tmp/postplan-*feature-warn-*"):
            if os.path.exists(p):
                os.unlink(p)


def test_onto_conflict_declines_today():
    """Characterization: --onto rebase that conflicts pins the exact reason prefix and leaves no rebase dir."""
    d, parent_tip, master_sha, key, branch = _make_squash_repo()
    try:
        g = LiveGit(d)
        pre_head = g.head()
        # Plant a conflicting change on master so the --onto replay conflicts
        _sh(d, "checkout", "master")
        open(os.path.join(d, "feature.txt"), "w").write("master version\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: conflict seed")
        new_master = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", new_master)
        _sh(d, "checkout", branch)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert result.reason.startswith("--onto rebase still conflicts:")
        assert g.head() == pre_head
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
        assert not os.path.exists(os.path.join(d, ".git", "rebase-apply"))
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


# ── LLM-driven resolution tests (scenarios 1-3, 8) ───────────────────────────

_LOSTWORK_EQUIV = (
    "#!/usr/bin/env bash\n"
    "echo 'TREE-EQUIVALENT'\n"
    "exit 0\n"
)
_LOSTWORK_DIVERGED = (
    "#!/usr/bin/env bash\n"
    "echo 'TREE DIVERGED -- inspect before pushing'\n"
    "exit 0\n"
)
_CONFLICT_MARKERS = "<<<<<<< HEAD\nours\n=======\ntheirs\n>>>>>>> branch\n"


class _WritingLlm:
    """Stub LLM: writes a fixed content to a pre-configured path on each resolve call.

    Avoids parsing the prompt so a prompt reword does not break the test.
    """

    def __init__(self, worktree: str, resolve_path: str, *,
                 content: str = "merged content\n",
                 review_reply: str = "CONFLICT-REVIEW=CLEAN\n"):
        self.calls: list[dict] = []
        self._full_path = os.path.join(worktree, resolve_path)
        self._content = content
        self._review_reply = review_reply

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({"purpose": purpose, "allowed_tools": allowed_tools,
                           "denied_tools": denied_tools})
        if purpose == "conflict-review":
            return self._review_reply
        # conflict-resolve:<path>: write the configured content
        with open(self._full_path, "w") as fh:
            fh.write(self._content)
        return "RESOLVED"


def _make_modify_conflict_repo(lostwork_script: str = None):
    """Squash-trap fixture where feature.txt exists from the base commit.

    Stage 1 (base = parent_tip): "base content"
    Stage 2 (ours = master):     "master version"
    Stage 3 (theirs = feature):  "feature version"

    All three stages present -> classify() allows resolution.
    Returns (d, parent_tip, master_sha, key, branch).
    """
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-mc-{suffix}"
    key = branch  # no / or :

    d = tempfile.mkdtemp(prefix="postplan-mc-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    # Base commit: feature.txt present so both sides can MODIFY it
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    open(os.path.join(d, "feature.txt"), "w").write("base content\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    # Proof scripts committed to master
    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    open(os.path.join(scripts_dir, "lostwork.sh"), "w").write(
        lostwork_script or _LOSTWORK_EQUIV
    )
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    # Parent branch: squash-trap on parent.txt (does not touch feature.txt)
    _sh(d, "checkout", "-b", "parent")
    open(os.path.join(d, "parent.txt"), "w").write("step1\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step1")
    open(os.path.join(d, "parent.txt"), "w").write("step2\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: parent step2")
    parent_tip = _rev(d, "HEAD")

    # Squash merge parent; add conflict seed on master
    _sh(d, "checkout", "master")
    _sh(d, "merge", "--squash", "parent")
    _sh(d, "commit", "-m", "squash: parent")
    open(os.path.join(d, "feature.txt"), "w").write("master version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: master version of feature")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    # Feature branch from parent_tip: modifies feature.txt
    _sh(d, "checkout", "-b", branch, parent_tip)
    open(os.path.join(d, "feature.txt"), "w").write("feature version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")
    _sh(d, "config", f"branch.{branch}.iblBase", parent_tip)

    return d, parent_tip, master_sha, key, branch


def _make_migration_conflict_repo():
    """Minimal repo with an add-add conflict on a SQL migration file.

    The migration class gate in classify() fires before stages are checked,
    so stages {2, 3} triggers abort_and_restore() with zero LLM calls.
    Returns (d, key, branch).
    """
    migration = "ibl5/migrations/20240101_test.sql"
    suffix = uuid.uuid4().hex[:8]
    branch = f"feature-sql-{suffix}"
    key = branch  # no / or :

    d = tempfile.mkdtemp(prefix="postplan-sql-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    # Base commit
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")

    # Proof scripts
    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    open(os.path.join(scripts_dir, "lostwork.sh"), "w").write(_LOSTWORK_EQUIV)
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")

    # Master adds the SQL migration
    os.makedirs(os.path.join(d, "ibl5", "migrations"), exist_ok=True)
    open(os.path.join(d, migration), "w").write("-- master schema\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: master migration")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    # Feature branch from base_sha: adds the same migration file (add-add conflict)
    _sh(d, "checkout", "-b", branch, base_sha)
    os.makedirs(os.path.join(d, "ibl5", "migrations"), exist_ok=True)
    open(os.path.join(d, migration), "w").write("-- feature schema\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature migration")
    _sh(d, "config", f"branch.{branch}.iblBase", base_sha)

    return d, key, branch


def _cleanup_full(key: str, branch: str, d: str) -> None:
    """Full cleanup: standard /tmp files + verdict artifacts + flag + dirs + worktree."""
    from harness.armable import conflict_flag_path as _cfp
    from harness.conflict import purge_verdict_artifacts

    _cleanup_tmp(key)
    purge_verdict_artifacts(key)
    flag = _cfp(branch)
    if os.path.exists(flag):
        os.unlink(flag)
    for extra in [f"/tmp/postplan-conflict-files-{key}-autoresolved.txt"]:
        if os.path.exists(extra):
            os.unlink(extra)
    for dpath in [
        f"/tmp/postplan-conflict-review-{key}",
        f"/tmp/postplan-conflict-stages-{key}",
    ]:
        if os.path.exists(dpath):
            shutil.rmtree(dpath, ignore_errors=True)
    if d and os.path.exists(d):
        shutil.rmtree(d, ignore_errors=True)


def test_autoresolve_llm_happy_path():
    """Scenario 1: resolve+proof+clean-review yields resolved=True, flag and verdict written."""
    d, parent_tip, master_sha, key, branch = _make_modify_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV
    )
    llm = _WritingLlm(d, "feature.txt",
                      content="merged content\n",
                      review_reply="CONFLICT-REVIEW=CLEAN\n")
    try:
        pre_head = _rev(d, "HEAD")
        result = LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert result.resolved is True
        assert result.auto_resolved is True
        assert result.post_resolution_sha != pre_head
        from harness.armable import conflict_flag_path, conflict_verdict_for
        assert os.path.exists(conflict_flag_path(branch))
        assert conflict_verdict_for(branch) == "CONFLICT-REVIEW=CLEAN"
        sidecar = f"/tmp/postplan-conflict-sha-{key}.txt"
        assert open(sidecar).read().strip() == result.post_resolution_sha
    finally:
        _cleanup_full(key, branch, d)


def test_autoresolve_llm_proof_failure_rc0():
    """Scenario 2: conjunctive gate — TREE DIVERGED with rc 0 still aborts+restores."""
    d, parent_tip, master_sha, key, branch = _make_modify_conflict_repo(
        lostwork_script=_LOSTWORK_DIVERGED
    )
    llm = _WritingLlm(d, "feature.txt", content="merged content\n")
    try:
        pre_head = _rev(d, "HEAD")
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert _rev(d, "HEAD") == pre_head
        status = subprocess.run(
            ["git", "-C", d, "status", "--porcelain"],
            capture_output=True, text=True, check=True
        ).stdout
        tracked = [l for l in status.splitlines() if l.strip() and not l.startswith("?? ")]
        assert not tracked
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
        assert not os.path.exists(os.path.join(d, ".git", "rebase-apply"))
        from harness.armable import conflict_flag_path
        assert not os.path.exists(conflict_flag_path(branch))
    finally:
        _cleanup_full(key, branch, d)


def test_autoresolve_llm_round_cap():
    """Scenario 3: stub leaves markers every round; after MAX_RESOLVE_ROUNDS calls, abort+restore."""
    from harness.conflict import MAX_RESOLVE_ROUNDS
    d, parent_tip, master_sha, key, branch = _make_modify_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV
    )
    llm = _WritingLlm(d, "feature.txt", content=_CONFLICT_MARKERS)
    try:
        pre_head = _rev(d, "HEAD")
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert _rev(d, "HEAD") == pre_head
        resolve_calls = [c for c in llm.calls
                         if c["purpose"].startswith("conflict-resolve:")]
        assert len(resolve_calls) == MAX_RESOLVE_ROUNDS
        from harness.armable import conflict_flag_path
        assert not os.path.exists(conflict_flag_path(branch))
    finally:
        _cleanup_full(key, branch, d)


def test_autoresolve_llm_unresolvable_migration():
    """Scenario 8: migration class gate fires before LLM — abort+restore, zero call_tooled calls."""
    d, key, branch = _make_migration_conflict_repo()
    llm = _WritingLlm(d, "ibl5/migrations/20240101_test.sql", content="-- merged\n")
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        assert exc.value.kind == "rebase-conflict"
        assert len(llm.calls) == 0
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        _cleanup_full(key, branch, d)
