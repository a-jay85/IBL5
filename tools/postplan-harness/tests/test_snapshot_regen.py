"""Tests for golden-snapshot regeneration in the conflict ladder's regen rung
(harness/snapshot_regen.py and the `make_regen_rung` hunk that calls it).

Every ladder test builds a real temp repo, merges master into the branch by hand, and
commits so HEAD has parents (pre, master). `prove` and the PHPUnit runner are fakes.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import snapshot_regen
from harness.adapters.gitad import LiveGit
from harness.conflict_ladder import (
    GENERATED_FILES,
    LadderContext,
    make_regen_rung,
    match_generated,
    run_ladder,
)
from harness.snapshot_regen import CAPTURE_ENV, RunResult

_GIT_ENV = {
    "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
    "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t",
}

SNAP = "ibl5/tests/Demo/__snapshots__/demo-page.html"
OWNER = "ibl5/tests/Demo/DemoSnapshotTest.php"
ITEMS = "ibl5/src/items.txt"
OTHER_GOLDEN = "ibl5/tests/Demo/fixtures/other.golden.html"


# -- fixtures and helpers -------------------------------------------------------

@pytest.fixture()
def key():
    k = f"snapregen-{uuid.uuid4().hex[:10]}"
    yield k
    shutil.rmtree(f"/tmp/postplan-conflict-stages-{k}", ignore_errors=True)
    try:
        os.remove(f"/tmp/postplan-lostwork-{k}.sh")
    except OSError:
        pass


def _git(d, *args, check=True):
    r = subprocess.run(["git", "-C", str(d), *args], capture_output=True, text=True,
                       env={**os.environ, **_GIT_ENV})
    if check and r.returncode != 0:
        raise AssertionError(f"git {args} failed: {r.stderr or r.stdout}")
    return r


def _write(d, files):
    for rel, text in files.items():
        full = Path(d) / rel
        full.parent.mkdir(parents=True, exist_ok=True)
        full.write_text(text)


def _ctx(d, key, pre, master, conflicted, prove, llm=None, stage_sets=None,
         extend_pre_patch=None):
    g = LiveGit(str(d))
    return LadderContext(
        llm=llm, run=g._run, run_out=g._run_out, prove=prove, worktree=str(d),
        key=key, master_sha=master, pre_rebase_sha=pre,
        conflicted_files=tuple(conflicted), stage_sets=stage_sets or {},
        extend_pre_patch=extend_pre_patch)


def _head(d):
    return _git(d, "rev-parse", "HEAD").stdout.strip()


def _render(items):
    return "<ul>" + "".join(f"<li>{i}</li>" for i in items) + "</ul>\n"


_BASE_ITEMS = ["a", "c", "k1", "k2", "k3", "z"]
_BRANCH_ITEMS = ["a", "b", "c", "k1", "k2", "k3", "z"]
_MASTER_ITEMS = ["a", "c", "k1", "k2", "k3", "z", "m"]
_MERGED_ITEMS = ["a", "b", "c", "k1", "k2", "k3", "z", "m"]
_OWNER_TEXT = "<?php\n$this->assertSnapshotMatches('demo-page', $html);\n"


def _lines(items):
    return "".join(f"{i}\n" for i in items)


def _snapshot_repo(tmp_path, *, owner_refs=True, drop_master_item=False):
    """SNAP conflicts; the first pass kept the branch bytes. ITEMS merges cleanly.
    `drop_master_item` commits the first-pass merge with ITEMS missing master's `m`."""
    d = tmp_path / "snap"
    d.mkdir(parents=True)
    subprocess.run(["git", "init", "-b", "master", str(d)], check=True, capture_output=True)
    _git(d, "config", "user.email", "t@t")
    _git(d, "config", "user.name", "t")
    _git(d, "config", "commit.gpgsign", "false")
    owner_text = _OWNER_TEXT if owner_refs else "<?php\n// no snapshot reference here\n"
    _write(d, {ITEMS: _lines(_BASE_ITEMS), SNAP: _render(_BASE_ITEMS),
               OWNER: owner_text, OTHER_GOLDEN: "<p>other</p>\n",
               ".gitignore": "ibl5/vendor/\n"})
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "base")
    _git(d, "checkout", "-b", "feat")
    _write(d, {ITEMS: _lines(_BRANCH_ITEMS), SNAP: _render(_BRANCH_ITEMS)})
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "feat")
    pre = _git(d, "rev-parse", "HEAD").stdout.strip()
    _git(d, "checkout", "master")
    _write(d, {ITEMS: _lines(_MASTER_ITEMS), SNAP: _render(_MASTER_ITEMS)})
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "master")
    master_sha = _git(d, "rev-parse", "HEAD").stdout.strip()
    _git(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _git(d, "checkout", "feat")
    merged = _git(d, "merge", "master", "--no-edit", check=False)
    assert merged.returncode != 0, "fixture merge must conflict"
    status = _git(d, "status", "--porcelain").stdout
    assert f"UU {SNAP}" in status
    assert f"M  {ITEMS}" in status
    _write(d, {SNAP: _render(_BRANCH_ITEMS)})
    if drop_master_item:
        _write(d, {ITEMS: _lines(_BRANCH_ITEMS)})
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "merge master (first pass)")
    parents = _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]
    assert parents == [pre, master_sha]
    return d, pre, master_sha, _git(d, "rev-parse", "HEAD").stdout.strip()


_SNAP_STAGES = {SNAP: frozenset({1, 2, 3})}


def _lost_input():
    return f"LOST: {SNAP}: +{_render(_MASTER_ITEMS).rstrip()}\n"


# -- Phase 1: characterization (must pass on unmodified master) -----------------

def test_generated_registry_excludes_snapshot_paths():
    paths = [SNAP, "ibl5/tests/SeasonArchive/__snapshots__/detail-rich.html",
             "ibl5/tests/BasketballStats/Tables/fixtures/per36-minutes-hostile-players.golden.html"]
    assert match_generated(paths) == ()
    assert match_generated(paths, registry=GENERATED_FILES) == ()


def test_snapshot_conflict_declines_without_regen_support(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    ctx = _ctx(d, key, pre, master, (SNAP,), lambda: (False, _lost_input()),
               stage_sets=_SNAP_STAGES)
    out = run_ladder(ctx, _lost_input(), (make_regen_rung(),))
    assert out.rung == ""
    assert out.trail[0].startswith("regen: skipped (")
    assert _head(d) == merge
    assert _git(d, "status", "--porcelain").stdout == ""
    assert (Path(d) / SNAP).read_text() == _render(_BRANCH_ITEMS)


# -- Phase 2: predicate, owner mapping, runner, audit channel -------------------

def _repo_root():
    return Path(subprocess.run(
        ["git", "rev-parse", "--show-toplevel"], cwd=Path(__file__).resolve().parent,
        capture_output=True, text=True, check=True).stdout.strip())


def test_snapshot_patterns_match_repo():
    tracked = subprocess.run(
        ["git", "ls-files", "ibl5/tests"], cwd=_repo_root(),
        capture_output=True, text=True, check=True).stdout.splitlines()
    expected = [p for p in tracked if "/__snapshots__/" in p or p.endswith(".golden.html")]
    assert expected
    for p in expected:
        assert snapshot_regen.is_snapshot_path(p), p
    for p in tracked:
        if "-snapshots/" in p or p.endswith(".png"):
            assert not snapshot_regen.is_snapshot_path(p), p


@pytest.mark.parametrize("path", [
    "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/home.png",
    "ibl5/tests/SeasonArchive/SnapshotTestTrait.php",
    "ibl5/classes/X/fixtures/a.golden.html",
    "ibl5/tests/Demo/__snapshots__/a.png",
    "ibl5/tests/Demo/__snapshots__/sub/a.html",
    "ibl5/src/items.txt",
])
def test_is_snapshot_path_rejects_non_snapshot_paths(path):
    assert snapshot_regen.is_snapshot_path(path) is False


def test_find_owning_test_direct_literal(tmp_path):
    _write(tmp_path, {
        "ibl5/tests/Demo/DemoSnapshotTest.php": "<?php\n$this->assertSnapshotMatches('demo-page', $h);\n",
        "ibl5/tests/Demo/OtherTest.php": "<?php\n// nothing here\n",
    })
    owner, reason = snapshot_regen.find_owning_test(str(tmp_path), SNAP)
    assert (owner, reason) == ("ibl5/tests/Demo/DemoSnapshotTest.php", "")


def test_find_owning_test_data_provider_prefix(tmp_path):
    _write(tmp_path, {
        "ibl5/tests/Tables/Per36Test.php":
            "<?php\n$f = __DIR__ . '/fixtures/per36-minutes-' . $case . '.golden.html';\n",
        "ibl5/tests/Tables/OtherTest.php": "<?php\n",
    })
    owner, reason = snapshot_regen.find_owning_test(
        str(tmp_path), "ibl5/tests/Tables/fixtures/per36-minutes-hostile-players.golden.html")
    assert (owner, reason) == ("ibl5/tests/Tables/Per36Test.php", "")


def test_find_owning_test_declines_none_and_ambiguous(tmp_path):
    _write(tmp_path, {"ibl5/tests/Demo/OtherTest.php": "<?php\n"})
    owner, reason = snapshot_regen.find_owning_test(str(tmp_path), SNAP)
    assert owner is None
    assert reason.startswith("no owning test under ibl5/tests/Demo")
    _write(tmp_path, {
        "ibl5/tests/Demo/AOneTest.php": "<?php\n'demo-page';\n",
        "ibl5/tests/Demo/BTwoTest.php": "<?php\n\"demo-page\";\n",
    })
    owner, reason = snapshot_regen.find_owning_test(str(tmp_path), SNAP)
    assert owner is None
    assert reason.startswith("ambiguous owner:")


def test_parse_tests_run_shapes():
    parse = snapshot_regen.parse_tests_run
    assert parse("OK (3 tests, 5 assertions)") == 3
    assert parse("OK, but there were issues!\nTests: 4, Assertions: 23, PHPUnit Deprecations: 1.") == 4
    assert parse("No tests executed!") == 0
    assert parse("\x1b[33mTests: 4, Assertions: 23\x1b[0m") == 4


def _fake_php(tmp_path, body):
    bin_dir = tmp_path / "bin"
    bin_dir.mkdir(exist_ok=True)
    php = bin_dir / "php"
    php.write_text("#!/bin/bash\n" + body)
    php.chmod(0o755)
    return str(php)


def test_host_runner_capture_and_verify_env(tmp_path, monkeypatch):
    record = tmp_path / "record.txt"
    php = _fake_php(tmp_path, (
        f'{{ echo "UPDATE=${{UPDATE_SNAPSHOTS-UNSET}}"; echo "CAPTURE=${{IBL_GOLDEN_CAPTURE-UNSET}}"; '
        f'echo "ARGV=$*"; }} >> "{record}"\n'
        'echo "OK (2 tests, 2 assertions)"\n'))
    monkeypatch.setenv("UPDATE_SNAPSHOTS", "1")
    monkeypatch.setenv("IBL_GOLDEN_CAPTURE", "")
    ibl5 = tmp_path / "ibl5"
    ibl5.mkdir()
    run = snapshot_regen.host_runner(php)
    r1 = run(str(ibl5), "tests/Demo/DemoSnapshotTest.php", CAPTURE_ENV, 30)
    r2 = run(str(ibl5), "tests/Demo/DemoSnapshotTest.php", {}, 30)
    assert r1.ok and r1.tests_run == 2
    assert r2.ok and r2.tests_run == 2
    text = record.read_text()
    first, second = text.split("UPDATE=")[1:]
    assert first.startswith("1\nCAPTURE=1\n")
    assert second.startswith("UNSET\nCAPTURE=UNSET\n")
    assert "--exclude-group database" in text
    assert "--colors=never" in text
    assert "vendor/bin/phpunit" in text


def test_host_runner_zero_tests_and_timeout(tmp_path, monkeypatch):
    ibl5 = tmp_path / "ibl5"
    ibl5.mkdir()
    php = _fake_php(tmp_path, 'echo "No tests executed!"\nexit 1\n')
    r = snapshot_regen.host_runner(php)(str(ibl5), "t.php", {}, 30)
    assert (r.ok, r.tests_run) == (False, 0)

    php = _fake_php(tmp_path, 'echo "OK (1 test, 1 assertion)"\nexit 0\n')
    with monkeypatch.context() as m:
        m.setattr(snapshot_regen, "parse_tests_run", lambda _out: 0)
        r = snapshot_regen.host_runner(php)(str(ibl5), "t.php", {}, 30)
    assert r.ok is False

    php = _fake_php(tmp_path, "exec sleep 5\n")
    r = snapshot_regen.host_runner(php)(str(ibl5), "t.php", {}, 1)
    assert r.ok is False
    assert r.detail == "timed out after 1s"


def test_default_runner_named_declines(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    runner_fn, reason = snapshot_regen.default_runner(str(wt))
    assert runner_fn is None and "ibl5/vendor/autoload.php missing" in reason
    (wt / "ibl5/vendor/bin").mkdir(parents=True)
    (wt / "ibl5/vendor/autoload.php").write_text("<?php\n")
    runner_fn, reason = snapshot_regen.default_runner(str(wt))
    assert runner_fn is None and "ibl5/vendor/bin/phpunit missing" in reason
    (wt / "ibl5/vendor/bin/phpunit").write_text("")
    runner_fn, reason = snapshot_regen.default_runner(
        str(wt), which=lambda _: None, fallbacks=())
    assert runner_fn is None and "php not found" in reason
    php = _fake_php(tmp_path, "exit 0\n")
    runner_fn, reason = snapshot_regen.default_runner(
        str(wt), which=lambda _: None, fallbacks=(php,))
    assert callable(runner_fn) and reason == ""


def test_require_snapshot_only_rejects_other_paths():
    with pytest.raises(ValueError):
        snapshot_regen.require_snapshot_only((SNAP, "ibl5/src/items.txt"))
    assert snapshot_regen.require_snapshot_only((SNAP,)) == (SNAP,)


def test_audit_lines_round_trip(tmp_path):
    subprocess.run(["git", "init", "-b", "master", str(tmp_path)], check=True,
                   capture_output=True)
    wt = str(tmp_path)
    git_dir = _git(tmp_path, "rev-parse", "--absolute-git-dir").stdout.strip()
    assert os.path.realpath(snapshot_regen._audit_file(wt)).startswith(
        os.path.realpath(git_dir) + os.sep)
    snapshot_regen.clear_audit_lines(wt)   # missing file: no raise
    snapshot_regen.append_audit_lines(wt, ("one", "two"))
    assert snapshot_regen.pop_audit_lines(wt) == ("one", "two")
    assert snapshot_regen.pop_audit_lines(wt) == ()
    snapshot_regen.append_audit_lines(wt, ("one", "two", "three"))
    snapshot_regen.remove_audit_lines(wt, ("two",))
    assert snapshot_regen.pop_audit_lines(wt) == ("one", "three")
    snapshot_regen.append_audit_lines(wt, ("one",))
    snapshot_regen.clear_audit_lines(wt)
    assert snapshot_regen.pop_audit_lines(wt) == ()


# -- Phase 3: runner.py audit emission --------------------------------------------

_REGEN_LINE = f"snapshot regenerated: {SNAP} via {OWNER}"


def _phase2_git(tmp_path, on_rebase):
    subprocess.run(["git", "init", "-b", "master", str(tmp_path)], check=True,
                   capture_output=True)

    class _Git:
        worktree = str(tmp_path)
        last_conflict_rung = ""
        last_conflict_fix_summary = ""
        last_conflict_files = ()
        conflict_review_retries = {}

        def head(self):
            return "abc"

        def branch(self):
            return "feat"

        def rebase_onto(self):
            on_rebase(self.worktree)

    return _Git()


def _phase2_res():
    from harness.state import RunResult as HarnessRunResult, TerminalState
    return HarnessRunResult(terminal=TerminalState.FAILED)


def test_phase2_rebase_logs_snapshot_regen_lines(tmp_path):
    git = _phase2_git(tmp_path, lambda wt: snapshot_regen.append_audit_lines(wt, (_REGEN_LINE,)))
    log = []
    runner._phase2_rebase(git, _phase2_res(), log.append)
    assert f"phase2: {_REGEN_LINE}" in log
    assert snapshot_regen.pop_audit_lines(str(tmp_path)) == ()


def test_phase2_rebase_ignores_stale_snapshot_audit_file(tmp_path):
    git = _phase2_git(tmp_path, lambda wt: None)
    snapshot_regen.append_audit_lines(str(tmp_path), (_REGEN_LINE,))
    log = []
    runner._phase2_rebase(git, _phase2_res(), log.append)
    assert not any("snapshot regenerated" in l for l in log)


# -- Phase 4: the rung, end to end over a real conflicted merge -------------------

class _FakeRunner:
    """Stands in for the PHPUnit host runner. Capture writes SNAP from the items on disk;
    verify passes only when SNAP equals the render of those items."""

    def __init__(self, d, *, capture_ok=True, verify_ok=True, write=True, tests_run=1,
                 dirty_other=False, render=None):
        self.d = Path(d)
        self.render = render
        self.capture_ok, self.verify_ok = capture_ok, verify_ok
        self.write, self.tests_run, self.dirty_other = write, tests_run, dirty_other
        self.calls = []

    def _on_disk_render(self):
        # `render` pins the output when the snapshot's inputs are not the items file.
        return _render(self.render or (self.d / ITEMS).read_text().split())

    def __call__(self, ibl5_dir, test_rel, env, timeout):
        self.calls.append((test_rel, dict(env)))
        snap = self.d / SNAP
        if env.get("UPDATE_SNAPSHOTS") == "1":
            if self.write and not snap.exists():
                snap.write_text(self._on_disk_render())
            if self.dirty_other:
                (self.d / OTHER_GOLDEN).write_text("<p>stale</p>\n")
            return RunResult(self.capture_ok, self.tests_run, "fake capture")
        ok = self.verify_ok and snap.exists() and snap.read_text() == self._on_disk_render()
        return RunResult(ok, self.tests_run, "fake verify")


class _Seam:
    def __init__(self):
        self.calls = []
        self.events = []

    def __call__(self, paths):
        self.calls.append(tuple(paths))
        return lambda: self.events.append("undo")


def _prove(d, seam):
    def prove():
        lost = []
        items = _git(d, "show", f"HEAD:{ITEMS}").stdout.split()
        head_snap = _git(d, "show", f"HEAD:{SNAP}").stdout
        if not any(SNAP in call for call in seam.calls):
            lost.append(f"LOST: {SNAP}: +{_render(_MASTER_ITEMS).rstrip()}")
        elif head_snap != _render(items):
            lost.append(f"LOST: {SNAP}: +{_render(items).rstrip()}")
        if "m" not in items:
            # Two lines per drop, so the LOST count does not fall and the ladder discards
            # the rung instead of keeping it as a partial base.
            lost.append(f"LOST: {ITEMS}: +m")
            lost.append(f"LOST: {ITEMS}: +m (dropped from the snapshot source)")
        if lost:
            return False, "\n".join(lost) + "\n"
        return True, "TREE-EQUIVALENT\n"
    return prove


def _run(d, key, pre, master, fake, seam, *, stage_sets=_SNAP_STAGES, proof_in=None):
    ctx = _ctx(d, key, pre, master, (SNAP,), _prove(d, seam), stage_sets=stage_sets,
               extend_pre_patch=seam)
    return run_ladder(ctx, proof_in if proof_in is not None else _lost_input(),
                      (make_regen_rung(snapshot_runner=fake),))


def _assert_declined(out, d, merge, seam, runner_reason):
    assert out.rung == ""
    assert out.trail[0].startswith("regen: skipped (snapshot regen declined: ")
    assert runner_reason in out.trail[0]
    assert _head(d) == merge
    assert _git(d, "status", "--porcelain").stdout == ""
    assert (Path(d) / SNAP).read_text() == _render(_BRANCH_ITEMS)
    assert seam.calls == []
    assert snapshot_regen.pop_audit_lines(str(d)) == ()


_TEST_REL = "tests/Demo/DemoSnapshotTest.php"


def test_snapshot_regen_success_clean_merge_and_proof(tmp_path, key):
    d, pre, master, _ = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    assert out.rung == "regen"
    parents = _git(d, "rev-list", "--parents", "-n", "1", "HEAD").stdout.split()[1:]
    assert parents == [pre, master]
    assert _git(d, "show", f"HEAD:{SNAP}").stdout == _render(_MERGED_ITEMS)
    assert seam.calls == [(SNAP,)], out.trail
    assert fake.calls == [(_TEST_REL, CAPTURE_ENV), (_TEST_REL, {})]
    assert _git(d, "status", "--porcelain").stdout == ""
    assert snapshot_regen.pop_audit_lines(str(d)) == (f"snapshot regenerated: {SNAP} via {OWNER}",)


def test_success_fixture_fails_without_snapshot_globs(tmp_path, key, monkeypatch):
    monkeypatch.setattr(snapshot_regen, "SNAPSHOT_PATTERNS", ())
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    assert out.rung == ""
    assert out.trail[0] == "regen: skipped (no generated file involved)"
    assert fake.calls == []
    assert _head(d) == merge


def test_snapshot_regen_declines_without_owner(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path, owner_refs=False)
    fake, seam = _FakeRunner(d), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "no owning test under ibl5/tests/Demo")
    assert fake.calls == []


def test_snapshot_regen_declines_when_capture_fails(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, capture_ok=False), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "failed during capture")


def test_snapshot_regen_declines_when_verify_fails(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, verify_ok=False), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "verify pass failed")
    assert len(fake.calls) == 2


def test_snapshot_regen_declines_when_zero_tests_run(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, tests_run=0), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "ran zero tests")


def test_snapshot_regen_declines_when_owner_writes_nothing(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, write=False), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "did not write the snapshot")


def test_snapshot_regen_declines_style3_bare_assert(tmp_path, key):
    # A bare assertStringEqualsFile owner fails on the deleted golden and writes nothing.
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, write=False, capture_ok=False), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    _assert_declined(out, d, merge, seam, "failed during capture")


@pytest.mark.parametrize("stage_sets", [
    {SNAP: frozenset({1, 2})},
    {SNAP: frozenset({1, 3})},
    {SNAP: frozenset({2, 3})},
    {},
])
def test_snapshot_regen_declines_unless_both_modified(tmp_path, key, stage_sets):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d), _Seam()
    out = _run(d, key, pre, master, fake, seam, stage_sets=stage_sets)
    _assert_declined(out, d, merge, seam, "not both-modified")
    assert fake.calls == []


def test_snapshot_regen_declines_without_vendor_or_php(tmp_path, key, monkeypatch):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    seam = _Seam()
    out = _run(d, key, pre, master, None, seam)
    _assert_declined(out, d, merge, seam, "ibl5/vendor/autoload.php missing")
    (d / "ibl5/vendor/bin").mkdir(parents=True)
    (d / "ibl5/vendor/autoload.php").write_text("<?php\n")
    (d / "ibl5/vendor/bin/phpunit").write_text("")
    monkeypatch.setattr(snapshot_regen, "resolve_php", lambda *a, **k: None)
    out = _run(d, key, pre, master, None, seam)
    _assert_declined(out, d, merge, seam, "php not found")


def test_snapshot_regen_restores_unrelated_golden(tmp_path, key):
    d, pre, master, merge = _snapshot_repo(tmp_path)
    fake, seam = _FakeRunner(d, dirty_other=True), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    assert out.rung == "regen"
    assert (d / OTHER_GOLDEN).read_text() == "<p>other</p>\n"
    assert _git(d, "show", f"HEAD:{OTHER_GOLDEN}").stdout == "<p>other</p>\n"
    changed = _git(d, "diff", "--name-only", merge, "HEAD").stdout.split()
    assert changed == [SNAP]


def test_snapshot_regen_lost_non_snapshot_line_declines(tmp_path, key):
    # A snapshot rebuilt from a tree that lost source lines would be a stale golden once a
    # later rung restores the source, so snapshot regen declines without running anything.
    d, pre, master, merge = _snapshot_repo(tmp_path, drop_master_item=True)
    fake, seam = _FakeRunner(d, render=_MERGED_ITEMS), _Seam()
    proof_in = (_lost_input() + f"LOST: {ITEMS}: +m\n"
                f"LOST: {ITEMS}: +m (dropped from the snapshot source)\n")
    out = _run(d, key, pre, master, fake, seam, proof_in=proof_in)
    assert out.rung == ""
    assert "non-snapshot files lost work" in out.trail[0]
    assert fake.calls == []
    assert seam.calls == []
    assert _head(d) == merge
    assert _git(d, "status", "--porcelain").stdout == ""
    assert snapshot_regen.pop_audit_lines(str(d)) == ()


def test_snapshot_regen_lost_non_snapshot_line_still_fails(tmp_path, key):
    # The first-pass proof names only the snapshot, so regen runs and amends. The source
    # file still lost a line, so the post-amend proof fails and the ladder must call the
    # undo_all closure: the seam undo fires and the audit line is removed.
    d, pre, master, merge = _snapshot_repo(tmp_path, drop_master_item=True)
    fake, seam = _FakeRunner(d, render=_MERGED_ITEMS), _Seam()
    out = _run(d, key, pre, master, fake, seam)
    assert out.rung == ""
    assert "regen: " in out.trail[0]
    assert "passed" not in out.trail[0], out.trail
    assert seam.calls == [(SNAP,)], out.trail
    assert seam.events == ["undo"]
    assert snapshot_regen.pop_audit_lines(str(d)) == ()
    assert _head(d) == merge
    assert _git(d, "status", "--porcelain").stdout == ""
