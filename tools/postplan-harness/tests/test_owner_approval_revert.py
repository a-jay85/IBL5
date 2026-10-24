"""Owner-approval revert check: pure rule, check_commit, the commit choke point, and the
mid-run record-set guard."""
from __future__ import annotations

import json
import os

import pytest

import runner
from harness import owner_approvals as oa
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import RecordingGh
from harness.state import HarnessError, RunResult, TerminalState
from harness.thread_ingestion import run_thread_ingestion

PATH = "ibl5/x.php"
KEEP = "if ($teamId === 0) { return; }"
GONE = "legacy_call();"


def _snap(keep=(KEEP,), gone=(), path=PATH):
    rec = oa.Record("oad-0123abcd", "owner approved deviation", 1,
                    (oa.ApprovedFile(path, tuple(keep), tuple(gone)),))
    return oa.ApprovalSnapshot((rec,), "fp", 0)


def _kinds(hits):
    return sorted(h.kind for h in hits)


# --- the pure rule -----------------------------------------------------------

def test_keep_line_removed_is_hit():
    pre = f"a();\n{KEEP}\n{KEEP}\nb();\n"
    post = f"a();\n{KEEP}\nb();\n"          # one of two duplicates gone
    hits = oa.revert_hits(_snap(), PATH, pre, post)
    assert _kinds(hits) == ["keep-lost"]
    assert hits[0].record_id == "oad-0123abcd" and hits[0].line == KEEP


def test_keep_line_token_change_is_hit():
    pre = f"a();\n{KEEP}\n"
    post = "a();\nif ($teamId == 0) { return; }\n"
    assert _kinds(oa.revert_hits(_snap(), PATH, pre, post)) == ["keep-lost"]


def test_keep_line_reindented_passes():
    pre = f"a();\n{KEEP}\n"
    post = f"a();\n        {KEEP}\n"
    assert oa.revert_hits(_snap(), PATH, pre, post) == []


def test_unrelated_line_edit_same_file_passes():
    pre = f"a();\n{KEEP}\nb();\n"
    post = f"a2();\n{KEEP}\nb();\nc();\n"
    assert oa.revert_hits(_snap(), PATH, pre, post) == []


def test_stale_keep_line_absent_before_is_not_hit():
    pre = "a();\nb();\n"                    # the keep line was already gone
    post = "a();\nb();\nc();\n"
    assert oa.revert_hits(_snap(), PATH, pre, post) == []
    assert oa.revert_hits(_snap(), PATH, pre, None) == []


def test_gone_line_restored_is_hit():
    snap = _snap(keep=(), gone=(GONE,))
    hits = oa.revert_hits(snap, PATH, "a();\n", f"a();\n{GONE}\n")
    assert _kinds(hits) == ["gone-restored"]
    assert oa.revert_hits(snap, PATH, f"a();\n{GONE}\n", f"a();\n{GONE}\n") == []


def test_gone_line_restored_in_recreated_file_is_hit():
    snap = _snap(keep=(), gone=(GONE,))
    hits = oa.revert_hits(snap, PATH, None, f"<?php\n{GONE}\n")
    assert _kinds(hits) == ["gone-restored"]


def test_file_deleted_with_keep_lines_is_file_gone():
    hits = oa.revert_hits(_snap(), PATH, f"a();\n{KEEP}\n", None)
    assert _kinds(hits) == ["file-gone"]


def test_file_deleted_with_only_gone_lines_passes():
    snap = _snap(keep=(), gone=(GONE,))
    assert oa.revert_hits(snap, PATH, "a();\nb();\n", None) == []


# --- check_commit ------------------------------------------------------------

class _Git(ReplayGit):
    """ReplayGit whose changed set is injected and whose file reads are recorded."""

    def __init__(self, fixture, changed=(PATH,)):
        super().__init__(fixture)
        self.changed = list(changed)
        self.reads: list[str] = []

    def changed_files(self, base="origin/master"):
        return list(self.changed)

    def file_at(self, ref, path):
        self.reads.append(f"{ref}:{path}")
        return super().file_at(ref, path)


def _fx(pre_text, post_text, slug="s", extra=None):
    fx = {"slug": slug, "head_sha": "pre-sha",
          "files_at": {f"replay-sha-{slug}^:{PATH}": pre_text,
                       f"replay-sha-{slug}:{PATH}": post_text}}
    fx.update(extra or {})
    return fx


def test_check_commit_reads_only_snapshot_paths_in_commit():
    git = _Git(_fx(f"{KEEP}\n", f"{KEEP}\n"),
               changed=(PATH, "ibl5/unlisted.php", "ibl5/other.php"))
    assert oa.check_commit(git, _snap(), "replay-sha-s") == []
    assert git.reads == [f"replay-sha-s^:{PATH}", f"replay-sha-s:{PATH}"]
    # A snapshot path the commit did not touch is never read either.
    git2 = _Git(_fx("", ""), changed=("ibl5/unlisted.php",))
    assert oa.check_commit(git2, _snap(), "replay-sha-s") == []
    assert git2.reads == []


def test_check_commit_read_error_fails_closed():
    git = _Git(_fx("a\n", "a\n", extra={"files_at_error": True}))
    with pytest.raises(HarnessError) as ei:
        oa.check_commit(git, _snap(), "replay-sha-s")
    assert ei.value.kind == "owner-approval-check"
    assert PATH in ei.value.detail


# --- the commit choke point --------------------------------------------------

class _Llm:
    def __init__(self, snap):
        self.snap = snap


def _wrap(git, llm, res=None):
    res = res if res is not None else RunResult(terminal=TerminalState.SHIPPED_HELD)
    logs: list[str] = []
    return res, logs, lambda: runner._commit_with_gate_fix(
        git, None, "msg", logs.append, phase="phase4.5", llm=llm, res=res)


def test_commit_wrapper_drops_reverting_commit_and_holds():
    git = _Git(_fx(f"{KEEP}\n", "other();\n"))
    res, logs, call = _wrap(git, _Llm(_snap()))
    with pytest.raises(HarnessError) as ei:
        call()
    assert ei.value.kind == "owner-approval-revert"
    assert git.drops == ["pre-sha"]
    assert res.owner_approval_hold["hits"]
    assert res.owner_approval_hold["hits"][0]["kind"] == "keep-lost"
    assert res.owner_approval_hold["sha"] == "replay-sha-s"
    assert any("dropped" in m for m in logs)


class _HeadDriftsGit(_Git):
    def drop_commit(self, pre):
        self.drops.append(pre)
        self.fx = dict(self.fx, head_sha="not-" + pre)
        return ""


def test_commit_wrapper_undo_failure_fails_closed():
    git = _HeadDriftsGit(_fx(f"{KEEP}\n", "other();\n"))
    res, _, call = _wrap(git, _Llm(_snap()))
    with pytest.raises(HarnessError) as ei:
        call()
    assert ei.value.kind == "owner-approval-undo-failed"
    assert git.drops == ["pre-sha"]
    assert res.owner_approval_hold is None


def test_commit_wrapper_clean_commit_returns_sha():
    git = _Git(_fx(f"{KEEP}\n", f"{KEEP}\nmore();\n"))
    res, _, call = _wrap(git, _Llm(_snap()))
    assert call() == "replay-sha-s"
    assert git.drops == [] and res.owner_approval_hold is None


def test_commit_wrapper_without_snapshot_fails_closed():
    git = _Git(_fx("a\n", "a\n"))
    _, _, call = _wrap(git, None)
    with pytest.raises(HarnessError) as ei:
        call()
    assert ei.value.kind == "owner-approval-check"
    assert git.drops == []


def test_commit_wrapper_empty_commit_skips_check():
    class _EmptyGit(_Git):
        def commit_all(self, message):
            return ""

    git = _EmptyGit(_fx("a\n", "a\n"))
    _, _, call = _wrap(git, None)          # no snapshot: the check would raise if it ran
    assert call() == ""
    assert git.reads == []


def test_new_kinds_are_fail_closed():
    for kind in ("owner-approval-revert", "owner-approval-undo-failed",
                 "owner-approval-check", "owner-approval-drift",
                 "owner-approval-record"):
        assert kind in runner._FAIL_CLOSED_KINDS, kind


# --- mid-run record-set guard ------------------------------------------------

def _rec_comment(comment_id, rid="oad-0123abcd"):
    payload = {"schema": 1, "id": rid, "summary": "s",
               "files": [{"path": PATH, "keep": [KEEP], "gone": []}]}
    body = f"{oa.APPROVAL_MARKER}\n```json\n{json.dumps(payload)}\n```\n"
    return {"id": comment_id, "body": body, "login": "a-jay85", "type": "User"}


class _PostingInner:
    """Fake fixer LLM: optionally posts an owner-looking record during its own call."""

    def __init__(self, gh, post=False):
        self.gh, self.post, self.calls = gh, post, 0

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls += 1
        if self.post:
            self.gh.fixture["pr_issue_comments"].append(_rec_comment(99, "oad-99999999"))
        return "ok"


def _guard(tmp_path, post):
    gh = RecordingGh(str(tmp_path / "out"), {"pr_issue_comments": [_rec_comment(1)]})
    snap = oa.snapshot(gh, 7)
    inner = _PostingInner(gh, post=post)
    return oa.OwnerGuardLlm(inner, snap, gh=gh, pr=7), inner


def test_guard_drift_after_fixer_call_fails_closed(tmp_path):
    guard, inner = _guard(tmp_path, post=True)
    with pytest.raises(HarnessError) as ei:
        guard.call_tooled("gate-fix", "opus", "p", cwd=".")
    assert ei.value.kind == "owner-approval-drift"
    assert inner.calls == 1


def test_guard_no_drift_returns_inner_result(tmp_path):
    guard, _ = _guard(tmp_path, post=False)
    assert guard.call_tooled("gate-fix", "opus", "p", cwd=".") == "ok"


def test_guard_read_only_call_skips_drift_check(tmp_path):
    guard, inner = _guard(tmp_path, post=True)
    assert guard.call_tooled("plan-fidelity-review", "opus", "p", cwd=".") == "ok"
    assert inner.calls == 1


# --- thread-ingestion propagation --------------------------------------------

PR = 7
THREAD = {"id": "PRRT_x", "commentId": 501, "isResolved": False, "isOutdated": False,
          "path": PATH, "line": 3, "score": None, "body": "please fix",
          "authorLogin": "a-jay85", "authorType": "User"}


class _VerdictLlm:
    def __init__(self, raise_kind=None):
        self.raise_kind = raise_kind

    def call_tooled(self, purpose, model, prompt, **kw):
        if self.raise_kind:
            raise HarnessError(self.raise_kind, "boom")
        return '{"verdict":"FIX","reason":"Applied the suggestion"}'


def _ingest(tmp_path, llm, commit):
    gh = RecordingGh(str(tmp_path / "out"),
                     {"trusted_threads": [THREAD], "pr_number": PR})
    pushes: list[int] = []
    os.makedirs(tmp_path / "res", exist_ok=True)
    try:
        run_thread_ingestion(
            gh, llm, ReplayGit({"head_sha": "pre-sha"}), str(tmp_path), PR,
            pre_posting_ids={THREAD["commentId"]}, out_dir=str(tmp_path / "res"),
            log=lambda m: None, commit=commit, push=lambda: pushes.append(1) or "p")
    finally:
        _ingest.pushes = pushes
    return gh


def test_thread_fixer_revert_hold_propagates_out_of_run_thread_ingestion(tmp_path):
    def _commit(msg):
        raise HarnessError("owner-approval-revert", "oad-0123abcd:ibl5/x.php:keep-lost")

    with pytest.raises(HarnessError) as ei:
        _ingest(tmp_path, _VerdictLlm(), _commit)
    assert ei.value.kind == "owner-approval-revert"
    assert _ingest.pushes == []


def test_thread_fixer_drift_from_llm_call_propagates(tmp_path):
    def _commit(msg):
        raise AssertionError("no commit may be attempted after drift")

    with pytest.raises(HarnessError) as ei:
        _ingest(tmp_path, _VerdictLlm("owner-approval-drift"), _commit)
    assert ei.value.kind == "owner-approval-drift"
    assert _ingest.pushes == []
