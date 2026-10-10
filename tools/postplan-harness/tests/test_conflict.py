"""Modify/delete resolution, merge orientation, the DELETED reply, and merge abort."""
from __future__ import annotations

import os
import sys
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conflict import (
    MODIFY_DELETE_STAGES,
    UNRESOLVABLE_STAGES,
    abort_and_restore,
    classify,
    resolve_one,
)
from harness.state import HarnessError

PATH = "bin/old-tool"


class _Run:
    """git-run stub. `responses` maps a leading-args tuple to a reply; the longest
    matching prefix wins. Every call is recorded in `.calls`."""

    def __init__(self, responses=None):
        self.responses = dict(responses or {})
        self.calls: list[tuple] = []

    def __call__(self, *args, check=True) -> str:
        self.calls.append(args)
        best = None
        for prefix, reply in self.responses.items():
            if args[: len(prefix)] == prefix and (best is None or len(prefix) > len(best[0])):
                best = (prefix, reply)
        if best is not None:
            return best[1]
        if args and args[0] == "show":
            return "stage content\n"
        if args and args[0] == "rev-parse" and "--git-path" in args:
            return "/nonexistent/path"
        return ""


class _Llm:
    """Records prompts; optionally writes `content` to the target before each reply."""

    def __init__(self, replies, *, write_to=None, content="merged\n"):
        self.replies = list(replies)
        self.prompts: list[str] = []
        self.write_to = write_to
        self.content = content

    def call_tooled(self, purpose, model, prompt, **_):
        self.prompts.append(prompt)
        if self.write_to:
            with open(self.write_to, "w") as fh:
                fh.write(self.content)
        return self.replies.pop(0) if self.replies else "FAILED"


def _refs(master_hits="", base_hits=""):
    return {
        ("merge-base",): "base0\n",
        ("diff", "--name-only"): "docs/a.md\n",
        ("grep", "-l", "-F", "-e", PATH, "m"): master_hits,
        ("grep", "-l", "-F", "-e", PATH, "base0"): base_hits,
    }


def _resolve(llm, run, tmp_path, stages, branch_stage=2):
    return resolve_one(
        llm, run, worktree=str(tmp_path), key=f"t-{uuid.uuid4().hex[:8]}", path=PATH,
        stages=frozenset(stages), branch_stage=branch_stage, master_sha="m", pre_sha="h",
    )


def _rm_called(run):
    return ("rm", "-q", "--", PATH) in run.calls


# ── classify ─────────────────────────────────────────────────────────────────

def test_modify_delete_stage_sets_are_resolvable():
    for stages in MODIFY_DELETE_STAGES:
        assert classify(PATH, set(stages)) is None


def test_refusal_reason_add_add():
    reason = classify(PATH, {2, 3})
    assert reason is not None
    assert reason.startswith(UNRESOLVABLE_STAGES)
    assert "add/add" in reason
    assert frozenset({2, 3}) not in MODIFY_DELETE_STAGES


def test_deleted_migration_is_still_refused_first():
    reason = classify("ibl5/migrations/001_x.sql", {1, 2})
    assert reason is not None and reason.startswith("migration file")


# ── branch-side deletion: no model call ──────────────────────────────────────

def test_branch_side_deletion_kept_without_model_call(tmp_path):
    run = _Run(_refs())
    llm = _Llm(["RESOLVED"])
    assert _resolve(llm, run, tmp_path, {1, 3}, branch_stage=2) == (True, "")
    assert _rm_called(run)
    assert llm.prompts == []


def test_branch_side_deletion_blocked_by_new_master_reference(tmp_path):
    run = _Run(_refs(master_hits="m:docs/a.md\n"))
    llm = _Llm(["RESOLVED"])
    success, reason = _resolve(llm, run, tmp_path, {1, 3}, branch_stage=2)
    assert success is False
    assert "newly referenced on master" in reason
    assert PATH in reason and "docs/a.md" in reason
    assert not _rm_called(run)
    assert llm.prompts == []


def test_branch_side_deletion_preexisting_reference_is_not_new(tmp_path):
    run = _Run(_refs(master_hits="m:docs/a.md\n", base_hits="base0:docs/a.md\n"))
    llm = _Llm([])
    assert _resolve(llm, run, tmp_path, {1, 3}, branch_stage=2) == (True, "")
    assert _rm_called(run)


def test_rebase_orientation_branch_stage_three(tmp_path):
    # Under a rebase, stage 3 is the branch: stages {1, 2} means the branch deleted.
    run = _Run(_refs())
    llm = _Llm(["RESOLVED"])
    assert _resolve(llm, run, tmp_path, {1, 2}, branch_stage=3) == (True, "")
    assert _rm_called(run)
    assert llm.prompts == []


# ── master-side deletion: the model decides ──────────────────────────────────

def test_master_side_deletion_resolved_by_model(tmp_path):
    os.makedirs(tmp_path / "bin", exist_ok=True)
    run = _Run()
    llm = _Llm(["RESOLVED"], write_to=str(tmp_path / PATH))
    assert _resolve(llm, run, tmp_path, {1, 2}, branch_stage=2) == (True, "")
    assert ("add", "--", PATH) in run.calls
    assert "(absent: this side deleted the file)" in llm.prompts[0]
    assert "Stage 2 (ours) is the branch; stage 3 (theirs) is master." in llm.prompts[0]
    assert "end with DELETED" in llm.prompts[0]
    assert not _rm_called(run)
    # The absent stage is never read.
    assert ("show", f":3:{PATH}") not in run.calls


def test_master_side_deletion_model_keeps_deletion(tmp_path):
    run = _Run()
    llm = _Llm(["DELETED"])
    assert _resolve(llm, run, tmp_path, {1, 2}, branch_stage=2) == (True, "")
    assert _rm_called(run)


def test_deleted_reply_on_three_stage_conflict_is_rejected(tmp_path):
    os.makedirs(tmp_path / "bin", exist_ok=True)
    (tmp_path / PATH).write_text("merged\n")
    run = _Run()
    llm = _Llm(["DELETED", "RESOLVED"])
    assert _resolve(llm, run, tmp_path, {1, 2, 3}) == (True, "")
    assert len(llm.prompts) == 2
    assert "DELETED is only valid" in llm.prompts[1]
    assert "(absent" not in llm.prompts[0]
    assert not _rm_called(run)


# ── abort_and_restore ────────────────────────────────────────────────────────

def test_abort_and_restore_aborts_merge_then_checks_merge_head(tmp_path):
    run = _Run({("rev-parse", "-q", "--verify", "MERGE_HEAD"): "deadbeef\n"})
    with pytest.raises(HarnessError) as ei:
        abort_and_restore(run, worktree=str(tmp_path), pre_rebase_sha="abc", reason="boom")
    assert "MERGE_HEAD still present" in ei.value.detail
    assert ("merge", "--abort") in run.calls
    assert run.calls.index(("merge", "--abort")) < run.calls.index(("rebase", "--abort"))
    assert not any(c[:1] == ("reset",) for c in run.calls)
