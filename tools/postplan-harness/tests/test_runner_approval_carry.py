"""Runner wiring of the owner-approval carry: ledger recording, the Phase 6.5 re-arm and kept-scope evidence."""
from __future__ import annotations

import ast
import dataclasses
import inspect
import json
import os
import subprocess
import sys
import types
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import approval_carry, approval_ledger, armable, fidelity
from harness import unplanned_scope as us

import runner

RUNNER_SRC = Path(runner.__file__).read_text(encoding="utf-8")
HEAD = "e" * 40
FA, FB, FC = "a" * 40, "b" * 40, "c" * 40
CREATED = "2026-11-19T10:00:00Z"
T1, T2, T3 = "2026-11-20T10:00:00Z", "2026-11-20T11:00:00Z", "2026-11-20T12:00:00Z"
BRANCH = "feat/carry"
FIX_FILE = "ibl5/classes/Foo/Foo.php"
LATER_FILE = "ibl5/classes/Foo/Other.php"


def _git(cwd, *args):
    return subprocess.run(["git", "-C", str(cwd), *args], capture_output=True, text=True, check=True).stdout


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q", "-b", "master")
    (root / "f.txt").write_text("x\n")
    _git(root, "add", "f.txt")
    _git(root, "-c", "user.name=t", "-c", "user.email=t@t", "commit", "-q", "-m", "init")
    return root


def _write_ledger(repo, links):
    path = approval_ledger.ledger_path(str(repo), BRANCH)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("".join(json.dumps(r, sort_keys=True) + "\n" for r in links), encoding="utf-8")


def _link(kind, frm, to, files, ts):
    return {"v": 1, "kind": kind, "from": frm, "to": to, "sha": HEAD, "files": sorted(files), "ts": ts}


class _Git:
    def __init__(self, target="origin/master"):
        self.target, self.fetched = target, []

    def merge_target(self):
        return self.target

    def fetch_base(self, base="origin/master"):
        self.fetched.append(base)

    def branch(self):
        return BRANCH


# --- ledger recording --------------------------------------------------------------------


def test_phase2_commit_records_no_ledger_link():
    tree = ast.parse(RUNNER_SRC)
    wrapper = next(n for n in ast.walk(tree) if isinstance(n, ast.FunctionDef) and n.name == "_commit_with_gate_fix")
    inside = {id(n) for n in ast.walk(wrapper)}
    calls = [n for n in ast.walk(tree)
             if isinstance(n, ast.Call) and isinstance(n.func, ast.Name) and n.func.id == "_commit_with_gate_fix"
             and id(n) not in inside]
    with_ledger = [c for c in calls if any(k.arg == "ledger" and isinstance(k.value, ast.Constant)
                                           and k.value.value is True for k in c.keywords)]
    without = [c for c in calls if not any(k.arg == "ledger" for k in c.keywords)]
    assert len(calls) == 7
    assert len(with_ledger) == 6
    assert len(without) == 1
    # The one without is the Phase 2 dirty-tree commit.
    assert any("copy['commit_subject']" in ast.unparse(a) or "commit_subject" in ast.unparse(a)
               for a in without[0].args)


def test_fix_commit_records_ledger_link(repo, monkeypatch):
    fps = iter([FA, FB])
    monkeypatch.setattr(runner, "_ledger_fp", lambda git, wt: next(fps))
    (repo / "a.txt").write_text("a\n")
    (repo / "b.txt").write_text("b\n")

    def commit(git, worktree, message, log, *args, **kwargs):
        _git(repo, "add", "-A")
        _git(repo, "-c", "user.name=t", "-c", "user.email=t@t", "commit", "-q", "-m", message)
        return _git(repo, "rev-parse", "HEAD").strip()

    monkeypatch.setattr(runner, "_commit_with_gate_fix_unrecorded", commit)
    captured = []
    monkeypatch.setattr(runner.approval_ledger, "append_link", lambda *a: captured.append(a) or True)
    head_before = _git(repo, "rev-parse", "HEAD").strip()
    sha = runner._commit_with_gate_fix(_Git(), str(repo), "fix", lambda m: None, ledger=True)
    assert sha != head_before
    assert captured == [(str(repo), BRANCH, "fix", FA, FB, sha, ["a.txt", "b.txt"])]


def test_fix_commit_skips_link_when_fp_unavailable(monkeypatch):
    monkeypatch.setattr(runner, "_ledger_fp", lambda git, wt: None)
    monkeypatch.setattr(runner, "_head_sha", lambda wt: HEAD)
    monkeypatch.setattr(runner, "_commit_with_gate_fix_unrecorded", lambda *a, **k: "d" * 40)
    captured, logs = [], []
    monkeypatch.setattr(runner.approval_ledger, "append_link", lambda *a: captured.append(a) or True)
    sha = runner._commit_with_gate_fix(_Git(), "/wt", "fix", logs.append, ledger=True)
    assert sha == "d" * 40
    assert captured == []
    assert any("fingerprint unavailable" in m for m in logs)


def test_ledger_fp_uses_merge_target(monkeypatch):
    seen = []

    def fake_fp(base, head, cwd):
        seen.append((base, head, cwd))
        return FA

    monkeypatch.setattr(runner._armable_mod, "current_diff_fingerprint", fake_fp)
    git = _Git(target="origin/parent-branch")
    assert runner._ledger_fp(git, "/wt") == FA
    assert seen == [("origin/parent-branch", "HEAD", "/wt")]
    assert git.fetched == ["origin/parent-branch"]
    assert "origin/master" not in git.fetched


# --- re-arm ------------------------------------------------------------------------------


def _decision(numbers=(7,), armed=False, conditions="old"):
    return types.SimpleNamespace(holds=[types.SimpleNamespace(number=n, reason="", name="n") for n in numbers],
                                 armed=armed, conditions=conditions, second_review_candidates=[])


def _call_rearm(inputs, decision, post_sticky=lambda *a: None, gh=None):
    res = types.SimpleNamespace(arm=None, hold_repeat=None)
    plan = types.SimpleNamespace(auto_merge_false=False, auto_merge_value=None)
    out = runner._rearm_after_carry(
        gh or types.SimpleNamespace(pr_labels=lambda: ["human-approved"]), _Git(), None, lambda m: None, res,
        inputs, decision, worktree="/wt", pr=7, plan=plan, slug="demo", state_dir=None,
        post_sticky=post_sticky, ci_line="ci")
    return out, res


@dataclasses.dataclass
class _Inputs:
    owner_approval: str
    pr_labels: list = dataclasses.field(default_factory=list)
    llm_safety_holds: list = dataclasses.field(default_factory=lambda: ["stale"])
    classification: object = None
    pr_title: str = "t"


def test_carry_runs_only_on_dropped_states(monkeypatch):
    calls = []
    monkeypatch.setattr(runner, "_try_owner_approval_carry", lambda pr, wt, log: calls.append(pr) or "not-carried")
    for state in ("no-label", "mismatch", "no-record"):
        calls.clear()
        _call_rearm(_Inputs(state), _decision())
        assert calls == [7], state
    for state in ("match", "unchecked"):
        calls.clear()
        _call_rearm(_Inputs(state), _decision())
        assert calls == [], state


def test_carry_failure_keeps_hold(monkeypatch):
    real_try = runner._try_owner_approval_carry
    for result in ("error", "not-carried"):
        monkeypatch.setattr(runner, "_try_owner_approval_carry", lambda pr, wt, log, r=result: r)
        inputs, decision = _Inputs("mismatch"), _decision()
        posts = []
        (out_inputs, out_decision), res = _call_rearm(inputs, decision, post_sticky=lambda *a: posts.append(a))
        assert out_inputs is inputs and out_decision is decision
        assert inputs.owner_approval == "mismatch" and inputs.pr_labels == []
        assert posts == [] and res.arm is None
    # A carry that crashes or prints no RESULT line reports 'error'.
    def boom(*a, **k):
        raise OSError("no exec")

    monkeypatch.setattr(runner.subprocess, "run", boom)
    logs = []
    assert real_try(7, "/wt", logs.append) == "error"
    monkeypatch.setattr(runner.subprocess, "run", lambda *a, **k: subprocess.CompletedProcess(a, 1, "", ""))
    assert real_try(7, "/wt", logs.append) == "error"
    monkeypatch.setattr(runner.subprocess, "run",
                        lambda *a, **k: subprocess.CompletedProcess(a, 0, "RESULT: carried\n", ""))
    assert real_try(7, "/wt", logs.append) == "carried"


def test_carry_rearm_refreshes_approval_and_arms(monkeypatch):
    events, seen = [], []
    monkeypatch.setattr(runner, "_try_owner_approval_carry", lambda pr, wt, log: events.append("carry") or "carried")
    monkeypatch.setattr(runner, "resolve_owner_approval", lambda *a: "match")
    new_decision = _decision(numbers=(), armed=True, conditions="new")

    def fake_evaluate(inp):
        seen.append((inp.owner_approval, list(inp.pr_labels)))
        return new_decision

    monkeypatch.setattr(runner, "evaluate", fake_evaluate)
    monkeypatch.setattr(runner.llm_calls, "safety_verdict_prompt", lambda *a: "prompt")
    monkeypatch.setattr(runner.holdrepeat, "structural_key", lambda c: c)
    monkeypatch.setattr(runner, "_record_hold_repeat", lambda *a: "recorded")
    llm = types.SimpleNamespace(call=lambda *a: {"holds": []})
    posted = []

    def post_sticky(d, ci, phase):
        events.append("post")
        posted.append((d, ci, phase))

    inputs = _Inputs("mismatch", pr_labels=[])
    res = types.SimpleNamespace(arm=None, hold_repeat=None)
    plan = types.SimpleNamespace(auto_merge_false=False, auto_merge_value=None)
    new_inputs, nd = runner._rearm_after_carry(
        types.SimpleNamespace(pr_labels=lambda: ["human-approved"]), _Git(), llm, lambda m: None, res, inputs,
        _decision(), worktree="/wt", pr=7, plan=plan, slug="demo", state_dir=None, post_sticky=post_sticky,
        ci_line="ci")
    assert seen and all(s == ("match", ["human-approved"]) for s in seen)
    assert events == ["carry", "post"]
    assert posted == [(new_decision, "ci", "phase6.5 carry")]
    assert nd is new_decision and new_inputs.owner_approval == "match"
    assert new_inputs.pr_labels == ["human-approved"] and new_inputs.llm_safety_holds == []
    assert inputs.owner_approval == "mismatch"  # the caller's object is replaced, not mutated
    assert res.arm is new_decision and res.hold_repeat == "recorded"


def test_carry_runs_after_sticky_post():
    src = inspect.getsource(runner._run_pass)
    post = src.index('sticky = _post_sticky(decision, ci_line, "phase6.5")')
    carry = src.index("_rearm_after_carry(")
    arm = src.index("if decision.armed:\n            gh.pr_merge_auto(pr)")
    assert post < carry < arm


# --- kept-scope evidence -----------------------------------------------------------------

TITLE = f"Extra refactor in `{FIX_FILE}` beyond what the plan asked for"
BLOCKER = (f"- [BLOCKING] [unplanned-scope] {TITLE}. Next action: revert the change or have the owner "
           "approve it.")
BULLET = f"- {TITLE} (`{FIX_FILE}`)"
KEPT_BODY = f"intro\n\n{us.KEPT_BEGIN}\n## Kept unplanned changes\n{BULLET}\n{us.KEPT_END}\n"


def _owner_comment(mode, diff, span=None, created=CREATED):
    lines = [f'Owner approved 2026-11-19: "ship it" (card A). Head {HEAD}. Conversation: T3 thread t.']
    if span is not None:
        lines += ["", "Kept unplanned changes this approval accepts:", us.APPROVAL_KEPT_BEGIN, *span,
                  us.APPROVAL_KEPT_END]
    lines += ["", f"<!-- owner-approve mode={mode} head={HEAD} diff={diff} -->"]
    return {"id": 1, "user": {"login": "a-jay85", "type": "User"}, "author_association": "OWNER",
            "created_at": created, "body": "\n".join(lines) + "\n"}


class _Gh:
    def __init__(self, comments):
        self.comments = comments

    def pr_base_ref(self):
        return "master"

    def pr_comments_json(self, pr):
        return self.comments


def test_scope_survives_later_fix_link(repo, monkeypatch, tmp_path):
    _write_ledger(repo, [
        _link("fix-first-scope", FA, FB, [FIX_FILE], T1),
        _link("fix-first-finish", FA, FB, [FIX_FILE], T2),
        _link("fix", FB, FC, [LATER_FILE], T3),
    ])
    verdict = tmp_path / "verdict.md"
    verdict.write_text(f"## FINDINGS\n{BLOCKER}\n\nNOT READY\n", encoding="utf-8")
    ctx = {"changed_paths": [FIX_FILE, LATER_FILE], "subjects_by_path": {FIX_FILE: ["chore: fix"]},
           "must_preserve": set(), "removed_guard_paths": set()}
    monkeypatch.setattr(runner, "_unplanned_ctx", lambda *a: ctx)
    monkeypatch.setattr(runner.owner_intent, "load", lambda slug: None)
    monkeypatch.setattr(fidelity, "verdict_path", lambda pr: str(verdict))
    monkeypatch.setattr(armable, "current_diff_fingerprint", lambda base, head, cwd: FC)
    monkeypatch.setattr(approval_carry, "_changed_paths", lambda wt, base: [FIX_FILE, LATER_FILE])

    def run(comments):
        inputs = types.SimpleNamespace(pr_body=KEPT_BODY, fidelity_verdict="NOT READY", fidelity_verdict_2=None,
                                       fidelity_tree_2=None, current_tree="t" * 40)
        res = types.SimpleNamespace(fidelity={"verdict_1": "NOT READY"}, slug="demo")
        runner._apply_kept_downgrade(inputs, res, _Git(), _Gh(comments), str(repo), None, "base", 4248,
                                     lambda m: None)
        return inputs, res

    inputs, res = run([_owner_comment("fix-first", FA)])
    assert inputs.fidelity_verdict == "READY"
    assert res.fidelity["posted_verdict"] == "READY"
    assert res.fidelity["kept_owner_scope"][0]["source"] == "owner-approval"
    assert res.fidelity["downgraded_unplanned"] is True
    # Control: with no fix-first approval comment the same tree stays NOT READY.
    inputs, res = run([])
    assert inputs.fidelity_verdict == "NOT READY"
    assert "posted_verdict" not in res.fidelity


def test_evidence_falls_back_to_carried_approval(repo, monkeypatch):
    span = [BULLET]
    comments = [_owner_comment("approve", FA, span=span)]
    monkeypatch.setattr(armable, "current_diff_fingerprint", lambda base, head, cwd: FB)
    logs = []
    # No marker for the current fingerprint and no ledger chain: no evidence.
    assert runner._owner_approval_evidence(_Gh(comments), _Git(), str(repo), 7, logs.append) is None
    _write_ledger(repo, [_link("fix", FA, FB, [FIX_FILE], T1)])
    got = runner._owner_approval_evidence(_Gh(comments), _Git(), str(repo), 7, logs.append)
    assert got is not None
    assert got.kept_span == BULLET
    assert got.created_at == CREATED
    assert got.words == "ship it (card A)"
