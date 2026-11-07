"""In-run fix round: accept-or-discard state machine over the existing fixers."""
import hashlib
import json
import os
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fix_round
from harness.armable import holding_findings
from harness.labels import LABEL_IMPORTANT, render_body
from harness.state import ArmDecision, ConditionResult, Finding, HarnessError, RunResult
from harness.thread_ingestion import declined_body, fixed_body
from harness.usage_pause import UsagePause
from test_mutation_repair import (HELD_LINE, NEW_MATCH, OLD_MATCH, STALE_LINE, _plan_text,
                                  _row_block)

PR = 77
FIX = '{"verdict":"FIX","reason":"Added the guard"}'
DECLINE = '{"verdict":"DECLINE","reason":"already guarded","category":"already-fixed"}'


def _decision(*nums):
    return ArmDecision(armed=False, conditions=[ConditionResult(n, f"c{n}", True) for n in nums])


def _finding(path="ibl5/a.php", body="Guard the null row.", score=85):
    f = Finding(source="code-review", agent="A", path=path, line=3, body=body, score=score)
    f.label = LABEL_IMPORTANT
    return f


def _thread(cid, *, login="a-jay85", typ="User", path="ibl5/t.php", body="Please cache this."):
    return {"commentId": cid, "isResolved": False, "isOutdated": False, "path": path,
            "line": 4, "score": None, "body": body, "authorLogin": login, "authorType": typ}


class _Gh:
    def __init__(self, threads=()):
        self.threads = list(threads)
        self.resolved = []

    def pr_thread_ids(self, pr):
        return {t["commentId"] for t in self.threads}

    def trusted_open_threads(self, pr, include_outdated=False):
        return list(self.threads)

    def actions(self):
        return []

    def resolve_review_thread(self, pr, cid, body):
        self.resolved.append((cid, body))


class _Git:
    def __init__(self, undo_error=""):
        self.sha = "head0"
        self.n = 0
        self.drops = []
        self.undo_error = undo_error

    def head(self):
        return self.sha

    def is_dirty(self):
        return False

    def changed_files(self, base=None):
        return []

    def drop_commit(self, pre):
        self.drops.append(pre)
        if self.undo_error:
            return self.undo_error
        self.sha = pre
        return ""

    def commit(self, msg):
        self.n += 1
        self.sha = f"head{self.n}"
        return self.sha


class _Llm:
    def __init__(self, git, replies, *, on_call=None):
        self.git = git
        self.replies = list(replies)
        self.calls = 0
        self.kwargs = []
        self.on_call = on_call

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls += 1
        self.kwargs.append(kw)
        if self.on_call:
            self.on_call(self.calls)
        return self.replies.pop(0)


class _Clock:
    def __init__(self):
        self.t = 0.0

    def __call__(self):
        return self.t


def _run(tmp_path, *, decision=None, res=None, gh=None, git=None, llm=None, recheck=None,
         already_tried=False, commit=None, clock=None, env=None, plan=None, inputs=None,
         mutation_check=None, fidelity_fix=None, conflict_rereview=None):
    git = git or _Git()
    res = res if res is not None else RunResult(terminal="x")
    gh = gh or _Gh()
    llm = llm or _Llm(git, [FIX])
    rec = fix_round.run_fix_round(
        decision=decision or _decision(2), res=res, gh=gh, llm=llm, git=git,
        worktree=str(tmp_path), plan=plan, pr=PR, out_dir=str(tmp_path), log=lambda m: None,
        commit=commit or (lambda msg: git.commit(msg)),
        recheck=recheck or (lambda pre: {"new_holding": [], "conformance_added": [],
                                         "degraded": False}),
        already_tried=already_tried, clock=clock or _Clock(), env=env or {}, inputs=inputs,
        mutation_check=mutation_check, fidelity_fix=fidelity_fix,
        conflict_rereview=conflict_rereview)
    return rec, res, gh, git, llm


def test_accepts_when_recheck_clean(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    rec, res, gh, git, _ = _run(tmp_path, res=res)
    assert rec["status"] == "accepted"
    assert rec["sha"] == "head1"
    assert gh.resolved == []
    assert rec["defer"] and rec["defer"][0]["kind"] == "fixed"
    assert os.path.exists(os.path.join(str(tmp_path), fix_round.RESULT_BASENAME))


def test_new_holding_finding_discards_commit(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    snap = list(res.findings)

    def rc(pre):
        res.findings = []        # the re-check replaced the list
        return {"new_holding": [("x", "y")], "conformance_added": [], "degraded": False}

    rec, res, gh, git, _ = _run(tmp_path, res=res, recheck=rc)
    assert rec["status"] == "rejected" and rec["reason"] == "new-finding"
    assert git.head() == "head0"
    assert res.findings == snap
    assert gh.resolved == []


def test_timeout_discards_without_push_or_reply(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding(), _finding(path="ibl5/b.php")]
    clock = _Clock()
    git = _Git()
    llm = _Llm(git, [FIX, FIX], on_call=lambda n: setattr(clock, "t", fix_round.DEADLINE_S + 1))
    rec, res, gh, git, llm = _run(tmp_path, res=res, git=git, llm=llm, clock=clock)
    assert rec["status"] == "rejected" and rec["reason"] == "timeout"
    assert git.drops == ["head0"]
    assert gh.resolved == []
    assert llm.calls == 1


def test_commit_denial_discards(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]

    def deny(msg):
        raise HarnessError("pre-commit-denied", "hook refused")

    rec, *_ = _run(tmp_path, res=res, commit=deny)
    assert rec["status"] == "rejected"
    assert rec["reason"] == "error:pre-commit-denied"


def test_usage_pause_drops_commit_and_reraises(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding(), _finding(path="ibl5/b.php")]
    git = _Git()

    def pause(n):
        if n == 2:
            raise UsagePause("limit") if _pause_takes_arg() else UsagePause()

    llm = _Llm(git, [FIX, FIX], on_call=pause)
    with pytest.raises(UsagePause):
        _run(tmp_path, res=res, git=git, llm=llm)
    assert git.head() == "head0"
    with open(os.path.join(str(tmp_path), fix_round.RESULT_BASENAME)) as fh:
        assert json.load(fh)["status"] == "aborted"


def _pause_takes_arg():
    try:
        UsagePause("x")
        return True
    except TypeError:
        return False


def test_undo_failure_raises_fail_closed(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    git = _Git(undo_error="not uptodate")
    with pytest.raises(HarnessError) as ei:
        _run(tmp_path, res=res, git=git,
             recheck=lambda pre: {"new_holding": ["k"], "conformance_added": [],
                                  "degraded": False})
    assert ei.value.kind == "fix-round-undo-failed"


def test_second_call_is_noop(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    rec, res, gh, git, llm = _run(tmp_path, res=res)
    res.fix_round = rec
    before = llm.calls
    rec2, *_ = _run(tmp_path, res=res, git=git, llm=llm)
    assert rec2 is rec
    assert llm.calls == before
    assert fix_round.MAX_FIX_ROUNDS == 1


def test_enabled_unset_empty_zero():
    assert fix_round.enabled({}) is True
    assert fix_round.enabled({"POSTPLAN_FIX_ROUND": ""}) is True
    assert fix_round.enabled({"POSTPLAN_FIX_ROUND": "0"}) is False
    assert fix_round.enabled({"POSTPLAN_FIX_ROUND": "off"}) is True


def test_skips_when_no_target_hold(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    rec, res, gh, git, llm = _run(tmp_path, res=res, decision=_decision(7, 8))
    assert rec["status"] == "skipped" and rec["reason"] == "no-target-hold"
    assert llm.calls == 0


def test_user_thread_reply_flushed_with_pushed_sha(tmp_path):
    gh = _Gh([_thread(901)])
    rec, res, gh, git, _ = _run(tmp_path, decision=_decision(11), gh=gh)
    assert rec["status"] == "accepted"
    row = rec["items"][0]
    assert row["author"] == "a-jay85" and row["outcome"] == "fixed"
    assert fix_round.flush(gh, PR, rec, "abc1234", lambda m: None) == 1
    assert gh.resolved == [(901, fixed_body("abc1234", "Added the guard"))]


def test_untrusted_thread_never_reaches_fixer(tmp_path):
    gh = _Gh([_thread(902, login="stranger")])
    rec, res, gh, git, llm = _run(tmp_path, decision=_decision(11), gh=gh)
    assert llm.calls == 0
    assert rec["status"] == "no-change"


def test_unthreaded_decline_not_pruned(tmp_path):
    res = RunResult(terminal="x")
    f = _finding()
    res.findings = [f]
    git = _Git()
    rec, res, gh, git, _ = _run(tmp_path, res=res, git=git, llm=_Llm(git, [DECLINE]))
    assert rec["status"] == "no-change"
    assert rec["items"][0]["outcome"] == "declined" and not rec["items"][0]["threaded"]
    assert fix_round.prune_keys(rec) == set()
    # A threaded decline of the same shape IS pruned.
    rec2 = {"items": [dict(rec["items"][0], threaded=True)]}
    assert len(fix_round.prune_keys(rec2)) == 1
    # And a fixed unthreaded finding is pruned by the rendered-body key.
    rec3 = {"items": [dict(rec["items"][0], outcome="fixed")]}
    from harness.same_run_threads import _key
    assert fix_round.prune_keys(rec3) == {_key(f.path, render_body(f, 1000))}


def test_confix_only_unattempted_items(tmp_path, monkeypatch):
    res = RunResult(terminal="x")
    res.unresolved_conformance = ["MISSING: a.php", "MISSING: b.php"]
    res.conformance_fix = {"items": ["MISSING: a.php"]}
    seen = {}

    def fake_remediate(llm, git, plan, worktree, out_dir, items, **kw):
        seen["items"] = list(items)
        return {"sha": "", "items": list(items)}

    monkeypatch.setattr(fix_round.conformance_fix, "remediate_missing", fake_remediate)
    rec, *_ = _run(tmp_path, res=res, decision=_decision(3))
    assert seen["items"] == ["MISSING: b.php"]
    assert rec["status"] == "no-change"


def test_holding_findings_filters_nit_and_low_score():
    a, b = _finding(), _finding(score=40)
    c = _finding()
    c.label = "nit"
    assert holding_findings([a, b, c]) == [a]


def test_summary_line_per_status():
    acc = {"status": "accepted", "sha": "abcdef1234567890",
           "items": [{"outcome": "fixed"}, {"outcome": "declined"}]}
    assert fix_round.summary_line(acc) == (
        "Fix round: fixed 1 of 2 items in abcdef123456; "
        "re-reviewed and re-evaluated on the new head")
    nc = {"status": "no-change", "reevaluate": True, "items": [{"outcome": "declined"}]}
    assert fix_round.summary_line(nc) == "Fix round: no code change; 1 threads declined"
    assert fix_round.summary_line(dict(nc, reevaluate=False)) == ""
    assert fix_round.summary_line({"status": "rejected", "reason": "timeout"}) == (
        "Fix round: discarded (timeout); this verdict is the pre-round hold")
    assert fix_round.summary_line({"status": "skipped", "reason": "holdrepeat-same-holds"}) == (
        "Fix round: skipped (already tried for these holds)")
    assert fix_round.summary_line({"status": "skipped", "reason": "disabled"}) == ""
    assert fix_round.summary_line({}) == ""


class _DirtyGit(_Git):
    """_Git whose tree is dirty until restore_paths runs; records every call in order."""

    def __init__(self, dirty=True, **kw):
        super().__init__(**kw)
        self.dirty = dirty
        self.calls = []
        self.clean_at_drop = None

    def is_dirty(self):
        return self.dirty

    def dirty_paths(self):
        self.calls.append(("dirty_paths", None))
        return ["a.php", "new.php"]

    def unstage_paths(self, paths):
        self.calls.append(("unstage_paths", list(paths)))

    def restore_paths(self, paths):
        self.calls.append(("restore_paths", list(paths)))
        self.dirty = False

    def drop_commit(self, pre):
        self.clean_at_drop = not self.dirty
        return super().drop_commit(pre)


class _ResLists:
    def __init__(self):
        self.findings = ["f1"]
        self.scored_findings = ["s1", "s2"]
        self.degraded_agents = ["A"]
        self.unresolved_conformance = ["MISSING: a.php"]
        self.review_delta = ["d1"]


def test_clean_tree_noop_when_clean():
    git = _DirtyGit(dirty=False)
    fix_round.clean_tree(git)
    assert git.calls == []


def test_clean_tree_unstages_then_restores_dirty_paths():
    git = _DirtyGit()
    fix_round.clean_tree(git)
    paths = ["a.php", "new.php"]
    assert git.calls == [("dirty_paths", None), ("unstage_paths", paths),
                         ("restore_paths", paths)]


def test_snapshot_restore_roundtrip():
    obj = _ResLists()
    originals = {k: list(getattr(obj, k)) for k in fix_round._SNAPSHOT_FIELDS}
    snap = fix_round.snapshot(obj)
    obj.findings.append("extra")           # in-place mutation
    obj.scored_findings = []               # reassignment
    obj.degraded_agents.clear()
    obj.unresolved_conformance = ["other"]
    obj.review_delta.append("x")
    fix_round.restore(obj, snap)
    for k, v in originals.items():
        assert getattr(obj, k) == v
    for k in originals:                    # restored lists are copies of the snapshot's
        snap[k].append("poison")
        assert getattr(obj, k) == originals[k]


def test_discard_cleans_tree_before_undo(tmp_path):
    res = RunResult(terminal="x")
    res.findings = [_finding()]
    git = _DirtyGit()
    rec, res, gh, git, _ = _run(
        tmp_path, res=res, git=git,
        recheck=lambda pre: {"new_holding": ["k"], "conformance_added": [], "degraded": False})
    assert rec["status"] == "rejected"
    assert git.clean_at_drop is True
    assert [c[0] for c in git.calls] == ["dirty_paths", "unstage_paths", "restore_paths"]
    assert git.head() == "head0"


class _FlushGh:
    def __init__(self, fail_resolve=False):
        self.fail_resolve = fail_resolve
        self.filed = []

    def resolve_review_thread(self, pr, cid, body):
        if self.fail_resolve:
            raise HarnessError("gh", "down")

    def issue_titles(self, label, strict=False):
        return []

    def followup_create(self, title, body, label):
        self.filed.append(title)
        return 5


def _followup_record():
    return {"defer": [{"comment_id": 9, "kind": "declined", "reason": "elsewhere", "path": "a.php", "line": 3,
                       "followup": {"title": "Fix it", "harm": "crash", "trigger": "null row"}}]}


def test_flush_files_deferred_followup_after_reply():
    gh = _FlushGh()
    assert fix_round.flush(gh, PR, _followup_record(), "abc", lambda m: None) == 1
    assert gh.filed == ["Fix it"]


def test_flush_skips_followup_when_reply_fails():
    gh = _FlushGh(fail_resolve=True)
    rec = _followup_record()
    assert fix_round.flush(gh, PR, rec, "abc", lambda m: None) == 0
    assert gh.filed == []
    assert rec["defer"][0]["flush_failed"] is True


def test_disabled_round_spends_no_tokens_on_new_holds(tmp_path):
    git = _Git()
    llm = _Llm(git, [])
    rec, _, _, _, llm = _run(tmp_path, decision=_decision(12, 14, 19), git=git, llm=llm,
                             env={"POSTPLAN_FIX_ROUND": "0"})
    assert rec["status"] == "skipped"
    assert rec["reason"] == "disabled"
    assert llm.calls == 0


# ---- Executable Mutations rows, condition (19) ----

CALC = "def f(total_cap, used):\n    return total_cap - used\n\ndef g():\n    return 1\n"
TEST_PATH = "tests/test_calc.py"
HELD_BLOCK = _row_block(8, match="return 1", replace="return 2", check="true")
STALE_BLOCK = _row_block(7, check="true")
NEW_MATCH_REPLY = f"NEW-MATCH: {NEW_MATCH}"
NO_RECHECK_FINDINGS = {"new_holding": [], "conformance_added": [], "degraded": False}


def _section(path, added):
    return (f"diff --git a/{path} b/{path}\nindex 1111111..2222222 100644\n--- a/{path}\n"
            f"+++ b/{path}\n@@ -1 +1,2 @@\n x\n+{added}\n")


class _MutGit(_Git):
    """`diff_vs_base` gives real-shaped sections, so the real patch-id delta runs on them."""

    def __init__(self, root, touches=(TEST_PATH,), *, diff_after=None):
        super().__init__()
        self.root = root
        self.touches = touches
        self.diff_after = diff_after

    def diff_vs_base(self, base=None):
        base_diff = _section("calc.py", "base")
        if self.sha == "head0":
            return base_diff
        if self.diff_after is not None:
            return self.diff_after
        moved = "".join(_section(p, "fixed") for p in self.touches)
        return moved if "calc.py" in self.touches else base_diff + moved

    def read_worktree_file(self, path):
        return (self.root / path).read_text()


def _plan(tmp_path, *blocks):
    (tmp_path / "calc.py").write_text(CALC)
    plan = tmp_path / "plan.md"
    plan.write_text(_plan_text(*blocks, rows=(7, 8)))
    return SimpleNamespace(path=str(plan))


def _proof(*tripped, holding=()):
    return SimpleNamespace(results=tuple(SimpleNamespace(row=r, kind="TRIPPED") for r in tripped)
                           + tuple(SimpleNamespace(row=h.split()[1], kind="HELD") for h in holding),
                           holding=tuple(holding), tree_suspect=False)


def _holding(*lines):
    return SimpleNamespace(exec_mutations_holding=list(lines))


def test_held_row_strengthened_test_is_accepted_3701(tmp_path):
    plan = _plan(tmp_path, HELD_BLOCK)
    seen = []

    def check(path, remaining):
        seen.append((path, remaining))
        return _proof("8")

    rec, _, _, git, llm = _run(tmp_path, decision=_decision(19), git=_MutGit(tmp_path), plan=plan,
                               inputs=_holding(HELD_LINE), mutation_check=check)
    assert rec["status"] == "accepted"
    assert rec["reevaluate"] is True
    assert rec["sha"] == "head1"
    assert git.drops == []
    assert llm.calls == 1
    assert seen[0][0] == plan.path

    other = tmp_path / "again"
    other.mkdir()
    plan = _plan(other, HELD_BLOCK)
    rec, _, _, git, _ = _run(other, decision=_decision(19), git=_MutGit(other), plan=plan,
                             inputs=_holding(HELD_LINE),
                             mutation_check=lambda p, r: _proof(holding=[HELD_LINE]))
    assert rec["status"] == "rejected"
    assert rec["reevaluate"] is False


def test_held_row_fix_touching_source_is_discarded(tmp_path):
    plan = _plan(tmp_path, HELD_BLOCK)
    calls = []
    rec, _, _, git, _ = _run(tmp_path, decision=_decision(19), plan=plan,
                             git=_MutGit(tmp_path, touches=("calc.py",)),
                             inputs=_holding(HELD_LINE),
                             mutation_check=lambda p, r: calls.append(p) or _proof("8"))
    assert (rec["status"], rec["reason"]) == ("rejected", "non-test-edit")
    assert git.drops == ["head0"]
    assert git.head() == "head0"
    assert calls == []


def test_held_row_fix_with_unreadable_delta_is_discarded(tmp_path):
    plan = _plan(tmp_path, HELD_BLOCK)
    rec, _, _, git, _ = _run(tmp_path, decision=_decision(19), plan=plan,
                             git=_MutGit(tmp_path, diff_after=""),
                             inputs=_holding(HELD_LINE),
                             mutation_check=lambda p, r: _proof("8"))
    assert (rec["status"], rec["reason"]) == ("rejected", "non-test-edit")
    assert git.drops == ["head0"]


def test_held_row_declined_runs_no_proof(tmp_path):
    plan = _plan(tmp_path, HELD_BLOCK)
    git = _MutGit(tmp_path)
    calls = []
    rec, _, _, _, _ = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                           llm=_Llm(git, [DECLINE]), inputs=_holding(HELD_LINE),
                           mutation_check=lambda p, r: calls.append(p))
    assert rec["status"] == "no-change"
    assert calls == []


def test_unproven_mutation_round_is_discarded(tmp_path):
    plan = _plan(tmp_path, STALE_BLOCK, HELD_BLOCK)
    git = _MutGit(tmp_path)
    res = RunResult(terminal="x")
    res.findings = [_finding()]

    def recheck(pre):
        res.findings = []
        return NO_RECHECK_FINDINGS

    rec, res, _, git, _ = _run(tmp_path, decision=_decision(19), git=git, plan=plan, res=res,
                               llm=_Llm(git, [FIX, NEW_MATCH_REPLY]), recheck=recheck,
                               inputs=_holding(STALE_LINE, HELD_LINE),
                               mutation_check=lambda p, r: _proof("7", holding=[HELD_LINE]))
    assert (rec["status"], rec["reason"]) == ("rejected", "mutation-unproven")
    assert git.head() == "head0"
    assert git.drops == ["head0"]
    assert [f.path for f in res.findings] == ["ibl5/a.php"]
    assert rec["row_repairs"] == []
    assert rec["reevaluate"] is False
    assert not os.path.exists(os.path.join(str(tmp_path), fix_round.REPAIRED_PLAN_BASENAME))


def test_stale_row_repair_recorded_plan_untouched_3680(tmp_path):
    plan = _plan(tmp_path, STALE_BLOCK)
    before = open(plan.path, "rb").read()
    git = _MutGit(tmp_path)
    seen = []
    rec, _, _, _, llm = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                             llm=_Llm(git, [NEW_MATCH_REPLY]), inputs=_holding(STALE_LINE),
                             mutation_check=lambda p, r: seen.append(p) or _proof("7"))
    assert (rec["status"], rec["reason"]) == ("no-change", "no-commit")
    assert rec["reevaluate"] is True
    assert rec["row_repairs"] == [{"row": "7", "file": "calc.py", "old_match": OLD_MATCH,
                                   "new_match": NEW_MATCH}]
    assert open(plan.path, "rb").read() == before
    copy = os.path.join(str(tmp_path), fix_round.REPAIRED_PLAN_BASENAME)
    assert seen == [copy]
    assert f"match: {NEW_MATCH}\n" in open(copy).read()
    assert rec["plan_sha256"] == hashlib.sha256(before).hexdigest()
    assert llm.kwargs[0]["allowed_tools"] == ("Read",)
    assert {"Write", "Edit", "Bash", "Agent"} <= set(llm.kwargs[0]["denied_tools"])


def test_refused_repair_records_reasons_and_skips_proof(tmp_path):
    plan = _plan(tmp_path, STALE_BLOCK)
    git = _MutGit(tmp_path)
    calls = []
    rec, _, _, _, _ = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                           llm=_Llm(git, ["NEW-MATCH: def g():"]), inputs=_holding(STALE_LINE),
                           mutation_check=lambda p, r: calls.append(p))
    assert "match-dissimilar row 7" in rec["mutation"]["refused"]
    assert rec["row_repairs"] == []
    assert (rec["status"], rec["reevaluate"]) == ("no-change", False)
    assert calls == []
    assert not os.path.exists(os.path.join(str(tmp_path), fix_round.REPAIRED_PLAN_BASENAME))


def test_repair_reply_without_new_match_records_no_repair(tmp_path):
    plan = _plan(tmp_path, STALE_BLOCK)
    git = _MutGit(tmp_path)
    rec, _, _, _, _ = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                           llm=_Llm(git, ['{"NEW-MATCH": "x"}']), inputs=_holding(STALE_LINE))
    assert rec["mutation"]["dropped"] == ["row 7: no-new-match"]
    assert rec["row_repairs"] == []
    assert fix_round.digest_rows(rec) == []


def test_repair_with_no_prover_is_discarded(tmp_path):
    plan = _plan(tmp_path, STALE_BLOCK)
    git = _MutGit(tmp_path)
    rec, _, _, _, _ = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                           llm=_Llm(git, [NEW_MATCH_REPLY]), inputs=_holding(STALE_LINE))
    assert (rec["status"], rec["reason"]) == ("rejected", "mutation-unproven")
    assert rec["row_repairs"] == []


def test_unparsable_plan_skips_the_rows(tmp_path):
    git = _MutGit(tmp_path)
    plan = SimpleNamespace(path=str(tmp_path / "missing.md"))
    rec, _, _, _, llm = _run(tmp_path, decision=_decision(19), git=git, plan=plan,
                             llm=_Llm(git, []), inputs=_holding(STALE_LINE, HELD_LINE))
    assert rec["mutation"]["skipped"] == "parse-failed"
    assert (rec["status"], rec["reevaluate"]) == ("no-change", False)
    assert llm.calls == 0


def test_no_eligible_hold_touches_nothing(tmp_path):
    class Untouchable(_Git):
        def head(self):
            raise AssertionError("no-eligible-hold returns before reading the head")

    git = Untouchable()
    gh = _Gh()
    gh.pr_thread_ids = None
    rec, _, _, _, llm = _run(tmp_path, decision=_decision(19, 12, 14), git=git, gh=gh,
                             llm=_Llm(git, []),
                             inputs=_holding("row 7 BASELINE-FAILED — check failed before mutation"))
    assert (rec["status"], rec["reason"]) == ("skipped", "no-eligible-hold")
    assert llm.calls == 0


# ---- Fidelity NOT READY, condition (12) ----

def test_fidelity_fix_called_once_for_not_ready(tmp_path):
    calls = []
    res = RunResult(terminal="x")
    res.findings = [_finding(), _finding(path="ibl5/b.php")]
    git = _Git()

    def fidelity_fix(remaining):
        calls.append(remaining)
        return {"ran": True, "blockers": 1}

    rec, _, _, _, _ = _run(tmp_path, decision=_decision(2, 12), res=res, git=git,
                           llm=_Llm(git, [DECLINE, DECLINE]), fidelity_fix=fidelity_fix)
    assert calls == [fix_round.DEADLINE_S]
    assert rec["fidelity"] == {"ran": True, "blockers": 1}


def test_fidelity_commit_goes_through_recheck(tmp_path):
    git = _Git()
    rejected, _, _, _, _ = _run(tmp_path, decision=_decision(12), git=git, llm=_Llm(git, []),
                                fidelity_fix=lambda rem: git.commit("fix") and {"ran": True},
                                recheck=lambda pre: dict(NO_RECHECK_FINDINGS, degraded=True))
    assert (rejected["status"], rejected["reason"]) == ("rejected", "recheck-degraded")
    assert git.drops == ["head0"]


# ---- Conflict review with no verdict, condition (14) ----

@pytest.mark.parametrize("got,reevaluate", [
    (("CONFLICT-REVIEW=CLEAN", 1, "reviewed"), True),
    (("CONFLICT-REVIEW=FOUND-PROBLEM", 1, "reviewed"), True),
    (("CONFLICT-REVIEW=ABSENT", 2, "reviewed"), False),
    (None, False),
])
def test_conflict_rereview_clean_sets_reevaluate(tmp_path, got, reevaluate):
    git = _Git()
    rec, _, _, _, llm = _run(tmp_path, decision=_decision(14), git=git, llm=_Llm(git, []),
                             conflict_rereview=lambda: got)
    assert rec["status"] == "no-change"
    assert rec["reevaluate"] is reevaluate
    assert llm.calls == 0
    if got:
        assert rec["conflict_rereview"] == {"verdict": got[0], "calls": got[1], "reason": got[2]}
    else:
        assert rec["conflict_rereview"] == {}


def test_conflict_rereview_runs_after_the_recheck(tmp_path):
    order = []
    res = RunResult(terminal="x")
    res.findings = [_finding()]

    def recheck(pre):
        order.append("recheck")
        return NO_RECHECK_FINDINGS

    rec, _, _, _, _ = _run(tmp_path, decision=_decision(2, 14), res=res, recheck=recheck,
                           conflict_rereview=lambda: order.append("conflict")
                           or ("CONFLICT-REVIEW=ABSENT", 2, "reviewed"))
    assert rec["status"] == "accepted"
    assert order == ["recheck", "conflict"]


# ---- Swapping the proven repair onto the plan, after the push ----

def _repaired_record(tmp_path, plan_path):
    copy = tmp_path / fix_round.REPAIRED_PLAN_BASENAME
    copy.write_bytes(b"repaired\n")
    with open(plan_path, "rb") as fh:
        sha = hashlib.sha256(fh.read()).hexdigest()
    return {"status": "no-change", "repaired_plan": str(copy), "plan_sha256": sha,
            "row_repairs": [{"row": "7", "file": "calc.py", "old_match": "old m",
                             "new_match": "new m"}]}


def test_apply_plan_repair_refuses_changed_plan(tmp_path):
    plan = tmp_path / "plan.md"
    plan.write_bytes(b"original\n")
    rec = _repaired_record(tmp_path, plan)
    plan.write_bytes(b"changed by someone else\n")
    logs = []
    assert fix_round.apply_plan_repair(SimpleNamespace(path=str(plan)), rec, str(tmp_path),
                                       logs.append) is False
    assert plan.read_bytes() == b"changed by someone else\n"
    assert rec["repair_applied"] is False
    assert logs == ["fix-round: plan changed during the round; row repair not applied"]
    assert fix_round.digest_rows(rec) == []
    assert "repaired" not in fix_round.summary_line(dict(rec, reevaluate=True))


def test_apply_plan_repair_swaps_through_a_symlink_and_keeps_the_original(tmp_path):
    real = tmp_path / "real.md"
    real.write_bytes(b"original\n")
    link = tmp_path / "link.md"
    link.symlink_to(real)
    rec = _repaired_record(tmp_path, link)
    assert fix_round.apply_plan_repair(SimpleNamespace(path=str(link)), rec, str(tmp_path),
                                       lambda m: None) is True
    assert link.is_symlink()
    assert real.read_bytes() == b"repaired\n"
    assert (tmp_path / fix_round.ORIGINAL_PLAN_BASENAME).read_bytes() == b"original\n"
    assert rec["repair_applied"] is True
    on_disk = json.loads((tmp_path / fix_round.RESULT_BASENAME).read_text())
    assert on_disk["repair_applied"] is True


def test_apply_plan_repair_without_repairs_is_a_noop(tmp_path):
    plan = tmp_path / "plan.md"
    plan.write_bytes(b"original\n")
    assert fix_round.apply_plan_repair(SimpleNamespace(path=str(plan)), {"row_repairs": []},
                                       str(tmp_path), lambda m: None) is False
    assert plan.read_bytes() == b"original\n"


def test_apply_plan_repair_read_failure_is_not_applied(tmp_path):
    rec = {"row_repairs": [{"row": "7"}], "plan_sha256": "x", "repaired_plan": "y"}
    logs = []
    assert fix_round.apply_plan_repair(SimpleNamespace(path=str(tmp_path / "gone.md")), rec,
                                       str(tmp_path), logs.append) is False
    assert rec["repair_applied"] is False
    assert logs[0].startswith("fix-round: row repair not applied")


def test_summary_and_digest_list_the_repair():
    repair = {"row": "7", "file": "calc.py", "old_match": "old m", "new_match": "new m"}
    rec = {"status": "no-change", "reevaluate": True, "items": [], "row_repairs": [repair],
           "repair_applied": True, "conflict_rereview": {"verdict": "CONFLICT-REVIEW=CLEAN"}}
    line = fix_round.summary_line(rec)
    assert "; repaired Executable Mutations row 7 match in calc.py" in line
    assert "; conflict review re-run: CONFLICT-REVIEW=CLEAN" in line
    assert "row 7 match in calc.py" in fix_round.summary_line(dict(rec, status="accepted", sha="abc"))
    rows = fix_round.digest_rows(rec)
    assert len(rows) == 1
    assert "row 7 (calc.py)" in rows[0] and "`old m`" in rows[0] and "`new m`" in rows[0]


# --- security-audit items wait for the reviewer bar ------------------------------------

def _sec_finding(path="ibl5/s.php"):
    f = Finding(source="security-audit", agent="security", path=path, line=7,
                body="Escape the name before output.", score=90)
    f.label = LABEL_IMPORTANT
    return f


def _bar(passed, reason="r"):
    from harness.reviewer_bar import Verdict
    return lambda: Verdict(passed, reason)


def test_collect_items_security_excluded_without_bar():
    res = RunResult(terminal="x")
    sec = _sec_finding()
    res.findings = [sec, _finding()]
    record = {}
    items = fix_round.collect_items(_Gh(), PR, res, [], record)
    assert [i["path"] for i in items] == ["ibl5/a.php"]
    assert record["security_excluded"] == 1
    assert record["security_bar"].startswith("FAIL ")
    # Condition (2) still holds on it: the finding is untouched in the holding set.
    assert sec in holding_findings(res.findings)


def test_collect_items_threaded_security_row_excluded_without_bar():
    t = dict(_thread(901, login="review-agent", typ="Bot"), agent="security")
    res = RunResult(terminal="x")
    record = {}
    items = fix_round.collect_items(_Gh([t]), PR, res, [], record,
                                    security_bar=_bar(False, "missing: x"))
    assert items == []
    assert record["security_excluded"] == 1
    assert record["security_bar"] == "FAIL missing: x"


def test_collect_items_security_included_when_bar_passes():
    res = RunResult(terminal="x")
    res.findings = [_sec_finding(), _finding()]
    record = {}
    items = fix_round.collect_items(_Gh(), PR, res, [], record,
                                    security_bar=_bar(True, "pass: ok"))
    assert sorted(i["path"] for i in items) == ["ibl5/a.php", "ibl5/s.php"]
    assert record["security_items"] == 1
    assert "security_excluded" not in record
    assert record["security_bar"] == "PASS pass: ok"


def test_collect_items_bar_raises_fails_closed():
    def boom():
        raise RuntimeError("bar broke")

    res = RunResult(terminal="x")
    res.findings = [_sec_finding()]
    record = {}
    items = fix_round.collect_items(_Gh(), PR, res, [], record, security_bar=boom)
    assert items == []
    assert record["security_bar"].startswith("FAIL error:")
    assert record["security_excluded"] == 1


def test_collect_items_no_security_candidate_never_calls_bar():
    calls = []

    def bar():
        calls.append(1)
        raise AssertionError("bar evaluated")

    res = RunResult(terminal="x")
    res.findings = [_finding()]
    record = {}
    items = fix_round.collect_items(_Gh([_thread(903)]), PR, res, [], record, security_bar=bar)
    assert len(items) == 2
    assert calls == []
    assert not [k for k in record if k.startswith("security_")]


def _run_sec(tmp_path, recheck, *, findings=None):
    git = _Git()
    res = RunResult(terminal="x")
    res.findings = findings if findings is not None else [_sec_finding()]
    rec = fix_round.run_fix_round(
        decision=_decision(2), res=res, gh=_Gh(), llm=_Llm(git, [FIX]), git=git,
        worktree=str(tmp_path), plan=None, pr=PR, out_dir=str(tmp_path), log=lambda m: None,
        commit=lambda msg: git.commit(msg), recheck=recheck, already_tried=False,
        clock=_Clock(), env={}, security_bar=_bar(True, "pass: ok"))
    return rec, res, git


def _seat_recheck(calls, res_box, *, seat="opus", degraded=(), clear=True):
    """A recheck stub that records its call and, when `clear`, leaves no finding."""
    def recheck(pre, **kw):
        calls.append((pre, kw))
        res = res_box[0]
        if clear:
            res.findings = []
        res.degraded_agents = list(degraded)
        return {"new_holding": [], "conformance_added": [], "degraded": False,
                "security_seat": seat}
    return recheck


def _run_seat(tmp_path, **kw):
    calls, box = [], []
    git = _Git()
    res = RunResult(terminal="x")
    res.findings = [_sec_finding()]
    box.append(res)
    rec = fix_round.run_fix_round(
        decision=_decision(2), res=res, gh=_Gh(), llm=_Llm(git, [FIX]), git=git,
        worktree=str(tmp_path), plan=None, pr=PR, out_dir=str(tmp_path), log=lambda m: None,
        commit=lambda msg: git.commit(msg), recheck=_seat_recheck(calls, box, **kw),
        already_tried=False, clock=_Clock(), env={}, security_bar=_bar(True, "pass: ok"))
    return rec, res, git, calls


def test_security_round_rechecks_with_opus_seat(tmp_path):
    rec, res, git, calls = _run_seat(tmp_path)
    assert calls == [("head0", {"security_seat": "opus"})]
    assert rec["status"] == "accepted"
    assert rec["security_items"] == 1


def test_security_recheck_not_clean_rejects(tmp_path):
    rec, res, git, calls = _run_seat(tmp_path, clear=False)
    assert rec["status"] == "rejected"
    assert rec["reason"] == "security-recheck-not-clean"
    assert git.drops == ["head0"] and git.sha == "head0"
    assert any(f.source == "security-audit" for f in holding_findings(res.findings))


def test_security_recheck_wrong_seat_rejects(tmp_path):
    rec, res, git, calls = _run_seat(tmp_path, seat="sonnet")
    assert rec["status"] == "rejected"
    assert rec["reason"] == "security-recheck-seat"
    assert git.drops == ["head0"]


def test_security_recheck_missing_seat_rejects(tmp_path):
    # The runner reports no seat when the delta review never ran.
    rec, res, git, calls = _run_seat(tmp_path, seat=None)
    assert rec["status"] == "rejected"
    assert rec["reason"] == "security-recheck-seat"


def test_security_recheck_degraded_rejects(tmp_path):
    rec, res, git, calls = _run_seat(tmp_path, degraded=("security-audit",))
    assert rec["status"] == "rejected"
    assert rec["reason"] == "security-recheck-degraded"


def test_non_security_round_recheck_single_arg(tmp_path):
    calls = []

    def recheck(pre):                # a one-argument stub, as before this change
        calls.append(pre)
        return {"new_holding": [], "conformance_added": [], "degraded": False}

    rec, res, git = _run_sec(tmp_path, recheck, findings=[_finding()])
    assert calls == ["head0"]
    assert rec["status"] == "accepted"
    assert "security_items" not in rec
