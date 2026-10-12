"""runner wiring for the local-gate fixer: commit and push wrappers (Phases 5-6)."""
from __future__ import annotations

import os
import re
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, classify, gatefix
from harness.adapters.gitad import classify_local_gate_denial
from harness.state import HarnessError, RunResult, TerminalState
from harness.usage_pause import UsagePause

from test_gatefix import WritingLlm, home, repo  # noqa: F401  (fixtures + fake)

BYTE_BUDGET_TEXT = (
    "git commit: bin/check-rules-byte-budget: path-unscoped rules exceed the budget\n"
    "Trim the rule(s) above."
)
OTHER_TEXT = "git commit: some other hook said no.\nFix the above."
DOC_TEXT = "git commit: stale docs.\nBump last_verified on the files above."
ADR_TEXT = ("git push: pre-push-adr-hook: a decision-trigger surface is being pushed "
            "without an ADR.")
PUSH_TEXT = "git push: pre-push gate failed.\nSomething unknown."


def _denial(text, cmd="git commit"):
    return HarnessError("local-gate", text, cmd=cmd)


class FakeGit:
    def __init__(self, commit_errors=(), push_errors=(), dirty=False):
        self.commit_errors, self.push_errors = list(commit_errors), list(push_errors)
        self.dirty = dirty
        self.commits: list[str] = []
        self.pushes = 0

    def commit_all(self, message):
        self.commits.append(message)
        i = len(self.commits) - 1
        err = self.commit_errors[i] if i < len(self.commit_errors) else None
        if err:
            raise err
        return f"{i + 1:040d}"

    def push(self):
        i = self.pushes
        self.pushes += 1
        err = self.push_errors[i] if i < len(self.push_errors) else None
        if err:
            raise err

    def head(self):
        return "a" * 40

    def branch(self):
        return "wt-slug"

    def is_dirty(self):
        return self.dirty


class FakeGh:
    def __init__(self):
        self.edits: list[tuple] = []

    def pr_body_fresh(self):
        return "Existing body"

    def pr_edit_body(self, pr, body):
        self.edits.append((pr, body))


@pytest.fixture(autouse=True)
def _home(home, monkeypatch):  # noqa: F811
    monkeypatch.setenv("HOME", str(home))


def _res():
    return RunResult(terminal=TerminalState.FAILED)


def _fix_xphp(cwd):
    (cwd / "ibl5/x.php").write_text("<?php // fixed\n")


def _commit(git, repo, llm, res, logs):
    return runner._commit_with_gate_fix(git, str(repo), "msg", logs.append, llm=llm,
                                        res=res)


def _push(git, repo, llm, res, logs, gh=None, pr=None, tmp=None):
    return runner._push_with_gate_fix(git, logs.append, "phase2", llm=llm,
                                      worktree=str(repo), out_dir=str(tmp or repo),
                                      res=res, pr=pr, gh=gh)


def _tree_bytes(repo):
    return {str(p.relative_to(repo)): p.read_bytes() for p in repo.rglob("*")
            if p.is_file() and ".git" not in p.relative_to(repo).parts}


# --- commit wrapper ------------------------------------------------------------------


def test_commit_fix_then_retry_passes(repo):
    git, res, logs = FakeGit([_denial(BYTE_BUDGET_TEXT)]), _res(), []
    sha = _commit(git, repo, WritingLlm(edit=_fix_xphp), res, logs)
    assert sha and len(git.commits) == 2
    assert git.commits[1].endswith(runner._GATE_FIX_NOTE)
    assert res.gate_fix["retry"] == "passed"
    assert any("gate fix retry passed" in ln for ln in logs)


def test_commit_fix_gate_path_no_retry(repo):
    git, res, logs = FakeGit([_denial(BYTE_BUDGET_TEXT)]), _res(), []
    llm = WritingLlm(edit=lambda cwd: (cwd / "bin/check-docs").write_text("exit 0\n"))
    with pytest.raises(HarnessError) as ei:
        _commit(git, repo, llm, res, logs)
    assert len(git.commits) == 1
    res.terminal, res.error_kind = TerminalState.FAILED, ei.value.kind
    assert runner.exit_code_for(res) == 3
    assert res.gate_fix["status"] == "guard-rejected"


def test_commit_fix_no_change_no_retry(repo):
    git, res, logs = FakeGit([_denial(OTHER_TEXT)]), _res(), []
    with pytest.raises(HarnessError):
        _commit(git, repo, WritingLlm(), res, logs)
    assert len(git.commits) == 1 and res.gate_fix["status"] == "no-change"


def test_commit_fix_timeout_no_retry(repo):
    git, res, logs = FakeGit([_denial(OTHER_TEXT)]), _res(), []
    before = _tree_bytes(repo)
    llm = WritingLlm(edit=_fix_xphp, raises=HarnessError("llm-tooled-cli", "timeout"))
    with pytest.raises(HarnessError) as ei:
        _commit(git, repo, llm, res, logs)
    assert ei.value.kind == "local-gate"
    assert len(git.commits) == 1 and res.gate_fix["status"] == "fixer-error"
    assert _tree_bytes(repo) == before


def test_commit_retry_still_denied_raises_original(repo):
    first, second = _denial(BYTE_BUDGET_TEXT), _denial(OTHER_TEXT)
    git, res, logs = FakeGit([first, second]), _res(), []
    with pytest.raises(HarnessError) as ei:
        _commit(git, repo, WritingLlm(edit=_fix_xphp), res, logs)
    assert ei.value.detail == BYTE_BUDGET_TEXT
    assert len(git.commits) == 2 and res.gate_fix["retry"] == "denied"
    assert any("hook still denied after the gate fix" in ln for ln in logs)
    res.error_kind = ei.value.kind
    assert runner.exit_code_for(res) == 3


def test_commit_fix_once_per_run(repo):
    git, res, logs = FakeGit([_denial(OTHER_TEXT)]), _res(), []
    res.gate_fix = {"status": "no-change"}
    llm = WritingLlm(edit=_fix_xphp)
    with pytest.raises(HarnessError):
        _commit(git, repo, llm, res, logs)
    assert llm.calls == []
    assert any("gatefix skipped: already attempted" in ln for ln in logs)


def test_commit_fix_skips_adr_after_adr_draft(repo):
    git, res, logs = FakeGit([_denial(ADR_TEXT)]), _res(), []
    res.adr_drafted = True
    llm = WritingLlm(edit=_fix_xphp)
    with pytest.raises(HarnessError):
        _commit(git, repo, llm, res, logs)
    assert llm.calls == []


def test_commit_fix_unwired_call_unchanged(repo):
    git, logs = FakeGit([_denial(OTHER_TEXT)]), []
    with pytest.raises(HarnessError):
        runner._commit_with_gate_fix(git, str(repo), "msg", logs.append)
    assert len(git.commits) == 1


def test_commit_fix_usage_pause_not_swallowed(repo):
    git, res, logs = FakeGit([_denial(OTHER_TEXT)]), _res(), []
    llm = WritingLlm(raises=UsagePause("gate-fix", dirty=False))
    with pytest.raises(UsagePause):
        _commit(git, repo, llm, res, logs)
    assert res.gate_fix == {}


def test_commit_doc_staleness_then_fix_bounded(repo, monkeypatch):
    assert classify_local_gate_denial(DOC_TEXT) == "doc-staleness"
    monkeypatch.setattr(runner, "_remediate_doc_staleness", lambda *a, **k: 1)
    errs = [_denial(DOC_TEXT), _denial(DOC_TEXT), _denial(DOC_TEXT)]
    git, res, logs = FakeGit(errs), _res(), []
    with pytest.raises(HarnessError):
        _commit(git, repo, WritingLlm(edit=_fix_xphp), res, logs)
    assert len(git.commits) == 3


_SRC = Path(runner.__file__).read_text()


def _call_sites(name):
    return [m.start() for m in re.finditer(rf"(?<!def ){re.escape(name)}\(", _SRC)]


def _span(name):
    start = _SRC.index(f"def {name}(")
    nxt = re.search(r"\n(?:def |_[A-Z_]+ = )", _SRC[start + 1:])
    return start, start + 1 + (nxt.start() if nxt else len(_SRC))


def test_commit_sites_use_gate_fix_wrapper():
    s0, s1 = _span("_commit_with_gate_fix")
    remediation = [p for p in _call_sites("_commit_with_gate_remediation")
                   if not s0 <= p < s1]
    assert len(remediation) == 1       # run_meta_checks_local, deliberately unwired
    fix = [p for p in _call_sites("_commit_with_gate_fix") if not s0 <= p < s1]
    assert len(fix) == 4


# --- push wrapper --------------------------------------------------------------------


def test_push_fix_commits_separately_then_repushes(repo, tmp_path):
    git, res, logs, gh = FakeGit(push_errors=[_denial(PUSH_TEXT, "git push")]), _res(), [], FakeGh()
    pushed = _push(git, repo, WritingLlm(edit=_fix_xphp), res, logs, gh=gh, pr=7,
                   tmp=tmp_path)
    assert pushed
    assert len(git.commits) == 1
    assert git.commits[0].startswith("chore: auto-fix local pre-push gate denial")
    assert git.pushes == 2 and res.gate_fix["retry"] == "passed"
    assert len(gh.edits) == 1 and classify.GATE_FIX_BEGIN in gh.edits[0][1]


def test_push_retry_still_denied_raises_original(repo, tmp_path):
    first, second = _denial(PUSH_TEXT, "git push"), _denial("git push: other", "git push")
    git, res, logs, gh = FakeGit(push_errors=[first, second]), _res(), [], FakeGh()
    with pytest.raises(HarnessError) as ei:
        _push(git, repo, WritingLlm(edit=_fix_xphp), res, logs, gh=gh, pr=7, tmp=tmp_path)
    assert ei.value.detail == PUSH_TEXT
    assert res.gate_fix["retry"] == "denied" and gh.edits == []
    res.error_kind = ei.value.kind
    assert runner.exit_code_for(res) == 3


def test_push_fix_commit_denied_fails_closed(repo, tmp_path):
    git = FakeGit(push_errors=[_denial(PUSH_TEXT, "git push")],
                  commit_errors=[_denial(OTHER_TEXT)])
    res, logs = _res(), []
    with pytest.raises(HarnessError) as ei:
        _push(git, repo, WritingLlm(edit=_fix_xphp), res, logs, tmp=tmp_path)
    assert ei.value.detail == PUSH_TEXT
    assert git.pushes == 1


def test_push_fix_skipped_on_dirty_tree(repo, tmp_path):
    git = FakeGit(push_errors=[_denial(PUSH_TEXT, "git push")], dirty=True)
    res, logs, llm = _res(), [], WritingLlm(edit=_fix_xphp)
    with pytest.raises(HarnessError):
        _push(git, repo, llm, res, logs, tmp=tmp_path)
    assert llm.calls == [] and res.gate_fix == {}
    assert any("gatefix skipped: dirty tree at push site" in ln for ln in logs)


def test_push_adr_denial_after_draft_skips_fixer(repo, tmp_path, monkeypatch):
    drafts = []

    def fake_draft(*a, **k):
        drafts.append(1)
        return adr_draft.AdrDraftResult("ibl5/docs/decisions/0134-x.md", "0134",
                                        "claude-opus-5-5", "b" * 40)

    monkeypatch.setattr(runner.adr_draft, "draft", fake_draft)
    git = FakeGit(push_errors=[_denial(ADR_TEXT, "git push"), _denial(ADR_TEXT, "git push")])
    res, logs, llm = _res(), [], WritingLlm(edit=_fix_xphp)
    with pytest.raises(HarnessError):
        _push(git, repo, llm, res, logs, tmp=tmp_path)
    assert llm.calls == [] and len(drafts) == 1


def test_push_non_gate_error_untouched(repo, tmp_path):
    err = HarnessError("push-failed", "network down")
    git, res, logs, llm = FakeGit(push_errors=[err]), _res(), [], WritingLlm(edit=_fix_xphp)
    with pytest.raises(HarnessError) as ei:
        _push(git, repo, llm, res, logs, tmp=tmp_path)
    assert ei.value is err and llm.calls == []


def test_commit_then_push_one_fixer_per_run(repo, tmp_path):
    git = FakeGit(commit_errors=[_denial(OTHER_TEXT)],
                  push_errors=[_denial(PUSH_TEXT, "git push")])
    res, logs, llm = _res(), [], WritingLlm(edit=_fix_xphp)
    _commit(git, repo, llm, res, logs)
    with pytest.raises(HarnessError):
        _push(git, repo, llm, res, logs, tmp=tmp_path)
    assert len(llm.calls) == 1


def test_push_fix_usage_pause_not_swallowed(repo, tmp_path):
    git = FakeGit(push_errors=[_denial(PUSH_TEXT, "git push")])
    res, logs = _res(), []
    llm = WritingLlm(raises=UsagePause("gate-fix", dirty=False))
    with pytest.raises(UsagePause):
        _push(git, repo, llm, res, logs, tmp=tmp_path)
    assert git.commits == []


def test_push_sites_use_gate_fix_wrapper():
    s0, s1 = _span("_push_with_gate_fix")
    a0, a1 = _span("_push_with_adr_draft")
    adr_calls = [p for p in _call_sites("_push_with_adr_draft")
                 if not (s0 <= p < s1 or a0 <= p < a1)]
    assert adr_calls == []
    fix = [p for p in _call_sites("_push_with_gate_fix") if not s0 <= p < s1]
    assert len(fix) == 5
