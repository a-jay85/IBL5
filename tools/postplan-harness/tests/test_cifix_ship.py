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
    def pr_checks_json(self, pr):
        """`checks` is a fixed list, or a list of per-call lists (last one repeats)."""
        if self.checks and isinstance(self.checks[0], list):
            return list(self.checks.pop(0) if len(self.checks) > 1 else self.checks[0])
        return list(self.checks)
    def run_log_failed(self, run_id, job_id, dest): pass
    def run_rerun_failed(self, run_id): pass
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
         rewatch=(), res=None, run_started=None):
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
    # A no-change attempt reaches the rerun probe, which sleeps and watches live.
    monkeypatch.setattr(runner.time, "sleep", lambda s: None)
    monkeypatch.setattr(runner.ciwatch, "watch_live",
                        lambda *a, **kw: CiOutcome(-1, [], "probe not scripted"))
    res = res if res is not None else RunResult(terminal=TerminalState.FAILED)
    out = runner._ci_fix_loop(git, gh, llm, lines.append, res, worktree=None, pr=4242,
                              sha="a" * 40, outcome=CiOutcome(8, list(failed), "red"),
                              out_dir=str(tmp_path), mode="live", fixture={},
                              run_started=(time.time() if run_started is None
                                           else run_started))
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


# ---------------------------------------------------------------------------
# Phase 3: cifix_ship helpers
# ---------------------------------------------------------------------------

from harness import cifix_ship  # noqa: E402

_BODY = ("## Summary\n\nOld summary paragraph.\n\n"
         "**no-adr:** tooling only\n\n"
         "## Manual Testing\n\n- [ ] Check the page renders\n")


def test_judge_refuses_no_adr_comment_conversion():
    proposed = _BODY.replace("**no-adr:** tooling only", "<!-- no-adr: tooling only -->")
    v = cifix_ship.judge_proposal(_BODY, proposed)
    assert v.action == "refuse"
    assert "waiver" in v.reason


def test_judge_refuses_post_merge_recipe_ok_addition():
    proposed = _BODY.replace("Old summary paragraph.",
                             "Old summary paragraph.\n\n<!-- post-merge-recipe-ok -->")
    v = cifix_ship.judge_proposal(_BODY, proposed)
    assert v.action == "refuse"
    assert "waiver" in v.reason


def test_judge_refuses_manual_testing_sentinel():
    proposed = _BODY.replace("- [ ] Check the page renders",
                             "No manual testing needed.")
    v = cifix_ship.judge_proposal(_BODY, proposed)
    assert v.action == "refuse"
    assert v.reason == "## Manual Testing change"


def test_judge_refuses_manual_testing_heading_removal():
    proposed = _BODY.split("## Manual Testing")[0].rstrip() + "\n"
    v = cifix_ship.judge_proposal(_BODY, proposed)
    assert v.action == "refuse"
    assert v.reason == "## Manual Testing change"


def test_judge_applies_benign_summary_edit():
    proposed = _BODY.replace("Old summary paragraph.", "New, clearer summary paragraph.")
    v = cifix_ship.judge_proposal(_BODY, proposed)
    assert v.action == "apply"
    assert v.body == proposed


def test_judge_absent_and_noop():
    assert cifix_ship.judge_proposal(_BODY, None).action == "absent"
    assert cifix_ship.judge_proposal(_BODY, "\n  " + _BODY + "\n\n").action == "noop"


def _cp(rc, out):
    return SimpleNamespace(returncode=rc, stdout=out, stderr="")


def _fake_run_git(outputs):
    seq = list(outputs)
    return lambda args, cwd: seq.pop(0)


def test_master_dep_files_changed_true_and_false():
    base = _cp(0, "c" * 40 + "\n")
    assert cifix_ship.master_dep_files_changed(
        "/wt", run_git=_fake_run_git([base, _cp(0, "ibl5/bun.lock\n")])) is True
    assert cifix_ship.master_dep_files_changed(
        "/wt", run_git=_fake_run_git([base, _cp(0, "")])) is False
    assert cifix_ship.master_dep_files_changed(
        "/wt", run_git=_fake_run_git([base, _cp(128, "ibl5/bun.lock\n")])) is False
    assert cifix_ship.master_dep_files_changed(
        "/wt", run_git=_fake_run_git([_cp(128, "")])) is False
    assert cifix_ship.master_dep_files_changed(None) is False


def _meta(run_id, state):
    return {"name": "Meta checks", "state": state,
            "link": f"https://github.com/a/b/actions/runs/{run_id}/job/{run_id}9"}


def _poller(polls):
    seq = list(polls)
    calls = []

    def fn():
        calls.append(1)
        return seq.pop(0) if len(seq) > 1 else seq[0]
    return fn, calls


def test_wait_ignores_pre_edit_red_and_cancelled():
    fn, calls = _poller([[_meta(100, "FAILURE")], [_meta(101, "CANCELLED")],
                         [_meta(100, "FAILURE"), _meta(102, "SUCCESS")]])
    got = cifix_ship.wait_for_fresh_meta_run(fn, "100", deadline=time.time() + 3600,
                                             sleep=lambda s: None)
    assert got == "green"
    assert len(calls) == 3


def test_wait_indeterminate_on_deadline():
    clock = [1000.0]

    def sleep(s):
        clock[0] += s

    fn, _ = _poller([[_meta(101, "CANCELLED")]])
    got = cifix_ship.wait_for_fresh_meta_run(fn, "100", deadline=1100.0, sleep=sleep,
                                             now=lambda: clock[0])
    assert got == "indeterminate"


# ---------------------------------------------------------------------------
# Phase 4: stale-base catch-up on the Phase 7 push
# ---------------------------------------------------------------------------

_STALE = "pre-push-adr-hook: branch does not contain origin/master"


def test_stale_base_push_catches_up_and_rewatches_new_head(monkeypatch, tmp_path):
    git = CiFixGit(head_shas=["b" * 40, "c" * 40], push_errors=[
        HarnessError("local-gate", f"git push: {_STALE}", output=_STALE + "\n")])
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(), failed=["PHPUnit"],
             rewatch=[CiOutcome(0, [], "green")])
    assert (git.fetches, git.rebases, git.proofs) == (1, 1, 1)
    assert git.push_calls == ["push", "push"]
    assert r.watched == ["c" * 40]
    assert r.res.ci_head == "c" * 40
    assert _has(r.lines, "caught up to origin/master")
    assert _has(r.lines, "outcome=fixed")


def test_non_stale_base_push_denial_stops_without_catchup(monkeypatch, tmp_path):
    git = CiFixGit(push_errors=[HarnessError(
        "local-gate", "git push: pre-push-adr-hook: ADR required",
        output="pre-push-adr-hook: ADR required\n")])
    gh, llm = CiFixGh(), ScriptedLlm()
    r = _run(monkeypatch, tmp_path, git, gh, llm, failed=["PHPUnit"])
    assert (git.fetches, git.rebases) == (0, 0)
    assert len(llm.calls) == 1
    assert any("push-time denial" in ln and "ADR required" in ln for ln in r.lines)
    assert "(push-time)" in _trail_text(gh)
    assert "ADR required" in _trail_text(gh)


def test_catchup_rebase_conflict_stops_with_push_stage(monkeypatch, tmp_path):
    git = CiFixGit(push_errors=[
        HarnessError("local-gate", f"git push: {_STALE}", output=_STALE + "\n")])
    git.rebase_error = HarnessError(
        "rebase-conflict", "CONFLICT (content): ibl5/x.php",
        output="CONFLICT (content): Merge conflict in ibl5/x.php\n")
    gh = CiFixGh()
    r = _run(monkeypatch, tmp_path, git, gh, ScriptedLlm(), failed=["PHPUnit"])
    assert _has(r.lines, "stage=push")
    assert _has(r.lines, "outcome=error:rebase-conflict")
    assert "ibl5/x.php" in _trail_text(gh)
    assert r.watched == []


def test_catchup_push_with_no_budget_left_skips_rewatch(monkeypatch, tmp_path):
    clock = [time.time()]
    monkeypatch.setattr(runner, "time",
                        SimpleNamespace(time=lambda: clock[0], sleep=lambda s: None))

    class SlowPushGit(CiFixGit):
        def push(self):
            out = super().push()
            clock[0] += runner._CI_FIX_WALL_BUDGET_SECS
            return out

    git = SlowPushGit(head_shas=["b" * 40])
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(), failed=["PHPUnit"],
             rewatch=[CiOutcome(0, [], "green")])
    assert r.watched == []
    assert r.res.ci_head == "b" * 40
    assert _has(r.lines, "outcome=pushed-unwatched")


# ---------------------------------------------------------------------------
# Phase 5: one-shot bun-audit catch-up before an Opus attempt
# ---------------------------------------------------------------------------

_BUN = "JS Dependency Audit (bun)"


def _dep_changed(monkeypatch, changed):
    monkeypatch.setattr(runner.cifix_ship, "master_dep_files_changed",
                        lambda worktree, **kw: changed)


def test_bun_audit_master_changed_catches_up_before_llm(monkeypatch, tmp_path):
    _dep_changed(monkeypatch, True)
    git, llm = CiFixGit(head_shas=["a" * 40, "c" * 40]), ScriptedLlm()
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), llm, failed=[_BUN],
             rewatch=[CiOutcome(0, [], "green")])
    assert llm.calls == []
    assert git.rebases == 1
    assert git.push_calls == ["push"]
    assert r.watched == ["c" * 40]
    assert r.res.ci_head == "c" * 40


def test_bun_audit_master_unchanged_no_catchup(monkeypatch, tmp_path):
    _dep_changed(monkeypatch, False)
    git, llm = CiFixGit(head_shas=["b" * 40]), ScriptedLlm()
    r = _run(monkeypatch, tmp_path, git, CiFixGh(), llm, failed=[_BUN],
             rewatch=[CiOutcome(0, [], "green")])
    assert git.rebases == 0
    assert len(llm.calls) == 1
    assert _has(r.lines, "no catch-up")


def test_bun_audit_catchup_once_per_run(monkeypatch, tmp_path):
    _dep_changed(monkeypatch, True)
    git, llm = CiFixGit(head_shas=["a" * 40, "c" * 40]), ScriptedLlm()
    red = CiOutcome(8, [_BUN], "red")
    _run(monkeypatch, tmp_path, git, CiFixGh(), llm, failed=[_BUN],
         commit=("d" * 40, "e" * 40, "f" * 40), rewatch=[red, red, red])
    assert git.rebases == 1
    assert len(llm.calls) >= 2


def test_bun_audit_budget_exhausted_no_catchup(monkeypatch, tmp_path):
    _dep_changed(monkeypatch, True)
    git, llm = CiFixGit(), ScriptedLlm()
    _run(monkeypatch, tmp_path, git, CiFixGh(), llm, failed=[_BUN],
         run_started=time.time() - runner._CI_FIX_WALL_BUDGET_SECS)
    assert git.fetches == 0
    assert llm.calls == []


# ---------------------------------------------------------------------------
# Phase 6: ci_fix_prompt
# ---------------------------------------------------------------------------

from harness import cifix, fidelity  # noqa: E402


def _prompt(**kw):
    return cifix.ci_fix_prompt(4242, 1, ["PHPUnit"], {}, "/tmp/d.patch", [], **kw)


def test_prompt_drops_master_green_claim():
    p = _prompt()
    assert "Master is green" not in p
    assert "infrastructure failure" in p


def test_prompt_dep_advisory_guidance_only_when_flagged():
    on = _prompt(dep_advisory=True)
    assert "overrides" in on
    assert "never FLAKY" in on
    assert "overrides" not in _prompt(dep_advisory=False)


def test_prompt_names_proposal_path():
    path = "/tmp/x/proposed-pr-body.md"
    on = _prompt(proposal_path=path)
    assert path in on
    assert "do NOT run gh pr edit" in on
    off = _prompt()
    assert path not in off
    assert "do NOT run gh pr edit" not in off


# ---------------------------------------------------------------------------
# Phase 7: ci-fix-only gh pr edit deny, harness-applied body proposals
# ---------------------------------------------------------------------------

_META = "Meta checks"


def _write_proposal(text):
    def effect(kw):
        with open(os.path.join(kw["add_dirs"][0], cifix_ship.PROPOSAL_FILENAME), "w") as fh:
            fh.write(text)
    return effect


def test_ci_fix_denied_tools_add_gh_pr_edit_only_for_cifix(monkeypatch, tmp_path):
    assert "Bash(gh pr edit:*)" in cifix.CI_FIX_DENIED_TOOLS
    assert "Bash(gh pr edit:*)" not in fidelity.REMEDIATION_DENIED_TOOLS
    llm = ScriptedLlm()
    _run(monkeypatch, tmp_path, CiFixGit(head_shas=["b" * 40]), CiFixGh(), llm,
         failed=["PHPUnit"], rewatch=[CiOutcome(0, [], "green")])
    assert "Bash(gh pr edit:*)" in llm.calls[0][2]["denied_tools"]


def test_benign_body_proposal_applied_once_and_waits_fresh_meta_run(monkeypatch, tmp_path):
    proposed = _BODY.replace("Old summary paragraph.", "New, clearer summary paragraph.")
    gh = CiFixGh(body=_BODY)
    gh.checks = [[_meta(100, "FAILURE")],    # failed-job log refs
                 [_meta(100, "FAILURE")],    # baseline before the edit
                 [_meta(100, "FAILURE")],    # poll 1: only the pre-edit run
                 [_meta(101, "SUCCESS")]]    # poll 2: the run the edit fired
    git = CiFixGit()
    r = _run(monkeypatch, tmp_path, git, gh, ScriptedLlm([_write_proposal(proposed)]),
             failed=[_META], commit=("",))
    sha, outcome = r.out
    assert len(gh.body_edits) == 1
    assert gh.body_edits[0][1] == proposed
    assert outcome.exit_code == 0
    assert sha != runner.BODY_ONLY_SHA
    assert r.res.ci_head != runner.BODY_ONLY_SHA
    assert git.pushes == 0
    assert _has(r.lines, "outcome=body-fixed")


def test_body_baseline_is_newest_meta_run_when_older_entry_listed_last(monkeypatch, tmp_path):
    proposed = _BODY.replace("Old summary paragraph.", "New, clearer summary paragraph.")
    gh = CiFixGh(body=_BODY)
    stale_order = [_meta(105, "FAILURE"), _meta(100, "FAILURE")]   # older run listed last
    gh.checks = [[_meta(105, "FAILURE")],    # failed-job log refs
                 stale_order,                # baseline before the edit
                 stale_order,                # poll 1: only pre-edit runs
                 [_meta(106, "SUCCESS")]]    # poll 2: the run the edit fired
    seen = []
    real_wait = cifix_ship.wait_for_fresh_meta_run

    def spy(checks_fn, baseline, **kw):
        seen.append(baseline)
        return real_wait(checks_fn, baseline, **kw)

    monkeypatch.setattr(cifix_ship, "wait_for_fresh_meta_run", spy)
    r = _run(monkeypatch, tmp_path, CiFixGit(), gh, ScriptedLlm([_write_proposal(proposed)]),
             failed=[_META], commit=("",))
    assert seen == ["105"]
    assert r.out[1].exit_code == 0
    assert _has(r.lines, "outcome=body-fixed")


@pytest.mark.parametrize("proposed, reason", [
    (_BODY.replace("**no-adr:** tooling only", "<!-- no-adr: tooling only -->"),
     "refused (waiver change"),
    (_BODY.replace("- [ ] Check the page renders", "No manual testing needed."),
     "refused (## Manual Testing change"),
])
def test_waiver_body_proposal_refused_never_edits(monkeypatch, tmp_path, proposed, reason):
    gh = CiFixGh(body=_BODY)
    r = _run(monkeypatch, tmp_path, CiFixGit(), gh,
             ScriptedLlm([_write_proposal(proposed)]), failed=[_META], commit=("",))
    assert gh.body_edits == []
    assert _has(r.lines, reason)


# ---------------------------------------------------------------------------
# Phase 8: refused proposals quoted in the survivor comment
# ---------------------------------------------------------------------------

def test_refused_proposal_quoted_redacted_bounded_in_survivor_comment(monkeypatch, tmp_path):
    token = f"ghp_{_ALNUM36}"
    proposed = (_BODY + "\n<!-- no-adr: x -->\n\n"
                f"remote https://x-access-token:{token}@github.com/a/b.git\n"
                + "padding line\n" * 500)
    assert len(proposed) > 5000
    gh = CiFixGh(body=_BODY)
    red = CiOutcome(8, [_META], "red")
    _run(monkeypatch, tmp_path, CiFixGit(), gh, ScriptedLlm([_write_proposal(proposed)]),
         failed=[_META], commit=("b" * 40, "c" * 40, "d" * 40), rewatch=[red, red, red])
    body = gh.comments[-1][2]
    assert "Proposed PR body (refused" in body
    assert "waiver change" in body
    assert "~~~" in body
    assert "(truncated)" in body
    assert _ALNUM36 not in body
    assert len(body) < len(proposed)


def test_survivor_comment_without_refusals_unchanged():
    s, trail = ["PHPUnit"], ["attempt 1: still-red"]
    plain = cifix.survivor_comment(s, trail, False)
    assert plain == cifix.survivor_comment(s, trail, False, refused=())
    assert "Proposed PR body" not in plain
