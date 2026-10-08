"""Swallow-site propagation sweep for the usage-gate pause (ADR-0143 addendum).

`UsagePause` is a `BaseException`, so every `except Exception` and `except HarnessError`
that wraps a model call lets a pause through to `runner.run()`. Each test below drives
the smallest enclosing function around one such site with an LLM that always pauses and
asserts the pause escapes it. The two structural guards at the bottom catch a future
site: a bare or `BaseException` handler would swallow a pause, and a new `UsagePause`
handler needs a human to decide it is a legitimate catch.
"""
from __future__ import annotations

import ast
import os
import pathlib
import stat
import sys
import tempfile
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, conflict, fidelity, usage_pause
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.review import ReviewPhase
from harness.state import (Classification, HarnessError, PlanInfo, RunResult,
                           TerminalState, UsageLedger)
from harness.thread_ingestion import PURPOSE as THREAD_PURPOSE, run_thread_ingestion
from harness.usage_pause import UsagePause

# Fixtures reused from the ADR-draft suite: a real throwaway repo, and the bin/* seam stub.
from test_adr_draft_on_denial import fake_scripts, repo  # noqa: F401

ROOT = pathlib.Path(__file__).resolve().parent.parent

_NOOP = lambda _m: None  # noqa: E731


class PausingLlm:
    """An adapter whose model calls always pause. Mirrors the FixtureLlm signatures.

    `pause_on` limits the pause to those purposes; any other purpose returns its entry
    from `replies` (or raises llm-fixture-missing), so a test can walk a real run up to
    the one call it wants to pause.
    """

    def __init__(self, pause_on=None, replies=None):
        self.pause_on = None if pause_on is None else set(pause_on)
        self.replies = dict(replies or {})
        self.purposes: list[str] = []

    def _answer(self, purpose):
        self.purposes.append(purpose)
        if self.pause_on is None or purpose in self.pause_on:
            raise UsagePause(purpose)
        if purpose not in self.replies:
            raise HarnessError("llm-fixture-missing", purpose)
        return self.replies[purpose]

    def call(self, purpose, model, prompt, validate=None, max_retries=1, normalizer=None):
        return self._answer(purpose)

    def call_tooled(self, purpose, model, prompt, *, cwd=None, agent=None,
                    allowed_tools=(), denied_tools=("Bash", "Agent"), add_dirs=(),
                    append_system_prompt=None, setting_sources="user,project",
                    timeout=0, max_turns=0, max_retries=1):
        return self._answer(purpose)


_GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "shimbin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(_GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


def _cleanup_verdicts(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


# --- harness/fidelity.py ------------------------------------------------------

def test_fidelity_review_site_propagates_pause(tmp_path):
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        fidelity.review(llm, str(tmp_path), str(tmp_path), str(tmp_path), "pause-review")
    assert llm.purposes == ["plan-fidelity-review"]
    assert not os.path.exists(fidelity.verdict_path("pause-review"))


def test_fidelity_rereview_site_propagates_pause(tmp_path, git_shim):
    llm = PausingLlm()
    git = ReplayGit({"slug": "demo", "diff": "diff --git a/x b/x\n"})
    try:
        with pytest.raises(UsagePause):
            fidelity.re_review(llm, git, str(tmp_path), str(tmp_path), _plan(),
                               "dead" * 10, "body", "pause-rereview", "round-sha-1",
                               fidelity.verdict_path("pause-rereview"), round_num=1,
                               log=_NOOP)
        assert llm.purposes == ["plan-fidelity-re-review-2"]
    finally:
        _cleanup_verdicts("pause-rereview-2")


def test_fidelity_list_site_propagates_pause(tmp_path):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("6d checks\n\n- a note\n\nREADY WITH NOTES\n")
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        fidelity.extract_notes(llm, str(verdict), _NOOP)
    assert llm.purposes == ["fidelity-notes"]


# --- harness/conflict.py ------------------------------------------------------

class _StubRun:
    """Minimal git-run stub, shape copied from tests/test_conflict_resolver.py."""

    def __init__(self):
        self.calls: list[tuple] = []

    def __call__(self, *args, check=True) -> str:
        self.calls.append(args)
        first = args[0] if args else ""
        if first == "show":
            return "base content\n"
        if first == "rev-parse":
            return "abc1234"
        return ""


def test_conflict_resolver_site_propagates_pause(tmp_path):
    path = "app/foo.py"
    full = tmp_path / path
    full.parent.mkdir(parents=True)
    full.write_text("resolved content\n")
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        conflict.resolve_one(llm, _StubRun(), worktree=str(tmp_path),
                             key="pause-resolver", path=path)
    assert llm.purposes == [f"conflict-resolve:{path}"]


def test_conflict_review_site_propagates_pause(tmp_path):
    key = "pause-review-site"
    llm = PausingLlm()
    try:
        with pytest.raises(UsagePause):
            conflict.review_resolution(llm, _StubRun(), worktree=str(tmp_path), key=key,
                                       resolved_files=("app/foo.py",),
                                       proof_out="TREE-EQUIVALENT\n")
        assert llm.purposes == ["conflict-review"]
        assert not list(pathlib.Path("/tmp").glob(f"postplan-conflict-verdict-{key}-*"))
    finally:
        conflict.purge_verdict_artifacts(key)


# --- harness/adr_draft.py -----------------------------------------------------

def test_adr_draft_site_propagates_pause_and_discards(repo, tmp_path, monkeypatch):  # noqa: F811
    wt, git = repo["wt"], repo["git"]
    out_dir = str(tmp_path / "out")
    fake_scripts(monkeypatch)
    target = wt / "ibl5" / "docs" / "decisions" / "0134-wt-slug.md"

    class _WriteThenPause(PausingLlm):
        def call_tooled(self, purpose, model, prompt, **kw):
            # the drafter had already started writing when the gate paused the run
            target.write_text("half-written draft\n")
            return super().call_tooled(purpose, model, prompt, **kw)

    llm = _WriteThenPause()
    with pytest.raises(UsagePause) as exc_info:
        adr_draft.draft(llm, git, str(wt), out_dir, _NOOP, phase="phase2",
                        today="2026-09-20")
    assert exc_info.value.purpose == adr_draft.ADR_DRAFT_PURPOSE
    assert not isinstance(exc_info.value, HarnessError)   # never a local-gate kind
    assert not target.exists()
    assert os.path.exists(os.path.join(out_dir, adr_draft.REJECTED_DRAFT_NAME))


# --- harness/thread_ingestion.py ----------------------------------------------

_THREAD = {
    "id": "PRRT_x", "commentId": 4074926171, "isResolved": False, "isOutdated": False,
    "path": "ibl5/classes/X.php", "line": 41, "score": None,
    "body": "Consider caching this lookup.", "authorLogin": "a-jay85",
    "authorType": "User",
}


def _thread_gh(tmp_path):
    return RecordingGh(str(tmp_path), {"trusted_threads": [_THREAD], "pr_number": 2340})


def test_thread_ingestion_site_propagates_pause(tmp_path):
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        run_thread_ingestion(
            _thread_gh(tmp_path), llm, ReplayGit({}), str(tmp_path), 2340,
            pre_posting_ids={4074926171}, out_dir=str(tmp_path), log=_NOOP,
            commit=lambda m: "abc123", push=lambda: "def456")
    assert llm.purposes == [THREAD_PURPOSE]


# --- harness/review.py --------------------------------------------------------

class _NullGh:
    def post_review_findings(self, pr, sha, title, findings): pass
    def post_review_summary(self, pr, title, body): pass


def test_review_agent_site_propagates_pause():
    """A pause raised inside the ThreadPoolExecutor worker re-raises at f.result()."""
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.lines_php_changed = 100
    c.has_comments_in_diff = True
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        ReviewPhase(llm, _NullGh()).run({}, c, PlanInfo())
    assert llm.purposes   # at least one agent ran


# --- runner.py ----------------------------------------------------------------

def test_runner_ci_fix_site_propagates_pause(tmp_path):
    class _Git:
        def is_dirty(self): return False
        def diff_vs_base(self): return ""
        def head(self): return "a" * 40
        def branch(self): return "wt-slug"

    class _Gh:
        def pr_checks_json(self, pr): return []
        def run_log_failed(self, run_id, job_id, dest): pass

    llm = PausingLlm()
    outcome = runner.ciwatch.CiOutcome(8, ["PHPUnit"], "red")
    with pytest.raises(UsagePause):
        runner._ci_fix_loop(_Git(), _Gh(), llm, _NOOP, RunResult(terminal=TerminalState.FAILED),
                            worktree=None, pr=1, sha="a" * 40, outcome=outcome,
                            out_dir=str(tmp_path), mode="replay", fixture={},
                            run_started=__import__("time").time())
    assert llm.purposes == ["ci-fix"]


def test_runner_remediation_site_propagates_pause(tmp_path, git_shim):
    llm = PausingLlm(pause_on={"fidelity-remediation"},
                     replies={"plan-fidelity-review": "6d checks\n\n- finding\n\nNOT READY\n"})
    git = ReplayGit({"slug": "demo", "worktree_diff": "", "diff": "diff --git a/x b/x\n",
                     "head_trees": ["a" * 40, "b" * 40]})
    res = types.SimpleNamespace(fidelity={}, adr_drafted=False, adr_path=None,
                                adr_draft_model=None)
    try:
        with pytest.raises(UsagePause):
            runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git,
                                 RecordingGh(str(tmp_path)), _plan(), "diff", "body",
                                 9901, "dead" * 10, "a" * 40, False, _NOOP, res)
        assert llm.purposes == ["plan-fidelity-review", "fidelity-remediation"]
    finally:
        _cleanup_verdicts(9901, "9901-2")


def test_runner_manual_recheck_site_propagates_pause():
    row = types.SimpleNamespace(number=1, text="Looks right on mobile")
    plan = types.SimpleNamespace(truly_manual_rows=[row])
    llm = PausingLlm()
    with pytest.raises(UsagePause):
        runner._recheck_manual_rows(llm, None, plan, Classification(), _NOOP,
                                    types.SimpleNamespace(manual_demotions=[]))
    assert llm.purposes == ["manual-recheck"]


_CANNED_RUN = {
    "pr-copy": {"type": "chore", "title": "chore: pause-test",
                "commit_subject": "chore: test commit", "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _canned_run_fixture():
    return {
        "slug": "synthetic-pause",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9998,
        "pr_meta": {"number": 9998, "title": "fix: synthetic",
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody.\n",
    }


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_runner_retrospective_site_propagates_pause(tmp_path, monkeypatch):
    monkeypatch.setattr(usage_pause, "marker_exists", lambda ctx: True)
    canned = dict(_CANNED_RUN, retrospective={"kind": "usage-pause"})
    res = runner.run(_canned_run_fixture(), str(tmp_path / "out"),
                     FixtureLlm(UsageLedger(), canned), mode="replay")
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "usage-pause"
    assert "retrospective" in res.error
    assert runner.exit_code_for(res) == 75


def test_runner_thread_ingestion_wrapper_propagates_pause(tmp_path):
    llm = PausingLlm()
    res = RunResult(terminal=TerminalState.FAILED)
    with pytest.raises(UsagePause):
        runner._run_thread_ingestion_phase(
            _thread_gh(tmp_path), llm, ReplayGit({}), str(tmp_path), 2340, {4074926171},
            str(tmp_path), _NOOP, res)
    assert llm.purposes == [THREAD_PURPOSE]


# --- structural guards --------------------------------------------------------

def _harness_sources():
    files = [ROOT / "runner.py", *sorted((ROOT / "harness").rglob("*.py"))]
    return [(p, ast.parse(p.read_text(), filename=str(p))) for p in files]


def _type_names(node):
    """Every Name id and Attribute attr in an except clause's type expression."""
    if node is None:
        return []
    names = []
    for sub in ast.walk(node):
        if isinstance(sub, ast.Name):
            names.append(sub.id)
        elif isinstance(sub, ast.Attribute):
            names.append(sub.attr)
    return names


def test_no_bare_or_baseexception_except_in_harness():
    offenders = []
    for path, tree in _harness_sources():
        for node in ast.walk(tree):
            if not isinstance(node, ast.ExceptHandler):
                continue
            if node.type is None or "BaseException" in _type_names(node.type):
                offenders.append(f"{path.relative_to(ROOT)}:{node.lineno}")
    assert not offenders, f"bare/BaseException handlers swallow a pause: {offenders}"


def test_usage_pause_handlers_are_only_the_known_sites():
    counts = {}
    for path, tree in _harness_sources():
        n = sum(1 for node in ast.walk(tree)
                if isinstance(node, ast.ExceptHandler)
                and "UsagePause" in _type_names(node.type))
        if n:
            counts[str(path.relative_to(ROOT))] = n
    assert counts == {"runner.py": 2, os.path.join("harness", "adr_draft.py"): 1}
