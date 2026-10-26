"""Phase 5.0-pre conformance fixer wired into runner.run() (replay mode).

No model is called. `WritingLlm` performs the writes a real fixer would; the replayed git
exposes the post-fix diff once the fixer's commit has landed, which is how the runner's
refresh (files, conformance files, diff, classification) becomes observable.
"""
import json
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, conformance, conformance_fix
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.adapters.verify import ReplayVerify
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

SLUG = "confix-synthetic"
PROMISED = "ibl5/tests/Fixer/PromisedTest.php"

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit",
                "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
    "conformance-fix": "done",
}

PLAN = f"""# Synthetic plan

## Verification Matrix

| # | What to verify | Test type | When | Test |
|---|---|---|---|---|
| 1 | the promised behavior holds | PHPUnit | post-impl | `{PROMISED}` |

## Required Test Methods

- `testFoo()`
"""

PHASE_PLAN = PLAN + """
## Phase 1: Production

Edit `ibl5/classes/Fixer/Thing.php`.

## Phase 2: Never shipped

Edit `ibl5/classes/Fixer/NeverShipped.php`.
"""

BASE_DIFF = ("diff --git a/ibl5/classes/Fixer/Thing.php b/ibl5/classes/Fixer/Thing.php\n"
             "--- a/ibl5/classes/Fixer/Thing.php\n+++ b/ibl5/classes/Fixer/Thing.php\n"
             "@@ -1 +1,2 @@\n <?php\n+echo 1;\n")


def _test_file_diff(method):
    return (f"diff --git a/{PROMISED} b/{PROMISED}\nnew file mode 100644\n"
            f"--- /dev/null\n+++ b/{PROMISED}\n@@ -0,0 +1,5 @@\n"
            "+<?php\n+final class PromisedTest extends TestCase\n+{\n"
            f"+    public function {method}(): void\n+    {{ $this->assertSame(1, 1); }}\n")


FIXED_DIFF = BASE_DIFF + _test_file_diff("testFoo")

GOOD_PHP = """<?php
final class PromisedTest extends TestCase
{
    public function testFoo(): void
    {
        $this->assertSame(1, 1);
    }
}
"""
WRONG_METHOD_PHP = GOOD_PHP.replace("testFoo", "testBar")


def _fixture(plan=PLAN, diff=BASE_DIFF):
    return {
        "slug": SLUG,
        "diff": diff,
        "pr_number": 4243,
        "pr_meta": {"number": 4243, "title": "fix: synthetic",
                    "body": "## Manual Testing\n\nNo manual testing needed\n\n**Merge danger:** Door: two-way · Blast radius: localized · Why: mechanical: no one-way trigger in the realized diff\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": plan,
    }


class WritingLlm(FixtureLlm):
    """FixtureLlm that also performs the writes a real fixer would, or raises."""

    def __init__(self, ledger, canned, writes=None, raise_kind=None, git_getter=None):
        super().__init__(ledger, canned)
        self.writes = writes or {}
        self.raise_kind = raise_kind
        self.git_getter = git_getter

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        out = super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)
        if purpose != "conformance-fix":
            return out
        git = self.git_getter() if self.git_getter else None
        for rel, text in self.writes.items():
            p = Path(cwd) / rel
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(text)
            if git is not None and rel not in git.extra_dirty:
                git.extra_dirty.append(rel)
        if self.raise_kind:
            raise HarnessError(self.raise_kind, "boom")
        return out


class Harness:
    """Wires one replay run: worktree, post-fix git view, phase-order events."""

    def __init__(self, tmp_path, monkeypatch, *, fixed_diff=FIXED_DIFF, base_diff=BASE_DIFF):
        self.tmp_path = tmp_path
        self.wt = tmp_path / "wt"
        self.wt.mkdir()
        self.gits = []
        self.events = []
        self.verify_cls = []
        wt = self.wt
        gits = self.gits
        orig_init = ReplayGit.__init__

        def init(git, fx):
            orig_init(git, fx)
            gits.append(git)

        def diff_vs_base(git, base="origin/master"):
            return fixed_diff if conformance_fix.COMMIT_MSG in git.commit_messages else base_diff

        def read_worktree_file(git, path):
            try:
                return (wt / path).read_text()
            except OSError:
                return None

        # A worktree switches on the commit-site ADR gate, which shells out to bin/adr-check.
        monkeypatch.setattr(adr_draft, "commit_gate", lambda worktree: (0, ""))
        monkeypatch.setattr(ReplayGit, "__init__", init)
        monkeypatch.setattr(ReplayGit, "diff_vs_base", diff_vs_base)
        monkeypatch.setattr(ReplayGit, "read_worktree_file", read_worktree_file)

        orig_run = ReplayVerify.run
        events, verify_cls = self.events, self.verify_cls

        def verify_run(v, cls):
            events.append("verify")
            verify_cls.append(cls)
            return orig_run(v, cls)

        monkeypatch.setattr(ReplayVerify, "run", verify_run)
        orig_handoff = runner._write_conformance_handoff

        def handoff(out_dir, unresolved):
            events.append("handoff")
            return orig_handoff(out_dir, unresolved)

        monkeypatch.setattr(runner, "_write_conformance_handoff", handoff)

    @property
    def git(self):
        return self.gits[-1]

    def llm(self, writes=None, raise_kind=None):
        return WritingLlm(UsageLedger(), dict(CANNED), writes=writes, raise_kind=raise_kind,
                          git_getter=lambda: self.git)

    def run(self, llm, fixture=None, name="out"):
        res = runner.run(fixture or _fixture(), str(self.tmp_path / name), llm,
                         mode="replay", worktree=str(self.wt))
        return res


def _spawned(llm):
    return [a[0] for a in llm.tooled_argvs if a[0] == "conformance-fix"]


# --- ordering and refresh ---------------------------------------------------------------

def test_pre_verify_pass_writes_no_handoff_marker(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    llm = h.llm(writes={PROMISED: GOOD_PHP})
    res = h.run(llm)
    assert res.conformance_fix["reason"] == "committed"
    assert h.events == ["verify", "handoff"]
    # The handoff marker exists only because the final 5.0 pass wrote it, after verify.
    assert os.path.exists(tmp_path / "out" / runner.CONFORMANCE_DONE_NAME)


def test_fixer_commit_is_in_verify_classification(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    res = h.run(h.llm(writes={PROMISED: GOOD_PHP}))
    assert res.conformance_fix["reason"] == "committed"
    assert len(h.verify_cls) == 1
    assert PROMISED in h.verify_cls[0].files


# --- what the fixer must never be handed --------------------------------------------------

def test_missing_phase_only_never_spawns_fixer_and_holds(tmp_path, monkeypatch):
    # The method and matrix path are already in the diff, so only the phase item remains.
    # A phase's citation counts as checkable only when tracked, so list it as tracked.
    monkeypatch.setattr(conformance, "_tracked_files",
                        lambda: ["ibl5/classes/Fixer/Thing.php", "ibl5/classes/Fixer/NeverShipped.php"])
    h = Harness(tmp_path, monkeypatch, base_diff=FIXED_DIFF)
    llm = h.llm()
    res = h.run(llm, fixture=_fixture(plan=PHASE_PLAN, diff=FIXED_DIFF))
    assert "conformance-fix" not in [a[0] for a in llm.tooled_argvs]
    assert any(i.startswith("MISSING-PHASE:") for i in res.unresolved_conformance)
    assert [i for i in res.unresolved_conformance if not i.startswith("MISSING-PHASE:")] == []
    assert res.conformance_fix["reason"] == "no-fixable-items"
    assert conformance_fix.COMMIT_MSG not in h.git.commit_messages


# --- the second check is the only thing that clears condition (3) ---------------------------

def test_fixed_method_clears_condition_three_and_arms(tmp_path, monkeypatch):
    # Clean baseline: the same fixture with the promised test already in the diff.
    (tmp_path / "baseline").mkdir()
    with monkeypatch.context() as mp:
        hb = Harness(tmp_path / "baseline", mp, base_diff=FIXED_DIFF)
        res_clean = hb.run(hb.llm(), fixture=_fixture(diff=FIXED_DIFF))
    assert res_clean.unresolved_conformance == []

    (tmp_path / "fixed").mkdir()
    h = Harness(tmp_path / "fixed", monkeypatch)
    llm = h.llm(writes={PROMISED: GOOD_PHP})
    res = h.run(llm)
    assert _spawned(llm) == ["conformance-fix"]
    assert res.conformance_fix["reason"] == "committed"
    assert res.unresolved_conformance == []
    assert res.conformance_fix["still_missing"] == []
    assert res.conformance_fix["resolved"]          # both the path and the method cleared
    assert res.terminal == res_clean.terminal
    assert [c.number for c in res.arm.holds] == [c.number for c in res_clean.arm.holds]


def test_unresolving_method_write_keeps_hold(tmp_path, monkeypatch):
    h = Harness(tmp_path, monkeypatch)
    llm = h.llm(writes={PROMISED: WRONG_METHOD_PHP})
    res = h.run(llm)
    assert res.conformance_fix["reason"] == "rejected"
    assert res.conformance_fix["detail"] == "method-not-written"
    assert any(i.startswith("MISSING-METHOD: testFoo") for i in res.unresolved_conformance)
    cond3 = next(c for c in res.arm.holds if c.number == 3)
    assert cond3.reason.endswith("[conformance-fix: rejected (method-not-written)]")
    assert not (h.wt / PROMISED).exists()
    assert conformance_fix.COMMIT_MSG not in h.git.commit_messages


# --- kill switch and result record ---------------------------------------------------------

def test_disabled_env_runs_byte_identical_today(tmp_path, monkeypatch):
    # Reference: today's run, with the fixer entry point replaced by a no-op.
    (tmp_path / "ref").mkdir()
    with monkeypatch.context() as mp:
        mp.setattr(conformance_fix, "remediate_missing", lambda *a, **k: {})
        hr = Harness(tmp_path / "ref", mp)
        res_ref = hr.run(hr.llm(writes={PROMISED: GOOD_PHP}))
    assert res_ref.unresolved_conformance  # the fixture really has MISSING items

    (tmp_path / "off").mkdir()
    with monkeypatch.context() as mp:
        mp.setenv(conformance_fix.ENV_FLAG, "0")
        h = Harness(tmp_path / "off", mp)
        llm = h.llm(writes={PROMISED: GOOD_PHP})
        res = h.run(llm)
    assert _spawned(llm) == []
    assert conformance_fix.COMMIT_MSG not in h.git.commit_messages
    assert not (h.wt / PROMISED).exists()
    assert res.conformance_fix["reason"] == "disabled"
    assert res.unresolved_conformance == res_ref.unresolved_conformance
    assert res.terminal == res_ref.terminal
    assert [c.number for c in res.arm.holds] == [c.number for c in res_ref.arm.holds]


def test_result_json_carries_conformance_fix_record():
    assert "conformance_fix" not in json.loads(RunResult(terminal=TerminalState.FAILED, slug="x").to_json())
    rec = {"enabled": True, "reason": "committed"}
    res = RunResult(terminal=TerminalState.FAILED, slug="x", conformance_fix=rec)
    assert json.loads(res.to_json())["conformance_fix"]["reason"] == "committed"
    empty = RunResult(terminal=TerminalState.FAILED, slug="x", conformance_fix={})
    assert "conformance_fix" not in json.loads(empty.to_json())
