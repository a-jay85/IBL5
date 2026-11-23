"""The in-run fix round wired into runner.run() (replay mode, no model called).

`FrLlm` answers the review agents from a script (first call, then later calls) and performs the
write a real thread fixer would. The replayed git advances its head with every commit, so the
runner sees a real new head after an accepted round.
"""
import glob
import hashlib
import json
import os
import shutil
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, fix_round, holdrepeat, thread_ingestion
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import RecordingGh
from harness.adapters.verify import ReplayVerify
from harness.mutations import MutationReport
from harness.review import VERIFIER_PURPOSES
from harness.state import HarnessError, TerminalState, UsageLedger
from test_mutation_repair import NEW_MATCH, OLD_MATCH, STALE_LINE, _plan_text, _row_block
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


@pytest.fixture
def armable_rig(rig, monkeypatch):
    """The rig with conditions (3) and (20) neutralized, so (2) is its only possible hold.

    Condition (2) and every other condition stay computed by production code; only the two
    inputs the replay fixture cannot satisfy are blanked before `evaluate` runs.
    """
    monkeypatch.setattr(runner, "_removed_guards_holds",
                        lambda worktree, diff_body, plan, arm_body: [])
    real_evaluate = runner.evaluate
    rig.held_sets = []

    def evaluate(inputs):
        inputs.unresolved_conformance = []
        decision = real_evaluate(inputs)
        rig.held_sets.append({c.number for c in decision.holds})
        return decision

    monkeypatch.setattr(runner, "evaluate", evaluate)
    return rig


def test_fix_round_clears_finding_hold_ready(armable_rig):
    rig = armable_rig
    res = rig.run(rig.llm(later=[]))
    assert res.fix_round["status"] == "accepted"
    # Precondition: before the round, the rig held on the review finding alone.
    assert {2} in rig.held_sets
    # The step-9 re-evaluation ran once and produced the armed decision.
    text = (rig.tmp_path / "out" / "audit.log").read_text()
    assert text.count("(post-fix-round)") == 1
    assert rig.held_sets[-1] == set()
    assert res.arm.armed is True
    assert _holds(res) == set()
    # The sticky is composed with the re-evaluated (armed) decision.
    body = _sticky_body(rig)
    assert "auto-merge armed" in body
    assert "Auto-merge held:" not in body
    assert rig.git.commit_messages and rig.git.commit_messages[-1].startswith("fix(review)")
    assert rig.git.pushes >= 1


def test_fix_round_rejected_on_armable_rig_stays_held(armable_rig):
    """The variant does not arm on its own: a rejected round leaves exactly the (2) hold."""
    rig = armable_rig
    res = rig.run(rig.llm(later=[OTHER]))
    assert res.fix_round["status"] == "rejected"
    assert res.arm.armed is False
    assert _holds(res) == {2}
    assert "auto-merge held" in _sticky_body(rig)


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
    # This rig keeps returning thread 501 as open after it is fixed, so the hold set shrinks
    # and a second round would start. Pin one round: the test is about round 1's reply.
    monkeypatch.setattr(fix_round, "MAX_FIX_ROUNDS", 1)
    extra = {"thread_ids": [501], "trusted_threads": [
        {"commentId": 501, "path": PATH, "line": 2, "body": "please reconsider this",
         "authorLogin": thread_ingestion.TRUSTED_LOGIN, "authorType": "User",
         "isResolved": False, "isOutdated": False}]}
    llm = rig.llm(thread_reply=FIX_REPLY, edit=True, later=[])
    res = rig.run(llm, extra=extra)
    assert res.fix_round["status"] == "accepted"
    pushed = res.fix_round["sha"]
    assert pushed and pushed == rig.git.head()
    assert pushed != res.fix_round["head_before"]
    rows = [r for r in res.fix_round["items"] if r["commentId"] == 501]
    assert rows and rows[0]["outcome"] == "fixed"
    resolved = [a for a in rig.actions() if a["action"] == "pr_resolve_thread"
                and a["comment_id"] == 501]
    # The recorded reply names the pushed sha and the fixer's reason, not a decline.
    assert [a["body"] for a in resolved] == [thread_ingestion.fixed_body(pushed, "guarded the call")]


def test_fix_round_reevaluates_every_condition(rig, monkeypatch):
    # Spy on every arming evaluation: what head the run stood on, and what (15) and
    # current_tree held. A genuinely new ArmInputs per evaluation is a fresh read.
    evals = []
    real_evaluate = runner.evaluate

    def spy(inputs):
        if not evals or evals[-1]["inputs"] is not inputs:
            evals.append({"inputs": inputs, "head": rig.git.head(), "pushes": rig.git.pushes,
                          "tree_now": rig.git.head_tree(), "tree": inputs.current_tree,
                          "failed": list(inputs.failed_checks)})
        return real_evaluate(inputs)

    monkeypatch.setattr(runner, "evaluate", spy)
    # Replay reads (15) from this fixture key at evaluation time; the push makes it go red,
    # so only an evaluation made after the push can see it.
    red = []
    real_push = ReplayGit.push

    def push(git):
        if any(m.startswith("fix(review)") for m in git.commit_messages):
            red.append("ci-red-after-push")
        return real_push(git)

    monkeypatch.setattr(ReplayGit, "push", push)
    res = rig.run(rig.llm(later=[]), extra={"red_ci_checks": red})
    assert res.fix_round["status"] == "accepted"
    text = (rig.tmp_path / "out" / "audit.log").read_text()
    assert text.count("phase6.5: HELD") == 2
    assert text.count("(post-fix-round)") == 1
    assert 2 not in _holds(res)
    assert 3 in _holds(res)      # the conformance hold the round could not touch still stands
    assert len(evals) == 2
    old, new = evals
    # The first evaluation stood on the old head, before the round's push.
    assert old["head"] == res.fix_round["head_before"]
    # The second stood on the new head, after the round's push.
    assert new["head"] == res.fix_round["sha"] != old["head"]
    assert new["pushes"] == old["pushes"] + 1
    # current_tree was re-read on the new head, not carried over from the old one.
    assert old["tree"] == old["tree_now"]
    assert new["tree"] == new["tree_now"] != old["tree"]
    # Condition (15) input was re-read after the push: absent before, present after.
    assert old["failed"] == []
    assert new["failed"] == ["ci-red-after-push"]
    assert 15 in _holds(res)


def test_fix_round_one_round_per_run(rig):
    llm = rig.llm(later=[FINDING, OTHER])
    res = rig.run(llm)
    # At most two rounds; a rejected round 1 never starts round 2, so one fixer call here.
    assert fix_round.MAX_FIX_ROUNDS == 2
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


def test_fix_round_rejected_keeps_fingerprint(rig, tmp_path, monkeypatch):
    # Replay has no plan file to fingerprint, so derive one from the diff the run ends on. A
    # rejected round that leaked its commit would end on a different diff, hence a different one.
    monkeypatch.setattr(holdrepeat, "fingerprint",
                        lambda plan_path, worktree, *a, **k: hashlib.sha256(
                            rig.git.diff_vs_base().encode()).hexdigest())
    # Baseline: the same run with the round off.
    monkeypatch.setenv(fix_round.ENV_FLAG, "0")
    rig.run(rig.llm(), state_dir=str(tmp_path / "state-off"), name="off")
    baseline = holdrepeat.load_record(str(tmp_path / "state-off"), SLUG)
    assert baseline and baseline["fingerprint"]
    assert "fix_round" not in baseline
    monkeypatch.delenv(fix_round.ENV_FLAG)
    sd = tmp_path / "state"
    llm = rig.llm(later=[OTHER])
    res = rig.run(llm, state_dir=str(sd), name="rejected")
    assert res.fix_round["status"] == "rejected"
    rec = holdrepeat.load_record(str(sd), SLUG)
    assert rec["fix_round"]["attempts"] == 1
    assert rec["fix_round"]["targets"] == [2, 3]
    assert rec["fix_round"]["status"] == "rejected"
    assert rec["fingerprint"] == baseline["fingerprint"]
    volatile = ("fix_round", "updated_at")
    assert ({k: v for k, v in rec.items() if k not in volatile}
            == {k: v for k, v in baseline.items() if k not in volatile})


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


# ---- Holds (12), (14), (19) through the runner ----

CALC = "def f(total_cap, used):\n    return total_cap - used\n"
ROW_PLAN = _plan_text(_row_block(1, check="true"), rows=(1,))
BASELINE_LINE = "row 1 BASELINE-FAILED — true exited 1 before any mutation"
GATE_FILE = ".claude/rules/some-gate.md"
FID_DIGEST = "\n## DIGEST\n\n**What changed:** a synthetic replay change\n"


def _fid_doc(word, findings=""):
    return f"## 6d checks\n\nCheck 3: fine.\n\n{word}\n{findings}{FID_DIGEST}"


class QuietLlm(FrLlm):
    """Reviews find nothing, so no (2) hold brings the review fixers along."""

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        return WritingLlm.call(self, purpose, model, prompt, validate, max_retries, normalizer)

    def round_purposes(self):
        wanted = {"fix-round-row-repair", "fidelity-remediation", "thread-remediation", "conflict-review"}
        return [purpose for purpose, _ in self.tooled_argvs if purpose in wanted]


def _quiet(rig, **canned):
    merged = dict(CANNED)
    for p in VERIFIER_PURPOSES:
        merged[p] = _all_real(1)
    merged.update(canned)
    return QuietLlm(UsageLedger(), merged, git_getter=lambda: rig.git)


def _sticky_body(rig):
    posts = [a for a in rig.actions() if a["action"] == "pr_sticky_verdict"]
    assert posts
    return posts[-1]["body"]


# ---- (14): a conflict review with no verdict stays a hold ----

def test_runner_absent_conflict_review_never_arms_3705(rig, monkeypatch):
    key = f"t-{os.getpid()}-3705"
    review_dir = f"/tmp/postplan-conflict-review-{key}"
    os.makedirs(review_dir, exist_ok=True)
    with open(os.path.join(review_dir, "resolved-files.txt"), "w") as fh:
        fh.write("a.php\n")
    with open(os.path.join(review_dir, "proof-output.txt"), "w") as fh:
        fh.write("TREE-EQUIVALENT")
    git_run = lambda *a, **k: "deadbeef\n"
    got = []

    def rereview(llm, git):
        got.append(runner.conflict.rereview_absent(llm, git_run, worktree=str(rig.wt), key=key))
        return got[-1]

    monkeypatch.setattr(runner, "_fix_round_conflict_rereview", rereview)
    llm = _quiet(rig, **{"conflict-review": "no token here"})
    try:
        res = rig.run(llm, extra={"conflict_resolved": True,
                                  "conflict_verdict": runner.conflict.VERDICT_ABSENT})
    finally:
        runner.conflict.purge_verdict_artifacts(key)
        shutil.rmtree(review_dir, ignore_errors=True)
    assert got == [(runner.conflict.VERDICT_ABSENT, runner.conflict.CONFLICT_REREVIEW_ATTEMPTS, "reviewed")]
    assert llm.round_purposes() == ["conflict-review"] * (runner.conflict.CONFLICT_REREVIEW_ATTEMPTS * 2)
    assert res.fix_round["conflict_rereview"]["verdict"] == runner.conflict.VERDICT_ABSENT
    assert res.fix_round["reevaluate"] is False
    assert 14 in _holds(res)
    assert not res.arm.armed


# ---- (12): one remediation pass for a NOT READY blocker that names a PR file ----

def _pr_extra(pr):
    return {"pr_number": pr, "pr_meta": dict(_fixture()["pr_meta"], number=pr)}


@pytest.fixture
def fid_pr():
    """Hand out a PR number of the test's own, and clear its /tmp verdict files afterward."""
    used = []

    def take(pr):
        used.append(pr)
        return pr

    yield take
    for pr in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{pr}*"):
            os.unlink(path)


def _not_ready_llm(rig, finding):
    doc = _fid_doc("NOT READY", f"\n## FINDINGS\n\n- [BLOCKING] {finding}\n")
    return _quiet(rig, **{"plan-fidelity-review": doc, "fidelity-remediation": "done",
                          "plan-fidelity-re-review-2": doc, "plan-fidelity-re-review-3": doc,
                          "plan-fidelity-re-review-4": doc})


def test_fidelity_pass_keeps_gate_path_deny(rig, monkeypatch, fid_pr):
    pr = fid_pr(7301)
    monkeypatch.setattr(
        ReplayGit, "conformance_files",
        lambda git, base="origin/master": [GATE_FILE] if base.endswith("^") else [PATH, GATE_FILE])
    verdicts = []
    real_permit = runner.fidelity_filefix.permit

    def permit(hits, *a, **k):
        verdicts.append((list(hits), real_permit(hits, *a, **k)))
        return verdicts[-1][1]

    monkeypatch.setattr(runner.fidelity_filefix, "permit", permit)

    def strip(gitad, sha, hits, commit, log, out):
        gitad.commit_messages.pop()   # the real strip leaves no commit for a gate-only edit
        return ""

    monkeypatch.setattr(runner.fidelity, "_strip_gate_paths", strip)
    res = rig.run(_not_ready_llm(rig, f"`{PATH}` still drops the row."), extra=_pr_extra(pr))
    assert res.fix_round["fidelity"]["ran"] is True
    assert res.fix_round["fidelity"]["blockers"] == 1
    assert res.fix_round["fidelity"]["outcome"] == "gate-path-edit"
    assert verdicts[-1] == ([GATE_FILE], False)
    assert res.fix_round["status"] == "no-change"
    assert runner.fidelity.REMEDIATION_COMMIT_MSG not in rig.git.commit_messages
    assert 12 in _holds(res)


def test_fidelity_fix_skips_unplanned_scope_blockers(rig, monkeypatch, fid_pr):
    pr = fid_pr(7302)
    # Keep the hold standing, so the round is the one that meets these blockers.
    monkeypatch.setattr(runner.unplanned_scope, "downgrade_applies", lambda *a, **k: False)
    llm = _not_ready_llm(rig, f"[unplanned-scope] `{PATH}` is outside every phase.")
    during_round = []
    real_round = fix_round.run_fix_round

    def counted(**kw):
        before = len(llm.round_purposes())
        out = real_round(**kw)
        during_round.extend(llm.round_purposes()[before:])
        return out

    monkeypatch.setattr(fix_round, "run_fix_round", counted)
    res = rig.run(llm, extra=_pr_extra(pr))
    assert res.fix_round["fidelity"] == {"ran": False, "reason": "unplanned-scope-only"}
    assert during_round == []
    assert 12 in _holds(res)


def test_named_paths_line_suffix_matches_pr_file():
    from harness import fidelity_filefix as ff
    full = "tools/postplan-harness/harness/sql_readonly_scan.py"
    got = ff.named_paths(f"- [BLOCKING] `{full}:1587` drops the row.", [full], line_suffix=True)
    assert got == {full}
    got = ff.named_paths("`sql_readonly_scan.py:12-40` drops it.", [full], line_suffix=True)
    assert got == {full}


def test_named_paths_line_suffix_off_by_default():
    from harness import fidelity_filefix as ff
    full = "tools/postplan-harness/harness/sql_readonly_scan.py"
    assert ff.named_paths(f"`{full}:1587` drops the row.", [full]) == set()


def test_eligible_paths_ignores_line_suffix():
    from harness import fidelity_filefix as ff
    rules = ".claude/rules/ci-gotchas.md"
    assert ff.eligible_paths([f"- [BLOCKING] `{rules}:26` is wrong."], [rules]) == frozenset()


def test_named_paths_line_suffix_near_miss():
    from harness import fidelity_filefix as ff
    files = ["harness/foo.py"]
    for span in ("foo.py:abc", "foo.py:", "12:30"):
        assert ff.named_paths(f"`{span}` is wrong.", files, line_suffix=True) == set(), span


def test_fr_fidelity_fix_accepts_line_suffixed_blocker(rig, monkeypatch, fid_pr):
    pr = fid_pr(7303)
    monkeypatch.setattr(runner.unplanned_scope, "downgrade_applies", lambda *a, **k: False)
    res = rig.run(_not_ready_llm(rig, f"`{PATH}:1587` still drops the row."), extra=_pr_extra(pr))
    assert res.fix_round["fidelity"].get("ran") is True
    assert res.fix_round["fidelity"].get("reason") != "no-file-blocker"


def test_fr_fidelity_fix_unplanned_scope_only_reason(rig, monkeypatch, fid_pr):
    pr = fid_pr(7304)
    monkeypatch.setattr(runner.unplanned_scope, "downgrade_applies", lambda *a, **k: False)
    res = rig.run(_not_ready_llm(rig, f"[unplanned-scope] `{PATH}:12` is outside every phase."),
                  extra=_pr_extra(pr))
    assert res.fix_round["fidelity"] == {"ran": False, "reason": "unplanned-scope-only"}


def test_fr_fidelity_fix_no_texts_keeps_no_file_blocker(rig, monkeypatch, fid_pr):
    pr = fid_pr(7305)
    monkeypatch.setattr(runner.unplanned_scope, "downgrade_applies", lambda *a, **k: False)
    monkeypatch.setattr(runner.fidelity, "blocking_finding_texts", lambda path: [])
    res = rig.run(_not_ready_llm(rig, f"`{PATH}` still drops the row."), extra=_pr_extra(pr))
    assert res.fix_round["fidelity"] == {"ran": False, "reason": "no-file-blocker"}


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


# ---- Condition (16): the pre-push flag after a meta-check fix ----

class _BranchGit:
    def branch(self):
        return "b"


def _meta_flag_setup(tmp_path, monkeypatch):
    from harness.armable import meta_checks_flag_path
    from harness.state import RunResult
    monkeypatch.setattr(runner, "META_CHECKS_TMP_DIR", str(tmp_path))
    flag = meta_checks_flag_path("b", str(tmp_path))
    with open(flag, "w") as fh:
        fh.write("check-docs-since\n")
    res = RunResult(terminal="x")
    res.meta_check_failures = [{"name": "check-docs-since", "output": "x"}]
    res.meta_checks_ok = False
    return flag, res


def test_meta_flag_cleared_after_accepted_cleared_round(tmp_path, monkeypatch):
    flag, res = _meta_flag_setup(tmp_path, monkeypatch)
    logs = []
    fr = {"status": "accepted", "meta": {"verdict": "cleared"}}
    assert runner._clear_meta_flag_after_fix_round(fr, _BranchGit(), res, logs.append) is True
    assert not os.path.exists(flag)
    assert res.meta_check_failures == []
    assert res.meta_checks_ok is True
    assert any("meta-check flag cleared" in m for m in logs)


@pytest.mark.parametrize("fr", [
    {"status": "rejected", "meta": {"verdict": "cleared"}},
    {"status": "accepted", "meta": {"verdict": "refused"}},
])
def test_meta_flag_kept_when_round_not_cleared(tmp_path, monkeypatch, fr):
    flag, res = _meta_flag_setup(tmp_path, monkeypatch)
    assert runner._clear_meta_flag_after_fix_round(fr, _BranchGit(), res, lambda m: None) is False
    assert os.path.exists(flag)
    assert res.meta_check_failures == [{"name": "check-docs-since", "output": "x"}]


# ---- The second round's gate, through the real runner loop ----

def _hold_sequence(monkeypatch, *sets):
    """Make each distinct arming evaluation block exactly the listed target conditions.

    The rig's own holds are real production output; this overrides only the target members
    per evaluation, in order, so a test names the pre- and post-round sets. The last set
    repeats for any later evaluation.
    """
    seen = []
    real_evaluate = runner.evaluate

    def evaluate(inputs):
        if not seen or seen[-1] is not inputs:
            seen.append(inputs)
        want = sets[min(len(seen), len(sets)) - 1]
        decision = real_evaluate(inputs)
        for c in decision.conditions:
            if c.number in fix_round.TARGET_CONDITIONS:
                c.blocked = c.number in want
        return decision

    monkeypatch.setattr(runner, "evaluate", evaluate)


def _fake_rounds(rig, monkeypatch, *outcomes):
    """Replace run_fix_round with a recorder that returns one canned record per call.

    An accepted outcome commits through the runner's own commit callback first, so the rig
    git advances its head and the runner pushes a real new sha. Returns (calls, records).
    """
    calls, records = [], []

    def fake(**kw):
        calls.append(kw)
        status, reason = outcomes[len(calls) - 1]
        rec = {"status": status, "reason": reason, "head_before": rig.git.head(),
               "targets": fix_round.targets(kw["decision"]), "items": [], "defer": [],
               "row_repairs": [], "reevaluate": status == "accepted"}
        if status == "accepted":
            kw["commit"](f"fix(review): round {len(calls)}")
            rec["sha"] = rig.git.head()
        records.append(rec)
        return rec

    monkeypatch.setattr(fix_round, "run_fix_round", fake)
    return calls, records


ACCEPTED = ("accepted", "recheck-clean")


def _log(rig):
    return (rig.tmp_path / "out" / "audit.log").read_text()


def test_runner_second_round_runs_after_accepted_shrink(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {3}, set())
    calls, _ = _fake_rounds(rig, monkeypatch, ACCEPTED, ACCEPTED)
    res = rig.run(rig.llm(later=[]))
    assert len(calls) == 2
    rounds = res.fix_round["rounds"]
    assert len(rounds) == 2
    assert rounds[0]["status"] == "accepted"
    on_disk = json.loads((rig.tmp_path / "out" / "fix-round.json").read_text())
    assert on_disk["rounds"] == rounds
    assert "phase6.5-fix: blocked target set shrank; starting round 2" in _log(rig)


def test_runner_second_round_gets_prior_and_shared_deadline(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {3}, set())
    calls, records = _fake_rounds(rig, monkeypatch, ACCEPTED, ACCEPTED)
    rig.run(rig.llm(later=[]))
    assert len(calls) == 2
    assert calls[0]["prior"] is None
    assert calls[0]["already_tried"] is False
    assert calls[1]["prior"] == records[0]
    assert calls[1]["already_tried"] is False
    assert calls[0]["deadline_at"] is not None
    assert calls[0]["deadline_at"] == calls[1]["deadline_at"]


def test_runner_no_second_round_without_shrink(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {2, 3})
    calls, _ = _fake_rounds(rig, monkeypatch, ACCEPTED, ACCEPTED)
    res = rig.run(rig.llm(later=[]))
    assert len(calls) == 1
    assert "rounds" not in res.fix_round
    assert "phase6.5-fix: no round 2 (no-shrink)" in _log(rig)


def test_runner_no_second_round_after_reject(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {3})
    calls, _ = _fake_rounds(rig, monkeypatch, ("rejected", "new-finding"), ACCEPTED)
    res = rig.run(rig.llm(later=[]))
    assert len(calls) == 1
    assert "rounds" not in res.fix_round
    assert "phase6.5-fix: no round 2 (round-1-rejected)" in _log(rig)


def test_runner_no_second_round_after_deadline(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {3})
    calls, _ = _fake_rounds(rig, monkeypatch, ACCEPTED, ACCEPTED)
    monkeypatch.setattr(fix_round, "time_left", lambda *a, **k: 0.0)
    res = rig.run(rig.llm(later=[]))
    assert len(calls) == 1
    assert "rounds" not in res.fix_round
    assert "phase6.5-fix: no round 2 (deadline)" in _log(rig)


def test_runner_second_round_push_failure_keeps_round_one(rig, monkeypatch):
    _hold_sequence(monkeypatch, {2, 3}, {3}, {3})
    calls, _ = _fake_rounds(rig, monkeypatch, ACCEPTED, ACCEPTED)
    real_push = runner._push_with_gate_fix
    pushes = []

    def boom(git, log, phase, *a, **k):
        if phase == "phase6.5-fix":
            pushes.append(git.head())
            if len(pushes) == 2:
                raise HarnessError("push-failed", "no")
        return real_push(git, log, phase, *a, **k)

    monkeypatch.setattr(runner, "_push_with_gate_fix", boom)
    res = rig.run(rig.llm(later=[]))
    assert len(calls) == 2
    rounds = res.fix_round["rounds"]
    assert rounds[0]["status"] == "accepted"
    assert rounds[1]["status"] == "rejected"
    assert rounds[1]["reason"] == "push-failed"
    assert rig.git.head() == rounds[0]["sha"]
    assert rig.git.drops == [rounds[1]["head_before"]]


# ---- fix-round fidelity re-judge (verdict 2 on the post-fix tree) ----

TREE_A = "a" * 40
TREE_B = "b" * 40
FIX_SHA = "c" * 40


class _TreeGit:
    """A git whose head tree is fixed (or scripted) for the re-judge unit tests."""

    def __init__(self, *trees):
        self.trees = list(trees)
        self.calls = 0

    def head_tree(self):
        tree = self.trees[min(self.calls, len(self.trees) - 1)]
        self.calls += 1
        return tree


class _PrGh:
    def pr_body_fresh(self):
        return "body"


def test_repin_without_verdict_2_still_skips_for_stale_base_caller(monkeypatch):
    seen = []
    monkeypatch.setattr(runner.fidelity, "re_review", lambda *a, **k: seen.append(a) or ("READY", TREE_B, "p"))
    res = SimpleNamespace(fidelity={"verdict_1": "READY WITH NOTES", "reviewed_tree": TREE_A}, slug="")
    runner._repin_fidelity_after_same_run(None, _TreeGit(TREE_B), _PrGh(), "out", "wt", None,
                                          "m", "body", 7, FIX_SHA, res, lambda *_: None)
    assert seen == []
    assert "verdict_2" not in res.fidelity


def _rejudge(monkeypatch, fid, *, head=TREE_B, fix_sha=FIX_SHA, returns=("READY", TREE_B, "/tmp/p")):
    seen = []

    def rec(*a, **k):
        seen.append((a, k))
        return returns

    monkeypatch.setattr(runner.fidelity, "re_review", rec)
    res = SimpleNamespace(fidelity=fid, slug="")
    runner._rejudge_fidelity_after_fix_round(None, _TreeGit(head), _PrGh(), "out", "wt", None,
                                             "m", "body", 7, fix_sha, res, lambda *_: None)
    return res, seen


def _fid(verdict="READY WITH NOTES"):
    return {"verdict_1": verdict, "reviewed_tree": TREE_A, "verdict_path": "/tmp/v1"}


def test_re_review_records_packet_tree_when_head_moves(tmp_path, monkeypatch):
    class MovingGit:
        def __init__(self):
            self.calls = 0

        def head_tree(self):
            self.calls += 1
            return TREE_A if self.calls == 1 else TREE_B

        def diff_vs_base(self, base="origin/master"):
            return ""

    class Llm:
        def call_tooled(self, *a, **k):
            return _fid_doc("READY")

    packet_trees = []

    def build_packet(out_dir, master_sha, tree, *a, **k):
        packet_trees.append(tree)
        return str(tmp_path)

    monkeypatch.setattr(runner.fidelity, "build_packet", build_packet)
    monkeypatch.setattr(runner.fidelity, "verdict_path", lambda pr: str(tmp_path / f"v-{pr}.md"))
    verdict, tree, _ = runner.fidelity.re_review(Llm(), MovingGit(), str(tmp_path), str(tmp_path), None,
                                                 "m", "body", 7, FIX_SHA, "/tmp/v1", round_num=1)
    assert verdict == "READY"
    assert packet_trees == [TREE_A]
    assert tree == TREE_A


def test_fix_round_rejudge_sets_verdict_2_on_post_fix_tree(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid())
    fid = res.fidelity
    assert len(seen) == 1
    assert seen[0][0][8] == FIX_SHA   # remediation_sha positional
    assert fid["verdict_2"] == "READY"
    assert fid["reviewed_tree_2"] == TREE_B
    assert fid["same_run_repin"]["kind"] == "fix-round"
    assert fid["same_run_repin"]["sha"] == FIX_SHA


def test_fix_round_rejudge_never_sets_remediation_sha(monkeypatch):
    res, _ = _rejudge(monkeypatch, _fid())
    assert "remediation_sha" not in res.fidelity


def test_fix_round_rejudge_none_verdict_leaves_verdict_2_unset(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid(), returns=(None, TREE_B, "/tmp/p"))
    assert len(seen) == 1
    for key in ("verdict_2", "reviewed_tree_2", "same_run_repin"):
        assert key not in res.fidelity


def test_fix_round_rejudge_none_tree_leaves_verdict_2_unset(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid(), returns=("READY", None, "/tmp/p"))
    assert len(seen) == 1
    for key in ("verdict_2", "reviewed_tree_2", "same_run_repin"):
        assert key not in res.fidelity


def test_fix_round_rejudge_skips_when_head_tree_unchanged(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid(), head=TREE_A)
    assert seen == []
    assert "verdict_2" not in res.fidelity


def test_fix_round_rejudge_skips_without_fix_sha(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid(), fix_sha=None)
    assert seen == []
    assert "verdict_2" not in res.fidelity


def test_fix_round_rejudge_skips_not_ready_verdict_1(monkeypatch):
    res, seen = _rejudge(monkeypatch, _fid("NOT READY"))
    assert seen == []
    assert "verdict_2" not in res.fidelity


def test_second_fix_round_repin_keeps_fix_round_kind(monkeypatch):
    """Round 2's repin rewrites same_run_repin with no kind; the fix-round wording must survive."""
    from harness import fidelity
    fid = dict(_fid(), verdict_2="READY", reviewed_tree_2=TREE_B,
               same_run_repin={"sha": FIX_SHA, "verdict": "READY", "verdict_path": "/tmp/p",
                               "kind": "fix-round"})
    res = SimpleNamespace(fidelity=fid, slug="")
    prior = dict(fid["same_run_repin"])
    monkeypatch.setattr(runner.fidelity, "re_review", lambda *a, **k: ("NOT READY", TREE_A, "/tmp/p2"))
    runner._repin_fidelity_after_same_run(None, _TreeGit(TREE_A), _PrGh(), "out", "wt", None,
                                          "m", "body", 7, "d" * 40, res, lambda *_: None)
    assert "kind" not in res.fidelity["same_run_repin"]  # the shared helper stays untouched
    runner._keep_fix_round_kind(res, prior)
    assert res.fidelity["same_run_repin"]["kind"] == "fix-round"
    assert res.fidelity["same_run_repin"]["sha"] == "d" * 40
    assert fidelity.sticky_terminal(res.fidelity).startswith("NOT READY")


def test_keep_fix_round_kind_leaves_pr_copy_repin_alone():
    res = SimpleNamespace(fidelity={"same_run_repin": {"sha": FIX_SHA, "verdict": "READY"}})
    runner._keep_fix_round_kind(res, {"sha": "x", "verdict": "READY"})
    assert "kind" not in res.fidelity["same_run_repin"]


def _rejudge_rig_llm(rig, **kw):
    llm = rig.llm(**kw)
    llm.canned["plan-fidelity-review"] = _fid_doc("READY WITH NOTES")
    return llm


def test_fix_round_rejudges_fidelity_on_post_fix_tree(rig, monkeypatch, fid_pr):
    pr = fid_pr(7311)
    calls = []

    def rejudge(*a, **k):
        calls.append(rig.git.head_tree())
        return "READY", rig.git.head_tree(), f"/tmp/post-plan-fidelity-verdict-{pr}-2.md"

    monkeypatch.setattr(runner.fidelity, "re_review", rejudge)
    seen = []
    real_evaluate = runner.evaluate

    def spy(inputs):
        seen.append(inputs.fidelity_verdict_2)
        return real_evaluate(inputs)

    monkeypatch.setattr(runner, "evaluate", spy)
    res = rig.run(_rejudge_rig_llm(rig, later=[]), extra=_pr_extra(pr))
    assert res.fix_round["status"] == "accepted"
    assert len(calls) == 1
    assert res.fidelity["reviewed_tree_2"] == rig.git.head_tree() == calls[0]
    assert res.fidelity["reviewed_tree_2"] != res.fidelity["reviewed_tree"]
    assert seen[-1] == "READY"


def test_no_change_fix_round_runs_no_fidelity_judge(rig, monkeypatch, fid_pr):
    pr = fid_pr(7312)
    calls = []
    monkeypatch.setattr(runner.fidelity, "re_review", lambda *a, **k: calls.append(a) or ("READY", TREE_B, "p"))
    res = rig.run(_rejudge_rig_llm(rig, later=[OTHER]), extra=_pr_extra(pr))
    assert res.fix_round["status"] == "rejected"
    assert calls == []
    assert "verdict_2" not in res.fidelity or res.fidelity["verdict_2"] is None
