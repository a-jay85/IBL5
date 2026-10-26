import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import tier_measure as tm

PATH = "ibl5/classes/Foo.php"


def _corpus():
    cases = [{"id": cid, "commit": "x", "status": "confirmed",
              "expected": [{"path": PATH, "start": 100, "end": 102}]} for cid in tm.BUG_CASES]
    clean = [{"id": cid, "commit": "x"} for cid in tm.CLEAN_CASES]
    return {"cases": cases, "clean": clean}


def _prompt_for(case):
    return "prompt-" + case["id"]


def _envelope(result, cost=1.0, **extra):
    env = {"type": "result", "is_error": False, "result": result,
           "total_cost_usd": cost, "usage": {"output_tokens": 321}}
    env.update(extra)
    return env


HIT = json.dumps([{"path": PATH, "line": 101, "body": "bug"}])
MISS = json.dumps([{"path": "elsewhere.php", "line": 5, "body": "other"}])


def _proc(env, rc=0):
    return subprocess.CompletedProcess([], rc, stdout=json.dumps(env), stderr="")


class FakeRunner:
    def __init__(self, cost=1.0, result=HIT):
        self.cost = cost
        self.result = result
        self.calls = []

    def __call__(self, argv, prompt, timeout):
        self.calls.append((argv, prompt, timeout))
        return _proc(_envelope(self.result, self.cost))


def _run(tmp_path, runner, *, cells=tm.CELLS, max_calls=None, already_spent=0.0,
         dry_run=False, name="out.jsonl"):
    out = tmp_path / name
    code = tm.run(Path("."), _corpus(), out, cells=cells, runner=runner,
                  max_calls=max_calls, already_spent=already_spent, dry_run=dry_run,
                  prompt_for=_prompt_for)
    return code, out


def _records(out):
    return [json.loads(line) for line in out.read_text().splitlines() if line.strip()]


def _write_canned(directory, cost):
    for run_idx, case_id, cell in tm.plan_calls(tm.CELLS):
        result = HIT if case_id in tm.BUG_CASES else "[]"
        (directory / f"{cell}__{case_id}__{run_idx}.json").write_text(
            json.dumps(_envelope(result, cost)))


def test_plan_calls_round_robin_order_and_count():
    calls = tm.plan_calls(tm.CELLS)
    assert len(calls) == 56
    names = [c[0] for c in tm.CELLS]
    assert [c[2] for c in calls[:4]] == names
    assert calls[0] == (1, tm.BUG_CASES[0], names[0])
    assert calls[4] == (1, tm.BUG_CASES[1], names[0])
    assert calls[24] == (1, tm.CLEAN_CASES[0], names[0])
    assert calls[32] == (2, tm.BUG_CASES[0], names[0])
    assert calls[-1] == (2, tm.BUG_CASES[-1], names[-1])
    assert all(c[0] == 1 for c in calls if c[1] in tm.CLEAN_CASES)
    assert len(tm.plan_calls(tm.CELLS[:2])) == 28


def test_build_argv_carries_effort_budget_and_toolless():
    argv = tm.build_argv("claude-opus-5-5", "max", "flag")
    assert argv == ["claude", "-p", "--model", "claude-opus-5-5", "--effort", "max",
                    "--tools", "", "--max-turns", "1", "--output-format", "json",
                    "--max-budget-usd", "1.50"]


def test_build_argv_settings_mechanism_replaces_effort_flag():
    argv = tm.build_argv("claude-opus-5-5", "high", "settings")
    assert "--effort" not in argv
    i = argv.index("--settings")
    assert json.loads(argv[i + 1]) == {"effortLevel": "high"}
    assert argv[argv.index("--tools") + 1] == ""
    with pytest.raises(ValueError):
        tm.build_argv("m", "high", "env")


def test_rejects_unknown_effort_level_before_any_call(tmp_path):
    class Boom:
        def __call__(self, *a):
            raise AssertionError("runner must not be called")

    with pytest.raises(ValueError):
        _run(tmp_path, Boom(), cells=(("bad", "claude-sonnet-5-5", "turbo"),))
    with pytest.raises(ValueError):
        tm.build_argv("m", "turbo", "flag")
    assert not (tmp_path / "out.jsonl").exists()


def test_must_stop_boundary_at_ceiling():
    assert tm.must_stop(23.50) is False
    assert tm.must_stop(23.51) is True


def test_missing_or_error_cost_charged_full_cap():
    assert tm.charged_cost(None) == 1.50
    assert tm.charged_cost({"is_error": True, "total_cost_usd": 0.2}) == 1.50
    assert tm.charged_cost({"is_error": False}) == 1.50
    assert tm.charged_cost({"is_error": False, "total_cost_usd": "0.2"}) == 1.50
    assert tm.charged_cost({"is_error": False, "total_cost_usd": 0.37}) == 0.37


def test_canned_run_truncates_at_ceiling_and_exits_3(tmp_path):
    canned = tmp_path / "canned"
    canned.mkdir()
    _write_canned(canned, 1.40)
    code, out = _run(tmp_path, tm.CannedRunner(canned))
    assert code == 3
    recs = _records(out)
    calls = [r for r in recs if "cell" in r]
    assert len(calls) == 17
    assert recs[-1]["budget_truncated"] is True
    assert recs[-1]["spent"] == pytest.approx(23.80)
    assert calls[0]["status"] == "ok" and calls[0]["hit"] is True
    assert calls[0]["output_tokens"] == 321
    assert calls[0]["cost_charged"] == 1.40


def test_canned_missing_file_is_call_error_charged_cap(tmp_path):
    canned = tmp_path / "canned"
    canned.mkdir()
    code, out = _run(tmp_path, tm.CannedRunner(canned), max_calls=1)
    assert code == 0
    rec = _records(out)[0]
    assert rec["status"] == "call-error"
    assert rec["hit"] is None
    assert rec["cost_charged"] == 1.50


def test_resume_skips_recorded_calls_and_counts_their_spend(tmp_path):
    plan = tm.plan_calls(tm.CELLS)
    cell_model = {c[0]: c[1] for c in tm.CELLS}

    def seed(path, cost):
        with open(path, "w") as fh:
            for run_idx, case_id, cell in plan[:3]:
                fh.write(json.dumps({"run_idx": run_idx, "case_id": case_id, "cell": cell,
                                     "status": "ok", "hit": True, "cost_charged": cost}) + "\n")

    out = tmp_path / "resume.jsonl"
    seed(out, 1.00)
    runner = FakeRunner(cost=1.0)
    code = tm.run(Path("."), _corpus(), out, cells=tm.CELLS, runner=runner, max_calls=1,
                  already_spent=0.0, dry_run=False, prompt_for=_prompt_for)
    assert code == 0
    assert len(runner.calls) == 1
    argv, prompt, _t = runner.calls[0]
    assert argv[argv.index("--model") + 1] == cell_model[plan[3][2]]
    assert prompt == "prompt-" + plan[3][1]
    assert [(r["run_idx"], r["case_id"], r["cell"]) for r in _records(out)[3:]] == [plan[3]]

    # recorded spend counts: 3 x 8.00 = 24.00 leaves no room for another 1.50 call
    out2 = tmp_path / "resume2.jsonl"
    seed(out2, 8.00)
    boom = FakeRunner()
    code = tm.run(Path("."), _corpus(), out2, cells=tm.CELLS, runner=boom, max_calls=None,
                  already_spent=0.0, dry_run=False, prompt_for=_prompt_for)
    assert code == 3
    assert boom.calls == []
    assert _records(out2)[-1] == {"budget_truncated": True, "spent": 24.0}


def test_resume_matches_identity_for_cell_subset(tmp_path):
    out = tmp_path / "subset.jsonl"
    cells = tm.CELLS[1:3]
    first = FakeRunner(cost=0.5)
    tm.run(Path("."), _corpus(), out, cells=cells, runner=first, max_calls=5,
           already_spent=0.0, dry_run=False, prompt_for=_prompt_for)
    second = FakeRunner(cost=0.5)
    tm.run(Path("."), _corpus(), out, cells=cells, runner=second, max_calls=None,
           already_spent=0.0, dry_run=False, prompt_for=_prompt_for)
    assert len(first.calls) == 5
    assert len(second.calls) == 28 - 5
    ids = [(r["run_idx"], r["case_id"], r["cell"]) for r in _records(out)]
    assert len(ids) == len(set(ids)) == 28


def test_already_spent_counts_toward_ceiling(tmp_path):
    code0, out0 = _run(tmp_path, FakeRunner(cost=1.0), name="a.jsonl")
    code20, out20 = _run(tmp_path, FakeRunner(cost=1.0), already_spent=20.0, name="b.jsonl")
    assert code0 == code20 == 3
    assert len([r for r in _records(out0) if "cell" in r]) == 24
    assert len([r for r in _records(out20) if "cell" in r]) == 4


def test_parse_failure_is_error_record_never_clean_pass(tmp_path):
    runner = FakeRunner(result="I looked at the diff and found nothing wrong.")
    code, out = _run(tmp_path, runner, max_calls=1)
    assert code == 0
    rec = _records(out)[0]
    assert rec["status"] == "parse-error"
    assert rec["hit"] is None
    assert tm.parse_findings(_envelope("no json here")) is None
    assert tm.parse_findings(_envelope('{"x": 1}')) is None
    assert tm.parse_findings(_envelope("[]")) == []
    summary = tm.summarize([rec])
    assert summary[rec["cell"]]["H_n"] == 0
    assert summary[rec["cell"]]["H"] is None
    assert summary[rec["cell"]]["error_rate"] == 1.0


def test_wrapped_findings_envelope_is_unwrapped(tmp_path):
    wrapped = json.dumps({"findings": [{"path": PATH, "line": 101, "body": "bug"}]})
    code, out = _run(tmp_path, FakeRunner(result=wrapped), max_calls=1)
    rec = _records(out)[0]
    assert rec["status"] == "ok"
    assert rec["hit"] is True
    assert rec["n_findings"] == 1


def test_timeout_is_recorded_and_charged_cap(tmp_path):
    def slow(argv, prompt, timeout):
        raise subprocess.TimeoutExpired(argv, timeout)

    code, out = _run(tmp_path, slow, max_calls=1)
    rec = _records(out)[0]
    assert rec["status"] == "timeout"
    assert rec["cost_charged"] == 1.50
    assert rec["hit"] is None


def _rec(run_idx, case_id, cell="c", status="ok", hit=None, n=None, cost=1.0, wall=2.0):
    return {"run_idx": run_idx, "case_id": case_id, "cell": cell, "model": "m",
            "effort": "high", "status": status, "hit": hit, "n_findings": n,
            "cost_charged": cost, "wall_s": wall, "output_tokens": 10}


def test_summarize_hit_rate_strict_score_and_spread():
    recs = []
    miss_run1 = {tm.BUG_CASES[0]}
    miss_run2 = {tm.BUG_CASES[1], tm.BUG_CASES[2]}
    for cid in tm.BUG_CASES:
        recs.append(_rec(1, cid, hit=cid not in miss_run1, n=1))
        recs.append(_rec(2, cid, hit=cid not in miss_run2, n=1))
    recs.append(_rec(1, tm.CLEAN_CASES[0], n=1))
    recs.append(_rec(1, tm.CLEAN_CASES[1], n=0))
    recs.append({"budget_truncated": True, "spent": 99.0})
    m = tm.summarize(recs)["c"]
    assert m["H_n"] == 12 and m["H_hits"] == 9
    assert m["H"] == pytest.approx(0.75)
    assert (m["S"].hits, m["S"].scored) == (3, 6)
    assert m["spread"] == pytest.approx(abs(5 / 6 - 4 / 6))
    assert m["FP"] == 1
    assert m["C"] == pytest.approx(1.0)
    assert m["T"] == pytest.approx(2.0)
    assert m["completed_bug_runs"] == 12
    assert m["error_rate"] == 0.0
    assert m["inconclusive"] is False
    md = tm.render_markdown({"c": m}, 14.0, False)
    assert "| cell | model | effort | bug runs | H | S | spread | FP | C (USD/call) |" in md
    assert "total spent: $14.00" in md
    assert "reviewer-eval v=tier-measure c=c recall=3/6" in md


def test_inconclusive_below_8_bug_runs_or_over_25pct_errors():
    few = [_rec(1, cid, cell="few", hit=True, n=0) for cid in tm.BUG_CASES]
    few.append(_rec(2, tm.BUG_CASES[0], cell="few", hit=True, n=0))
    s = tm.summarize(few)["few"]
    assert s["completed_bug_runs"] == 7
    assert s["inconclusive"] is True

    def full(cell, timeouts):
        recs = []
        for cid in tm.BUG_CASES:
            recs.append(_rec(1, cid, cell=cell, hit=True, n=0))
            recs.append(_rec(2, cid, cell=cell, hit=True, n=0))
        recs += [_rec(1, tm.CLEAN_CASES[0], cell=cell, n=0),
                 _rec(1, tm.CLEAN_CASES[1], cell=cell, n=0)]
        recs += [_rec(2, tm.BUG_CASES[0], cell=cell, status="timeout", cost=1.5)
                 for _ in range(timeouts)]
        return recs

    ok = tm.summarize(full("ok", 4))["ok"]          # 4/18 = 22%
    bad = tm.summarize(full("bad", 5))["bad"]       # 5/19 = 26%
    assert ok["inconclusive"] is False
    assert bad["inconclusive"] is True
    assert bad["completed_bug_runs"] == 12
    assert bad["error_rate"] > 0.25


def test_dry_run_never_invokes_runner(tmp_path, capsys):
    class Boom:
        def __call__(self, *a):
            raise AssertionError("runner must not be called")

    code, out = _run(tmp_path, Boom(), dry_run=True)
    assert code == 0
    assert not out.exists()
    text = capsys.readouterr().out
    assert "planned_calls=56" in text
    assert "ceiling=25.00 per_call_cap=1.50" in text


def test_report_concatenated_records(tmp_path):
    src = tmp_path / "in.jsonl"
    rows = [_rec(1, cid, hit=True, n=0, cost=2.0) for cid in tm.BUG_CASES]
    rows.append({"budget_truncated": True, "spent": 3.0})
    src.write_text("\n".join(json.dumps(r) for r in rows) + "\n")
    md = tmp_path / "out.md"
    assert tm.main(["report", "--in", str(src), "--md", str(md)]) == 0
    text = md.read_text()
    assert "total spent: $12.00 (budget truncated)" in text
    assert "inconclusive" in text


def test_unknown_cli_flag_exits_2(tmp_path):
    with pytest.raises(SystemExit) as e:
        tm.main(["run", "--repo-root", ".", "--out", str(tmp_path / "o.jsonl"), "--bogus"])
    assert e.value.code == 2
    with pytest.raises(SystemExit) as e:
        tm.main(["run", "--repo-root", ".", "--out", str(tmp_path / "o.jsonl"),
                 "--effort-mechanism", "env"])
    assert e.value.code == 2
    with pytest.raises(SystemExit) as e:
        tm.main(["run", "--repo-root", ".", "--out", str(tmp_path / "o.jsonl"),
                 "--drop-cell", "nope"])
    assert e.value.code == 2


def test_subprocess_runner_isolates_like_claude_cli_call(monkeypatch):
    seen = {}

    def fake_run(argv, **kw):
        seen.update(kw)
        seen["cwd_existed"] = os.path.isdir(kw["cwd"])
        return subprocess.CompletedProcess(argv, 0, stdout="{}", stderr="")

    monkeypatch.setattr(tm.subprocess, "run", fake_run)
    repo = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    tm.subprocess_runner(["claude"], "x" * (tm.MAX_PROMPT_BYTES + 10), 5)
    assert seen["cwd_existed"]
    assert not os.path.realpath(seen["cwd"]).startswith(os.path.realpath(repo))
    assert seen["env"]["CLAUDE_HEADLESS"] == "1"
    assert seen["input"].endswith("[TRUNCATED]")
