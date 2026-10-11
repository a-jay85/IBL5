"""Phase 7: the review-cache write policy.

Pure rows cover `reviewcache.vet_*` and `build_record`; runner rows drive the replay runner
(the same driver as tests/test_review_cache_hit.py) and read back the record the run left in
`state_dir`. Only a clean, fully-vetted arm is ever persisted: degraded, NOT READY,
remediated and failed outcomes write a `null` arm (or nothing) so no later hit can clear an
arming condition from a record the run never earned.
"""
import datetime
import glob
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import reviewcache
from harness.review import ReviewPhase
from harness.state import Finding, HarnessError, RunResult, TerminalState, UsageLedger
from harness.adapters.llm import FixtureLlm
from test_review_cache_hit import (ALPHA, BETA, CANNED_FINDINGS, FILES, FIXTURE, GATES, KEY, PR,
                                   SLUG, _seed_from)
from test_runner_replay import DegradingLlm, ScriptedToolLlm, _verdict_doc

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

REVIEW_PURPOSES = ("review-agent-a", "review-agent-b", "review-agent-d",
                   "security-audit", "score-findings")
RUN = "run-write"


def _finding(score=85, path=ALPHA):
    return Finding(source="code-review", agent="A", path=path, line=3, body="a bug", score=score)


def _scored(findings):
    return [{"source": f.source, "agent": f.agent, "path": f.path, "line": f.line,
             "score": f.score, "body_head": f.body[:160]} for f in findings]


def _fid(**over):
    fid = {"verdict_1": "READY", "error_kind": None, "verdict_2": None, "remediation_sha": None,
           "rounds": [], "rounds_completed": 0, "diff_id": "d" * 40, "plan_hash": "p" * 64}
    fid.update(over)
    return fid


# ---- pure rows: vetting -----------------------------------------------------------------

def test_vet_review_accepts_clean_run():
    findings = [_finding(85), _finding(90, BETA)]
    arm = reviewcache.vet_review(findings, _scored(findings), [], dict(GATES), RUN)
    assert arm is not None
    assert arm["run_id"] == RUN and arm["degraded_agents"] == []
    assert arm["gates"] == GATES
    assert [f["score"] for f in arm["findings"]] == [85, 90]
    assert arm["scored_findings"] == _scored(findings)


@pytest.mark.parametrize("purpose", REVIEW_PURPOSES)
def test_vet_review_rejects_each_degraded_purpose(purpose):
    findings = [_finding()]
    assert reviewcache.vet_review(findings, _scored(findings), [purpose], dict(GATES), RUN) is None
    # Not vacuous: the same inputs without the degraded purpose vet clean.
    assert reviewcache.vet_review(findings, _scored(findings), [], dict(GATES), RUN) is not None


def test_vet_review_purpose_set_is_the_five_spec_purposes():
    assert set(reviewcache.REVIEW_PURPOSES) == set(REVIEW_PURPOSES)


def test_vet_review_rejects_unscored_finding():
    findings = [_finding(85), _finding(None, BETA)]
    assert reviewcache.vet_review(findings, _scored(findings), [], dict(GATES), RUN) is None
    assert reviewcache.vet_review("not-a-list", [], [], dict(GATES), RUN) is None


def test_vet_body_check_rejects_degraded_and_empty_hash():
    result = {"corrected_body": "b", "findings": []}
    ok = reviewcache.vet_body_check("h" * 64, result, [], RUN)
    assert ok == {"run_id": RUN, "input_hash": "h" * 64, "result": result}
    assert reviewcache.vet_body_check("h" * 64, result, ["body-check"], RUN) is None
    assert reviewcache.vet_body_check("", result, [], RUN) is None
    assert reviewcache.vet_body_check("h" * 64, {"findings": "nope"}, [], RUN) is None
    assert reviewcache.vet_body_check("h" * 64, None, [], RUN) is None


@pytest.mark.parametrize("over", [
    {"verdict_1": "NOT READY"},
    {"verdict_2": "READY"},
    {"remediation_sha": "abc123"},
    {"rounds_completed": 1},
], ids=["not-ready", "re-reviewed-verdict-2", "remediation-sha", "rounds-completed"])
def test_vet_fidelity_rejects_not_ready_and_remediated(over):
    assert reviewcache.vet_fidelity(_fid(**over), RUN) is None
    # Not vacuous: the untouched dict vets clean.
    assert reviewcache.vet_fidelity(_fid(), RUN) == {
        "run_id": RUN, "verdict": "READY", "diff_id": "d" * 40, "plan_hash": "p" * 64,
        "remediated": False}


def test_build_record_roundtrips_through_load_record(tmp_path):
    findings = [_finding()]
    review = reviewcache.vet_review(findings, _scored(findings), [], dict(GATES), RUN)
    now = datetime.datetime(2026, 10, 10, 12, 30, 5, tzinfo=datetime.timezone.utc)
    doc = reviewcache.build_record(slug=SLUG, pr_number=PR, run_id=RUN, key=KEY, per_file_ids=FILES,
                                   review=review, now=now)
    assert doc["written_at"] == "2026-10-10T12:30:05Z"
    assert doc["body_check"] is None and doc["fidelity"] is None
    reviewcache.save_record(str(tmp_path), SLUG, doc)
    loaded = reviewcache.load_record(str(tmp_path), SLUG)
    assert loaded == doc
    assert loaded["key"] == KEY and loaded["per_file_ids"] == FILES
    assert loaded["review"]["findings"][0]["score"] == 85
    assert glob.glob(str(tmp_path / "*.tmp")) == []


# ---- runner rows ------------------------------------------------------------------------

def _drive(monkeypatch, tmp_path, *, name, canned, record=None, key=KEY, llm=None):
    """tests/test_review_cache_hit.py's driver, but with a plan FILE on disk.

    The hit driver feeds the plan as inline `plan_content`, which leaves the plan hash empty,
    and an empty plan hash correctly keeps the fidelity arm null. A real plan file gives the
    run a plan hash, so the fidelity arm is writable and the NOT READY / remediated rows
    are not vacuous.
    """
    plans = tmp_path / "plans"
    plans.mkdir(exist_ok=True)
    (plans / f"{SLUG}.md").write_text(FIXTURE["plan_content"])
    monkeypatch.setenv("PLANS_DIR", str(plans))
    fixture = {k: v for k, v in FIXTURE.items() if k != "plan_content"}
    state = _state(tmp_path, name)
    out = str(tmp_path / f"out-{name}")
    monkeypatch.setattr(runner, "_review_cache_context", lambda *a, **k: (key, dict(FILES)))
    if record is not None:
        reviewcache.save_record(state, SLUG, record)
    llm = llm or FixtureLlm(UsageLedger(), canned)
    res = runner.run(fixture, out, llm, mode="replay", headless=True, state_dir=state)
    return res, out


def _fresh(monkeypatch, tmp_path):
    res, out = _drive(monkeypatch, tmp_path, name="fresh", canned=CANNED_FINDINGS)
    assert res.terminal != TerminalState.FAILED, res.error
    return res, out


def _state(tmp_path, name):
    return str(tmp_path / f"state-{name}")


def _load(tmp_path, name):
    return reviewcache.load_record(_state(tmp_path, name), SLUG)


def _record_bytes(tmp_path, name):
    with open(reviewcache.record_path(_state(tmp_path, name), SLUG), "rb") as fh:
        return fh.read()


def test_clean_run_writes_all_three_arms(monkeypatch, tmp_path):
    res, out = _drive(monkeypatch, tmp_path, name="clean", canned=CANNED_FINDINGS)
    assert res.terminal != TerminalState.FAILED, res.error
    rec = _load(tmp_path, "clean")
    assert rec is not None
    run = os.path.basename(out)
    for arm in ("review", "body_check", "fidelity"):
        assert isinstance(rec[arm], dict), arm
        assert rec[arm]["run_id"] == run, arm
    assert rec["run_id"] == run
    assert isinstance(rec["review"]["findings"][0]["score"], int)
    assert rec["key"] == KEY
    assert rec["pr_number"] == PR
    assert glob.glob(os.path.join(_state(tmp_path, "clean"), "**", "*.tmp"), recursive=True) == []


def test_degraded_review_agent_writes_null_review_arm(monkeypatch, tmp_path):
    # A deleted canned reply is `llm-fixture-missing`, which fails the whole run; the
    # degraded shape (llm-invalid-output on one agent) is what DegradingLlm raises.
    llm = DegradingLlm(UsageLedger(), CANNED_FINDINGS, {"review-agent-b"})
    res, _ = _drive(monkeypatch, tmp_path, name="degraded", canned=None, llm=llm)
    assert "review-agent-b" in res.degraded_agents, (res.terminal, res.error)
    rec = _load(tmp_path, "degraded")
    assert rec is not None
    assert rec["review"] is None
    assert isinstance(rec["body_check"], dict)
    assert isinstance(rec["fidelity"], dict)


def test_degraded_body_check_writes_null_body_arm(monkeypatch, tmp_path):
    llm = DegradingLlm(UsageLedger(), CANNED_FINDINGS, {"body-check"})
    res, _ = _drive(monkeypatch, tmp_path, name="degraded-bc", canned=None, llm=llm)
    assert "body-check" in res.degraded_agents, (res.terminal, res.error)
    rec = _load(tmp_path, "degraded-bc")
    assert rec is not None
    assert rec["body_check"] is None
    assert isinstance(rec["review"], dict)
    assert isinstance(rec["fidelity"], dict)


def test_not_ready_fidelity_writes_null_fidelity_arm(monkeypatch, tmp_path):
    real = runner._run_fidelity

    def not_ready(*a, **k):
        real(*a, **k)
        res = a[-1] if isinstance(a[-1], RunResult) else k["res"]
        res.fidelity = dict(res.fidelity, verdict_1="NOT READY")
        return "NOT READY", ""

    monkeypatch.setattr(runner, "_run_fidelity", not_ready)
    res, _ = _drive(monkeypatch, tmp_path, name="notready", canned=CANNED_FINDINGS)
    assert res.fidelity["verdict_1"] == "NOT READY", (res.terminal, res.error)
    rec = _load(tmp_path, "notready")
    assert rec is not None
    assert rec["fidelity"] is None
    assert isinstance(rec["review"], dict)
    assert isinstance(rec["body_check"], dict)


def test_remediated_fidelity_writes_null_fidelity_arm(monkeypatch, tmp_path):
    canned = dict(CANNED_FINDINGS)
    canned["plan-fidelity-review"] = ""       # opts the fixture into the real Phase 5.5 path
    llm = ScriptedToolLlm(UsageLedger(), canned, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    res, _ = _drive(monkeypatch, tmp_path, name="remediated", canned=canned, llm=llm)
    assert res.fidelity["verdict_2"] == "READY", (res.terminal, res.error)
    assert res.fidelity["rounds_completed"] >= 1
    rec = _load(tmp_path, "remediated")
    assert rec is not None
    assert rec["fidelity"] is None
    assert isinstance(rec["review"], dict)
    assert isinstance(rec["body_check"], dict)


def test_failed_run_leaves_prior_record_untouched(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    seed = _seed_from(fresh)
    state = _state(tmp_path, "failed")
    reviewcache.save_record(state, SLUG, seed)
    before = _record_bytes(tmp_path, "failed")

    def boom(self, *a, **k):
        raise HarnessError("gh", "synthetic review failure")

    monkeypatch.setattr(ReviewPhase, "run", boom)
    # A different key, so the run cannot hit and must reach the (raising) review phase.
    other = dict(KEY, pr_title=KEY["pr_title"] + "x")
    res, _ = _drive(monkeypatch, tmp_path, name="failed", canned=CANNED_FINDINGS, key=other)
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind
    assert _record_bytes(tmp_path, "failed") == before
    assert glob.glob(os.path.join(state, "*.tmp")) == []


def test_hit_rewrites_arm_with_original_run_id(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    seed = _seed_from(fresh)
    assert seed["review"]["run_id"] == "live-prior" and seed["run_id"] == "live-prior"
    hit, out = _drive(monkeypatch, tmp_path, name="hit", canned=CANNED_FINDINGS, record=seed)
    assert hit.terminal != TerminalState.FAILED, hit.error
    assert hit.reused_from == {"review": "live-prior"}
    rec = _load(tmp_path, "hit")
    assert rec["run_id"] == os.path.basename(out)
    assert rec["review"]["run_id"] == "live-prior"
    assert rec["review"]["findings"] == seed["review"]["findings"]


def test_interdiff_run_becomes_new_producer(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    seed = _seed_from(fresh)
    # The cached diff differs from the current one in BETA only: a per-file interdiff.
    seed["key"]["diff_id"] = "e" * 40
    seed["per_file_ids"] = dict(FILES, **{BETA: "f" * 40})
    res, out = _drive(monkeypatch, tmp_path, name="interdiff", canned=CANNED_FINDINGS, record=seed)
    assert res.terminal != TerminalState.FAILED, res.error
    assert res.review_delta == [BETA]
    rec = _load(tmp_path, "interdiff")
    assert rec["review"]["run_id"] == os.path.basename(out)
    assert rec["run_id"] == os.path.basename(out)
    assert rec["per_file_ids"] == FILES
    assert rec["key"] == KEY
