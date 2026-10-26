"""bench/compare_fidelity_shadow.py: buckets, exit criteria, rejected flags, shadow pr id.

No test makes a live LLM call. The reviewer is a FixtureLlm serving canned verdict text, and
`fidelity.verdict_path` is redirected under tmp_path so nothing is written to /tmp.
"""
import importlib.util
import json
import os
from pathlib import Path

import pytest

from harness import fidelity
from harness.adapters.llm import FixtureLlm

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

_spec = importlib.util.spec_from_file_location(
    "compare_fidelity_shadow_t", HARNESS / "bench" / "compare_fidelity_shadow.py")
cfs = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(cfs)

SEEDED = HARNESS / "tests" / "fixtures" / "fidelity-shadow" / "seeded"


# --- builders ----------------------------------------------------------------------------

def shadow_fields(hold3=False, hold18=False, consistent=True) -> dict:
    return {
        "schema": "fidelity-shadow/1",
        "planned_landed": {
            "hold": hold3,
            "missing": [{"kind": "file", "token": "tools/demo/helper.py", "why": "never added"}]
            if hold3 else []},
        "merge_danger": {"door": "one-way" if hold18 else "two-way", "hold": hold18,
                         "why": "migration drops a column" if hold18 else "additive change"},
        "body_numbers": {
            "consistent": consistent,
            "mismatches": [] if consistent else [{"claim": "3 tests", "actual": "2 tests"}]},
    }


def shadow_rec(status="present", hold3=False, hold18=False, consistent=True,
               old=None, plan_blind=False) -> dict:
    rec = {"status": status, "source": "verdict-1", "reviewed_tree": None,
           "plan_blind": plan_blind}
    if status in ("present", "carried"):
        rec["fields"] = shadow_fields(hold3, hold18, consistent)
    if old is not None:
        rec["old"] = old
    return rec


def write_run(out_dir, name, shadow=None, arm_conditions=None, plan_found=True, pr=4242,
              packet=False):
    d = Path(out_dir) / f"live-{name}"
    d.mkdir(parents=True)
    result = {"slug": name, "pr_number": pr, "plan": {"found": plan_found, "path": "/x/plan.md"},
              "fidelity": {}}
    if shadow is not None:
        result["fidelity"]["shadow"] = shadow
    if arm_conditions is not None:
        result["arm"] = {"armed": False, "conditions": arm_conditions}
    (d / "result.json").write_text(json.dumps(result))
    if packet:
        p = d / "fidelity-packet"
        p.mkdir()
        (p / "plan.md").write_text("# Plan: x\n\n## Phase 1: x\n\nDo it.\n")
        (p / "plan-index.txt").write_text("1 Phase 1\n")
        (p / "diff.patch").write_text(
            "diff --git a/a.py b/a.py\n--- a/a.py\n+++ b/a.py\n@@ -1 +1,2 @@\n x\n+y\n")
        (p / "pr-body.md").write_text("Adds y.\n")
        (p / "context.md").write_text(
            f"# Context\n\nPR_NUMBER: {pr}\nVERDICT_OUTPUT_PATH: /tmp/real-{pr}.md\n")
    return d


def verdict_text(hold3=False, hold18=False, consistent=True) -> str:
    block = json.dumps(shadow_fields(hold3, hold18, consistent), indent=2)
    return ("## 6d. Fidelity checks\n\nAll good.\n\n## SHADOW-ARMING\n\n```json\n"
            f"{block}\n```\n\nREADY\n")


def write_canned(canned_dir, overrides=None):
    """One reply per seeded case matching its expect.json; `overrides` maps case -> kwargs."""
    canned_dir.mkdir(parents=True, exist_ok=True)
    for case_dir in sorted(SEEDED.iterdir()):
        expect = json.loads((case_dir / "expect.json").read_text())["expect"]
        kwargs = {"hold3": expect.get("3") == "hold", "hold18": expect.get("18") == "hold",
                  "consistent": expect.get("body_numbers") != "mismatch"}
        kwargs.update((overrides or {}).get(case_dir.name, {}))
        (canned_dir / f"{case_dir.name}.md").write_text(verdict_text(**kwargs))


@pytest.fixture(autouse=True)
def redirect_verdicts(tmp_path, monkeypatch):
    """Send every verdict file under tmp_path and record which pr ids were asked for."""
    seen = []

    def _path(pr):
        seen.append(str(pr))
        return str(tmp_path / f"verdict-{pr}.md")

    monkeypatch.setattr(fidelity, "verdict_path", _path)
    return seen


def run_cli(argv, capsys):
    rc = cfs.main(argv)
    return rc, capsys.readouterr().out


def corpus_json(tmp_path, capsys, extra=()):
    out_json = tmp_path / "res.json"
    rc, text = run_cli(["--out-dir", str(tmp_path / "out"), "--json", str(out_json), *extra],
                       capsys)
    assert rc == 0
    return json.loads(out_json.read_text()), text


def crit(res, cid):
    return next(c for c in res["criteria"] if c["id"] == cid)


# --- corpus buckets ----------------------------------------------------------------------

def test_agree_and_disagree_buckets(tmp_path, capsys):
    out = tmp_path / "out"
    write_run(out, "ah", shadow_rec(hold3=True, old={"3": "hold", "18": "clear"}))
    write_run(out, "ac", shadow_rec(hold3=False, old={"3": "clear", "18": "clear"}))
    write_run(out, "oho", shadow_rec(hold3=False, old={"3": "hold", "18": "clear"}))
    write_run(out, "nho", shadow_rec(hold3=True, old={"3": "clear", "18": "clear"}))
    res, text = corpus_json(tmp_path, capsys)
    c3 = res["corpus"]["conditions"]["3"]
    for b in ("agree-hold", "agree-clear", "old-hold-only", "new-hold-only"):
        assert c3["buckets"][b] == 1, b
    assert c3["lists"]["old-hold-only"] == ["live-oho"]
    assert c3["lists"]["new-hold-only"] == ["live-nho"]
    assert c3["compared"] == 4
    assert "FLIP EXIT CRITERIA" in text


def test_unknown_never_counts_as_agree(tmp_path, capsys):
    out = tmp_path / "out"
    write_run(out, "real", shadow_rec(hold3=False, old={"3": "clear", "18": "clear"}))
    bad = shadow_rec(status="invalid:bad-json", old={"3": "clear", "18": "clear"})
    bad["states"] = {"3": "clear", "18": "clear", "body_numbers": "consistent"}   # poison
    write_run(out, "bad", bad)
    res, _ = corpus_json(tmp_path, capsys)
    for n in ("3", "18"):
        cond = res["corpus"]["conditions"][n]
        assert cond["buckets"]["unknown"] == 1
        assert cond["buckets"]["agree-clear"] == 1
        assert cond["compared"] == 1          # the E5 denominator excludes the unknown run
    assert "1/1 = 100.0%" in crit(res, "E5")["detail"]
    assert cfs.bucket("unknown", "clear") == "unknown"
    assert cfs.bucket("unknown", "hold") == "unknown"


def test_pre_shadow_runs_counted_separately(tmp_path, capsys):
    out = tmp_path / "out"
    write_run(out, "old", shadow=None,
              arm_conditions=[{"number": 3, "blocked": True}, {"number": 18, "blocked": False}])
    res, _ = corpus_json(tmp_path, capsys)
    corpus = res["corpus"]
    assert corpus["totals"]["pre_shadow"] == 1
    for n in ("3", "18"):
        assert corpus["conditions"][n]["buckets"]["pre-shadow"] == 1
        assert corpus["conditions"][n]["compared"] == 0
    assert corpus["status_histogram"] == {}
    assert corpus["conditions"]["3"]["old_hold"] == 1
    assert corpus["conditions"]["18"]["old_clear"] == 1


def test_old_outcome_falls_back_to_arm_conditions(tmp_path, capsys):
    # condition 18 listed BEFORE 3 so a list-index match would pick the wrong entry
    conds = [{"number": 18, "blocked": True}, {"number": 3, "blocked": False}]
    result = {"arm": {"conditions": conds}, "fidelity": {"shadow": shadow_rec()}}
    assert cfs.old_outcomes(result) == {"3": "clear", "18": "hold"}
    assert cfs.old_outcomes({"arm": {"conditions": [{"number": 18, "holds": True}]}}) == \
        {"3": "not-evaluated", "18": "hold"}
    assert cfs.old_outcomes({}) == {"3": "not-evaluated", "18": "not-evaluated"}
    out = tmp_path / "out"
    write_run(out, "fb", shadow_rec(hold18=True), arm_conditions=conds)
    res, _ = corpus_json(tmp_path, capsys)
    assert res["corpus"]["conditions"]["18"]["buckets"]["agree-hold"] == 1
    assert res["corpus"]["conditions"]["3"]["buckets"]["agree-clear"] == 1


def test_plan_blind_excluded_from_condition_3(tmp_path, capsys):
    out = tmp_path / "out"
    write_run(out, "blind", shadow_rec(plan_blind=True, old={"3": "clear", "18": "clear"}))
    res, text = corpus_json(tmp_path, capsys)
    corpus = res["corpus"]
    assert corpus["totals"]["plan_blind_excluded_cond3"] == 1
    assert sum(corpus["conditions"]["3"]["buckets"].values()) == 0
    assert corpus["conditions"]["18"]["buckets"]["agree-clear"] == 1
    assert "plan-blind excluded from condition (3)=1" in text


# --- seeded mode and exit criteria -------------------------------------------------------

def test_seeded_canned_miss_fails_e1(tmp_path, capsys):
    canned = tmp_path / "canned"
    write_canned(canned, {"missing-planned-file": {"hold3": False}})
    out_json = tmp_path / "res.json"
    argv = ["--seeded", "--canned", str(canned), "--out-dir", str(tmp_path / "out"),
            "--json", str(out_json)]
    rc, text = run_cli(argv, capsys)
    assert rc == 0                      # no --gate: prints and exits 0
    assert "E1 seeded recall: FAIL" in text
    res = json.loads(out_json.read_text())
    assert res["seeded"]["cases"]["missing-planned-file"]["keys"]["3"]["score"] == "miss"
    rc, _ = run_cli(argv + ["--gate"], capsys)
    assert rc == 1


def test_seeded_canned_all_hits_passes_e1_e2(tmp_path, capsys):
    canned = tmp_path / "canned"
    write_canned(canned)
    out_json = tmp_path / "res.json"
    rc, text = run_cli(["--seeded", "--canned", str(canned), "--out-dir", str(tmp_path / "out"),
                        "--json", str(out_json)], capsys)
    assert rc == 0
    res = json.loads(out_json.read_text())
    assert crit(res, "E1")["status"] == "PASS"
    assert crit(res, "E2")["status"] == "PASS"
    assert res["seeded"]["counts"]["miss"] == 0
    assert "E1 seeded recall: PASS" in text and "E2 seeded controls: PASS" in text
    assert crit(res, "E7")["status"] == "MANUAL"
    # --gate still fails: E3-E6 have no data in --seeded mode and PENDING counts as failure
    rc, _ = run_cli(["--seeded", "--canned", str(canned), "--out-dir", str(tmp_path / "out"),
                     "--gate"], capsys)
    assert rc == 1


# --- rejected command lines --------------------------------------------------------------

def test_live_sample_out_of_range_rejected(tmp_path, monkeypatch, capsys):
    def _boom(*a, **k):
        raise AssertionError("an LLM object was constructed")

    monkeypatch.setattr(cfs, "ClaudeCli", _boom)
    monkeypatch.setattr(cfs, "FixtureLlm", _boom)
    for n in ("26", "0"):
        with pytest.raises(SystemExit) as exc:
            cfs.main(["--live-sample", n, "--out-dir", str(tmp_path / "out")])
        assert exc.value.code == 2
        assert "--live-sample must be 1..25" in capsys.readouterr().err


def test_unknown_flag_rejected(tmp_path):
    with pytest.raises(SystemExit) as exc:
        cfs.main(["--bogus", "--out-dir", str(tmp_path / "out")])
    assert exc.value.code == 2


def test_seeded_without_live_or_canned_rejected(tmp_path):
    with pytest.raises(SystemExit) as exc:
        cfs.main(["--seeded", "--out-dir", str(tmp_path / "out")])
    assert exc.value.code == 2


# --- live sample -------------------------------------------------------------------------

def test_live_sample_uses_shadow_pr_id(tmp_path, monkeypatch, capsys, redirect_verdicts):
    out = tmp_path / "out"
    run = write_run(out, "alpha", shadow=None, pr=4242, packet=True,
                    arm_conditions=[{"number": 3, "blocked": False},
                                    {"number": 18, "blocked": True}])
    # no packet and no arm: never eligible
    write_run(out, "nopacket", shadow=None, arm_conditions=[{"number": 3, "blocked": True},
                                                            {"number": 18, "blocked": True}])
    built = []

    def _fake_cli(ledger, out_dir=None, **kw):
        built.append(out_dir)
        return FixtureLlm(ledger, {"plan-fidelity-review": verdict_text(hold18=True)})

    monkeypatch.setattr(cfs, "ClaudeCli", _fake_cli)
    out_json = tmp_path / "res.json"
    rc, text = run_cli(["--live-sample", "5", "--seed", "3", "--out-dir", str(out),
                        "--json", str(out_json)], capsys)
    assert rc == 0
    assert len(built) == 1                                  # only the eligible run
    shadow_id = f"shadow-{run.name}"
    assert redirect_verdicts and set(redirect_verdicts) == {shadow_id}
    assert "4242" not in "".join(redirect_verdicts)
    assert (tmp_path / f"verdict-{shadow_id}.md").is_file()
    assert not (tmp_path / "verdict-4242.md").exists()
    packet = out / "shadow-compare" / run.name
    context = (packet / "context.md").read_text()
    assert f"PR_NUMBER: {shadow_id}" in context
    assert "/tmp/real-4242.md" not in context
    assert "SHADOW_ARMING: requested" in context
    assert (packet / "procedure.md").is_file()
    res = json.loads(out_json.read_text())
    sample = res["live_sample"]
    assert sample["runs"][run.name]["pr"] == shadow_id
    assert sample["table"]["18"]["agree-hold"] == 1
    assert sample["table"]["3"]["agree-clear"] == 1
    assert "Live sample (1 of 1 eligible runs" in text
