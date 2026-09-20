"""Phase 3 — the remediation + bounded one-shot re-review loop."""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, llm_calls
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger

import runner

TREE_1 = "a" * 40
TREE_2 = "b" * 40
TREE_3 = "c" * 40
TREE_4 = "d" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


def _plan(auto_merge_false=False):
    return types.SimpleNamespace(found=False, path="", auto_merge_false=auto_merge_false)


def _verdict(tmp_path, word, body="- finding one\n"):
    """A verdict shaped like a real one: the word, then its findings, then the digest.

    The findings body matters since Phase 3 -- a NOT READY verdict whose work list is
    empty is skipped rather than handed to a fixer with nothing to do.
    """
    p = tmp_path / "verdict.md"
    p.write_text(f"6d checks\n\n{word}\n\n{body}\n## DIGEST\nstuff\n")
    return str(p)


def _git(dirty=True):
    return ReplayGit({"slug": "demo", "worktree_diff": "diff --git a/x b/x\n" if dirty else "",
                      "diff": "diff --git a/x b/x\n", "head_trees": [TREE_1, TREE_2]})


def _packet(tmp_path):
    d = tmp_path / "packet"
    d.mkdir()
    (d / "diff.patch").write_text("diff\n")
    return str(d)


# --- remediation gating -------------------------------------------------------

@pytest.mark.parametrize("word", ["READY", "READY WITH NOTES"])
def test_remediation_skipped_for_non_blocking_verdicts(tmp_path, git_shim, word):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    git = _git(dirty=False)
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             _verdict(tmp_path, word), "deadbeef")
    assert sha is None
    assert llm.tooled_argvs == []
    assert git.commit_messages == [] and git.pushes == 0


def test_remediation_skipped_on_a_dirty_worktree(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    git = _git(dirty=True)
    assert git.is_dirty()
    logged = []
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             _verdict(tmp_path, "NOT READY"), "deadbeef", log=logged.append)
    assert sha is None
    assert llm.tooled_argvs == []
    assert git.commit_messages == [] and git.pushes == 0
    assert any("dirty worktree" in m for m in logged)


def test_remediation_procedure_missing_is_typed(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text("#!/usr/bin/env bash\nexit 128\n")
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    with pytest.raises(HarnessError) as exc:
        fidelity.remediate(FixtureLlm(UsageLedger(), {}), _git(dirty=False), str(tmp_path),
                           str(tmp_path), _packet(tmp_path),
                           _verdict(tmp_path, "NOT READY"), "deadbeef")
    assert exc.value.kind == "remediation-procedure-missing"


# --- no push authority --------------------------------------------------------

def test_remediation_model_has_no_push_authority(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "edited"})
    git = _git(dirty=False)
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             _verdict(tmp_path, "NOT READY"), "deadbeef")
    assert sha
    purpose, argv = llm.tooled_argvs[0]
    assert purpose == "fidelity-remediation"
    allowed = argv[argv.index("--tools") + 1].split(",")
    denied = argv[argv.index("--disallowedTools") + 1].split(",")
    # Bash is now allowed for read-only inspection and gh pr edit; scoped denies
    # prevent push/commit/merge/review/api. Agent remains denied entirely.
    assert "Bash" in allowed and "Agent" not in allowed
    assert "Agent" in denied
    assert "Bash(git push:*)" in denied and "Bash(git commit:*)" in denied
    assert "Bash(gh pr merge:*)" in denied and "Bash(gh pr review:*)" in denied
    assert "Bash(gh api:*)" in denied
    assert "Edit" in allowed and "Write" in allowed
    assert "--agent" not in argv                      # Sonnet tier: MODEL_MAP supplies the pin
    # the HARNESS committed and pushed, after the model call
    assert git.commit_messages == [fidelity.REMEDIATION_COMMIT_MSG]
    assert git.pushes == 1


# --- re-review bounds ---------------------------------------------------------

def test_re_review_runs_even_when_plan_holds_auto_merge(tmp_path, git_shim):
    """auto_merge_false no longer skips re-review."""
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-re-review-2": "NOT READY\n"})
    path2 = fidelity.verdict_path("77-2")
    try:
        got = fidelity.re_review(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                                 _plan(auto_merge_false=True), "deadbeef", "body", 77,
                                 "sha123", _verdict(tmp_path, "NOT READY"))
        assert got[0] == "NOT READY"
        assert len(llm.tooled_argvs) == 1
    finally:
        if os.path.exists(path2):
            os.unlink(path2)


def test_re_review_skipped_without_a_remediation(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-re-review-2": "READY\n"})
    got = fidelity.re_review(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                             _plan(), "deadbeef", "body", 78, None,
                             _verdict(tmp_path, "NOT READY"))
    assert got == (None, None, None)
    assert llm.tooled_argvs == []


def test_re_review_is_one_shot_even_when_still_not_ready(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nNOT READY\n\n## DIGEST\nd\n",
    })
    git = _git(dirty=False)
    v1 = _verdict(tmp_path, "NOT READY")
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             v1, "deadbeef")
    verdict_2, tree_2, _path2 = fidelity.re_review(llm, git, str(tmp_path), str(tmp_path),
                                                   _plan(), "deadbeef", "body", 79, sha, v1)
    path2 = fidelity.verdict_path("79-2")
    try:
        assert verdict_2 == "NOT READY"
        # exactly ONE reviewer call for the whole run, never a loop back into remediation
        reviews = [p for p, _ in llm.tooled_argvs if p == "plan-fidelity-re-review-2"]
        assert len(reviews) == 1
        # verdict 1 is never overwritten
        assert "NOT READY" in open(v1).read()
        assert tree_2 == fidelity.read_reviewed_tree(path2)
    finally:
        if os.path.exists(path2):
            os.unlink(path2)


def test_re_review_records_the_post_push_tree(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "READY\n",
    })
    git = _git(dirty=False)
    assert git.head_tree() == TREE_1                   # pre-remediation
    v1 = _verdict(tmp_path, "NOT READY")
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             v1, "deadbeef")
    verdict_2, tree_2, _ = fidelity.re_review(llm, git, str(tmp_path), str(tmp_path),
                                              _plan(), "deadbeef", "body", 80, sha, v1)
    path2 = fidelity.verdict_path("80-2")
    try:
        assert verdict_2 == "READY"
        assert tree_2 == TREE_2 != TREE_1              # the post-push tree, not the stale one
    finally:
        if os.path.exists(path2):
            os.unlink(path2)


def test_re_review_degrades_to_verdict_1_on_adapter_failure(tmp_path, git_shim):
    class _Raising(FixtureLlm):
        def call_tooled(self, purpose, model, prompt, **kw):
            raise HarnessError("llm-tooled-empty", purpose)

    got = fidelity.re_review(_Raising(UsageLedger(), {}), _git(dirty=False), str(tmp_path),
                             str(tmp_path), _plan(), "deadbeef", "body", 81, "sha",
                             _verdict(tmp_path, "NOT READY"))
    assert got == (None, None, None)
    assert not os.path.exists(fidelity.verdict_path("81-2"))


def test_re_review_packet_is_separate_from_the_first(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-re-review-2": "READY\n"})
    out = tmp_path / "out"
    out.mkdir()
    first = fidelity.build_packet(str(out), "deadbeef", TREE_1, _plan(), "d", "b", 82,
                                  False, worktree=str(tmp_path))
    fidelity.re_review(llm, _git(dirty=False), str(out), str(tmp_path), _plan(),
                       "deadbeef", "body", 82, "sha", _verdict(tmp_path, "NOT READY"))
    path2 = fidelity.verdict_path("82-2")
    try:
        second = os.path.join(str(out), "fidelity-packet-2")
        assert os.path.isdir(second) and second != first
        ctx = open(os.path.join(second, "context.md")).read()
        assert "REMEDIATION_COMMIT: sha" in ctx
        assert "This is a RE-REVIEW" in ctx
    finally:
        if os.path.exists(path2):
            os.unlink(path2)


# --- multi-round fixture tests ------------------------------------------------

def test_re_review_round_2_uses_different_purpose(tmp_path, git_shim):
    """round_num=2 uses purpose plan-fidelity-re-review-3."""
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-re-review-3": "READY\n"})
    path3 = fidelity.verdict_path("83-3")
    try:
        got = fidelity.re_review(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                                 _plan(), "deadbeef", "body", 83, "sha123",
                                 _verdict(tmp_path, "NOT READY"), round_num=2)
        assert got[0] == "READY"
        purposes = [p for p, _ in llm.tooled_argvs]
        assert purposes == ["plan-fidelity-re-review-3"]
    finally:
        if os.path.exists(path3):
            os.unlink(path3)


def test_re_review_round_3_uses_different_purpose(tmp_path, git_shim):
    """round_num=3 uses purpose plan-fidelity-re-review-4."""
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-re-review-4": "NOT READY\n"})
    path4 = fidelity.verdict_path("84-4")
    try:
        got = fidelity.re_review(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                                 _plan(), "deadbeef", "body", 84, "sha123",
                                 _verdict(tmp_path, "NOT READY"), round_num=3)
        assert got[0] == "NOT READY"
    finally:
        if os.path.exists(path4):
            os.unlink(path4)


def test_extract_notes_returns_list_from_llm(tmp_path):
    """extract_notes calls llm with purpose fidelity-notes and returns the list."""
    notes_fixture = [{"title": "Add index", "detail": "The users table needs an index on email.",
                      "kind": "followup"}]
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": notes_fixture})
    vpath = _verdict(tmp_path, "READY WITH NOTES")
    result = fidelity.extract_notes(llm, vpath)
    assert result == notes_fixture


def test_extract_notes_keeps_only_followup_kind(tmp_path):
    """The three junk kinds — and an absent kind — are dropped; only followup files."""
    notes_fixture = [
        {"title": "Assert the parsed value, not itself", "kind": "followup",
         "detail": "The new test compares a value to itself, so it cannot fail."},
        {"title": "Accept the phpstan-baseline count increase", "kind": "plan-deviation-ok",
         "detail": "Placement differs from the plan by a better route."},
        {"title": "Change the PR title from feat to chore", "kind": "pr-copy",
         "detail": "The diff is dev tooling, invisible to a GM."},
        {"title": "Queue the Phase 4B review", "kind": "process",
         "detail": "Check 4b was worked explicitly and does not fire."},
        {"title": "Untyped note from an older verdict", "detail": "No kind field at all."},
    ]
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": notes_fixture})
    result = fidelity.extract_notes(llm, _verdict(tmp_path, "READY WITH NOTES"))
    assert [n["title"] for n in result] == ["Assert the parsed value, not itself"]


def test_fidelity_notes_prompt_types_notes_and_drops_when_torn():
    """The prompt names all four kinds and tells Haiku which way to fall when unsure."""
    prompt = llm_calls.fidelity_notes_prompt("READY WITH NOTES\n\n### Note 1 — cosmetic")
    for kind in ("followup", "plan-deviation-ok", "pr-copy", "process"):
        assert kind in prompt
    assert "still worth doing" in prompt
    assert "do NOT call it" in prompt          # the drop-when-torn instruction
    assert '"kind": "followup"' in prompt      # the shape Haiku must return


def test_extract_notes_returns_empty_on_llm_failure(tmp_path):
    """extract_notes returns [] when llm raises HarnessError."""
    from harness.state import HarnessError as _HE

    class _Raising(FixtureLlm):
        def call(self, purpose, model, prompt, validate, **kw):
            raise _HE("llm-fixture-missing", purpose)

    vpath = _verdict(tmp_path, "READY WITH NOTES")
    assert fidelity.extract_notes(_Raising(UsageLedger(), {}), vpath) == []


def test_extract_notes_returns_empty_on_missing_file(tmp_path):
    assert fidelity.extract_notes(
        FixtureLlm(UsageLedger(), {}), str(tmp_path / "nope.md")) == []


def test_file_note_issues_dedupes_by_normalized_title(tmp_path):
    """Two notes with near-identical titles file only one issue."""
    gh = RecordingGh(str(tmp_path))
    notes = [
        {"title": "Add index on email", "detail": "Needs an index."},
        {"title": "Add Index On Email!", "detail": "Same thing."},
    ]
    nums = fidelity.file_note_issues(gh, notes, 99, "verdict text")
    assert len(nums) == 1
    acts = [a for a in gh.actions() if a["action"] == "issue_create"]
    assert len(acts) == 1


def test_file_note_issues_skips_existing_titles(tmp_path):
    """A note whose normalized title is already in issue_titles() is skipped."""

    class _Gh(RecordingGh):
        def issue_titles(self, label):
            return ["Add index on email"]

    gh = _Gh(str(tmp_path))
    notes = [{"title": "Add index on email", "detail": "Needs an index."}]
    nums = fidelity.file_note_issues(gh, notes, 99, "verdict")
    assert nums == []
    assert not [a for a in gh.actions() if a["action"] == "issue_create"]


# --- _run_fidelity loop helpers -----------------------------------------------

class _CountingGit(ReplayGit):
    """Returns a distinct sha per commit so rounds are distinguishable."""
    def commit_all(self, message):
        super().commit_all(message)
        return f"round-sha-{len(self.commit_messages)}"


def _counting_git():
    return _CountingGit({"slug": "demo", "worktree_diff": "",
                         "diff": "diff --git a/x b/x\n",
                         "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4]})


class _Res:
    def __init__(self):
        self.fidelity = {}
        self.adr_drafted = False
        self.adr_path = None
        self.adr_draft_model = None


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


# --- _run_fidelity loop tests -------------------------------------------------

def test_round2_ready(tmp_path, git_shim):
    """Round 2 re-review returns READY: verdict_2='READY', rounds_completed=1,
    terminal_line starts 'READY (re-review)'."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh, _plan(),
            "diff", "body", 991, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["rounds_completed"] == 1
        tl = fidelity.terminal_line(
            res.fidelity.get("verdict_1"),
            res.fidelity.get("error_kind"),
            res.fidelity.get("remediation_sha"),
            res.fidelity.get("verdict_2"),
            res.fidelity.get("reviewed_tree_2"),
            res.fidelity.get("rounds_completed", 0),
        )
        assert tl.startswith("READY (re-review)")
    finally:
        _cleanup(991, "991-2")


def test_round2_no_verdict_overwrites_last_round_fields(tmp_path, git_shim):
    """Round 1 re-review NOT READY, round 2 re-review produces no verdict: the
    last-round fields describe round 2 (no stale round-1 verdict or tree)."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh, _plan(),
            "diff", "body", 9910, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity["rounds_completed"] == 2
        assert res.fidelity["remediation_sha"] == "round-sha-2"
        assert res.fidelity["verdict_2"] is None
        assert res.fidelity["reviewed_tree_2"] is None
        tl = fidelity.terminal_line(
            res.fidelity["verdict_1"], res.fidelity["error_kind"],
            res.fidelity["remediation_sha"], res.fidelity["verdict_2"],
            res.fidelity["reviewed_tree_2"], res.fidelity["rounds_completed"],
        )
        assert tl == "NOT READY — re-review produced no verdict; re-run /post-plan"
    finally:
        _cleanup(9910, "9910-2", "9910-3")


def test_remediate_none_stops(tmp_path, git_shim):
    """Round 2 remediate returns None (dirty tree): loop stops, rounds_completed==1,
    no plan-fidelity-re-review-3 call made."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
    }

    class _DirtyAfterFirstCommit(_CountingGit):
        def is_dirty(self):
            return len(self.commit_messages) >= 1

    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _DirtyAfterFirstCommit({
                "slug": "demo", "worktree_diff": "",
                "diff": "diff --git a/x b/x\n",
                "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4],
            }), gh, _plan(),
            "diff", "body", 992, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity["rounds_completed"] == 1
        purposes = [p for p, _ in llm.tooled_argvs]
        assert "plan-fidelity-re-review-3" not in purposes
    finally:
        _cleanup(992, "992-2")


def test_push_failed_propagates(tmp_path, git_shim):
    """HarnessError('push-failed') on round 2 push propagates out of _run_fidelity."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
    }

    class _PushFailOnRound2(_CountingGit):
        def push(self):
            if len(self.commit_messages) >= 2:
                raise HarnessError("push-failed", "remote rejected")
            super().push()

    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        with pytest.raises(HarnessError) as exc:
            runner._run_fidelity(
                llm, str(tmp_path), str(tmp_path), _PushFailOnRound2({
                    "slug": "demo", "worktree_diff": "",
                    "diff": "diff --git a/x b/x\n",
                    "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4],
                }), gh, _plan(),
                "diff", "body", 993, "dead" * 10, TREE_1, False, lambda m: None, res,
            )
        assert exc.value.kind == "push-failed"
        failed_res = RunResult(terminal=TerminalState.FAILED, error_kind="push-failed")
        assert runner.exit_code_for(failed_res) == 1
    finally:
        _cleanup(993, "993-2")


def test_auto_merge_false_still_loops(tmp_path, git_shim):
    """auto_merge_false=True does not skip remediation rounds."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh,
            _plan(auto_merge_false=True),
            "diff", "body", 994, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity.get("rounds_completed", 0) >= 1
        purposes = [p for p, _ in llm.tooled_argvs]
        assert "plan-fidelity-re-review-2" in purposes
    finally:
        _cleanup(994, "994-2")


def test_notes_end_to_end(tmp_path, git_shim):
    """READY WITH NOTES: no remediation rounds, notes filed as backlog issues."""
    notes_fixture = [
        {"title": "Add index on email", "detail": "Needs an index.", "kind": "followup"},
        {"title": "Cache expensive query", "detail": "Use Redis.", "kind": "followup"},
    ]
    canned = {
        "plan-fidelity-review": "6d checks\n\nREADY WITH NOTES\n",
        "fidelity-notes": notes_fixture,
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh, _plan(),
            "diff", "body", 995, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity.get("rounds_completed", 0) == 0
        assert res.fidelity.get("remediation_sha") is None
        nums = res.fidelity.get("backlog_issue_numbers") or []
        assert len(nums) == 2
        creates = [a for a in gh.actions() if a["action"] == "issue_create"]
        assert len(creates) == 2
        sticky = fidelity.compose_sticky(
            "", "", res.fidelity, None, [], "", fidelity.terminal_line(
                res.fidelity.get("verdict_1"),
                res.fidelity.get("error_kind"),
                res.fidelity.get("remediation_sha"),
                res.fidelity.get("verdict_2"),
                res.fidelity.get("reviewed_tree_2"),
                res.fidelity.get("rounds_completed", 0),
            ),
        )
        assert "**Backlog issues filed:** a-jay85/IBL5-backlog#" in sticky
    finally:
        _cleanup(995)


def test_notes_from_re_review_round_are_filed(tmp_path, git_shim):
    """Round 1 NOT READY, round 2 re-review READY WITH NOTES: the re-review's notes
    are extracted from ITS verdict file and filed as backlog issues."""
    notes_fixture = [{"title": "Log the issue_titles fallback", "detail": "Warn.",
                      "kind": "followup"}]
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "notes body\n\nREADY WITH NOTES\n",
        "fidelity-notes": notes_fixture,
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        verdict, _ = runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh, _plan(),
            "diff", "body", 9950, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert verdict == "READY WITH NOTES"
        assert res.fidelity["verdict_1"] == "NOT READY"
        assert res.fidelity["rounds_completed"] == 1
        assert len(res.fidelity["backlog_issue_numbers"]) == 1
        creates = [a for a in gh.actions() if a["action"] == "issue_create"]
        assert len(creates) == 1
    finally:
        _cleanup(9950, "9950-2")


def test_notes_dedupe_run_twice(tmp_path, git_shim):
    """When the note title already exists in issue_titles, no new issue is created.
    Also: two notes differing only in case/punctuation collapse to one issue."""
    notes_fixture = [{"title": "Add index on email", "detail": "Needs an index."}]
    canned = {
        "plan-fidelity-review": "6d checks\n\nREADY WITH NOTES\n",
        "fidelity-notes": notes_fixture,
    }

    class _SeededGh(RecordingGh):
        def issue_titles(self, label):
            return ["Add index on email"]

    llm = FixtureLlm(UsageLedger(), canned)
    gh = _SeededGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(), gh, _plan(),
            "diff", "body", 996, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        nums = res.fidelity.get("backlog_issue_numbers") or []
        assert nums == []
        creates = [a for a in gh.actions() if a["action"] == "issue_create"]
        assert creates == []
    finally:
        _cleanup(996)

    # Two notes differing only by case and trailing punctuation collapse to one issue.
    notes_case = [
        {"title": "Add index on email", "detail": "First."},
        {"title": "ADD INDEX ON EMAIL!", "detail": "Same."},
    ]
    gh2 = RecordingGh(str(tmp_path))
    nums2 = fidelity.file_note_issues(gh2, notes_case, 9960, "verdict")
    assert len(nums2) == 1
    creates2 = [a for a in gh2.actions() if a["action"] == "issue_create"]
    assert len(creates2) == 1


def test_verdict_path_threading(tmp_path, git_shim):
    """Each remediation round receives the previous round's verdict path."""
    pr = 997
    captured = []
    _orig = fidelity.remediate

    def _spy(llm, gitad, out_dir, worktree, packet_dir, verdict1_path, master_sha, log=None,
             **kw):
        captured.append(verdict1_path)
        return _orig(llm, gitad, out_dir, worktree, packet_dir, verdict1_path, master_sha, log,
                     **kw)

    mp = git_shim  # git_shim returns monkeypatch
    mp.setattr(fidelity, "remediate", _spy)

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
        "plan-fidelity-re-review-3": "NOT READY\n",
        "plan-fidelity-re-review-4": "NOT READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _CountingGit({
                "slug": "demo", "worktree_diff": "",
                "diff": "diff --git a/x b/x\n",
                "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4],
            }), gh, _plan(),
            "diff", "body", pr, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert captured == [
            fidelity.verdict_path(pr),
            fidelity.verdict_path(f"{pr}-2"),
            fidelity.verdict_path(f"{pr}-3"),
        ]
    finally:
        _cleanup(pr, f"{pr}-2", f"{pr}-3", f"{pr}-4")


def test_file_note_issues_continues_after_failed_create(tmp_path):
    """A failed issue_create does not abort remaining notes."""
    from harness.state import HarnessError as _HE

    calls = [0]

    class _FailFirst(RecordingGh):
        def issue_create(self, title, body, label):
            calls[0] += 1
            if calls[0] == 1:
                raise _HE("gh", "first failed")
            return super().issue_create(title, body, label)

    gh = _FailFirst(str(tmp_path))
    notes = [
        {"title": "First note", "detail": "Detail one."},
        {"title": "Second note", "detail": "Detail two."},
    ]
    nums = fidelity.file_note_issues(gh, notes, 99, "verdict")
    assert len(nums) == 1


# --- remediation commit path ----------------------------------------------------

class _EmptyCommitGit(_CountingGit):
    """commit_all's "nothing staged" answer: the model made no edits."""
    def commit_all(self, message):
        return ""


def test_remediation_with_no_edits_skips_push_and_commit_log(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "no changes"})
    git = _EmptyCommitGit({"slug": "demo", "worktree_diff": ""})
    lines = []
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             _verdict(tmp_path, "NOT READY"), "deadbeef", log=lines.append)
    assert sha is None
    assert git.pushes == 0
    assert not any("remediation committed" in m for m in lines)
    assert any("made no edits" in m for m in lines)


class _GateDeniedGit(_CountingGit):
    """The first `denials` commits raise local-gate with `detail`; later ones succeed."""
    def __init__(self, fx, detail, denials=1):
        super().__init__(fx)
        self.detail, self.denials, self.attempts = detail, denials, []

    def commit_all(self, message):
        self.attempts.append(message)
        if len(self.attempts) <= self.denials:
            raise HarnessError("local-gate", self.detail)
        return super().commit_all(message)


def _gate_fx():
    return {"slug": "demo", "worktree_diff": "", "diff": "diff --git a/x b/x\n",
            "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4]}


def test_remediation_gate_denial_logs_the_hook_message(tmp_path, git_shim):
    canned = {"plan-fidelity-review": "6d checks\n\nNOT READY\n",
              "fidelity-remediation": "edited"}
    git = _GateDeniedGit(_gate_fx(), "check-docs: stale ref in foo.md\n"
                         "Fix the above doc issues before committing.", denials=9)
    lines = []
    res = _Res()
    try:
        runner._run_fidelity(
            FixtureLlm(UsageLedger(), canned), str(tmp_path), str(tmp_path), git,
            RecordingGh(str(tmp_path)), _plan(), "diff", "body", 990, "dead" * 10,
            TREE_1, False, lines.append, res)
        line = next(m for m in lines if "remediation unavailable" in m)
        assert "(local-gate) class=unknown" in line
        assert "stale ref in foo.md Fix the above doc issues" in line   # newlines collapsed
        assert git.pushes == 0 and res.fidelity["rounds_completed"] == 0
    finally:
        _cleanup(990)


def test_remediation_doc_staleness_denial_is_auto_bumped(tmp_path, git_shim):
    canned = {"plan-fidelity-review": "6d checks\n\nNOT READY\n",
              "fidelity-remediation": "edited",
              "plan-fidelity-re-review-2": "READY\n"}
    git = _GateDeniedGit(_gate_fx(), "docs/x.md is stale. Bump last_verified", denials=1)
    git_shim.setattr(runner, "_remediate_doc_staleness", lambda w, g, l: 2)
    lines = []
    res = _Res()
    try:
        runner._run_fidelity(
            FixtureLlm(UsageLedger(), canned), str(tmp_path), str(tmp_path), git,
            RecordingGh(str(tmp_path)), _plan(), "diff", "body", 989, "dead" * 10,
            TREE_1, False, lines.append, res)
        assert len(git.attempts) == 2
        assert git.attempts[1].endswith(runner._REMEDIATION_NOTE)
        assert git.pushes == 1 and res.fidelity["rounds_completed"] == 1
        assert res.fidelity["verdict_2"] == "READY"
        assert "phase5.5: local-gate denial classified as doc-staleness" in lines
    finally:
        _cleanup(989, "989-2")


# --- Phase 3 prompt embedding tests -------------------------------------------

class PromptCapturingLlm(FixtureLlm):
    """FixtureLlm that also records the raw prompt text for each tooled call."""

    def __init__(self, ledger, canned):
        super().__init__(ledger, canned)
        self.captured_prompts: dict[str, str] = {}

    def call_tooled(self, purpose, model, prompt, **kw):
        self.captured_prompts[purpose] = prompt
        return super().call_tooled(purpose, model, prompt, **kw)


def test_remediation_prompt_embeds_procedure_verdict_and_diff(tmp_path, git_shim):
    """All three inputs — procedure, verdict, diff — appear inline in the prompt."""
    packet_dir = _packet(tmp_path)
    # Overwrite the default diff.patch with a sentinel line.
    with open(os.path.join(packet_dir, "diff.patch"), "w") as fh:
        fh.write("+SENTINEL_DIFF_LINE\n")
    verdict_path = str(tmp_path / "verdict_embed.md")
    with open(verdict_path, "w") as fh:
        fh.write("6d checks\n\nNOT READY\n\nSENTINEL_FINDING\n\n## DIGEST\nstuff\n")
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                       packet_dir, verdict_path, "deadbeef")
    prompt = llm.captured_prompts["fidelity-remediation"]
    assert "PROCEDURE BODY" in prompt
    assert "SENTINEL_FINDING" in prompt
    assert "+SENTINEL_DIFF_LINE" in prompt
    assert "=== END DIFF ===" in prompt


def test_remediation_prompt_never_relies_on_packet_path_alone(tmp_path, git_shim):
    """When a packet path appears in the prompt, its content is also inline."""
    packet_dir = _packet(tmp_path)
    with open(os.path.join(packet_dir, "diff.patch"), "w") as fh:
        fh.write("+SENTINEL_DIFF_LINE\n")
    verdict_path = str(tmp_path / "verdict_path.md")
    with open(verdict_path, "w") as fh:
        fh.write("6d checks\n\nNOT READY\n\n- finding one\n\n## DIGEST\nstuff\n")
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                       packet_dir, verdict_path, "deadbeef")
    prompt = llm.captured_prompts["fidelity-remediation"]
    # A packet path in the prompt means the content is also present inline.
    if packet_dir in prompt:
        assert "+SENTINEL_DIFF_LINE" in prompt


def test_remediation_prompt_truncates_oversized_diff(tmp_path, git_shim):
    """Diffs beyond REMEDIATION_DIFF_INLINE_CAP are truncated; tail content absent."""
    packet_dir = _packet(tmp_path)
    cap = fidelity.REMEDIATION_DIFF_INLINE_CAP
    # Build a diff that is cap+5000 bytes; last 100 bytes contain TAIL_SENTINEL.
    tail = ("TAIL_SENTINEL" * 8)[:100]
    padding = "X" * (cap + 5000 - 100)
    with open(os.path.join(packet_dir, "diff.patch"), "w") as fh:
        fh.write(padding + tail)
    verdict_path = _verdict(tmp_path, "NOT READY")
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                       packet_dir, verdict_path, "deadbeef")
    prompt = llm.captured_prompts["fidelity-remediation"]
    assert "TAIL_SENTINEL" not in prompt
    assert "diff truncated at" in prompt
    assert os.path.join(packet_dir, "diff.patch") in prompt


def test_remediation_prompt_survives_unreadable_verdict(tmp_path, git_shim, monkeypatch):
    """When the verdict file cannot be read, the marker appears and no exception is raised."""
    packet_dir = _packet(tmp_path)
    missing_verdict = str(tmp_path / "does_not_exist.md")
    monkeypatch.setattr(fidelity, "parse_verdict", lambda _path: "NOT READY")
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                       packet_dir, missing_verdict, "deadbeef",
                       work_list=[{"hold": "12", "text": "finding one"}])
    prompt = llm.captured_prompts["fidelity-remediation"]
    assert "(verdict unreadable)" in prompt


def test_remediation_prompt_mentions_offset_limit_read(tmp_path, git_shim):
    """The prompt instructs the model to use offset and limit for large file reads."""
    packet_dir = _packet(tmp_path)
    verdict_path = _verdict(tmp_path, "NOT READY")
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _git(dirty=False), str(tmp_path), str(tmp_path),
                       packet_dir, verdict_path, "deadbeef")
    prompt = llm.captured_prompts["fidelity-remediation"]
    assert "offset and limit" in prompt


# --- Phase 5.5 round inputs: model, tagged work list, gate-path deny, outcome ---


class _GateEditGit(ReplayGit):
    """ReplayGit whose remediation commit touches the paths the round names."""

    def __init__(self, fixture, touched):
        super().__init__(fixture)
        self._touched = touched

    def changed_files(self, base="origin/master"):
        return list(self._touched)


def _round_git(touched, dirty=False):
    return _GateEditGit(
        {"slug": "demo",
         "worktree_diff": "diff --git a/x b/x\n" if dirty else "",
         "diff": "diff --git a/x b/x\n", "head_trees": [TREE_1, TREE_2]},
        touched)


def test_remediate_forwards_model_alias(tmp_path, git_shim):
    """The round's model reaches call_tooled, which is what puts it in the CLI argv."""
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _round_git(["ibl5/x.php"]), str(tmp_path), str(tmp_path),
                       _packet(tmp_path), _verdict(tmp_path, "NOT READY"), "deadbeef",
                       model="opus")
    purpose, argv = llm.tooled_argvs[-1]
    assert purpose == "fidelity-remediation"
    assert "claude-opus-5" in argv


def test_remediate_prompt_carries_tagged_work_list_and_deny_text(tmp_path, git_shim):
    """Every hold is labelled in the prompt, and the gate-path deny is stated."""
    work_list = [{"hold": "12", "text": "finding one"},
                 {"hold": "3", "text": "MISSING: a.php (plan named it)"},
                 {"hold": "16", "text": "check-docs\nFAIL"},
                 {"hold": "2", "text": "b.php:4 score=90 bad"}]
    llm = PromptCapturingLlm(UsageLedger(), {"fidelity-remediation": "done"})
    fidelity.remediate(llm, _round_git(["ibl5/x.php"]), str(tmp_path), str(tmp_path),
                       _packet(tmp_path), _verdict(tmp_path, "NOT READY"), "deadbeef",
                       work_list=work_list)
    prompt = llm.captured_prompts["fidelity-remediation"]
    for hold in fidelity.HOLD_SOURCES:
        assert f"[hold {hold} — " in prompt
    assert "NEVER edit these gate-owning paths" in prompt


def test_gate_path_edit_raises_before_push(tmp_path, git_shim):
    """A commit touching a gate-owning path never reaches origin."""
    gitad_double = _round_git([".claude/rules/x.md", "ibl5/ok.php"])
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    outcome: dict = {}
    with pytest.raises(HarnessError) as ei:
        fidelity.remediate(llm, gitad_double, str(tmp_path), str(tmp_path),
                           _packet(tmp_path), _verdict(tmp_path, "NOT READY"),
                           "deadbeef", outcome=outcome)
    assert ei.value.kind == "gate-path-edit"
    assert ".claude/rules/x.md" in (ei.value.detail or "")
    assert gitad_double.pushes == 0
    assert outcome["reason"] == "gate-path-edit"


def test_non_gate_edit_still_pushes(tmp_path, git_shim):
    gitad_double = _round_git(["ibl5/x.php"])
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    outcome: dict = {}
    sha = fidelity.remediate(llm, gitad_double, str(tmp_path), str(tmp_path),
                             _packet(tmp_path), _verdict(tmp_path, "NOT READY"),
                             "deadbeef", outcome=outcome)
    assert sha
    assert gitad_double.pushes == 1
    assert outcome == {"reason": "committed", "model": "sonnet"}


def test_gate_owning_prefixes_cover_armable_and_rules():
    assert fidelity.denied_gate_edits(
        [".claude/rules/a.md", ".github/workflows/x.yml", "bin/check-plan",
         "tools/postplan-harness/harness/armable.py", "ibl5/ok.php"]) == [
        ".claude/rules/a.md", ".github/workflows/x.yml", "bin/check-plan",
        "tools/postplan-harness/harness/armable.py"]


def test_outcome_reports_no_edits(tmp_path, git_shim):
    """commit_all returning "" is the model having made no edits."""
    gitad_double = _round_git(["ibl5/x.php"])
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    outcome: dict = {}
    sha = fidelity.remediate(llm, gitad_double, str(tmp_path), str(tmp_path),
                             _packet(tmp_path), _verdict(tmp_path, "NOT READY"),
                             "deadbeef", commit=lambda _m: "", outcome=outcome)
    assert sha is None
    assert outcome["reason"] == "no-edits"
    assert gitad_double.pushes == 0


def test_outcome_reports_empty_work_list_and_never_spawns(tmp_path, git_shim):
    """An empty union is a no-op round, not a fixer with nothing to do."""
    gitad_double = _round_git(["ibl5/x.php"])
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    outcome: dict = {}
    sha = fidelity.remediate(llm, gitad_double, str(tmp_path), str(tmp_path),
                             _packet(tmp_path), _verdict(tmp_path, "NOT READY"),
                             "deadbeef", work_list=[], outcome=outcome)
    assert sha is None
    assert outcome["reason"] == "empty-work-list"
    assert llm.tooled_argvs == []
    assert gitad_double.pushes == 0
