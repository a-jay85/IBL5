"""Runner wiring for condition (20): `_inject_removed_guards` (Phase 2 body block) and
`_removed_guards_holds` (the arm-time detector rerun that feeds `ArmInputs`)."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

import runner
from harness import removed_guards

SEASON_DIFF = (
    "diff --git a/ibl5/classes/Season/Season.php b/ibl5/classes/Season/Season.php\n"
    "--- a/ibl5/classes/Season/Season.php\n"
    "+++ b/ibl5/classes/Season/Season.php\n"
    "@@ -1,4 +1,1 @@\n"
    "-        try {\n"
    "-        } catch (\\RuntimeException) {\n"
    " }\n"
)


def _stub_detector(tmp_path, rc):
    lib = tmp_path / "bin" / "lib"
    lib.mkdir(parents=True)
    script = lib / "deleted-guards"
    script.write_text(f"#!/bin/sh\ncat >/dev/null\nexit {rc}\n")
    script.chmod(0o755)
    return str(tmp_path)


def test_runner_injects_block_for_flagged_removal():
    copy = {"summary_md": "## Summary\n- change"}
    logs = []
    assert runner._inject_removed_guards(copy, None, SEASON_DIFF, None, logs.append) is True
    body = copy["summary_md"]
    assert removed_guards.BEGIN in body and removed_guards.END in body
    assert "`try@ibl5/classes/Season/Season.php#" in body
    assert removed_guards.PLACEHOLDER in body
    assert any(ln.startswith("phase2 removed-guard: try@") for ln in logs)
    # The rendered placeholder holds until a human writes a reason.
    assert any(h.startswith("removed-guards:UNFILLED:")
               for h in removed_guards.classify_body(body, []))


def test_runner_arm_inputs_carry_detector_holds():
    # No block in the body: the rerun's flagged keys hold as UNLISTED.
    holds = runner._removed_guards_holds(None, SEASON_DIFF, None, "## Summary\n- change")
    assert any(h.startswith("removed-guards:UNLISTED:try@ibl5/classes/Season/Season.php#")
               for h in holds)
    # The same keys listed with substantive reasons arm.
    ok, rows = removed_guards.run_detector(runner._detector_root(None), SEASON_DIFF, None)
    assert ok and rows
    body = removed_guards.render_block(
        rows, {r[0]: "moved into SeasonRepository::load" for r in rows})
    assert runner._removed_guards_holds(None, SEASON_DIFF, None, body) == []
    # Wiring: the ArmInputs construction passes the computed holds.
    src = open(runner.__file__).read()
    arm_at = src.index("inputs = ArmInputs(")
    assert src.index("_rg_holds = _removed_guards_holds(") < arm_at
    assert src.index("removed_guards_holds=_rg_holds,") > arm_at
    assert src.index("_inject_removed_guards(copy, plan, diff, worktree, log)") > \
        src.index('_inject_scope_notes(copy, plan, files, diff, copy["summary_md"], log)')


def test_detector_root_ignores_worktree_copy(tmp_path):
    wt = _stub_detector(tmp_path, 2)
    assert runner._detector_root(wt) == runner.conformance._HARNESS_REPO_ROOT


def test_removed_guards_holds_ignore_weakened_worktree_detector(tmp_path):
    # The PR worktree's detector is weakened to exit 0 with no rows. The arm-time hold must
    # still come from the harness checkout's detector and classify_body, so it holds.
    wt = _stub_detector(tmp_path, 0)
    holds = runner._removed_guards_holds(wt, SEASON_DIFF, None, "## Summary\n- change")
    assert any(h.startswith("removed-guards:UNLISTED:try@ibl5/classes/Season/Season.php#")
               for h in holds)


def test_runner_detector_error_holds(tmp_path, monkeypatch):
    wt = _stub_detector(tmp_path, 2)
    monkeypatch.setattr(runner, "_detector_root", lambda worktree: wt)
    assert runner._removed_guards_holds(wt, SEASON_DIFF, None, "") == \
        ["removed-guards:DETECTOR-ERROR"]
    copy = {"summary_md": "## Summary"}
    logs = []
    assert runner._inject_removed_guards(copy, None, SEASON_DIFF, wt, logs.append) is False
    assert copy["summary_md"] == "## Summary"
    assert "phase2 removed-guards: detector error" in logs


# ---- Condition (20) pre-evaluate fixer: runner._run_removed_guard_fix ----

from harness import removed_guard_fix  # noqa: E402
from harness.armable import evaluate  # noqa: E402
from test_armable import inputs as arm_inputs  # noqa: E402

FIX_PATH = "ibl5/classes/Season/Season.php"
FIX_KEY = "try@ibl5/classes/Season/Season.php#1a2b3c4d"
FIX_ROW = (FIX_KEY, FIX_PATH, "try", "try {")
FIX_REASON = "moved into SeasonFactory::build, which keeps the same catch"


class FakeGh:
    """Mirrors RecordingGh's body cache: pr_edit_body sets the override pr_body() returns."""

    def __init__(self, body):
        self.live = body
        self._override = None
        self.edits = []

    def pr_body_fresh(self):
        self._override = None
        return self.live

    def pr_body(self):
        return self._override if self._override is not None else self.live

    def pr_edit_body(self, pr, body):
        self.edits.append((pr, body))
        self._override = body


class FakeGit:
    def diff_vs_base(self):
        return SEASON_DIFF


class FixLlm:
    def __init__(self, raise_on=None):
        self.calls = []
        self.raise_on = raise_on

    def call(self, purpose, model, prompt, validate, **kw):
        self.calls.append((purpose, model))
        if purpose == self.raise_on:
            raise RuntimeError(f"{purpose} boom")
        if purpose == removed_guard_fix.PURPOSE_WRITE:
            reply = {"key": FIX_KEY, "reason": FIX_REASON}
        else:
            reply = {"key": FIX_KEY, "verdict": "safe", "why": "the hunk shows the move"}
        validate(reply)
        return reply


def _placeholder_body():
    return removed_guards.upsert_block("## Summary\n- change\n",
                                       removed_guards.render_block([FIX_ROW], {}))


def _first_evaluate_c20(gh):
    """What the first `_evaluate_arm` computes for (20): the holds on `gh.pr_body() or body`."""
    holds = runner._removed_guards_holds(None, SEASON_DIFF, None, gh.pr_body())
    d = evaluate(arm_inputs(removed_guards_holds=holds))
    return holds, [c for c in d.conditions if c.number == 20][0]


@pytest.fixture
def stub_detector(monkeypatch):
    monkeypatch.setattr(removed_guards, "run_detector", lambda *a, **k: (True, [FIX_ROW]))
    monkeypatch.delenv(removed_guard_fix.ENV_FLAG, raising=False)


def test_removed_guard_fix_lifts_condition_20_on_first_evaluate(stub_detector):
    body = _placeholder_body()
    gh, llm, logs = FakeGh(body), FixLlm(), []
    assert _first_evaluate_c20(gh)[1].blocked
    runner._run_removed_guard_fix(gh, llm, FakeGit(), None, None, 42, body, logs.append)
    assert len(gh.edits) == 1
    pr, new_body = gh.edits[0]
    assert pr == 42
    bullet = [ln for ln in new_body.split("\n") if ln.startswith(f"- `{FIX_KEY}`")][0]
    assert bullet.split("Reason: ", 1)[1].startswith(removed_guards.HARNESS_MARKER)
    holds, c20 = _first_evaluate_c20(gh)
    assert not [h for h in holds if h.startswith("removed-guards:")]
    assert not c20.blocked
    assert "removed-guard-fix: filled 1 reason(s), kept 0" in logs


@pytest.mark.parametrize("where", ["checker", "fixer_run"])
def test_removed_guard_fix_error_keeps_condition_20(stub_detector, monkeypatch, where):
    body = _placeholder_body()
    gh, logs = FakeGh(body), []
    llm = FixLlm(raise_on=removed_guard_fix.PURPOSE_CHECK if where == "checker" else None)
    if where == "fixer_run":
        def boom(**kw):
            raise RuntimeError("fixer exploded")
        monkeypatch.setattr(removed_guard_fix, "run", boom)
    runner._run_removed_guard_fix(gh, llm, FakeGit(), None, None, 42, body, logs.append)
    assert gh.edits == []
    assert gh.pr_body() == body
    holds, c20 = _first_evaluate_c20(gh)
    assert f"removed-guards:UNFILLED:{FIX_KEY}" in holds
    assert c20.blocked


def test_removed_guard_fix_disabled_makes_no_llm_call(stub_detector, monkeypatch):
    monkeypatch.setenv(removed_guard_fix.ENV_FLAG, "0")
    body = _placeholder_body()
    gh, llm = FakeGh(body), FixLlm()
    runner._run_removed_guard_fix(gh, llm, FakeGit(), None, None, 42, body, lambda m: None)
    purposes = {p for p, _ in llm.calls}
    assert not purposes & {removed_guard_fix.PURPOSE_WRITE, removed_guard_fix.PURPOSE_CHECK}
    assert gh.edits == [] and gh.pr_body() == body


def test_removed_guard_fix_no_pr_or_no_target_makes_no_llm_call(stub_detector):
    body = _placeholder_body()
    gh, llm = FakeGh(body), FixLlm()
    runner._run_removed_guard_fix(gh, llm, FakeGit(), None, None, None, body, lambda m: None)
    assert llm.calls == [] and gh.edits == []
    filled = removed_guards.upsert_block(
        "## Summary\n", removed_guards.render_block([FIX_ROW], {FIX_KEY: FIX_REASON}))
    gh2 = FakeGh(filled)
    runner._run_removed_guard_fix(gh2, llm, FakeGit(), None, None, 42, filled, lambda m: None)
    assert llm.calls == [] and gh2.edits == []


def test_removed_guard_fix_runs_directly_before_first_evaluate():
    src = open(runner.__file__).read()
    call = "        _run_removed_guard_fix(gh, llm, git, worktree, plan, pr, body, log)\n"
    first_eval = "        _arm = _evaluate_arm()\n"
    assert src.count(call) == 1 and src.count(first_eval) == 1
    assert src.index(call) + len(call) == src.index(first_eval)
