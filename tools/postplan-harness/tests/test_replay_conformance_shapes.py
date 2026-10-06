"""bench/replay_phase_omission.py: compare classes 3 and 4 and the --methods replay."""
import importlib.util
import json
import os
import stat
import subprocess
import sys
from pathlib import Path

import pytest

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, str(HARNESS))

_spec = importlib.util.spec_from_file_location(
    "replay_phase_omission_shapes", HARNESS / "bench" / "replay_phase_omission.py")
rpo = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rpo)

SCRIPT = str(HARNESS / "bench" / "replay_phase_omission.py")


def _side(runs: dict) -> dict:
    return {"runs": {n: {"slug": n, "items": i, "notes": notes, "audit_items": [],
                         "audit_match": True} for n, (i, notes) in runs.items()},
            "skipped": {}, "replayable": len(runs)}


ITEM = "MISSING-PHASE: 1 — Mask (phase cites bin/wt-up; none appeared in the diff)"


def test_compare_classifies_heading_named_as_class3():
    before = _side({"x": ([ITEM], [])})
    after = _side({"x": ([], ["HEADING-NAMED-PHASE: 1 — Mask (heading names Dockerfile, "
                              "changed as Dockerfile)"])})
    res = rpo.compare(before, after)
    assert res["class3"] == [ITEM]
    assert res["regressions"] == []
    assert res["exit_code"] == 0


def test_compare_classifies_non_repo_citation_as_class4():
    item = "MISSING-PHASE: 8 — Ship (phase cites /post-plan; none appeared in the diff)"
    before = _side({"x": ([item], [])})
    after = _side({"x": ([], ["NON-REPO-CITATION: 8 — /post-plan (leading / or ~/ and no "
                              "exact, suffix, or directory match)"])})
    res = rpo.compare(before, after)
    assert res["class4"] == [item]
    assert res["exit_code"] == 0


def test_compare_prefers_uncheckable_over_citation_note():
    item = "MISSING-PHASE: 8 — Ship (phase cites /post-plan; none appeared in the diff)"
    before = _side({"x": ([item], [])})
    after = _side({"x": ([], ["NON-REPO-CITATION: 8 — /post-plan (x)",
                              "UNCHECKABLE-PHASE: 8 — Ship (no repo-path citation among /post-plan)"])})
    res = rpo.compare(before, after)
    assert res["class1"] == [item]
    assert res["class4"] == []


def test_compare_note_for_other_phase_number_is_still_regression():
    before = _side({"x": ([ITEM], [])})
    after = _side({"x": ([], ["HEADING-NAMED-PHASE: 2 — Other (heading names Dockerfile, "
                              "changed as Dockerfile)"])})
    res = rpo.compare(before, after)
    assert res["regressions"] == [ITEM]
    assert res["exit_code"] == 2


def test_render_lists_new_classes():
    before = _side({"x": ([ITEM], []),
                    "y": (["MISSING-PHASE: 8 — Ship (phase cites /post-plan; none appeared)"], [])})
    after = _side({"x": ([], ["HEADING-NAMED-PHASE: 1 — Mask (x)"]),
                   "y": ([], ["NON-REPO-CITATION: 8 — /post-plan (x)"])})
    out = rpo.render(rpo.compare(before, after))
    assert "class-3 heading-named: 1" in out
    assert "class-4 non-repo-citation: 1" in out


# --- --methods replay -------------------------------------------------------------------

METHOD_PLAN = (
    "# Plan: Methods\n\n## Phase 1: Tests\n\nEdit `bin/test-x`.\n\n"
    "## Verification Matrix\n\n| # | What | Test type | Timing | File |\n|---|---|---|---|---|\n"
    "| 1 | x | CLI-executable | post-impl | `bin/test-x` |\n\n"
    "## Required Test Methods\n\n- `step67-clean`\n- `testGone`\n"
)


def _methods_fixture(tmp_path: Path, pr_number=2506) -> Path:
    out = tmp_path / "out"
    plan = tmp_path / "plan.md"
    plan.write_text(METHOD_PLAN)
    d = out / "live-m-1"
    d.mkdir(parents=True)
    result = {"slug": "m", "plan": {"path": str(plan)},
              "classification": {"files": ["bin/test-x"]}}
    if pr_number is not None:
        result["pr_number"] = pr_number
    (d / "result.json").write_text(json.dumps(result))
    return out


def _replay(out, **kw):
    return rpo.replay(str(out), str(HARNESS), str(HARNESS.parents[1]), tracked=[], **kw)


def test_methods_replay_uses_injected_diff_fetch_and_filters_items(tmp_path):
    out = _methods_fixture(tmp_path)
    res = _replay(out, methods={"live-m-1"}, diff_fetch=lambda pr: "+  step67-clean)\n")
    run = res["runs"]["live-m-1"]
    assert run["method_items"] == [
        "MISSING-METHOD: testGone (plan required a test method the diff never wrote)"]
    assert run["method_source"] == "gh pr diff 2506"


def test_methods_replay_run_without_pr_number_is_skipped(tmp_path):
    out = _methods_fixture(tmp_path, pr_number=None)
    res = _replay(out, methods="all", diff_fetch=lambda pr: "")
    assert res["runs"]["live-m-1"]["method_items"] is None
    assert res["skipped"]["methods-no-pr"] == 1


def test_methods_replay_fetch_failure_is_recorded_not_raised(tmp_path):
    out = _methods_fixture(tmp_path)

    def boom(pr):
        raise RuntimeError("gh: not logged in")

    res = _replay(out, methods="all", diff_fetch=boom)
    run = res["runs"]["live-m-1"]
    assert run["method_items"] is None
    assert "not logged in" in run["method_error"]
    assert res["skipped"]["methods-no-diff"] == 1


def test_methods_replay_not_requested_adds_no_keys(tmp_path):
    out = _methods_fixture(tmp_path)
    res = _replay(out)
    assert "method_items" not in res["runs"]["live-m-1"]


def _fake_gh(tmp_path: Path, exit_code: int, stdout: str = "", stderr: str = "") -> Path:
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    counter = tmp_path / "gh-calls"
    gh = bindir / "gh"
    gh.write_text(f"#!/bin/sh\necho run >> '{counter}'\n"
                  f"printf '%s' '{stdout}'\nprintf '%s' '{stderr}' >&2\nexit {exit_code}\n")
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    return bindir


def test_gh_pr_diff_uses_cache_then_gh(tmp_path, monkeypatch):
    bindir = _fake_gh(tmp_path, 0, stdout="+fake\n")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    cache = tmp_path / "cache"
    first = rpo._gh_pr_diff(2506, str(cache))
    assert first.strip() == "+fake"
    assert (cache / "2506.diff").is_file()
    (cache / "2506.diff").write_text("+cached")
    assert rpo._gh_pr_diff(2506, str(cache)) == "+cached"
    assert (tmp_path / "gh-calls").read_text().count("run") == 1


def test_gh_pr_diff_nonzero_exit_raises(tmp_path, monkeypatch):
    bindir = _fake_gh(tmp_path, 1, stderr="no pull requests found")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    cache = tmp_path / "cache"
    with pytest.raises(RuntimeError, match="no pull requests found"):
        rpo._gh_pr_diff(2506, str(cache))
    assert not (cache / "2506.diff").exists()


def test_compare_reports_method_flips_without_changing_exit():
    def side(items):
        s = _side({"x": ([], [])})
        s["runs"]["x"]["method_items"] = items
        return s

    before = side(["MISSING-METHOD: step67 (a)", "MISSING-METHOD: New (b)"])
    after = side(["MISSING-METHOD: testB (c)"])
    res = rpo.compare(before, after)
    assert res["method_cleared"] == ["x: MISSING-METHOD: New (b)", "x: MISSING-METHOD: step67 (a)"]
    assert res["method_appeared"] == ["x: MISSING-METHOD: testB (c)"]
    assert res["method_still"] == []
    assert res["exit_code"] == 0
    out = rpo.render(res)
    assert "methods cleared: 2" in out
    assert "methods appeared: 1" in out
    assert "methods still-held: 0" in out


def test_methods_flag_rejects_missing_value_and_requires_json():
    r = subprocess.run([sys.executable, SCRIPT, "--methods"], capture_output=True, text=True)
    assert r.returncode == 2
    assert "expected one argument" in r.stderr
    r = subprocess.run([sys.executable, SCRIPT, "--methods", "all"],
                       capture_output=True, text=True)
    assert r.returncode == 2
    assert "--json is required" in r.stderr
