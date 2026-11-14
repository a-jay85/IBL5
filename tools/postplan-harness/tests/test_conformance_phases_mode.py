"""`harness.conformance --phases`: MISSING-PHASE items on the working tree (the #3943 replay)."""
import subprocess

from harness import conformance
from harness.conformance import main

MARKER_REASON = "landed on master in 07ff351b8 (#3834)"

PHASE_1 = "## Phase 1: Guard the route\n\nEdit `src/Guard.php` to add the check.\n"
PHASE_2 = "## Phase 2: Wire the route\n\nEdit `src/Route.php` to call the guard.\n"
PHASE_3 = "## Phase 3: Docs and test\n\nUpdate `README.md` and add `tests/GuardTest.php`.\n"

HELD_FM = "---\nauto_merge: false\nhold_trigger: security-irreducible\n---\n\n"


def _run_git(repo, *args):
    subprocess.run(["git", "-c", "user.email=t@example.com",
                    "-c", "user.name=Phases Test", *args],
                   cwd=repo, check=True, capture_output=True, text=True)


def _make_repo(tmp_path):
    """Master holds Guard.php, Route.php, README.md; the diff touches only README.md and a new test."""
    repo = tmp_path / "repo"
    (repo / "src").mkdir(parents=True)
    (repo / "tests").mkdir()
    (repo / "src" / "Guard.php").write_text("<?php // guard\n")
    (repo / "src" / "Route.php").write_text("<?php // route\n")
    (repo / "README.md").write_text("seed\n")
    _run_git(repo, "init", "-q", ".")
    _run_git(repo, "add", "src/Guard.php", "src/Route.php", "README.md")
    _run_git(repo, "commit", "-qm", "seed")
    _run_git(repo, "update-ref", "refs/remotes/origin/master", "HEAD")
    (repo / "README.md").write_text("seed\nedited\n")
    (repo / "tests" / "GuardTest.php").write_text("<?php // test\n")
    return repo


def _write_plan(tmp_path, *, frontmatter=HELD_FM, p1=PHASE_1, p2=PHASE_2, tail=""):
    """Plan file OUTSIDE the repo; asserts it parses so no test passes vacuously."""
    from harness.planfile import locate_plan
    plan_path = tmp_path / "plans" / "phases.md"
    plan_path.parent.mkdir(parents=True)
    plan_path.write_text(f"{frontmatter}# Synthetic 3943 plan\n\n{p1}\n{p2}\n{PHASE_3}{tail}")
    parsed = locate_plan(slug=None, plans_dir=None, explicit_path=str(plan_path))
    assert parsed.found, "fixture plan did not parse as a plan"
    assert len(parsed.phases) == 3, "fixture plan did not parse three phases"
    return plan_path


def _marked(phase, reason=MARKER_REASON):
    return phase + f"\n**No diff:** {reason}\n"


def _run(capsys, plan, repo, flag="--phases"):
    rc = main([flag, str(plan), str(repo)])
    cap = capsys.readouterr()
    return rc, cap.out, cap.err


def test_replay_3943_unmarked_landed_phases_fail(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 1
    assert "MISSING-PHASE: 1" in out
    assert "MISSING-PHASE: 2" in out
    assert "MISSING-PHASE: 3" not in out


def test_hint_names_the_no_diff_fix(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    _, out, _ = _run(capsys, plan, repo)
    hints = [ln for ln in out.splitlines() if ln.startswith("HINT:")]
    assert hints
    assert "landed on master in <sha> (#PR)" in hints[0]


def test_replay_3943_no_diff_markers_pass(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path, p1=_marked(PHASE_1), p2=_marked(PHASE_2))
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 0
    assert "MISSING-PHASE:" not in out


def test_short_no_diff_reason_still_fails(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path, p1=_marked(PHASE_1, "on master"), p2=_marked(PHASE_2, "on master"))
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 1
    assert "MISSING-PHASE: 1" in out


def test_out_of_scope_listing_passes(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path, tail="\n## Out of Scope\n\n- Phase 1 landed already.\n- Phase 2 landed already.\n")
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 0
    assert "MISSING-PHASE:" not in out


def test_reminder_printed_for_held_plan_with_landed_markers(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path, p1=_marked(PHASE_1), p2=_marked(PHASE_2))
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 0
    reminders = [ln for ln in out.splitlines() if ln.startswith("REMINDER:")]
    assert reminders
    assert "security-irreducible" in reminders[0]


def test_no_reminder_without_hold_trigger(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path, frontmatter="", p1=_marked(PHASE_1), p2=_marked(PHASE_2))
    rc, out, _ = _run(capsys, plan, repo)
    assert rc == 0
    assert "REMINDER:" not in out


def test_phases_mode_rejects_relative_plan_path(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    rc = main(["--phases", "plans/phases.md", str(repo)])
    assert rc == 2
    assert "must be absolute" in capsys.readouterr().err


def test_near_miss_phase_flag_rejected(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    rc, _out, _err = _run(capsys, plan, repo, flag="--phase")
    assert rc == 2


def test_phases_mode_exception_returns_2(tmp_path, capsys, monkeypatch):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)

    def boom(*_a, **_k):
        raise RuntimeError("boom")

    monkeypatch.setattr(conformance, "_changed_files", boom)
    rc, _out, err = _run(capsys, plan, repo)
    assert rc == 2
    assert "could not evaluate" in err


def test_default_mode_ignores_phase_omissions(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    rc = main([str(plan), str(repo)])
    out = capsys.readouterr().out
    assert "MISSING-PHASE:" not in out
    assert rc in (0, 1)
