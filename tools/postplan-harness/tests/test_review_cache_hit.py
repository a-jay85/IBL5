"""Review-agent equal-key reuse: a cache hit restores findings without LLM calls or re-posts.

Unit rows cover `reviewcache.restore_review`; runner rows drive the replay runner with a
seeded `<slug>.reviewcache.json` and a monkeypatched `_review_cache_context`, so the key is
deterministic and the run goes through the real `run()` wiring.
"""
import dataclasses
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import reviewcache
from harness.adapters.llm import FixtureLlm
from harness.classify import classify, files_from_diff
from harness.review import ReviewPhase
from harness.state import Finding, TerminalState, UsageLedger
from test_runner_replay import CANNED, _actions

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

REVIEW_PURPOSES = ("review-agent-a", "review-agent-b", "review-agent-d",
                   "security-audit", "score-findings")
PR = 4242
SLUG = "review-cache-hit"
TITLE = "fix: review cache hit"


def _php_section(path, n_lines):
    body = "".join(f"+<?php // line {i}\n" for i in range(n_lines))
    return (f"diff --git a/{path} b/{path}\nnew file mode 100644\nindex 0000000..1111111\n"
            f"--- /dev/null\n+++ b/{path}\n@@ -0,0 +1,{n_lines} @@\n{body}")


def _ts_section(path):
    return (f"diff --git a/{path} b/{path}\nnew file mode 100644\nindex 0000000..2222222\n"
            f"--- /dev/null\n+++ b/{path}\n@@ -0,0 +1,2 @@\n+test('x', () => {{}});\n+// spec\n")


# Alpha has >50 added php lines (agent B on) and a spec file is present (agent D on), so a
# full review runs all five review purposes.
ALPHA, BETA, SPEC = "ibl5/classes/Alpha.php", "ibl5/classes/Beta.php", "ibl5/tests/e2e/hit.spec.ts"
DIFF = _php_section(ALPHA, 60) + _php_section(BETA, 3) + _ts_section(SPEC)
FILES = reviewcache.per_file_patch_ids(DIFF)
GATES = ReviewPhase(None, None).gates(classify(files_from_diff(DIFF), DIFF))
FIXTURE = {
    "slug": SLUG, "diff": DIFF, "pr_number": PR,
    "pr_meta": {"number": PR, "title": TITLE, "body": "## Manual Testing\n\nNo manual testing needed\n",
                "headRefOid": "deadbeef"},
    "labels": [], "final_state": "OPEN", "checks_outcome": {"exit": 0, "failed": []},
    "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
    "plan_content": "# Synthetic plan\n\nBody with no matrix and no frontmatter.\n",
}
KEY = reviewcache.make_key(diff_id="d" * 40, plan_hash="p" * 64, version="v" * 64,
                           pr_title=TITLE, file_list=sorted(FILES))

CANNED_FINDINGS = dict(
    CANNED,
    **{"review-agent-a": [{"path": ALPHA, "line": 3, "body": "alpha bug"},
                          {"path": BETA, "line": 1, "body": "beta bug"}],
       "score-findings": [{"n": 1, "score": 85}, {"n": 2, "score": 90}]})


def _finding_dict(path, score, body="cached body", source="code-review", agent="A"):
    return dataclasses.asdict(Finding(source=source, agent=agent, path=path, line=3,
                                      body=body, score=score))


def _arm(findings=None, **over):
    findings = findings if findings is not None else [_finding_dict(ALPHA, 85), _finding_dict(BETA, 90)]
    arm = {"run_id": "live-prior", "gates": dict(GATES), "findings": findings,
           "scored_findings": [{"source": f["source"], "agent": f["agent"], "path": f["path"],
                                "line": f["line"], "score": f["score"], "body_head": f["body"][:160]}
                               for f in findings],
           "degraded_agents": []}
    arm.update(over)
    return arm


# ---- unit rows: restore_review ---------------------------------------------------------

def test_restore_review_returns_tuple_in_run_order():
    arm = _arm()
    findings, gates, scored, degraded = reviewcache.restore_review(arm, dict(GATES))
    assert [type(f) for f in findings] == [Finding, Finding]
    assert findings[0].score == 85 and findings[1].score == 90
    assert findings[0].path == ALPHA
    assert gates == GATES
    assert scored == arm["scored_findings"]
    assert degraded == []


def test_restore_review_rejects_degraded_arm():
    assert reviewcache.restore_review(_arm(degraded_agents=["review-agent-b"]), dict(GATES)) is None


def test_restore_review_rejects_gate_mismatch():
    stored = dict(GATES, B=False)
    current = dict(GATES, B=True)
    assert reviewcache.restore_review(_arm(gates=stored), current) is None
    assert reviewcache.restore_review(_arm(gates=stored), dict(stored)) is not None


def test_restore_review_rejects_unknown_finding_field():
    extra = dict(_finding_dict(ALPHA, 85), severity="high")
    assert reviewcache.restore_review(_arm(findings=[extra]), dict(GATES)) is None


def test_restore_review_rejects_non_int_score():
    assert reviewcache.restore_review(_arm(findings=[_finding_dict(ALPHA, "85")]), dict(GATES)) is None
    assert reviewcache.restore_review(_arm(findings=[_finding_dict(ALPHA, None)]), dict(GATES)) is None


# ---- runner rows ------------------------------------------------------------------------

def _drive(monkeypatch, tmp_path, *, name, canned, record=None, key=KEY, files=FILES, llm=None):
    state = str(tmp_path / f"state-{name}")
    out = str(tmp_path / f"out-{name}")
    monkeypatch.setattr(runner, "_review_cache_context", lambda *a, **k: (key, dict(files)))
    if record is not None:
        reviewcache.save_record(state, SLUG, record)
    llm = llm or FixtureLlm(UsageLedger(), canned)
    res = runner.run(dict(FIXTURE), out, llm, mode="replay", headless=True, state_dir=state)
    return res, out


def _record(arm, key=KEY, pr=PR):
    return reviewcache.build_record(slug=SLUG, pr_number=pr, run_id="live-prior", key=key,
                                    per_file_ids=FILES, review=arm)


def _fresh(monkeypatch, tmp_path):
    res, out = _drive(monkeypatch, tmp_path, name="fresh", canned=CANNED_FINDINGS)
    assert res.terminal != TerminalState.FAILED, res.error
    return res, out


def _seed_from(res):
    arm = reviewcache.vet_review(res.findings, res.scored_findings, res.degraded_agents,
                                 ReviewPhase(None, None).gates(res.classification), "live-prior")
    assert arm is not None
    return _record(arm)


def _purposes(res):
    return [c.purpose for c in res.ledger.calls]


def _review_posts(out):
    return [a for a in _actions(out)
            if a["action"] == "pr_review_findings"
            or (a["action"] == "pr_comment" and a.get("title") in ("Code review", "Security audit"))]


def _no_review_canned():
    return {k: v for k, v in CANNED_FINDINGS.items() if k not in REVIEW_PURPOSES}


def test_review_hit_makes_no_review_llm_calls_and_posts_nothing(monkeypatch, tmp_path):
    fresh, fresh_out = _fresh(monkeypatch, tmp_path)
    assert set(REVIEW_PURPOSES) <= set(_purposes(fresh))      # the fresh run is not vacuous
    assert _review_posts(fresh_out)
    assert [f.score for f in fresh.findings] == [85, 90]

    hit, hit_out = _drive(monkeypatch, tmp_path, name="hit", canned=CANNED_FINDINGS,
                          record=_seed_from(fresh))
    assert hit.terminal != TerminalState.FAILED, hit.error
    assert not set(REVIEW_PURPOSES) & set(_purposes(hit))
    assert hit.reused_from == {"review": "live-prior"}
    assert _review_posts(hit_out) == []
    assert [dataclasses.asdict(f) for f in hit.findings] == [dataclasses.asdict(f) for f in fresh.findings]
    assert hit.scored_findings == fresh.scored_findings
    assert "reviewcache: review reused from live-prior" in "\n".join(hit.audit)


def test_review_hit_fails_closed_when_fixture_lacks_replies(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    record = _seed_from(fresh)
    canned = _no_review_canned()

    hit, _ = _drive(monkeypatch, tmp_path, name="hit", canned=canned, record=record)
    assert hit.terminal != TerminalState.FAILED, hit.error
    assert hit.reused_from == {"review": "live-prior"}

    # Same seed, but the key no longer matches: the review runs and the missing canned
    # replies surface, so the hit was the only thing keeping the LLM calls away.
    monkeypatch.setattr(reviewcache, "key_matches", lambda *a, **k: False)
    miss, _ = _drive(monkeypatch, tmp_path, name="miss", canned=canned, record=record)
    assert miss.terminal == TerminalState.FAILED
    assert "llm-fixture-missing" in (miss.error or "")


def test_review_miss_on_title_change_runs_full_review(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    record = _seed_from(fresh)
    record["key"]["pr_title"] += "x"
    res, _ = _drive(monkeypatch, tmp_path, name="miss", canned=CANNED_FINDINGS, record=record)
    assert res.terminal != TerminalState.FAILED, res.error
    assert set(REVIEW_PURPOSES) <= set(_purposes(res))
    assert res.reused_from == {}
    assert res.review_delta == []


def test_review_hit_not_marked_degraded(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    hit, _ = _drive(monkeypatch, tmp_path, name="hit", canned=CANNED_FINDINGS,
                    record=_seed_from(fresh))
    assert hit.reused_from == {"review": "live-prior"}
    assert hit.degraded_agents == []
    assert hit.phase4b_code_ran is True

    # A record whose arm holds a degraded agent is never restored: the hit falls to a full review.
    bad = _seed_from(fresh)
    bad["review"]["degraded_agents"] = ["review-agent-b"]
    miss, _ = _drive(monkeypatch, tmp_path, name="miss", canned=CANNED_FINDINGS, record=bad)
    assert miss.reused_from == {}
    assert set(REVIEW_PURPOSES) <= set(_purposes(miss))


def test_review_hit_arm_conditions_equal_fresh_run(monkeypatch, tmp_path):
    fresh, _ = _fresh(monkeypatch, tmp_path)
    hit, _ = _drive(monkeypatch, tmp_path, name="hit", canned=CANNED_FINDINGS,
                    record=_seed_from(fresh))
    assert hit.reused_from == {"review": "live-prior"}

    def conds(res):
        return [(c.number, c.name, c.blocked, c.reason) for c in res.arm.conditions]

    assert conds(hit) == conds(fresh)
    assert hit.arm.armed == fresh.arm.armed
    # Condition 2 counts the >=80 findings, so the comparison is not vacuous.
    assert any(c.number == 2 and c.blocked for c in fresh.arm.conditions)
