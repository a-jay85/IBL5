"""Local-gate fixer: path list, snapshot, guard, revert (Phases 1-2)."""
from __future__ import annotations

import os
import subprocess
import types
from pathlib import Path

import pytest

from harness import fidelity, gatefix, rules_budget_carveout

# --- Phase 1 -------------------------------------------------------------------------


def test_local_gate_prefixes_superset_of_gate_owning():
    for prefix in fidelity.GATE_OWNING_PREFIXES:
        assert prefix in fidelity.LOCAL_GATE_PATH_PREFIXES


@pytest.mark.parametrize("path", [
    "bin/pre-commit-hook",
    "bin/lib/pr-armable.sh",
    ".claude/settings.local.json",
    ".claude/skills/post-plan/_phase-6.5-arm-auto-merge.md",
    "tools/postplan-harness/runner.py",
    "tools/postplan-harness/harness/armable.py",
    ".githooks/pre-commit",
    "bin/check-prose",
    ".claude/rules/x.md",
    ".github/workflows/t.yml",
    "bin/test-postplan-arm-conditions",
    "bin/adr-check",
])
def test_denied_local_gate_edits_flags_each_gate_path(path):
    assert fidelity.denied_local_gate_edits([path]) == [path]


def test_denied_local_gate_edits_normalizes_dot_segments():
    paths = ["./bin/check-x", "ibl5/../bin/lib/y.sh"]
    assert fidelity.denied_local_gate_edits(paths) == paths


def test_denied_local_gate_edits_passes_ordinary_paths():
    paths = ["ibl5/classes/Foo.php", "bin/plan-now", ".claude/agents/x.md", "README.md"]
    assert fidelity.denied_local_gate_edits(paths) == []


# --- Phase 2 helpers -----------------------------------------------------------------


def _sh(cwd, *args, check=True):
    return subprocess.run(args, cwd=cwd, capture_output=True, text=True, check=check)


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "wt"
    root.mkdir()
    _sh(root, "git", "init", "-q", "-b", "master")
    _sh(root, "git", "config", "user.email", "t@example.com")
    _sh(root, "git", "config", "user.name", "T")
    (root / "a.txt").write_text("base a\n")
    (root / "gone.txt").write_text("base gone\n")
    (root / "ibl5").mkdir()
    (root / "ibl5/x.php").write_text("<?php // base\n")
    (root / "bin").mkdir()
    (root / "bin/lib").mkdir()
    (root / "bin/lib/old.sh").write_text("echo old\n")
    (root / ".claude/rules").mkdir(parents=True)
    (root / ".claude/rules/big.md").write_text("big rule\n" * 50)
    (root / ".claude/rules/other.md").write_text("other rule\n" * 5)
    (root / ".gitignore").write_text(".claude/settings.local.json\n")
    _sh(root, "git", "add", "-A")
    _sh(root, "git", "commit", "-q", "-m", "base")
    _sh(root, "git", "update-ref", "refs/remotes/origin/master", "HEAD")
    return root


@pytest.fixture
def home(tmp_path):
    h = tmp_path / "home"
    (h / ".claude/hooks").mkdir(parents=True)
    (h / ".claude/hooks/x.sh").write_text("#!/bin/sh\nexit 0\n")
    (h / ".claude/settings.json").write_text("{}\n")
    return h


def make_run(budget_rc=0, post_rc=0):
    """A `run` that fakes the repo's bin/check-* scripts and passes git through."""
    calls = []

    def _run(argv, **kw):
        if argv[0].startswith("bin/check-"):
            calls.append(list(argv))
            is_budget = argv[0] == rules_budget_carveout.BUDGET_SCRIPT
            rc = budget_rc if is_budget else post_rc
            if is_budget and calls.count(list(argv)) > 1:
                rc = post_rc
            return types.SimpleNamespace(returncode=rc, stdout="", stderr="boom")
        return subprocess.run(argv, **kw)

    _run.calls = calls
    return _run


def _snap(repo, home, run=None):
    return gatefix.snapshot(repo, home=home, run=run or make_run())


def _guard(repo, home, mutate, run=None):
    run = run or make_run()
    before = gatefix.snapshot(repo, home=home, run=run)
    mutate()
    after = gatefix.snapshot(repo, home=home, run=run)
    return gatefix.guard(repo, before, after, run=run), before, after


# --- Phase 2: snapshot ---------------------------------------------------------------


def test_snapshot_detects_edit_to_already_dirty_file(repo, home):
    (repo / "a.txt").write_text("dirty one\n")
    before = _snap(repo, home)
    (repo / "a.txt").write_text("dirty two\n")
    after = _snap(repo, home)
    assert ("M", "a.txt") in gatefix.changed_paths(repo, before, after)


def test_snapshot_leaves_real_index_untouched(repo, home):
    (repo / "a.txt").write_text("staged\n")
    _sh(repo, "git", "add", "a.txt")
    (repo / "new.txt").write_text("untracked\n")
    idx = repo / ".git/index"
    cached = _sh(repo, "git", "diff", "--cached").stdout
    raw = idx.read_bytes()
    _snap(repo, home)
    _snap(repo, home)
    assert _sh(repo, "git", "diff", "--cached").stdout == cached
    assert idx.read_bytes() == raw


# --- Phase 2: guard ------------------------------------------------------------------


def test_guard_passes_ordinary_edit(repo, home):
    verdict, *_ = _guard(repo, home, lambda: (repo / "ibl5/x.php").write_text("<?php // fixed\n"))
    assert verdict.ok
    assert verdict.changed == ["ibl5/x.php"]


def test_guard_rejects_new_untracked_gate_file(repo, home):
    verdict, *_ = _guard(repo, home, lambda: (repo / "bin/lib/new.sh").write_text("x\n"))
    assert not verdict.ok
    assert ("bin/lib/new.sh", "gate path") in verdict.denied


def test_guard_rejects_ignored_settings_local(repo, home):
    (repo / ".claude/settings.local.json").write_text("{}\n")
    verdict, *_ = _guard(
        repo, home, lambda: (repo / ".claude/settings.local.json").write_text('{"x":1}\n'))
    assert not verdict.ok
    assert (".claude/settings.local.json", "gate path") in verdict.denied


def _common_hooks(repo):
    return repo / ".git/hooks"


@pytest.mark.parametrize("which", [
    "edit-home-hook", "create-home-hook", "edit-home-settings", "edit-common-hook",
    "hooks-path",
])
def test_guard_rejects_out_of_repo_hook_edit(repo, home, which):
    (_common_hooks(repo) / "pre-commit").write_text("#!/bin/sh\nexit 1\n")

    def mutate():
        if which == "edit-home-hook":
            (home / ".claude/hooks/x.sh").write_text("exit 0 # changed\n")
        elif which == "create-home-hook":
            (home / ".claude/hooks/new.sh").write_text("exit 0\n")
        elif which == "edit-home-settings":
            (home / ".claude/settings.json").write_text('{"hooks":{}}\n')
        elif which == "edit-common-hook":
            (_common_hooks(repo) / "pre-commit").write_text("#!/bin/sh\nexit 0\n")
        else:
            _sh(repo, "git", "config", "core.hooksPath", "/tmp/x")

    verdict, *_ = _guard(repo, home, mutate)
    assert not verdict.ok
    assert any(reason == "out-of-repo gate path" for _p, reason in verdict.denied)


def _grow_rules(repo):
    (repo / ".claude/rules/big.md").write_text("big rule\n" * 5)
    (repo / ".claude/rules/big-detail.md").write_text(
        "---\npaths:\n  - 'ibl5/**'\n---\nmoved\n")


def test_rules_carveout_allows_shrink_when_budget_fails(repo, home):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")
    run = make_run(budget_rc=1, post_rc=0)
    verdict, before, _ = _guard(repo, home, lambda: _grow_rules(repo), run=run)
    assert before.carveout.active
    assert verdict.ok, verdict.denied


@pytest.mark.parametrize("case", ["grow", "delete", "outside-diff", "no-paths"])
def test_rules_carveout_denies_growth_delete_and_outside_diff(repo, home, case):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")

    def mutate():
        if case == "grow":
            (repo / ".claude/rules/big.md").write_text("big rule\n" * 400)
        elif case == "delete":
            (repo / ".claude/rules/big.md").unlink()
        elif case == "outside-diff":
            (repo / ".claude/rules/other.md").write_text("o\n")
        else:
            (repo / ".claude/rules/big.md").write_text("big\n")
            (repo / ".claude/rules/big-detail.md").write_text("no frontmatter\n")

    verdict, *_ = _guard(repo, home, mutate, run=make_run(budget_rc=1))
    assert not verdict.ok


def test_rules_carveout_inactive_when_budget_passes(repo, home):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")
    verdict, before, _ = _guard(
        repo, home, lambda: (repo / ".claude/rules/big.md").write_text("tiny\n"),
        run=make_run(budget_rc=0))
    assert not before.carveout.active
    assert not verdict.ok
    assert (".claude/rules/big.md", "gate path") in verdict.denied


def test_guard_error_fails_closed(repo, home):
    before = _snap(repo, home)
    (repo / "ibl5/x.php").write_text("changed\n")
    after = _snap(repo, home)

    def boom(*_a, **_k):
        raise RuntimeError("git exploded")

    verdict = gatefix.guard(repo, before, after, run=boom)
    assert not verdict.ok
    assert verdict.denied[0][0] == "<guard>"


# --- Phase 2: revert -----------------------------------------------------------------


def test_revert_restores_dirty_tree_bytes(repo, home):
    (repo / "a.txt").write_text("pre-dirty a\n")
    (repo / "gone.txt").write_text("pre-dirty gone\n")
    (repo / "staged.txt").write_text("staged\n")
    _sh(repo, "git", "add", "staged.txt")
    staged = _sh(repo, "git", "diff", "--cached").stdout
    before = _snap(repo, home)
    (repo / "a.txt").write_text("fixer edit\n")
    (repo / "added.txt").write_text("new\n")
    (repo / "gone.txt").unlink()
    after = _snap(repo, home)
    assert gatefix.revert(repo, before, after) == []
    assert (repo / "a.txt").read_text() == "pre-dirty a\n"
    assert not (repo / "added.txt").exists()
    assert (repo / "gone.txt").read_text() == "pre-dirty gone\n"
    assert _sh(repo, "git", "diff", "--cached").stdout == staged


def test_revert_restores_out_of_repo_bytes(repo, home):
    before = _snap(repo, home)
    (home / ".claude/hooks/x.sh").write_text("tampered\n")
    (home / ".claude/hooks/new.sh").write_text("tampered\n")
    after = _snap(repo, home)
    assert gatefix.revert(repo, before, after) == []
    assert (home / ".claude/hooks/x.sh").read_text() == "#!/bin/sh\nexit 0\n"
    assert not (home / ".claude/hooks/new.sh").exists()
