"""Tests for harness.manual_testing — Phase 6.7 execution logic."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import manual_testing as mt
from harness.adapters.probe import FixtureProbe, allowed as probe_allowed
from harness import armable

SHA = "abc123"


class FakeGh:
    def __init__(self, bodies):
        self.bodies = list(bodies)
        self.calls = 0

    def pr_body(self):
        b = self.bodies[min(self.calls, len(self.bodies) - 1)]
        self.calls += 1
        return b


class _ProbeWithAllowed:
    def allowed(self, argv):
        return probe_allowed(argv)

    def run(self, argv, timeout=120):
        return True, "ok"


# ---------------------------------------------------------------------------
# _load_script — blob loading + path preference
# ---------------------------------------------------------------------------

def _make_show_blob(mapping: dict):
    """Return a show_blob function backed by a dict of {ref: content}."""
    def _show(ref: str) -> str:
        return mapping.get(ref, "")
    return _show


def test_loader_review_shared_only():
    content = "#!/bin/bash\necho done\n"
    show = _make_show_blob({
        f"{SHA}:.claude/review-shared/scripts/wt-bring-up.sh": content,
    })
    path = mt._load_script(show, SHA, mt.BRINGUP_SCRIPT_PATHS, 99)
    assert path is not None
    assert path.exists()
    # The loaded content matches the review-shared script
    assert "done" in path.read_text()


def test_loader_pr_ready_only():
    content = "#!/bin/bash\necho done\n"
    show = _make_show_blob({
        f"{SHA}:.claude/skills/pr-ready/scripts/wt-bring-up.sh": content,
    })
    path = mt._load_script(show, SHA, mt.BRINGUP_SCRIPT_PATHS, 100)
    assert path is not None
    assert path.exists()


def test_loader_both_prefers_review_shared():
    content_a = "#!/bin/bash\necho review-shared\n"
    content_b = "#!/bin/bash\necho pr-ready\n"
    show = _make_show_blob({
        f"{SHA}:.claude/review-shared/scripts/wt-bring-up.sh": content_a,
        f"{SHA}:.claude/skills/pr-ready/scripts/wt-bring-up.sh": content_b,
    })
    path = mt._load_script(show, SHA, mt.BRINGUP_SCRIPT_PATHS, 101)
    assert path is not None
    # The review-shared path is first in BRINGUP_SCRIPT_PATHS, so it wins
    content = path.read_text()
    assert "review-shared" in content


def test_loader_missing():
    show = _make_show_blob({})
    path = mt._load_script(show, SHA, mt.BRINGUP_SCRIPT_PATHS, 102)
    assert path is None


# ---------------------------------------------------------------------------
# docker_available — gate tests
# ---------------------------------------------------------------------------

BODY_ONE_ROW = "## Manual Testing\n\n- [ ] **Row 1** — `bin/test-foo`\n"


def _make_scripts_show():
    """Return a show_blob that provides all three scripts."""
    script_content = "#!/bin/bash\necho done\n"
    keys = [
        f"{SHA}:.claude/review-shared/scripts/wt-bring-up.sh",
        f"{SHA}:.claude/review-shared/scripts/manual-rows.sh",
        f"{SHA}:.claude/review-shared/scripts/tick-rows.sh",
    ]
    return _make_show_blob({k: script_content for k in keys})


def _base_run_kwargs(monkeypatch, show=None, extra_body=None):
    """Set up the minimal monkeypatches and return run() kwargs."""
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))
    if show is None:
        show = _make_scripts_show()
    body = extra_body or BODY_ONE_ROW
    return dict(
        pr=1, worktree="/fake/worktree", body=body,
        gh=FakeGh([body, body, body]),
        probe=_ProbeWithAllowed(),
        show_blob=show,
        master_sha=SHA,
        head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )


def test_docker_not_on_path(monkeypatch):
    monkeypatch.setattr(mt.shutil, "which", lambda _: None)
    result = mt.run(
        pr=1, worktree="/fake/worktree",
        body=BODY_ONE_ROW,
        gh=FakeGh([BODY_ONE_ROW]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA,
        head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.skipped_reason == "docker-not-on-path"
    assert result.ran is False
    assert result.all_ticked is False


def test_docker_daemon_unreachable(monkeypatch):
    import subprocess as sp

    monkeypatch.setattr(mt.shutil, "which", lambda cmd: "/usr/bin/docker")

    class _CompletedResult:
        returncode = 1
        stdout = ""
        stderr = "Cannot connect"

    monkeypatch.setattr(sp, "run", lambda *a, **kw: _CompletedResult())
    result = mt.run(
        pr=1, worktree="/fake/worktree",
        body=BODY_ONE_ROW,
        gh=FakeGh([BODY_ONE_ROW]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA,
        head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.skipped_reason == "docker-daemon-unreachable"


def test_docker_probe_timeout(monkeypatch):
    import subprocess as sp

    monkeypatch.setattr(mt.shutil, "which", lambda cmd: "/usr/bin/docker")
    monkeypatch.setattr(
        sp, "run",
        lambda *a, **kw: (_ for _ in ()).throw(sp.TimeoutExpired(cmd=["docker"], timeout=10)),
    )
    result = mt.run(
        pr=1, worktree="/fake/worktree",
        body=BODY_ONE_ROW,
        gh=FakeGh([BODY_ONE_ROW]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA,
        head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.skipped_reason == "docker-probe-timeout"


# ---------------------------------------------------------------------------
# pending_rows
# ---------------------------------------------------------------------------

def test_pending_rows():
    body = (
        "## Manual Testing\n\n"
        "- [ ] **Row 1** — some description\n"
        "- [x] **Row 2** — already done\n"
        "- [x] bin/test-foo - bare bullet (no bold)\n"
        "> - [ ] **Row 3** — quoted line\n"
    )
    rows = mt.pending_rows(body)
    ids = [(r[0], r[2]) for r in rows]
    # Only bold rows inside section (not quoted)
    assert ("Row 1", False) in ids
    assert ("Row 2", True) in ids
    # bare bullet and quoted line excluded
    row_ids = [r[0] for r in rows]
    assert "Row 3" not in row_ids
    assert len([r for r in rows if "bin/test-foo" in r[0]]) == 0


# ---------------------------------------------------------------------------
# bring_up
# ---------------------------------------------------------------------------

def _fake_script_up(path, args, timeout_s, cwd=None):
    return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""


def _fake_script_already_up(path, args, timeout_s, cwd=None):
    return 0, "BRINGUP: ALREADY-UP\nBRINGUP-COMPLETE\n", ""


def _fake_script_skip_peer_dirty(path, args, timeout_s, cwd=None):
    return 0, "BRINGUP: SKIP peer-dirty\nBRINGUP-COMPLETE\n", ""


def _fake_script_not_ready(path, args, timeout_s, cwd=None):
    return 0, "BRINGUP: NOT-READY\nBRINGUP-COMPLETE\n", ""


def _fake_script_no_sentinel(path, args, timeout_s, cwd=None):
    return 0, "BRINGUP: UP\n", ""


from pathlib import Path as _Path

_FAKE_PATH = _Path("/tmp/fake-script.sh")


def test_bringup_up(monkeypatch):
    monkeypatch.setattr(mt, "_run_script", _fake_script_up)
    result = mt.bring_up(_FAKE_PATH, 1, "slug", "/worktree")
    assert result == "UP"


def test_bringup_already_up(monkeypatch):
    monkeypatch.setattr(mt, "_run_script", _fake_script_already_up)
    result = mt.bring_up(_FAKE_PATH, 1, "slug", "/worktree")
    assert result == "ALREADY-UP"


def test_bringup_skip_peer_dirty(monkeypatch):
    monkeypatch.setattr(mt, "_run_script", _fake_script_skip_peer_dirty)
    result = mt.bring_up(_FAKE_PATH, 1, "slug", "/worktree")
    assert result == "SKIP-peer-dirty"


def test_bringup_not_ready(monkeypatch):
    monkeypatch.setattr(mt, "_run_script", _fake_script_not_ready)
    result = mt.bring_up(_FAKE_PATH, 1, "slug", "/worktree")
    assert result == "NOT-READY"


def test_bringup_no_sentinel(monkeypatch):
    monkeypatch.setattr(mt, "_run_script", _fake_script_no_sentinel)
    result = mt.bring_up(_FAKE_PATH, 1, "slug", "/worktree")
    assert result == "incomplete"


def test_bringup_stops_without_running_executors(monkeypatch):
    # bringup returns NOT-READY → no rows should run
    script_calls = []

    def fake_run(path, args, timeout_s, cwd=None):
        script_calls.append(str(path))
        return 0, "BRINGUP: NOT-READY\nBRINGUP-COMPLETE\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    result = mt.run(**_base_run_kwargs(monkeypatch, extra_body=BODY_ONE_ROW))
    assert result.rows == []
    assert result.ticked == []


# ---------------------------------------------------------------------------
# HTTP rows error paths
# ---------------------------------------------------------------------------

def test_rows_incomplete(monkeypatch):
    call_count = [0]

    def fake_run(path, args, timeout_s, cwd=None):
        call_count[0] += 1
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        # manual-rows.sh without sentinel
        return 0, "ROW Row 1 PASS\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    kwargs = _base_run_kwargs(monkeypatch)
    result = mt.run(**kwargs)
    assert "rows-incomplete" in result.errors


def test_rows_timeout(monkeypatch):
    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        return -1, "", "timeout"

    monkeypatch.setattr(mt, "_run_script", fake_run)
    kwargs = _base_run_kwargs(monkeypatch)
    result = mt.run(**kwargs)
    assert "rows-timeout" in result.errors


# ---------------------------------------------------------------------------
# cli_argv
# ---------------------------------------------------------------------------

def test_cli_argv_rejected():
    result = mt.cli_argv("run `rm -rf /tmp/x` then check", _ProbeWithAllowed())
    assert result is None


def test_cli_argv_accepted():
    result = mt.cli_argv("`pytest tools/postplan-harness/tests`", _ProbeWithAllowed())
    assert result == ["pytest", "tools/postplan-harness/tests"]


def test_cli_argv_no_backticks():
    result = mt.cli_argv("no backticks here", _ProbeWithAllowed())
    assert result is None


# ---------------------------------------------------------------------------
# override guard: SKIP-HUMAN rows not ticked
# ---------------------------------------------------------------------------

def test_override_guard_skip_human(monkeypatch):
    body = (
        "## Manual Testing\n\n"
        "- [ ] **Row 1** — `bin/test-foo`\n"
    )
    tick_calls = []

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            tick_calls.append(args)
            return 0, "TICKED: 0\nTICK-COMPLETE\n", ""
        # manual-rows returns SKIP-HUMAN for Row 1
        return 0, "ROW Row 1 SKIP-HUMAN\nMANUAL-ROWS-COMPLETE\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    result = mt.run(
        pr=1, worktree="/fake/worktree", body=body,
        gh=FakeGh([body, body]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA, head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert "Row 1" not in result.ticked


# ---------------------------------------------------------------------------
# test_mixed_body (requirement a)
# ---------------------------------------------------------------------------

def test_mixed_body(monkeypatch):
    body = (
        "## Manual Testing\n\n"
        "- [ ] **Row 1** — `bin/test-postplan-arm-conditions` exits 0\n"
        "- [ ] **Row 2** — /ibl5/standings.php returns 200\n"
        "- [ ] **Row 3** — the standings table spacing looks right\n"
    )
    # Post-tick body: Row 1 ticked, others not
    post_body = (
        "## Manual Testing\n\n"
        "- [x] **Row 1** — `bin/test-postplan-arm-conditions` exits 0\n"
        "- [ ] **Row 2** — /ibl5/standings.php returns 200\n"
        "- [ ] **Row 3** — the standings table spacing looks right\n"
    )

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            return 0, "TICKED: 1\nTICKED-ROW: Row 1\nTICK-COMPLETE\n", ""
        # manual-rows: Row 1 SKIP-NOURL, Row 2 FAIL, Row 3 SKIP-HUMAN
        return (
            0,
            "ROW Row 1 SKIP-NOURL\nROW Row 2 FAIL http-500\nROW Row 3 SKIP-HUMAN\nMANUAL-ROWS-COMPLETE\n",
            "",
        )

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    # CLI probe: Row 1 passes (SKIP-NOURL → eligible for CLI)
    class _CliProbe:
        def allowed(self, argv):
            return probe_allowed(argv)

        def run(self, argv, timeout=120):
            if argv[0] == "bin/test-postplan-arm-conditions":
                return True, "all PASS"
            return False, "not found"

    result = mt.run(
        pr=1, worktree="/fake/worktree", body=body,
        gh=FakeGh([post_body]),
        probe=_CliProbe(),
        show_blob=_make_scripts_show(),
        master_sha=SHA, head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.ticked == ["Row 1"]
    assert result.all_ticked is False


# ---------------------------------------------------------------------------
# test_all_passing (requirement b)
# ---------------------------------------------------------------------------

def test_all_passing(monkeypatch):
    body = (
        "## Manual Testing\n\n"
        "- [ ] **Row 1** — `bin/test-foo`\n"
        "- [ ] **Row 2** — /ibl5/page.php returns 200\n"
    )
    post_body = (
        "## Manual Testing\n\n"
        "- [x] **Row 1** — `bin/test-foo`\n"
        "- [x] **Row 2** — /ibl5/page.php returns 200\n"
    )

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            return 0, "TICKED: 2\nTICKED-ROW: Row 1\nTICKED-ROW: Row 2\nTICK-COMPLETE\n", ""
        return (
            0,
            "ROW Row 1 PASS\nROW Row 2 PASS\nMANUAL-ROWS-COMPLETE\n",
            "",
        )

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    result = mt.run(
        pr=1, worktree="/fake/worktree", body=body,
        gh=FakeGh([post_body]),
        probe=FixtureProbe({}),
        show_blob=_make_scripts_show(),
        master_sha=SHA, head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.all_ticked is True
    assert armable.all_rows_ticked(post_body) is True


# ---------------------------------------------------------------------------
# test_tick_skipped_on_zero_passes
# ---------------------------------------------------------------------------

def test_tick_skipped_on_zero_passes(monkeypatch):
    tick_calls = []

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            tick_calls.append(args)
            return 0, "TICKED: 0\nTICK-COMPLETE\n", ""
        # all rows fail
        return 0, "ROW Row 1 FAIL err\nMANUAL-ROWS-COMPLETE\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    result = mt.run(**_base_run_kwargs(monkeypatch))
    # tick() is called but skips when pass_ids is empty — the script itself is not called
    assert len(tick_calls) == 0


# ---------------------------------------------------------------------------
# test_tick_unconfirmed
# ---------------------------------------------------------------------------

def test_tick_unconfirmed(monkeypatch):
    body = BODY_ONE_ROW

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            return 0, "TICKED: 2\nTICK-COMPLETE\n", ""
        return 0, "ROW Row 1 PASS\nMANUAL-ROWS-COMPLETE\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    # gh.pr_body() after tick still returns unticked body
    result = mt.run(
        pr=1, worktree="/fake/worktree", body=body,
        gh=FakeGh([body, body, body]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA, head_tree=lambda: "tree1",
        live=True, log=lambda s: None,
    )
    assert result.all_ticked is False
    assert any(e.startswith("tick-unconfirmed:") for e in result.errors)


# ---------------------------------------------------------------------------
# test_tree_gate
# ---------------------------------------------------------------------------

def test_tree_gate(monkeypatch):
    tick_calls = []

    def fake_run(path, args, timeout_s, cwd=None):
        name = str(path)
        if "bring-up" in name or "wt-bring" in name:
            return 0, "BRINGUP: UP\nBRINGUP-COMPLETE\n", ""
        if "tick" in name:
            tick_calls.append(args)
            return 0, "TICKED: 1\nTICK-COMPLETE\n", ""
        return 0, "ROW Row 1 PASS\nMANUAL-ROWS-COMPLETE\n", ""

    monkeypatch.setattr(mt, "_run_script", fake_run)
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))

    # head_tree: first call "tree1", second call "tree2"
    call_count = [0]

    def _head_tree():
        call_count[0] += 1
        return "tree1" if call_count[0] == 1 else "tree2"

    result = mt.run(
        pr=1, worktree="/fake/worktree", body=BODY_ONE_ROW,
        gh=FakeGh([BODY_ONE_ROW, BODY_ONE_ROW]),
        probe=_ProbeWithAllowed(),
        show_blob=_make_scripts_show(),
        master_sha=SHA, head_tree=_head_tree,
        live=True, log=lambda s: None,
    )
    assert len(tick_calls) == 0
    assert "tree-moved-mid-pass" in result.errors


# ---------------------------------------------------------------------------
# test_no_exception_escapes
# ---------------------------------------------------------------------------

def test_no_exception_escapes(monkeypatch):
    monkeypatch.setattr(mt, "docker_available", lambda: (True, ""))
    monkeypatch.setattr(mt, "resolve_slug", lambda w: ("harness-manual-rows-execute", ""))
    monkeypatch.setattr(mt, "_run_script", lambda *a, **kw: (_ for _ in ()).throw(OSError("disk full")))

    result = mt.run(**_base_run_kwargs(monkeypatch))
    assert isinstance(result, mt.ManualTestingResult)
    assert any(e.startswith("manual-testing-error:") for e in result.errors)
