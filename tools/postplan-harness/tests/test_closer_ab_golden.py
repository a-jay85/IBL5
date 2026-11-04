"""Golden action-sequence pins for the harness-only arm.

Three self-contained scenarios pin the ordered LLM call purposes, the terminal state, the
exit code and the mutating `gh` calls. They must stay green with `POSTPLAN_AB_ARM` unset and
with `POSTPLAN_AB_ARM=harness`, which is the "harness-only arm is byte-identical" constraint.
"""
from __future__ import annotations

import json
import os
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner
from harness import ciwatch
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger
from test_rearm_after_ci_fix import _armable_fixture, _held16_fixture, _merge_count
from test_runner_replay import CANNED, _LiveShapedGit, _actions

_MUTATING = ("pr_merge_auto", "pr_sticky_verdict", "pr_edit", "label")


class _RebaseConflictGit(_LiveShapedGit):
    def rebase_onto(self, base="origin/master"):
        raise HarnessError("rebase-conflict", "golden fixture conflict")

    def autoresolve_stacked_rebase(self):
        from harness.adapters.gitad import StackedRebaseResult
        return StackedRebaseResult(resolved=False, reason="golden fixture declined")


def _set_env(monkeypatch, ab_env):
    if ab_env is None:
        monkeypatch.delenv("POSTPLAN_AB_ARM", raising=False)
    else:
        monkeypatch.setenv("POSTPLAN_AB_ARM", ab_env)


def _go(monkeypatch, tmp_path, fx, git_cls=_LiveShapedGit, canned_extra=None):
    from harness.adapters.probe import FixtureProbe
    out = str(tmp_path / "out")
    monkeypatch.setattr(runner, "ReplayGit", git_cls)
    monkeypatch.setattr(ciwatch, "start_background_watch", lambda *a, **k: None)
    canned = dict(CANNED)
    canned.update(canned_extra or {})
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(fx, out, llm, mode="replay", headless=True, live=True,
                     probe=FixtureProbe(fx), state_dir=str(tmp_path / "state"))
    with open(os.path.join(out, "result.json")) as fh:
        result = json.load(fh)
    return res, result, out


def _observe(result, res, out):
    purposes = [c["purpose"] for c in result["ledger"]["calls"]]
    methods = sorted({a["action"] for a in _actions(out)
                      if a["action"] in _MUTATING or a["action"].startswith("label")})
    return {"purposes": purposes, "terminal": result["terminal"],
            "exit": runner.exit_code_for(res), "merges": _merge_count(out),
            "mutating": methods}


# Captured from a run of the pre-closer code (characterization, never predicted).
PIN_ARMED = {
    "purposes": ["pr-copy", "review-agent-a", "security-audit", "safety-verdict", "ci-fix",
                 "retrospective"],
    "terminal": "shipped-armed", "exit": 0, "merges": 1,
    "mutating": ["pr_merge_auto", "pr_sticky_verdict"],
}
PIN_HELD = {
    "purposes": ["pr-copy", "review-agent-a", "security-audit", "ci-fix", "retrospective"],
    "terminal": "shipped-held", "exit": 0, "merges": 0,
    "mutating": ["pr_sticky_verdict"],
}
PIN_REBASE = {
    "purposes": ["pr-copy"], "terminal": "failed", "exit": 3, "merges": 0, "mutating": [],
}


def _check(observed, pin, name):
    assert observed == pin, f"{name}: {observed!r}"


@pytest.mark.parametrize("ab_env", [None, "harness"])
def test_harness_only_golden_armed_sequence(monkeypatch, tmp_path, ab_env):
    _set_env(monkeypatch, ab_env)
    fx = _armable_fixture(monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    res, result, out = _go(monkeypatch, tmp_path, fx, canned_extra={"ci-fix": ["edits made"]})
    _check(_observe(result, res, out), PIN_ARMED, "armed")


@pytest.mark.parametrize("ab_env", [None, "harness"])
def test_harness_only_golden_held_heuristic_sequence(monkeypatch, tmp_path, ab_env):
    _set_env(monkeypatch, ab_env)
    fx, _flag = _held16_fixture(
        monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 8, "failed": ["Tests and Analysis"]}])
    res, result, out = _go(monkeypatch, tmp_path, fx, canned_extra={"ci-fix": ["edits made"]})
    _check(_observe(result, res, out), PIN_HELD, "held")


@pytest.mark.parametrize("ab_env", [None, "harness"])
def test_harness_only_golden_rebase_conflict_sequence(monkeypatch, tmp_path, ab_env):
    _set_env(monkeypatch, ab_env)
    fx = _armable_fixture(monkeypatch, tmp_path)
    res, result, out = _go(monkeypatch, tmp_path, fx, git_cls=_RebaseConflictGit)
    _check(_observe(result, res, out), PIN_REBASE, "rebase")


# ── closer reply contract (#3694) ─────────────────────────────────────────────

def test_closer_unparseable_reply_is_not_success(monkeypatch, tmp_path):
    """A JSON-free closer reply (the #3694 shape) fails closed: no commit, no push, no re-check."""
    from test_runner_closer import (Recorder, _TrackedGit, _arm, _closer_fixture, _install)
    from harness.closer import CloserOutcome

    class _UnparsedAware(Recorder):
        """Mirrors run_session: edits survive only when the caller did not ask to discard."""

        def __call__(self, llm, **kw):
            self.calls.append(kw)
            if kw.get("discard_on_unparsed"):
                return CloserOutcome("unparseable", "unparsed",
                                     "closer reply unparseable; no thread replies posted",
                                     (), 1.5, "unparseable", "")
            _TrackedGit.instances[-1].extra_dirty = ["src/closer-edit.php"]
            return CloserOutcome("edited", "unparsed", "unparsed", (), 1.5, "", "")

    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    rec = _UnparsedAware()
    _install(monkeypatch, rec)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert len(rec.calls) == 1
    assert res.closer["status"] == "unparseable"
    assert res.closer["recheck"] == "not-run"
    assert res.closer["replies_posted"] == 0
    git = _TrackedGit.instances[-1]
    assert not [m for m in git.commit_messages if "(closer)" in m]
    assert res.arm.armed is False
    assert _merge_count(out) == 0


# ── harness-applied PR body edits (#3694) ─────────────────────────────────────

def _body_edit_run(monkeypatch, tmp_path, edits, body_suffix):
    """Replay run whose closer session leaves the tree clean and asks for body `edits`."""
    from test_runner_closer import (Recorder, _TrackedGit, _arm, _closer_fixture, _install)
    from harness.closer import CloserOutcome

    class _BodyOnly(Recorder):
        def __call__(self, llm, **kw):
            self.calls.append(kw)
            git = _TrackedGit.instances[-1]
            self.pushes_before = git.pushes + git.ff_pushes
            return CloserOutcome("no-change", "fixed", "PR body count", (), 1.5, "", "",
                                 body_edits=tuple(edits))

    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    fx["pr_meta"]["body"] = fx["pr_meta"]["body"] + body_suffix
    rec = _BodyOnly()
    _install(monkeypatch, rec)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    return rec, res, out, _TrackedGit.instances[-1]


def _body_edit_actions(out, needle):
    return [a for a in _actions(out)
            if a["action"] == "pr_edit_body" and needle in a.get("body", "")]


def test_closer_body_only_edit_runs_gh_pr_edit_no_commit(monkeypatch, tmp_path):
    rec, res, out, git = _body_edit_run(
        monkeypatch, tmp_path, [("Two follow-ups", "Three follow-ups")],
        "\nFiled: Two follow-ups.\n")
    assert len(rec.calls) == 1
    assert len(_body_edit_actions(out, "Three follow-ups")) == 1
    assert not [m for m in git.commit_messages if "(closer)" in m]
    assert git.pushes + git.ff_pushes == rec.pushes_before
    assert res.closer["status"] == "no-change"
    assert res.closer["body_edits"] == "applied:1"
    assert res.closer["recheck"] == "deferred-body-edited"
    assert _merge_count(out) == 0


def test_closer_body_edit_rejected_leaves_body(monkeypatch, tmp_path):
    _rec, res, out, _git = _body_edit_run(
        monkeypatch, tmp_path, [("Two", "Three")], "\nTwo and Two.\n")
    assert not _body_edit_actions(out, "Three")
    assert res.closer["body_edits"].startswith("rejected:find not unique")


def test_closer_body_edit_failure_is_noted(monkeypatch, tmp_path):
    from harness.adapters import ghad
    real = ghad.RecordingGh.pr_edit_body

    def boom(self, pr, body):
        if "Three follow-ups" in body:
            raise RuntimeError("gh pr edit exploded")
        return real(self, pr, body)

    monkeypatch.setattr(ghad.RecordingGh, "pr_edit_body", boom)
    _rec, res, _out, _git = _body_edit_run(
        monkeypatch, tmp_path, [("Two follow-ups", "Three follow-ups")],
        "\nFiled: Two follow-ups.\n")
    assert res.closer["body_edits"] == "failed:edit"


# ── closer push failure is reported, not silent ───────────────────────────────

def test_closer_push_failed_sticky_leads_with_reason(monkeypatch, tmp_path):
    """A push failure is not success: `push_failure.sha` is set and the sticky leads with the reason."""
    from test_runner_closer import (Recorder, _TrackedGit, _arm, _closer_fixture, _install)
    from harness.closer import CloserOutcome

    class _Edits(Recorder):
        def __call__(self, llm, **kw):
            self.calls.append(kw)
            _TrackedGit.instances[-1].extra_dirty = ["src/closer-edit.php"]
            return CloserOutcome(status="edited", verdict="fixed", note="closer fixed it",
                                 cost_usd=1.5, pre_head="")

    note = ("push failed (push-rejected/non-fast-forward): "
            "! [rejected] HEAD -> wake (non-fast-forward); "
            "closer commit bbbbbbbbbbbb kept at refs/postplan-closer/3695-bbbbbbbbbbbb "
            "in /wt; branch reset to aaaaaaaaaaaa; "
            "recover: git -C /wt cherry-pick bbbbbbbbbbbb")

    def fake(git, llm, log, res, outcome, *, worktree, out_dir, gh, pr, phase, failure=None):
        failure.update({"stage": "push", "kind": "push-rejected",
                        "reason": "non-fast-forward",
                        "line": "! [rejected] HEAD -> wake (non-fast-forward)",
                        "sha": "b" * 40, "ref": "refs/postplan-closer/3695-bbbbbbbbbbbb",
                        "kept": True, "worktree": "/wt"})
        return note

    _arm(monkeypatch)
    fx = _closer_fixture(monkeypatch, tmp_path)
    _install(monkeypatch, _Edits())
    monkeypatch.setattr(runner, "_closer_commit_push", fake)
    res, _result, out = _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    assert res.closer["status"] == "push-failed"
    assert res.closer["push_failure"]["sha"] == "b" * 40
    assert res.closer["recheck"] == "not-run"
    assert res.closer["replies_posted"] == 0
    stickies = [a for a in _actions(out)
                if a["action"] == "pr_sticky_verdict" and "closer:" in a.get("body", "")]
    assert stickies
    body = stickies[-1]["body"]
    assert "closer: push-failed; push failed (push-rejected/non-fast-forward)" in body
    assert "bbbbbbbbbbbb" in body
