"""Phase 5.5 kept-scope downgrade with a bin/owner-approve comment as owner evidence.

The PR #4059 shape: one `[unplanned-scope] [GATE-EDIT]` blocker, a kept block in the
PR body, and no captured owner quote. Without an approval it stays NOT READY. With an
authentic approval on the current diff that lists the item, the posted word is READY.
"""
from __future__ import annotations

import dataclasses
import os
import sys
import types

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import armable, fidelity, unplanned_scope as us

import runner

GOTCHAS = ".claude/rules/ci-gotchas.md"
TITLE = ("[GATE-EDIT] Revert or approve the extra ci-gotchas.md trims. In `.claude/rules/ci-gotchas.md`, "
         "diff lines 17-18 rewrite more of the `All checks settled` paragraph than Phase 5 Edit 3 "
         "asked for")
BLOCKER = (f"- [BLOCKING] [unplanned-scope] {TITLE}. Next action: revert the trims or have "
           "the owner approve them.")
MISSING_TEST = "- [BLOCKING] Add the missing test for the plan-checks block."
BULLET = f"- {TITLE} (`{GOTCHAS}`)"
KEPT_BODY = (f"intro\n\n{us.KEPT_BEGIN}\n## Kept unplanned changes\n"
             "Phase 5.5 kept these changes. Veto any of them before merging.\n"
             f"{BULLET}\n{us.KEPT_END}\n")
HEAD = "a" * 40
FP = "b" * 40
OLD_FP = "c" * 40


def _ctx():
    return {"changed_paths": [GOTCHAS],
            "subjects_by_path": {GOTCHAS: ["chore: trim the lazy rule under its cap"]},
            "must_preserve": set(), "removed_guard_paths": set()}


def _verdict(tmp_path, *items):
    path = tmp_path / "verdict.md"
    path.write_text("## FINDINGS\n" + "\n".join(items) + "\n\nNOT READY\n", encoding="utf-8")
    return str(path)


def _approval(diff=FP, utype="User", mode="approve", created="2026-11-19T10:00:00Z"):
    body = (f'Owner approved 2026-11-19: "ship it" (approve). Head {HEAD}. '
            "Conversation: T3 thread t, session s.\n\n"
            "Kept unplanned changes this approval accepts:\n"
            f"{us.APPROVAL_KEPT_BEGIN}\n{BULLET}\n{us.APPROVAL_KEPT_END}\n\n"
            f"<!-- owner-approve mode={mode} head={HEAD} diff={diff} -->\n")
    return {"user": {"login": "a-jay85", "type": utype}, "author_association": "OWNER",
            "created_at": created, "body": body}


class _Gh:
    def __init__(self, comments=(), base="master", error=None):
        self.comments, self.base, self.error = list(comments), base, error
        self.comment_calls = 0

    def pr_base_ref(self):
        return self.base

    def pr_comments_json(self, pr):
        self.comment_calls += 1
        if self.error:
            raise self.error
        return self.comments


class _Git:
    def __init__(self):
        self.fetched = []

    def fetch_base(self, base="origin/master"):
        self.fetched.append(base)

    def diff_vs_base(self):
        return "diff"


def _setup(monkeypatch, tmp_path, *items, fp=FP):
    path = _verdict(tmp_path, *(items or (BLOCKER,)))
    monkeypatch.setattr(runner, "_unplanned_ctx", lambda *a: _ctx())
    monkeypatch.setattr(runner.owner_intent, "load", lambda slug: None)
    monkeypatch.setattr(fidelity, "verdict_path", lambda pr: path)
    calls = []

    def fake_fp(base_ref, head_ref, cwd):
        calls.append((base_ref, head_ref, cwd))
        return fp

    monkeypatch.setattr(armable, "current_diff_fingerprint", fake_fp)
    return path, calls


def _inputs(body=KEPT_BODY):
    return types.SimpleNamespace(pr_body=body, fidelity_verdict="NOT READY", fidelity_verdict_2=None,
                                 fidelity_tree_2=None, current_tree="t" * 40)


def _run(inputs, gh, git=None, log=None):
    res = types.SimpleNamespace(fidelity={"verdict_1": "NOT READY"}, slug="demo")
    runner._apply_kept_downgrade(inputs, res, git or _Git(), gh, "/wt", None, "base", 4059,
                                 log or (lambda m: None))
    return res


def _selected(inputs):
    return armable.select_fidelity_verdict(inputs.fidelity_verdict, inputs.fidelity_verdict_2,
                                           inputs.fidelity_tree_2, inputs.current_tree)[0]


def test_pr4059_shape_without_approval_stays_not_ready(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path)
    assert us.title_of(BLOCKER) == TITLE
    inputs = _inputs()
    res = _run(inputs, _Gh())
    assert inputs.fidelity_verdict == "NOT READY"
    assert _selected(inputs) == "NOT READY"
    assert "posted_verdict" not in res.fidelity
    assert "downgraded_unplanned" not in res.fidelity


def test_pr4059_shape_downgrades_with_owner_approval(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path)
    inputs = _inputs()
    logs: list[str] = []
    res = _run(inputs, _Gh([_approval()]), log=logs.append)
    assert inputs.fidelity_verdict == "READY"
    assert _selected(inputs) == "READY"
    assert res.fidelity["posted_verdict"] == "READY"
    assert res.fidelity["posted_source"] == "verdict-1"
    assert res.fidelity["downgraded_unplanned"] is True
    assert res.fidelity["kept_owner_scope"] == [
        {"title": TITLE, "quote": "ship it (approve)", "source": "owner-approval"}]
    assert res.fidelity["verdict_1"] == "NOT READY"
    assert any("owner-approve comment of 2026-11-19T10:00:00Z" in m for m in logs)


def test_approval_on_older_diff_keeps_not_ready(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path)
    inputs = _inputs()
    res = _run(inputs, _Gh([_approval(diff=OLD_FP)]))
    assert inputs.fidelity_verdict == "NOT READY"
    assert _selected(inputs) == "NOT READY"
    assert "posted_verdict" not in res.fidelity


def test_non_kept_blocking_finding_keeps_not_ready(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path, BLOCKER, MISSING_TEST)
    inputs = _inputs()
    res = _run(inputs, _Gh([_approval()]))
    assert inputs.fidelity_verdict == "NOT READY"
    assert "posted_verdict" not in res.fidelity


def test_bot_approval_keeps_not_ready(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path)
    inputs = _inputs()
    res = _run(inputs, _Gh([_approval(utype="Bot")]))
    assert inputs.fidelity_verdict == "NOT READY"
    assert "posted_verdict" not in res.fidelity


def test_approval_read_error_fails_closed(monkeypatch, tmp_path):
    _setup(monkeypatch, tmp_path)
    inputs = _inputs()
    logs: list[str] = []
    res = _run(inputs, _Gh(error=RuntimeError("gh api 502")), log=logs.append)
    assert inputs.fidelity_verdict == "NOT READY"
    assert "posted_verdict" not in res.fidelity
    assert any("owner approval unreadable" in m for m in logs)


@dataclasses.dataclass
class _ArmInputs:
    pr_body: str
    fidelity_verdict: str
    fidelity_verdict_2: object = None
    fidelity_tree_2: object = None
    current_tree: str = "t" * 40
    meta_checks_status: object = None
    failed_checks: list = dataclasses.field(default_factory=list)
    gate_backtest_status: object = None
    gate_backtest_reason: object = None
    fidelity_gate_edit_only: object = None
    fidelity_blocking_findings: object = None
    llm_safety_holds: list = dataclasses.field(default_factory=list)


class _Stop(Exception):
    def __init__(self, inputs):
        super().__init__("stop")
        self.inputs = inputs


def test_rearm_path_applies_approval_downgrade(monkeypatch, tmp_path):
    path, _ = _setup(monkeypatch, tmp_path)
    monkeypatch.setattr(runner, "_post_fix_meta_checks", lambda *a, **k: 0)
    monkeypatch.setattr(runner, "gate_backtest_result",
                        lambda *a, **k: types.SimpleNamespace(status="pass", reason=""))
    monkeypatch.setattr(runner.second_review, "selected_gate_edit_only", lambda *a: False)
    monkeypatch.setattr(runner.second_review, "selected_blocking_findings", lambda *a: [])

    def stop(new):
        raise _Stop(new)

    monkeypatch.setattr(runner, "evaluate", stop)
    git = _Git()
    git.head_tree = lambda: "t" * 40
    # Phase 6.5 posted READY into verdict-1; the re-arm puts the raw word back first.
    inputs = _ArmInputs(pr_body=KEPT_BODY, fidelity_verdict="READY")
    res = types.SimpleNamespace(fidelity={"verdict_1": "NOT READY", "verdict_path": path,
                                          "posted_source": "verdict-1", "posted_verdict": "READY"},
                                slug="demo")
    outcome = types.SimpleNamespace(failed=[])
    try:
        runner._rearm_after_ci_fix(git, _Gh([_approval()]), None, lambda m: None, res, None, inputs,
                                   None, outcome, worktree="/wt", pr=4059, sha=HEAD, live=False,
                                   fixture={}, out_dir=str(tmp_path), state_dir=None, slug="demo",
                                   flag=str(tmp_path / "flag"), post_sticky=None, plan=None,
                                   master_sha="base")
    except _Stop as s:
        assert s.inputs.fidelity_verdict == "READY"
        assert res.fidelity["kept_owner_scope"][0]["source"] == "owner-approval"
    else:
        raise AssertionError("evaluate() was never reached")


def test_stacked_pr_fingerprint_uses_pr_base_ref(monkeypatch, tmp_path):
    _, calls = _setup(monkeypatch, tmp_path)
    git = _Git()
    inputs = _inputs()
    _run(inputs, _Gh([_approval()], base="parent-branch"), git=git)
    assert git.fetched == ["origin/parent-branch"]
    assert calls == [("origin/parent-branch", "HEAD", "/wt")]
    assert inputs.fidelity_verdict == "READY"


def test_no_gh_calls_without_kept_block(monkeypatch, tmp_path):
    _, calls = _setup(monkeypatch, tmp_path)
    gh = _Gh([_approval()])
    inputs = _inputs(body="intro with no kept block\n")
    res = _run(inputs, gh)
    assert gh.comment_calls == 0
    assert calls == []
    assert inputs.fidelity_verdict == "NOT READY"
    assert "posted_verdict" not in res.fidelity
