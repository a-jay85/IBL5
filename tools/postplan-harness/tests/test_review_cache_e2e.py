"""End-to-end review-cache equivalence: one fixture, two real `runner.run` drives, one shared state dir.

Run 1 is a cold run and writes `<state>/<slug>.reviewcache.json` through the real write
site; run 2 starts from that record and from run 1's composed sticky comment (fed back as the
fixture's `prior_sticky_body`). Nothing seeds the record by hand.

Three seams keep the replay faithful to a live run:
  - the plan is a real file under `PLANS_DIR`, because a plan with no path hashes to "" and
    `key_matches` treats an empty plan hash as a miss;
  - the Phase 5.5 verdict file moves under `tmp_path`, because carry-forward re-reads it;
  - `_run_fidelity` is invoked with `live=True`, because only live runs read the prior sticky
    (replay's `not live` short-circuit never reaches carry-forward). `live` gates nothing else
    inside that function.
Each run also gets its own deep copy of the canned replies, because the runner rewrites the
canned `pr-copy` dict in place and run 2 would otherwise see run 1's corrected body.
"""
import copy
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, review, reviewcache
from harness.adapters.llm import FixtureLlm
from harness.state import TerminalState, UsageLedger
from test_review_cache_hit import ALPHA, BETA, CANNED_FINDINGS, FIXTURE, SLUG, SPEC

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

FIDELITY_PURPOSE = "plan-fidelity-review"
PURPOSES = {FIDELITY_PURPOSE, "review-agent-a", "review-agent-b", "review-agent-d",
            "security-audit", "score-findings", "body-check"}
SAMPLE_LEDGER = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                             "fixtures", "reviewcache", "sample-ledger.json")
CANNED = dict(CANNED_FINDINGS, **{FIDELITY_PURPOSE: "6d checks\n\nREADY\n"})
PLAN_BODY = FIXTURE["plan_content"]


def calls(res):
    return [c.purpose for c in res.ledger.calls]


def conds(res):
    return [(c.number, c.name, c.blocked, c.reason, c.warning) for c in res.arm.conditions]


class Harness:
    """Drives `runner.run` twice against one shared state dir."""

    def __init__(self, tmp_path, monkeypatch):
        self.tmp_path = tmp_path
        self.monkeypatch = monkeypatch
        self.state = str(tmp_path / "state")
        self.plan_path = tmp_path / "plans" / f"{SLUG}.md"
        self.plan_path.parent.mkdir()
        self.plan_path.write_text(PLAN_BODY)
        monkeypatch.setenv("PLANS_DIR", str(self.plan_path.parent))
        monkeypatch.setattr(fidelity, "verdict_path",
                            lambda pr: str(tmp_path / f"verdict-{pr}.md"))
        real_fidelity = runner._run_fidelity

        def live_fidelity(llm, out_dir, worktree, gitad, gh, plan, diff, body, pr, master_sha,
                          reviewed_tree, live, log, res, **kw):
            return real_fidelity(llm, out_dir, worktree, gitad, gh, plan, diff, body, pr,
                                 master_sha, reviewed_tree, True, log, res, **kw)

        monkeypatch.setattr(runner, "_run_fidelity", live_fidelity)

    @property
    def record_file(self):
        return reviewcache.record_path(self.state, SLUG)

    def run(self, name, *, prior_sticky=None, canned=CANNED):
        fixture = copy.deepcopy(FIXTURE)
        fixture.pop("plan_content")          # the plan comes from the real file under PLANS_DIR
        if prior_sticky is not None:
            fixture["prior_sticky_body"] = prior_sticky
        out = str(self.tmp_path / f"live-{name}")
        llm = FixtureLlm(UsageLedger(), copy.deepcopy(canned))
        res = runner.run(fixture, out, llm, mode="replay", headless=True, state_dir=self.state)
        assert res.terminal != TerminalState.FAILED, res.error
        actions = [json.loads(line) for line in open(os.path.join(out, "actions.jsonl"))]
        stickies = [a["body"] for a in actions if a["action"] == "pr_sticky_verdict"]
        return Run(res, out, os.path.basename(out), actions, stickies[-1] if stickies else None)

    def two_runs(self):
        run1 = self.run("run1")
        assert run1.sticky, "run 1 must compose a sticky for run 2 to carry forward from"
        return run1, self.run("run2", prior_sticky=run1.sticky)


class Run:
    def __init__(self, res, out, run_id, actions, sticky):
        self.res, self.out, self.id, self.actions, self.sticky = res, out, run_id, actions, sticky

    @property
    def log(self):
        return "\n".join(self.res.audit)

    def posts(self, action):
        return [a for a in self.actions if a["action"] == action]


@pytest.fixture
def h(tmp_path, monkeypatch):
    return Harness(tmp_path, monkeypatch)


def _all_arms_reused(run1, run2):
    return {"review": run1.id, "fidelity": run1.id, "body-check": run1.id}


# ---- same patch-id: every arm reused, arming unchanged --------------------------------------

def test_same_patch_id_reuses_all_arms_with_identical_arming(h):
    run1, run2 = h.two_runs()
    res1, res2 = run1.res, run2.res

    # Run 1 is a cold run that exercises the real write site.
    assert PURPOSES <= set(calls(res1))
    assert res1.reused_from == {}
    assert "reviewcache: wrote record" in run1.log
    assert os.path.exists(h.record_file)

    # Run 2 reads that record: the hit path, not the miss path.
    assert "reviewcache: review reused from" in run2.log
    assert "review miss" not in run2.log
    assert not PURPOSES & set(calls(res2))
    assert res2.reused_from == _all_arms_reused(run1, run2)
    assert conds(res2) == conds(res1)
    assert res2.arm.armed == res1.arm.armed
    assert conds(res1), "the condition list must not be vacuous"

    blob1, blob2 = json.loads(res1.to_json()), json.loads(res2.to_json())
    assert "reused_from" not in blob1
    assert blob2["reused_from"] == _all_arms_reused(run1, run2)

    m = fidelity.STICKY_REUSED_FROM_RE.search(run2.sticky)
    assert m is not None
    assert m.group(1) == run1.id
    assert set(m.group(2).split(", ")) == {"review", "fidelity", "body-check"}
    assert "**Reused from:**" in run2.sticky
    assert "**Reused from:**" not in run1.sticky


def test_master_sha_move_still_hits(h, monkeypatch):
    shas = iter(["a" * 40, "b" * 40])
    seen = []
    master = {"sha": None}

    def next_master(worktree):
        return master["sha"]

    def same_text(worktree, master_sha, paths, label):
        seen.append(master_sha)
        return "SAME PROCEDURE TEXT\n" + paths[0]

    monkeypatch.setattr(runner, "_master_sha", next_master)
    monkeypatch.setattr(fidelity, "_find_procedure", same_text)

    master["sha"] = next(shas)
    run1 = h.run("run1")
    master["sha"] = next(shas)
    run2 = h.run("run2", prior_sticky=run1.sticky)

    assert {"a" * 40, "b" * 40} <= set(seen)       # the move reached the version computation
    assert not PURPOSES & set(calls(run2.res))
    assert run2.res.reused_from == _all_arms_reused(run1, run2)
    assert conds(run2.res) == conds(run1.res)
    assert "reviewcache: review reused from" in run2.log


# ---- one changed line: that file only -------------------------------------------------------

def test_one_changed_line_reviews_that_file_only(h, monkeypatch):
    from harness.adapters import gitad

    run1 = h.run("run1")
    changed = FIXTURE["diff"].replace("+<?php // line 1\n", "+<?php // line 1 edited\n", 1)
    assert changed != FIXTURE["diff"]
    # The first "line 1" in the fixture diff sits in ALPHA's section (60 lines, listed first).
    assert changed.index("line 1 edited") < changed.index(f"diff --git a/{BETA}")

    class OneLineGit(gitad.ReplayGit):
        def diff_vs_base(self, base="origin/master"):
            return changed

    seen = []
    real_prompt = review.agent_a_prompt

    def spy(meta, cls, plan):
        seen.append(cls.filtered_diff)
        return real_prompt(meta, cls, plan)

    monkeypatch.setattr(runner, "ReplayGit", OneLineGit)
    monkeypatch.setattr(review, "agent_a_prompt", spy)
    run2 = h.run("run2", prior_sticky=run1.sticky)

    assert len(seen) == 1
    headers = [ln for ln in seen[0].splitlines() if ln.startswith("diff --git ")]
    assert headers == [f"diff --git a/{ALPHA} b/{ALPHA}"]
    assert BETA not in seen[0] and SPEC not in seen[0]
    assert FIDELITY_PURPOSE in calls(run2.res)       # fidelity has no interdiff mode
    assert run2.res.reused_from == {"review": run1.id, "body-check": run1.id}
    assert "body-check" not in calls(run2.res)


# ---- anything that must force a full run -----------------------------------------------------

def test_plan_file_edit_forces_full_run(h):
    run1 = h.run("run1")
    with open(h.plan_path, "a") as fh:
        fh.write("\n")
    run2 = h.run("run2", prior_sticky=run1.sticky)
    # Body-check keys on its rendered prompt only (Phase 5 key rule: neither diff_id nor
    # plan_hash participates), and the replay PR copy is byte-identical across runs, so
    # that arm alone stays reused. Review and fidelity both consume the plan and re-run.
    assert PURPOSES - {"body-check"} <= set(calls(run2.res))
    assert "body-check" not in calls(run2.res)
    assert run2.res.reused_from == {"body-check": "live-run1"}


def test_harness_version_bump_forces_full_run(h, monkeypatch):
    run1 = h.run("run1")
    monkeypatch.setattr(reviewcache, "MODEL_PINS", reviewcache.MODEL_PINS + ("x:y",))
    run2 = h.run("run2", prior_sticky=run1.sticky)
    assert PURPOSES <= set(calls(run2.res))
    assert run2.res.reused_from == {}


def test_corrupted_cache_forces_full_run_and_rewrites(h):
    run1 = h.run("run1")
    with open(h.record_file, "rb") as fh:
        data = fh.read()
    with open(h.record_file, "wb") as fh:
        fh.write(data[: len(data) // 2])
    assert reviewcache.load_record(h.state, SLUG) is None   # the truncation really corrupts it

    run2 = h.run("run2", prior_sticky=run1.sticky)           # must not raise
    assert PURPOSES <= set(calls(run2.res))
    assert run2.res.reused_from == {}

    rec = reviewcache.load_record(h.state, SLUG)
    assert rec is not None
    assert rec["run_id"] == run2.id
    for arm in ("review", "body_check", "fidelity"):
        assert isinstance(rec[arm], dict), arm
        assert rec[arm]["run_id"] == run2.id


# ---- cost and side effects of a hit ----------------------------------------------------------

def test_replayed_hit_prices_avoided_cost(h):
    run1, run2 = h.two_runs()
    absent = set(calls(run1.res)) - set(calls(run2.res))
    assert PURPOSES <= absent

    with open(SAMPLE_LEDGER) as fh:
        sample = json.load(fh)
    assert {c["purpose"] for c in sample} >= PURPOSES      # the price list covers every arm
    saved = reviewcache.avoided_cost(sample, absent)
    total = sum(c["cost_usd"] for c in sample)
    assert saved > 0
    assert saved == sum(c["cost_usd"] for c in sample if c["purpose"] in absent)
    print(f"avoided: ${saved:.4f} of ${total:.4f}")


def test_second_run_posts_no_review_comments(h):
    run1, run2 = h.two_runs()
    # Run 1 posted its findings and summary, so the zero below is not vacuous.
    assert run1.posts("pr_review_findings")
    assert run1.res.reused_from == {}
    assert run2.res.reused_from.get("review") == run1.id
    assert run2.posts("pr_review_findings") == []       # post_review_findings
    review_summaries = [a for a in run2.posts("pr_comment")
                        if a.get("title") in ("Code review", "Security audit")]
    assert review_summaries == []                         # post_review_summary
