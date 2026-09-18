"""Phase 3 — the remediation + bounded one-shot re-review loop."""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger

TREE_1 = "a" * 40
TREE_2 = "b" * 40

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


def _verdict(tmp_path, word):
    p = tmp_path / "verdict.md"
    p.write_text(f"6d checks\n\n{word}\n\n## DIGEST\nstuff\n")
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
    assert "Bash" not in allowed and "Agent" not in allowed
    assert "Bash" in denied and "Agent" in denied
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
    notes_fixture = [{"title": "Add index", "detail": "The users table needs an index on email."}]
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": notes_fixture})
    vpath = _verdict(tmp_path, "READY WITH NOTES")
    result = fidelity.extract_notes(llm, vpath)
    assert result == notes_fixture


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
