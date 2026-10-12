"""bench/replay_phase_omission.py: replay and compare over tmp fixtures."""
import importlib.util
import json
import os
import sys
from pathlib import Path

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, str(HARNESS))

_spec = importlib.util.spec_from_file_location(
    "replay_phase_omission", HARNESS / "bench" / "replay_phase_omission.py")
rpo = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rpo)

PLAN_TEXT = "# P\n\n## Phase 1: Elsewhere\n\nCites `/proc/self/fd/1` only.\n"


def _run(out: Path, name: str, result: dict) -> Path:
    d = out / name
    d.mkdir(parents=True)
    (d / "result.json").write_text(json.dumps(result))
    return d


def _fixtures(tmp_path: Path) -> Path:
    out = tmp_path / "out"
    plan = tmp_path / "plan.md"
    plan.write_text(PLAN_TEXT)
    _run(out, "live-a", {"plan": None, "classification": {"files": []}})
    _run(out, "live-b", {"plan": {"path": str(tmp_path / "gone.md")},
                         "classification": {"files": []}})
    _run(out, "live-c", {"plan": {"path": str(plan)}})
    _run(out, "live-d", {"slug": "d", "plan": {"path": str(plan)},
                         "classification": {"files": ["ibl5/docs/API_GUIDE.md"]}})
    return out


def test_replay_skips_null_and_missing_plans(tmp_path):
    out = _fixtures(tmp_path)
    res = rpo.replay(str(out), str(HARNESS), str(HARNESS.parents[1]), tracked=("bin/wt-up",))
    assert res["replayable"] == 1
    assert res["skipped"] == {"plan-null": 1, "plan-missing": 1, "no-files": 1}
    run = res["runs"]["live-d"]
    assert run["items"] == []
    assert len(run["notes"]) == 2
    assert run["notes"][0].startswith("NON-REPO-CITATION: 1")
    assert run["notes"][1].startswith("UNCHECKABLE-PHASE: 1")


def _side(runs: dict) -> dict:
    return {"runs": {n: {"slug": n, "items": i, "notes": notes, "audit_items": [],
                         "audit_match": True} for n, (i, notes) in runs.items()},
            "skipped": {}, "replayable": len(runs)}


def test_compare_classifies_uncheckable_and_no_diff():
    before = _side({
        "x": (["MISSING-PHASE: 1 — A (phase cites /proc/x; none appeared in the diff)"], []),
        "y": (["MISSING-PHASE: 2 — B (phase cites bin/q; none appeared in the diff)"], []),
    })
    after = _side({
        "x": ([], ["UNCHECKABLE-PHASE: 1 — A (no repo-path citation among /proc/x)"]),
        "y": ([], ["NO-DIFF-PHASE: 2 — B (exempt: queues the plan later)"]),
    })
    res = rpo.compare(before, after)
    assert len(res["class1"]) == 1
    assert len(res["class2"]) == 1
    assert res["regressions"] == []
    assert res["exit_code"] == 0


def test_compare_flags_unexplained_disappearance_as_regression():
    line = "MISSING-PHASE: 1 — A (phase cites bin/q; none appeared in the diff)"
    res = rpo.compare(_side({"x": ([line], [])}), _side({"x": ([], [])}))
    assert res["regressions"] == [line]
    assert res["exit_code"] == 2
    new = "MISSING-PHASE: 3 — C (phase cites bin/z; none appeared in the diff)"
    res = rpo.compare(_side({"x": ([], [])}), _side({"x": ([new], [])}))
    assert res["appeared"] == [new]
    assert res["exit_code"] == 2


def test_compare_keys_on_phase_number_not_item_text():
    old = "MISSING-PHASE: 1 — A (phase cites /proc/x, bin/q; none appeared in the diff)"
    new = "MISSING-PHASE: 1 — A (phase cites bin/q; none appeared in the diff)"
    res = rpo.compare(_side({"x": ([old], [])}), _side({"x": ([new], [])}))
    assert res["regressions"] == []
    assert res["appeared"] == []
    assert res["exit_code"] == 0


def test_replay_audit_crosscheck(tmp_path):
    out = _fixtures(tmp_path)
    audit = ("2026-10-01 phase2 residual-phase: MISSING-PHASE: 1 — Phase 1: Elsewhere "
             "(phase cites /proc/self/fd/1; none appeared in the diff)\n")
    (out / "live-d" / "audit.log").write_text(audit)
    res = rpo.replay(str(out), str(HARNESS), str(HARNESS.parents[1]), tracked=("bin/wt-up",))
    assert res["runs"]["live-d"]["audit_match"] is False
    assert rpo.compare(res, res)["audit_mismatch"] == 1


def test_unknown_flag_exits_2_with_usage():
    """argparse parse_args rejects a typo'd flag; parse_known_args would swallow it and replay."""
    import subprocess
    script = str(HARNESS / "bench" / "replay_phase_omission.py")
    r = subprocess.run([sys.executable, script, "--no-such-flag"],
                       capture_output=True, text=True)
    assert r.returncode == 2
    assert "usage:" in r.stderr
