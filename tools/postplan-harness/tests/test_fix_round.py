"""In-run fix round: accept-or-discard state machine over the existing fixers."""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fix_round
from harness.armable import holding_findings
from harness.labels import LABEL_IMPORTANT, render_body
from harness.state import ArmDecision, ConditionResult, Finding, HarnessError, RunResult
from harness.thread_ingestion import declined_body, fixed_body
from harness.usage_pause import UsagePause

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
         already_tried=False, commit=None, clock=None, env=None, plan=None):
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
        already_tried=already_tried, clock=clock or _Clock(), env=env or {})
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
    rec, res, gh, git, llm = _run(tmp_path, res=res, decision=_decision(7, 12))
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
    rec, res, git, calls = _run_seat(tmp_path, degraded=("security",))
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
