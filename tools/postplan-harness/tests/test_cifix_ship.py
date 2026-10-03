"""Phase 7 ci-fix ships its fixes.

Drives `runner._ci_fix_loop` through its commit and push arms with scripted fakes:
characterization of today's stop paths (`char`), the denial-text log, stale-base
catch-up, the one-shot bun-audit catch-up, the prompt, and harness-applied PR-body
proposals. Every fake lives in this file.
"""
from __future__ import annotations

import os
import sys
import time
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.ciwatch import CiOutcome
from harness.state import HarnessError, RunResult, TerminalState


# ---------------------------------------------------------------------------
# Fakes
# ---------------------------------------------------------------------------

class CiFixGit:
    """FakeGit from test_adr_draft_on_denial.py, extended for _ci_fix_loop. push() and
    push_ff() share one scripted error queue and one counter, so a test stays valid
    whichever of the two the loop calls."""

    def __init__(self, push_errors=None, head_shas=None, proof_ok=True):
        self.push_errors = list(push_errors or [])
        self.head_shas = list(head_shas or ["a" * 40])
        self.proof_ok = proof_ok
        self.pushes = 0
        self.rebases = 0
        self.fetches = 0
        self.proofs = 0
        self.push_calls: list[str] = []
        self.rebase_error: HarnessError | None = None

    def branch(self): return "wt-slug"
    def head(self): return self.head_shas[min(self.rebases, len(self.head_shas) - 1)]
    def is_dirty(self): return False
    def diff_vs_base(self): return "diff --git a/x b/x\n"
    def changed_files(self, ref): return ["ibl5/classes/X.php"]

    def _push(self, method):
        self.push_calls.append(method)
        err = self.push_errors[self.pushes] if self.pushes < len(self.push_errors) else None
        self.pushes += 1
        if err:
            raise err
        return ""

    def push(self): return self._push("push")
    def push_ff(self): return self._push("push_ff")

    def capture_lostwork_pre(self, key): return True
    def fetch_base(self, base="origin/master"): self.fetches += 1

    def rebase_onto(self, base="origin/master"):
        if self.rebase_error:
            raise self.rebase_error
        self.rebases += 1

    def prove_lostwork(self, key):
        self.proofs += 1
        return (True, "TREE-EQUIVALENT") if self.proof_ok else (False, "TREE DIVERGED")


class CiFixGh:
    def __init__(self, body=""):
        self.body = body
        self.comments: list[tuple] = []
        self.body_edits: list[tuple] = []
        self.disarmed: list = []
        self.checks: list[dict] = []

    def post_review_summary(self, pr, title, body): self.comments.append((pr, title, body))
    def pr_edit_body(self, pr, body): self.body_edits.append((pr, body))
    def pr_checks_json(self, pr): return list(self.checks)
    def run_log_failed(self, run_id, job_id, dest): pass
    def pr_body_fresh(self, *a, **kw): return self.body
    def merge_state_status(self, pr): return "CLEAN"
    def pr_disable_auto_merge(self, pr): self.disarmed.append(pr)


class ScriptedLlm:
    def __init__(self, side_effects=None):
        self.calls: list[tuple] = []
        self.side_effects = list(side_effects or [])

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls.append((purpose, prompt, kw))
        idx = len(self.calls) - 1
        if idx < len(self.side_effects) and self.side_effects[idx]:
            self.side_effects[idx](kw)
        return ""


def _run(monkeypatch, tmp_path, git, gh, llm, *, failed, commit=("b" * 40,),
         rewatch=(), res=None):
    """Drive _ci_fix_loop in live mode with every external call faked. `commit` is a
    sequence of new SHAs or HarnessErrors, one per attempt. `rewatch` is the list of
    CiOutcomes watch_or_reuse hands back."""
    commits = list(commit)
    outcomes = list(rewatch)
    watched: list[str] = []
    lines: list[str] = []

    def fake_commit(git_, worktree, message, log, *, phase):
        nxt = commits.pop(0) if commits else ""
        if isinstance(nxt, BaseException):
            raise nxt
        return nxt

    def fake_watch(worktree, pr, sha, out_dir, bg, **kw):
        watched.append(sha)
        return outcomes.pop(0) if outcomes else CiOutcome(-1, [], "exhausted")

    monkeypatch.setattr(runner, "_commit_with_gate_remediation", fake_commit)
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **kw: SimpleNamespace(action="noop", remote_sha="",
                                                         evidence=""))
    monkeypatch.setattr(runner.ciwatch, "start_background_watch",
                        lambda *a, **kw: object())
    monkeypatch.setattr(runner.ciwatch, "watch_or_reuse", fake_watch)
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", lambda *a, **kw: None)
    res = res if res is not None else RunResult(terminal=TerminalState.FAILED)
    out = runner._ci_fix_loop(git, gh, llm, lines.append, res, worktree=None, pr=4242,
                              sha="a" * 40, outcome=CiOutcome(8, list(failed), "red"),
                              out_dir=str(tmp_path), mode="live", fixture={},
                              run_started=time.time())
    return SimpleNamespace(out=out, res=res, lines=lines, watched=watched)


def _has(lines, needle):
    return any(needle in ln for ln in lines)


def _trail_text(gh):
    return "\n".join(body for _pr, _title, body in gh.comments)


# ---------------------------------------------------------------------------
# Characterization: today's Phase 7 commit and push failure paths
# ---------------------------------------------------------------------------

def test_char_commit_error_breaks_with_local_unpushed_log(monkeypatch, tmp_path):
    git, gh, llm = CiFixGit(), CiFixGh(), ScriptedLlm()
    r = _run(monkeypatch, tmp_path, git, gh, llm, failed=["PHPUnit"],
             commit=(HarnessError("local-gate", "pre-commit-hook: denied"),))
    assert len(llm.calls) == 1
    assert git.pushes == 0
    assert _has(r.lines, "outcome=error:local-gate")
    assert _has(r.lines, "ci-fix commit is LOCAL and unpushed")
    assert "attempt 1: error:local-gate" in _trail_text(gh)


def test_char_push_local_gate_breaks_and_keeps_ci_head(monkeypatch, tmp_path):
    git = CiFixGit(push_errors=[
        HarnessError("local-gate", "git push: pre-push-adr-hook: ADR required")])
    gh, llm = CiFixGh(), ScriptedLlm()
    r = _run(monkeypatch, tmp_path, git, gh, llm, failed=["PHPUnit"])
    assert len(llm.calls) == 1
    assert r.res.ci_head != "b" * 40
    assert r.watched == []
    assert _has(r.lines, "outcome=error:local-gate")


def test_char_remote_head_diverged_reraises_from_push(monkeypatch, tmp_path):
    git = CiFixGit(push_errors=[HarnessError("remote-head-diverged", "x")])
    with pytest.raises(HarnessError) as ei:
        _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(), failed=["PHPUnit"])
    assert ei.value.kind == "remote-head-diverged"


def test_char_successful_push_sets_ci_head_and_rewatches_new_sha(monkeypatch, tmp_path):
    git = CiFixGit(head_shas=["b" * 40])
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(), failed=["PHPUnit"],
             rewatch=[CiOutcome(0, [], "green")])
    assert r.res.ci_head == "b" * 40
    assert r.watched == ["b" * 40]
    assert _has(r.lines, "outcome=fixed")


# ---------------------------------------------------------------------------
# Phase 2: redacted denial text, distinct commit-time and push-time tags
# ---------------------------------------------------------------------------

_ALNUM36 = "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8"   # same literal test_blocked_cause.py uses


def test_push_denial_text_in_log_and_trail(monkeypatch, tmp_path):
    git = CiFixGit(push_errors=[HarnessError(
        "local-gate", "git push: To github.com:a/b.git",
        output="remote: ok\nerror: failed to push some refs\n"
               "pre-push-adr-hook: ADR required for bin/foo\n")])
    gh = CiFixGh()
    r = _run(monkeypatch, tmp_path, git, gh, ScriptedLlm(), failed=["PHPUnit"])
    assert any("push-time denial:" in ln and "ADR required for bin/foo" in ln
               for ln in r.lines)
    trail = _trail_text(gh)
    assert "(push-time)" in trail
    assert "ADR required for bin/foo" in trail


def test_commit_and_push_tags_differ(monkeypatch, tmp_path):
    gh1 = CiFixGh()
    r1 = _run(monkeypatch, tmp_path, CiFixGit(), gh1, ScriptedLlm(), failed=["PHPUnit"],
              commit=(HarnessError("local-gate", "x", output="pre-commit-hook: stale doc"),))
    assert _has(r1.lines, "stage=commit")
    assert not _has(r1.lines, "stage=push")
    assert "(commit-time)" in _trail_text(gh1)
    assert "push-time" not in _trail_text(gh1)

    gh2 = CiFixGh()
    git2 = CiFixGit(push_errors=[HarnessError("local-gate", "git push: pre-push-adr-hook: ADR")])
    r2 = _run(monkeypatch, tmp_path, git2, gh2, ScriptedLlm(), failed=["PHPUnit"])
    assert _has(r2.lines, "stage=push")
    assert "(push-time)" in _trail_text(gh2)
    assert "commit-time" not in _trail_text(gh2)


def test_credential_in_push_output_never_reaches_log_or_trail(monkeypatch, tmp_path):
    token = f"ghs_{_ALNUM36}"
    git = CiFixGit(push_errors=[HarnessError(
        "local-gate", "git push: denied",
        output=f"fatal: https://x-access-token:{token}@github.com/a/b.git\n"
               "pre-push-adr-hook: ADR required\n")])
    gh = CiFixGh()
    r = _run(monkeypatch, tmp_path, git, gh, ScriptedLlm(), failed=["PHPUnit"])
    assert _has(r.lines, "push-time denial:")
    assert not any(_ALNUM36 in ln for ln in r.lines)
    assert gh.comments
    assert not any(_ALNUM36 in body for _pr, _t, body in gh.comments)


def test_llm_stage_error_does_not_claim_local_commit(monkeypatch, tmp_path):
    def boom(kw):
        raise HarnessError("llm-timeout", "t")

    git = CiFixGit()
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm([boom]), failed=["PHPUnit"])
    assert _has(r.lines, "stage=llm")
    assert not _has(r.lines, "ci-fix commit is LOCAL")
    assert git.pushes == 0
