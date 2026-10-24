"""End-to-end replays of the owner-approved-deviation gate against the shapes of PRs
#3428, #3514 and #3520. Each fixture under fixtures/owner-approvals/ is a full run() replay
fixture (representative content, never the real diffs); the tests assert the revert check,
the hold, the fidelity downgrade and the "nothing pushed" guarantee on the whole run."""
import copy
import glob
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, owner_approvals as oa
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger
from test_runner_replay import CANNED, _actions, _verdict_doc

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures", "owner-approvals")

TX_PATH = "ibl5/classes/Trade/TradeExecutionService.php"
WD_PATH = "bin/lib/watchdog.sh"
THREAD_FIX = '{"verdict":"FIX","reason":"Restored the watchdog"}'


def _load(name):
    with open(os.path.join(FIXTURES, name)) as fh:
        return json.load(fh)


class _ScriptedLlm(FixtureLlm):
    """FixtureLlm serving scripted replies per purpose, in order; records tooled purposes."""

    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, dict(canned))
        self.scripts = {k: list(v) for k, v in scripts.items()}
        self.tooled = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.tooled.append(purpose)
        queue = self.scripts.get(purpose)
        if queue:
            self.canned[purpose] = queue.pop(0)
        return super().call_tooled(purpose, model, prompt, **kw)


@pytest.fixture
def verdict_cleanup():
    used = []
    yield used.append
    for n in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{n}*"):
            try:
                os.unlink(path)
            except OSError:
                pass


@pytest.fixture
def captured_git(monkeypatch):
    seen = []

    class _CapturingReplayGit(runner.ReplayGit):
        """ReplayGit that records itself. ReplayGit gives every commit the same sha, so the
        Phase 2 commit (always the first) would be checked against the fixer's `files_at`
        refs. It gets its own sha, which no fixture lists, so only the fixer commit is
        under test."""

        def __init__(self, fixture):
            super().__init__(fixture)
            self.events = []
            seen.append(self)

        def commit_all(self, message):
            first = not self.commit_messages
            sha = super().commit_all(message)
            self.events.append("commit-phase2" if first else "commit")
            return sha + "-phase2" if first else sha

        def drop_commit(self, pre):
            self.events.append("drop")
            return super().drop_commit(pre)

        def push(self):
            self.events.append("push")
            return super().push()

        def push_ff(self):
            self.events.append("push")
            return super().push_ff()

    monkeypatch.setattr(runner, "ReplayGit", _CapturingReplayGit)
    return seen


def _blocking_doc(path, extra=""):
    return ("## 6d checks\n\nCheck 3 deviates.\n\n## FINDINGS\n\n"
            f"- [BLOCKING] The filter removal in {path} deviates from the plan.\n{extra}"
            "\nNOT READY\n\n## DIGEST\n\n**What changed:** a synthetic replay change\n")


def _go(name, tmp_path, verdict_cleanup, scripts, mutate=None):
    fx = _load(name)
    if mutate:
        mutate(fx)
    pr = fx["pr_number"]
    verdict_cleanup(pr)
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _ScriptedLlm(UsageLedger(), canned, scripts)
    out = str(tmp_path / "out")
    res = runner.run(fx, out, llm, mode="replay")
    return res, llm, out, fidelity.verdict_path(pr)


def _pushes(git, out):
    """Pushes after the first fixer commit. The Phase 2 commit and its push precede every
    fixer and are not under test."""
    events = git.events
    after = events[events.index("commit") + 1:] if "commit" in events else []
    return after.count("push") + len(
        [a for a in _actions(out) if a["action"] in ("push", "pr_push")])


def _hit_kinds(res):
    return sorted(h["kind"] for h in res.owner_approval_hold["hits"])


def _held_notice(out):
    return [a for a in _actions(out)
            if a["action"] == "pr_comment" and a.get("title") == "Owner approval hold"]


# --- scripts ------------------------------------------------------------------

def _wd_scripts():
    return {"thread-remediation": [THREAD_FIX],
            "plan-fidelity-review": [_verdict_doc("READY")]}


def _fidelity_scripts(first_doc, second="READY"):
    return {"plan-fidelity-review": [first_doc],
            "fidelity-remediation": ["edits made"],
            "plan-fidelity-re-review-2": [_verdict_doc(second)]}


# --- #3514: the deleted watchdog is restored by a thread fix -------------------

def test_replay_3514_restored_watchdog_is_dropped_and_held(tmp_path, verdict_cleanup, captured_git):
    res, llm, out, _ = _go("pr3514-watchdog-deleted.json", tmp_path, verdict_cleanup, _wd_scripts())
    git = captured_git[0]
    assert "thread-remediation" in llm.tooled
    assert git.drops == ["pre-sha"]
    assert _pushes(git, out) == 0
    assert res.owner_approval_state == "REVERT"
    assert res.error_kind == "owner-approval-revert"
    assert _hit_kinds(res) == ["gone-restored"] * 6
    assert {h["path"] for h in res.owner_approval_hold["hits"]} == {WD_PATH}
    assert len(_held_notice(out)) == 1
    assert runner.exit_code_for(res) == 3


# --- #3428: the removed filter comes back via fidelity remediation --------------

def test_replay_3428_restored_filter_is_dropped_and_held(tmp_path, verdict_cleanup, captured_git):
    res, llm, out, _ = _go("pr3428-filter-removed.json", tmp_path, verdict_cleanup,
                           _fidelity_scripts(_blocking_doc(TX_PATH)))
    git = captured_git[0]
    assert "fidelity-remediation" in llm.tooled
    assert git.drops == ["pre-sha"]
    assert _pushes(git, out) == 0
    assert res.owner_approval_state == "REVERT"
    assert "gone-restored" in _hit_kinds(res)
    assert len(_held_notice(out)) == 1


# --- #3520: the fixer undoes two owner fixes ------------------------------------

def test_replay_3520_two_owner_fixes_reverted_are_dropped(tmp_path, verdict_cleanup, captured_git):
    res, _, out, _ = _go("pr3520-fixer-reverts-owner-fixes.json", tmp_path, verdict_cleanup,
                         _fidelity_scripts(_blocking_doc("ibl5/classes/Draft/DraftService.php")))
    git = captured_git[0]
    assert git.drops == ["pre-sha"]
    assert _pushes(git, out) == 0
    assert res.owner_approval_state == "REVERT"
    # Fix (a) is two keep lines in DraftService, fix (c) one line in DraftValidator.
    assert _hit_kinds(res) == ["keep-lost", "keep-lost", "keep-lost"]
    assert {h["path"] for h in res.owner_approval_hold["hits"]} == {
        "ibl5/classes/Draft/DraftService.php", "ibl5/classes/Draft/DraftValidator.php"}


def test_replay_3520_neighbor_edit_passes_and_pushes(tmp_path, verdict_cleanup, captured_git):
    res, llm, out, _ = _go("pr3520-neighbor-edit-passes.json", tmp_path, verdict_cleanup,
                           _fidelity_scripts(_blocking_doc("ibl5/classes/Draft/DraftService.php")))
    git = captured_git[0]
    assert "fidelity-remediation" in llm.tooled
    assert git.drops == []
    assert git.commit_messages, "the remediation commit must land"
    assert _pushes(git, out) >= 1
    assert res.owner_approval_state == "CLEAR"
    assert res.owner_approval_hold is None
    assert res.error_kind in (None, "")


# --- a Bot-authored record is ignored -------------------------------------------

def test_replay_3514_bot_record_has_no_effect(tmp_path, verdict_cleanup, captured_git):
    res, _, out, _ = _go("pr3514-bot-record-no-effect.json", tmp_path, verdict_cleanup, _wd_scripts())
    git = captured_git[0]
    assert res.owner_approvals == {"ids": [], "ignored_untrusted": 1}
    assert res.owner_snapshot.records == ()
    assert res.owner_snapshot.ignored_untrusted == 1
    assert git.drops == []
    assert git.commit_messages, "the restoring commit must stand"
    assert res.owner_approval_state == "CLEAR"
    assert _held_notice(out) == []


# --- #3428 fidelity: a cited finding is downgraded, an uncited one is not -----------

CITE = "  approved-by-owner: oad-3428bbbb"


def test_replay_3428_cited_finding_clears_condition_12(tmp_path, verdict_cleanup, captured_git):
    res, llm, out, vpath = _go("pr3428-cited-fidelity.json", tmp_path, verdict_cleanup,
                               _fidelity_scripts(_blocking_doc(TX_PATH, CITE + "\n")))
    git = captured_git[0]
    assert res.fidelity["verdict_1"] == "READY WITH NOTES"
    assert fidelity.parse_verdict(vpath) == "READY WITH NOTES"
    assert "[BLOCKING]" not in open(vpath).read().split(fidelity.DIGEST_CUT)[0]
    assert "fidelity-remediation" not in llm.tooled
    assert git.drops == [] and "commit" not in git.events   # only the Phase 2 commit
    assert res.owner_approval_state == "CLEAR"
    held = {c.number for c in res.arm.holds}
    assert 12 not in held and 20 not in held


def test_replay_3428_uncited_finding_still_not_ready(tmp_path, verdict_cleanup, captured_git):
    res, llm, out, vpath = _go("pr3428-uncited-fidelity.json", tmp_path, verdict_cleanup,
                               _fidelity_scripts(_blocking_doc(TX_PATH)))
    git = captured_git[0]
    assert res.fidelity["verdict_1"] == "NOT READY"
    assert "fidelity-remediation" in llm.tooled
    assert git.drops == ["pre-sha"]
    assert _pushes(git, out) == 0
    assert res.owner_approval_state == "REVERT"


# --- every held fixture pushes nothing -------------------------------------------

HELD = [
    ("pr3514-watchdog-deleted.json", lambda: _wd_scripts()),
    ("pr3428-filter-removed.json", lambda: _fidelity_scripts(_blocking_doc(TX_PATH))),
    ("pr3520-fixer-reverts-owner-fixes.json",
     lambda: _fidelity_scripts(_blocking_doc("ibl5/classes/Draft/DraftService.php"))),
    ("pr3428-uncited-fidelity.json", lambda: _fidelity_scripts(_blocking_doc(TX_PATH))),
]


@pytest.mark.parametrize("name,scripts", HELD, ids=[h[0] for h in HELD])
def test_replay_nothing_pushed_in_any_held_fixture(tmp_path, verdict_cleanup, captured_git, name, scripts):
    res, _, out, _ = _go(name, tmp_path, verdict_cleanup, scripts())
    git = captured_git[0]
    assert _pushes(git, out) == 0
    assert res.owner_approval_state != "CLEAR"
    assert git.drops, "a held fixture drops its fixer commit"
