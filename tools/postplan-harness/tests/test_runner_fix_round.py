"""The in-run fix round wired into runner.run() (replay mode, no model called).

`FrLlm` answers the review agents from a script (first call, then later calls) and performs the
write a real thread fixer would. The replayed git advances its head with every commit, so the
runner sees a real new head after an accepted round.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, fix_round, holdrepeat, thread_ingestion
from harness.adapters.gitad import ReplayGit
from harness.adapters.verify import ReplayVerify
from harness.review import VERIFIER_PURPOSES
from harness.state import HarnessError, TerminalState, UsageLedger
from test_runner_conformance_fix import (BASE_DIFF, CANNED, SLUG, WritingLlm, _fixture)

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

PATH = "ibl5/classes/Fixer/Thing.php"
FINDING = {"path": PATH, "line": 2, "body": "real bug in Thing"}
OTHER = {"path": PATH, "line": 1, "body": "a different real bug"}
FIX_REPLY = json.dumps({"verdict": "FIX", "reason": "guarded the call"})
DECLINE_REPLY = json.dumps({"verdict": "DECLINE", "reason": "intentional", "category": "intentional"})
FIXED_DIFF = BASE_DIFF.replace("@@ -1 +1,2 @@", "@@ -1 +1,3 @@") + "+echo 2;\n"


def _all_real(n):
    return json.dumps([{"n": i, "verdict": "real", "reason": "verified"} for i in range(1, n + 1)])


class FrLlm(WritingLlm):
    """Scripted review agent plus a thread fixer that edits Thing.php."""

    def __init__(self, ledger, canned, *, later=None, thread_reply=FIX_REPLY, edit=True,
                 git_getter=None, raise_in_fixer=None):
        super().__init__(ledger, canned, git_getter=git_getter)
        self.later = later if later is not None else []
        self.review_calls = 0
        self.thread_reply = thread_reply
        self.edit = edit
        self.raise_in_fixer = raise_in_fixer

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose == "review-agent-a":
            self.review_calls += 1
            self.canned["review-agent-a"] = [FINDING] if self.review_calls == 1 else self.later
        return super().call(purpose, model, prompt, validate, max_retries, normalizer)

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        if purpose != "thread-remediation":
            return super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)
        self.tooled_argvs.append((purpose, []))
        if self.raise_in_fixer is not None:
            raise self.raise_in_fixer
        if self.edit and self.git_getter:
            git = self.git_getter()
            if PATH not in git.extra_dirty:
                git.extra_dirty.append(PATH)
        return self.thread_reply


class Rig:
    def __init__(self, tmp_path, monkeypatch):
        self.tmp_path = tmp_path
        self.gits = []
        self.fixed = False
        wt = tmp_path / "wt"
        wt.mkdir()
        self.wt = wt
        rig = self
        orig_init = ReplayGit.__init__

        def init(git, fx):
            orig_init(git, fx)
            rig.gits.append(git)

        orig_commit_all = ReplayGit.commit_all

        def commit_all(git, message):
            git.last_commit_paths = list(git.extra_dirty)
            return orig_commit_all(git, message)

        orig_changed = ReplayGit.changed_files

        def changed_files(git, base="origin/master"):
            if base.endswith("^"):
                return list(getattr(git, "last_commit_paths", []))
            return orig_changed(git, base)

        monkeypatch.setattr(adr_draft, "commit_gate", lambda worktree: (0, ""))
        monkeypatch.setattr(ReplayGit, "__init__", init)
        monkeypatch.setattr(ReplayGit, "commit_all", commit_all)
        monkeypatch.setattr(ReplayGit, "changed_files", changed_files)
        def drop_commit(git, pre):
            git.drops.append(pre)
            del git.commit_messages[int(pre[-4:], 16):]
            return ""

        monkeypatch.setattr(ReplayGit, "drop_commit", drop_commit)
        monkeypatch.setattr(ReplayGit, "head", lambda git: f"{'a' * 36}{len(git.commit_messages):04x}")
        monkeypatch.setattr(ReplayGit, "diff_vs_base",
                            lambda git, base="origin/master": FIXED_DIFF if any(m.startswith("fix(review)") for m in git.commit_messages) else BASE_DIFF)
        monkeypatch.setattr(ReplayGit, "read_worktree_file", lambda git, p: None)
        monkeypatch.setattr(ReplayVerify, "run", ReplayVerify.run)
        real = runner._review_phase_for
        monkeypatch.setattr(runner, "_review_phase_for",
                            lambda llm, gh, copy, meta, log, worktree=None: real(
                                llm, gh, copy, meta, log, worktree=str(tmp_path)))

    @property
    def git(self):
        return self.gits[-1]

    def llm(self, **kw):
        canned = dict(CANNED)
        for p in VERIFIER_PURPOSES:
            canned[p] = _all_real(1)
        return FrLlm(UsageLedger(), canned, git_getter=lambda: self.git, **kw)

    def run(self, llm, *, extra=None, name="out", **kw):
        fx = _fixture()
        fx.update(extra or {})
        return runner.run(fx, str(self.tmp_path / name), llm, mode="replay",
                          worktree=str(self.wt), **kw)

    def actions(self, name="out"):
        path = self.tmp_path / name / "actions.jsonl"
        if not path.exists():
            return []
        return [json.loads(line) for line in path.read_text().splitlines() if line]


@pytest.fixture
def rig(tmp_path, monkeypatch):
    monkeypatch.delenv(fix_round.ENV_FLAG, raising=False)
    # Replay posts no real threads, so the review's findings stay unthreaded for the round.
    monkeypatch.setattr(fix_round.same_run_threads, "posted_keys", lambda actions: set())
    return Rig(tmp_path, monkeypatch)


def _holds(res):
    return {c.number for c in res.arm.holds}


def test_fix_round_clears_finding_hold_ready(rig):
    llm = rig.llm(later=[])
    res = rig.run(llm)
    assert res.fix_round["status"] == "accepted"
    assert 2 not in _holds(res)
    assert rig.git.commit_messages and rig.git.commit_messages[-1].startswith("fix(review)")
    assert rig.git.pushes >= 1


def test_fix_round_new_finding_posts_original_hold(rig):
    llm = rig.llm(later=[OTHER])
    res = rig.run(llm)
    assert res.fix_round["status"] == "rejected"
    assert res.fix_round["reason"] == "new-finding"
    assert 2 in _holds(res)
    assert rig.git.drops == [res.fix_round["head_before"]]


_POST_KINDS = ("pr_review_findings", "pr_comment")


def _review_posts(rig, name):
    return [a for a in rig.actions(name) if a.get("action") in _POST_KINDS]


def test_fix_round_rejected_posts_no_review_comments(rig, monkeypatch):
    # Baseline: the same run with the round off posts only the first review.
    monkeypatch.setenv(fix_round.ENV_FLAG, "0")
    rig.run(rig.llm(), name="off")
    baseline = len(_review_posts(rig, "off"))
    assert baseline > 0
    monkeypatch.delenv(fix_round.ENV_FLAG)
    res = rig.run(rig.llm(later=[OTHER]), name="rejected")
    assert res.fix_round["status"] == "rejected"
    assert len(_review_posts(rig, "rejected")) == baseline


def test_fix_round_timeout_posts_original_hold_no_push(rig, monkeypatch):
    monkeypatch.setattr(fix_round, "DEADLINE_S", 0)
    res = rig.run(rig.llm())
    assert res.fix_round["status"] == "rejected"
    assert res.fix_round["reason"] == "timeout"
    assert 2 in _holds(res)
    assert rig.git.drops == [res.fix_round["head_before"]]
    assert not rig.git.commit_messages or not rig.git.commit_messages[-1].startswith("fix(review)")


def test_fix_round_user_thread_reply_recorded(rig, monkeypatch):
    # Phase 4.5 would handle the thread first; stub it so the round is the one that sees it.
    monkeypatch.setattr(runner, "_run_thread_ingestion_phase", lambda *a, **k: {})
    extra = {"thread_ids": [501], "trusted_threads": [
        {"commentId": 501, "path": PATH, "line": 2, "body": "please reconsider this",
         "authorLogin": thread_ingestion.TRUSTED_LOGIN, "authorType": "User",
         "isResolved": False, "isOutdated": False}]}
    llm = rig.llm(thread_reply=DECLINE_REPLY, edit=False, later=[FINDING])
    res = rig.run(llm, extra=extra)
    assert res.fix_round["status"] in ("no-change", "accepted")
    rows = [r for r in res.fix_round["items"] if r["commentId"] == 501]
    assert rows and rows[0]["outcome"] == "declined"
    resolved = [a for a in rig.actions() if a["action"] == "pr_resolve_thread"]
    assert [a for a in resolved if a["comment_id"] == 501]


def test_fix_round_reevaluates_every_condition(rig):
    res = rig.run(rig.llm(later=[]))
    assert res.fix_round["status"] == "accepted"
    text = (rig.tmp_path / "out" / "audit.log").read_text()
    assert text.count("phase6.5: HELD") == 2
    assert text.count("(post-fix-round)") == 1
    assert 2 not in _holds(res)
    assert 3 in _holds(res)      # the conformance hold the round could not touch still stands


def test_fix_round_one_round_per_run(rig):
    llm = rig.llm(later=[FINDING, OTHER])
    res = rig.run(llm)
    assert fix_round.MAX_FIX_ROUNDS == 1
    fixer_calls = [a for a in llm.tooled_argvs if a[0] == "thread-remediation"]
    assert len(fixer_calls) == 1
    assert res.fix_round["status"] in ("rejected", "accepted", "no-change")


def test_fix_round_disabled_env(rig, monkeypatch):
    monkeypatch.setenv(fix_round.ENV_FLAG, "0")
    llm = rig.llm()
    res = rig.run(llm)
    assert not [a for a in llm.tooled_argvs if a[0] == "thread-remediation"]
    assert res.fix_round == {}
    assert 2 in _holds(res)


def test_fix_round_rejected_keeps_fingerprint(rig, tmp_path):
    sd = tmp_path / "state"
    llm = rig.llm(later=[OTHER])
    res = rig.run(llm, state_dir=str(sd))
    assert res.fix_round["status"] == "rejected"
    rec = holdrepeat.load_record(str(sd), SLUG)
    assert rec["fix_round"]["attempts"] == 1
    assert rec["fix_round"]["targets"] == [2, 3]
    assert rec["fix_round"]["status"] == "rejected"


def test_fix_round_dirty_tree_fails_closed(rig, monkeypatch):
    # An edit the round leaves behind and cannot clear must fail the run, never ship.
    monkeypatch.setattr(ReplayGit, "is_dirty",
                        lambda git: any(m.startswith("fix(review)") for m in git.commit_messages))
    monkeypatch.setattr(ReplayGit, "restore_paths", lambda git, paths: None)
    res = rig.run(rig.llm(later=[]))
    assert res.terminal == TerminalState.FAILED
    assert "fix-round-dirty-tree" in (rig.tmp_path / "out" / "audit.log").read_text()


def test_fix_round_push_failure_rewrites_record(rig, monkeypatch):
    real_push = runner._push_with_gate_fix

    def boom(git, log, phase, *a, **k):
        if phase == "phase6.5-fix":
            raise HarnessError("push-failed", "no")
        return real_push(git, log, phase, *a, **k)

    monkeypatch.setattr(runner, "_push_with_gate_fix", boom)
    res = rig.run(rig.llm(later=[]))
    assert res.fix_round["status"] == "rejected"
    assert res.fix_round["reason"] == "push-failed"
    on_disk = json.loads((rig.tmp_path / "out" / "fix-round.json").read_text())
    assert on_disk["status"] == "rejected"
    assert on_disk["reason"] == "push-failed"
    assert on_disk["defer"] == []


def test_fix_round_holdrepeat_skip_reaches_sticky_line(rig, monkeypatch):
    monkeypatch.setattr(holdrepeat, "fix_round_tried", lambda *a, **k: True)
    res = rig.run(rig.llm(), state_dir=str(rig.tmp_path / "state"))
    assert res.fix_round["status"] == "skipped"
    assert res.fix_round["reason"] == "holdrepeat-same-holds"
    assert (fix_round.summary_line(res.fix_round)
            == "Fix round: skipped (already tried for these holds)")


def test_runner_recheck_forwards_security_seat(rig, monkeypatch):
    from harness import review as review_mod
    seen = []
    real_run = review_mod.ReviewPhase.run

    def spy(self, meta, cls, plan, reused=None, **kw):
        seen.append(kw.get("security_model"))
        return real_run(self, meta, cls, plan, reused=reused, **kw)

    monkeypatch.setattr(review_mod.ReviewPhase, "run", spy)
    got = {}

    def fake_fix_round(**kw):
        kw["commit"]("fix(review): escape the name")
        got["rc"] = kw["recheck"]("head0", security_seat="opus")
        got["bar"] = kw["security_bar"]
        return {"status": "skipped", "reason": "test"}

    monkeypatch.setattr(fix_round, "run_fix_round", fake_fix_round)
    rig.run(rig.llm(later=[]))
    assert "opus" in seen
    assert got["rc"]["security_seat"] == "opus"
    assert callable(got["bar"])
