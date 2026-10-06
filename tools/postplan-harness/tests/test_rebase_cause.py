"""Rebase-block cause classifier: module, Phase 2 raise site, RESULT line, blocked-ship.txt.

The classifier is advisory. Every test that reaches the runner also asserts the exit
code stays 3: a sibling-caused block blocks exactly as before.
"""
import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import runner
from harness import rebase_cause
from harness.adapters.ghad import RecordingGh
from harness.adapters.llm import FixtureLlm, UsageLedger
from harness.rebase_cause import (CAUSE_MASTER_TRAFFIC, CAUSE_SIBLING_MERGED, CAUSE_TREE_PROOF,
                                  CAUSE_UNKNOWN, LINEAGE_CANDIDATE_CAP, RebaseBlockCause,
                                  classify_rebase_block, live_runners)
from harness.state import RunResult, TerminalState

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
_BOT_SUBJECT = "ci: update coverage & PHPStan baseline snapshots [auto]"


def _git(d, *args):
    # core.hooksPath=/dev/null: a developer's global hooks must not fire on fixture pushes.
    return subprocess.run(["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *args],
                          check=True, capture_output=True, text=True, env=_ENV)


def _rev(d, ref):
    return _git(d, "rev-parse", ref).stdout.strip()


def _edit_line(d, rel, n, text):
    target = Path(d) / rel
    lines = target.read_text().splitlines()
    lines[n - 1] = text
    target.write_text("\n".join(lines) + "\n")


def _commit_all(d, msg):
    _git(d, "add", "-A")
    _git(d, "commit", "-qm", msg)


class _FakeGh:
    def __init__(self, rc=0, out="[]"):
        self.rc, self.out, self.calls = rc, out, []

    def __call__(self, args):
        self.calls.append(list(args))
        return self.rc, self.out


class _RecordingGit:
    def __init__(self, inner):
        self.inner, self.calls = inner, []

    def __call__(self, args):
        self.calls.append(list(args))
        return self.inner(args)


def _world(tmp_path, master_commits=(), open_pr_from_x=None):
    """Bare origin, a seed clone that is the only writer of master, and a worktree clone.

    Base history is W then X. Our branch forks from X and edits line 10 of shared.txt.
    master_commits is applied in order after the clone:
      ("sib", N)           squash-merged PR forked from X, edits line 10
      ("unrelated", N[,L]) squash-merged PR forked from W, edits line L (default 38)
      ("bot",)             direct [auto] commit editing line 1
    pull/N/head is published in origin for every PR. Returns (wt, branch_head_sha)."""
    origin, seed, wt = tmp_path / "origin.git", tmp_path / "seed", tmp_path / "wt"
    _git(tmp_path, "init", "-q", "--bare", "-b", "master", str(origin))
    _git(tmp_path, "init", "-q", "-b", "master", str(seed))
    (seed / "shared.txt").write_text("".join(f"line {i}\n" for i in range(1, 41)))
    (seed / "other.txt").write_text("w\n")
    _commit_all(seed, "base W")
    w = _rev(seed, "HEAD")
    (seed / "other.txt").write_text("x\n")
    _commit_all(seed, "base X")
    x = _rev(seed, "HEAD")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    _git(tmp_path, "clone", "-q", str(origin), str(wt))
    _git(wt, "config", "user.email", "t@t")
    _git(wt, "config", "user.name", "t")
    _git(wt, "checkout", "-qb", "feature")
    _edit_line(wt, "shared.txt", 10, "ours")
    _commit_all(wt, "feat: branch work")
    head = _rev(wt, "HEAD")

    def publish_pr(n, fork_from, line, text):
        _git(seed, "checkout", "-q", "-b", f"pr{n}", fork_from)
        _edit_line(seed, "shared.txt", line, text)
        _commit_all(seed, f"pr {n} work")
        _git(seed, "push", "-q", "origin", f"pr{n}:refs/pull/{n}/head")
        _git(seed, "checkout", "-q", "master")

    for spec in master_commits:
        if spec[0] == "bot":
            _edit_line(seed, "shared.txt", 1, "bot")
            _commit_all(seed, _BOT_SUBJECT)
        else:
            n = spec[1]
            if spec[0] == "sib":
                publish_pr(n, x, 10, f"sibling {n}")
                subject = f"feat: sib (#{n})"
            else:
                line = spec[2] if len(spec) > 2 else 38
                publish_pr(n, w, line, f"other {n}")
                subject = f"feat: other (#{n})"
            _git(seed, "merge", "--squash", f"pr{n}")
            _git(seed, "commit", "-qm", subject)
        _git(seed, "push", "-q", "origin", "master")
    if open_pr_from_x:
        publish_pr(open_pr_from_x, x, 10, f"open {open_pr_from_x}")
    _git(wt, "fetch", "-q", "origin")
    return wt, head


def _classify(wt, head, *, paths=("shared.txt",), reason="no iblBase", gh=None, run_git=None):
    gh = gh or _FakeGh()
    run_git = run_git or live_runners(str(wt))[0]
    return classify_rebase_block(paths, reason, head_sha=head, branch="feature",
                                 run_git=run_git, run_gh=gh)


# ---------------------------------------------------------------------------
# Phase 1: the classifier
# ---------------------------------------------------------------------------

def test_merged_sibling_is_cause(tmp_path):
    wt, head = _world(tmp_path, [("sib", 101)])
    got = _classify(wt, head)
    assert got.cause == CAUSE_SIBLING_MERGED
    assert got.merged_siblings == (101,)


def test_bot_and_unrelated_master_traffic(tmp_path):
    wt, head = _world(tmp_path, [("bot",), ("unrelated", 202)])
    got = _classify(wt, head)
    assert got.cause == CAUSE_MASTER_TRAFFIC
    assert got.bot_commits == 1
    assert got.master_prs == (202,)
    assert got.merged_siblings == ()


def test_open_sibling_annotated_never_cause(tmp_path):
    wt, head = _world(tmp_path, [("bot",), ("unrelated", 202)], open_pr_from_x=303)
    gh = _FakeGh(out=json.dumps([
        {"number": 303, "headRefName": "other", "files": [{"path": "shared.txt"}]},
        {"number": 304, "headRefName": "feature", "files": [{"path": "shared.txt"}]},
        {"number": 305, "headRefName": "elsewhere", "files": [{"path": "unrelated.txt"}]},
    ]))
    got = _classify(wt, head, gh=gh)
    assert got.cause == CAUSE_MASTER_TRAFFIC
    assert got.open_siblings == (303,)


def test_tree_proof_label_makes_no_seam_calls():
    git, gh = _RecordingGit(lambda a: (0, "")), _FakeGh()
    got = classify_rebase_block(("a.txt",), "x | tree proof failed: TREE DIVERGED",
                                head_sha="h", branch="feature", run_git=git, run_gh=gh)
    assert got.cause == CAUSE_TREE_PROOF
    assert git.calls == [] and gh.calls == []


def test_gh_failure_keeps_git_classification(tmp_path):
    wt, head = _world(tmp_path, [("sib", 101)])
    got = _classify(wt, head, gh=_FakeGh(rc=1, out=""))
    assert got.cause == CAUSE_SIBLING_MERGED
    assert got.note == "gh pr list failed"
    assert got.open_siblings == ()


def test_seam_exception_degrades_to_unknown():
    def boom(args):
        raise RuntimeError("seam down")

    got = classify_rebase_block(("a.txt",), "reason", head_sha="h", branch="feature",
                                run_git=boom, run_gh=_FakeGh())
    assert got.cause == CAUSE_UNKNOWN
    assert got.note == "classifier error: RuntimeError"


def test_empty_paths_unknown():
    git, gh = _RecordingGit(lambda a: (0, "")), _FakeGh()
    got = classify_rebase_block((), "reason", head_sha="h", branch="feature",
                                run_git=git, run_gh=gh)
    assert got.cause == CAUSE_UNKNOWN
    assert git.calls == [] and gh.calls == []


def test_lineage_cap_bounds_fetches(tmp_path):
    wt, head = _world(tmp_path, [("unrelated", 400 + i, 3 * i + 12) for i in range(10)])
    git = _RecordingGit(live_runners(str(wt))[0])
    got = _classify(wt, head, run_git=git)
    assert len(got.master_prs) == 10
    assert sum(1 for c in git.calls if c and c[0] == "fetch") == LINEAGE_CANDIDATE_CAP


def test_render_format_exact():
    full = RebaseBlockCause(CAUSE_SIBLING_MERGED, tuple(f"p{i}.txt" for i in range(7)),
                            merged_siblings=(101, 102), master_prs=(101,), bot_commits=2,
                            open_siblings=(303,), note="gh pr list failed")
    assert full.render() == (
        "cause=sibling-merged; paths=p0.txt,p1.txt,p2.txt,p3.txt,p4.txt,+2 more; "
        "merged-siblings=#101,#102; master-prs=#101; bot-commits=2; open-siblings=#303; "
        "note=gh pr list failed")
    assert RebaseBlockCause(CAUSE_UNKNOWN).render() == (
        "cause=unknown; paths=-; merged-siblings=-; master-prs=-; bot-commits=0; "
        "open-siblings=-")


# ---------------------------------------------------------------------------
# Phase 2: the raise site (runner.run over a real conflicting rebase)
# ---------------------------------------------------------------------------

_COPY = {"type": "chore", "title": "chore: x", "commit_subject": "chore: x",
         "summary_md": "## Summary\n- x\n"}


def _run_conflict(tmp_path, monkeypatch, wt, gh=None):
    """runner.run in live mode; only the LLM seams are stubbed. The plain rebase conflicts,
    the FixtureLlm resolver cannot resolve, and the --onto fallback declines (no iblBase)."""
    gh = gh or _FakeGh()
    monkeypatch.setattr(runner, "_pr_copy", lambda *a, **k: (dict(_COPY), False))
    monkeypatch.setattr(runner, "_body_check", lambda *a, **k: ({}, False))
    # The branch is already committed; the repo's commit gates (bin/adr-check) are not
    # present in the throwaway clone.
    monkeypatch.setattr(runner, "_commit_with_adr_draft", lambda *a, **k: None)
    monkeypatch.setattr(runner, "_commit_with_gate_remediation", lambda git, *a, **k: git.head())
    monkeypatch.setattr(runner, "LiveGh", lambda out_dir, worktree, slug: RecordingGh(out_dir))
    monkeypatch.setattr(runner, "_rebase_cause_runners",
                        lambda worktree: (live_runners(worktree)[0], gh))
    (tmp_path / "plans").mkdir(exist_ok=True)
    return runner.run(None, str(tmp_path / "out"), FixtureLlm(UsageLedger(), {}),
                      mode="live", live=True, worktree=str(wt),
                      plans_dir=str(tmp_path / "plans"), state_dir=str(tmp_path / "state"))


def test_phase2_raise_carries_block_cause(tmp_path, monkeypatch):
    wt, _ = _world(tmp_path, [("unrelated", 202, 10)])
    res = _run_conflict(tmp_path, monkeypatch, wt)
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "rebase-conflict"
    assert res.block_cause.startswith("cause=master-traffic;")


def test_non_sibling_conflict_still_exits_3(tmp_path, monkeypatch):
    wt, _ = _world(tmp_path, [("unrelated", 202, 10)])
    res = _run_conflict(tmp_path, monkeypatch, wt)
    assert res.error_kind == "rebase-conflict"
    assert res.block_cause.startswith("cause=master-traffic;")
    assert runner.exit_code_for(res) == 3


def test_merged_sibling_block_still_exits_3(tmp_path, monkeypatch):
    wt, _ = _world(tmp_path, [("sib", 101)])
    res = _run_conflict(tmp_path, monkeypatch, wt)
    assert res.error_kind == "rebase-conflict"
    assert res.block_cause.startswith("cause=sibling-merged;")
    assert runner.exit_code_for(res) == 3


def test_classifier_crash_still_blocks_exit_3(tmp_path, monkeypatch):
    def boom(*a, **k):
        raise RuntimeError("classifier down")

    monkeypatch.setattr(rebase_cause, "classify_rebase_block", boom)
    wt, _ = _world(tmp_path, [("unrelated", 202, 10)])
    res = _run_conflict(tmp_path, monkeypatch, wt)
    assert res.error_kind == "rebase-conflict"
    assert res.block_cause.startswith("cause=unknown;")
    assert "classifier error: RuntimeError" in res.block_cause
    assert runner.exit_code_for(res) == 3


def test_result_json_omits_unset_block_cause():
    unset = RunResult(terminal=TerminalState.FAILED, error_kind="rebase-conflict")
    assert "block_cause" not in json.loads(unset.to_json())
    rendered = "cause=master-traffic; paths=a.txt"
    got = json.loads(RunResult(terminal=TerminalState.FAILED, error_kind="rebase-conflict",
                               block_cause=rendered).to_json())
    assert got["block_cause"] == rendered


# ---------------------------------------------------------------------------
# Phase 3: RESULT line and blocked-ship.txt
# ---------------------------------------------------------------------------

_CAUSE = ("cause=sibling-merged; paths=shared.txt; merged-siblings=#101; master-prs=#101; "
          "bot-commits=0; open-siblings=-")
_PREFIX = "RESULT: post-plan BLOCKED — rebase conflict, human required; ERROR terminal=failed, no PR opened."
_SUFFIX = "Resolve the rebase, then re-run bin/post-plan-now."


def _blocked(cause, error="rebase-conflict: boom | conflicted: shared.txt"):
    return RunResult(terminal=TerminalState.FAILED, error_kind="rebase-conflict",
                     error=error, block_cause=cause)


def test_result_line_names_cause():
    line = runner.verdict_line(_blocked(_CAUSE), 3, "")
    assert line.startswith(_PREFIX)
    assert " Cause: cause=sibling-merged;" in line
    assert line.endswith(_SUFFIX)


def test_result_line_unchanged_without_cause():
    line = runner.verdict_line(_blocked(None), 3, "")
    assert line == (f"{_PREFIX} rebase-conflict: boom | conflicted: shared.txt {_SUFFIX}")


def test_result_line_names_tree_proof_label():
    line = runner.verdict_line(_blocked("cause=tree-proof; paths=a.txt; merged-siblings=-"), 3, "")
    assert " Cause: cause=tree-proof;" in line


def _write_block(tmp_path, res, rc):
    runner.write_blocked_ship(str(tmp_path), res, rc, "/wt")
    return tmp_path / runner.BLOCKED_SHIP_FILE


def test_blocked_ship_names_cause(tmp_path):
    lines = _write_block(tmp_path, _blocked("cause=master-traffic; paths=a.txt"), 3) \
        .read_text().splitlines()
    assert lines[-2] == "Cause: cause=master-traffic; paths=a.txt"
    assert lines[-1].startswith("Log:")


def test_blocked_ship_omits_cause_when_unset(tmp_path):
    assert "Cause:" not in _write_block(tmp_path, _blocked(None), 3).read_text()


def test_blocked_ship_rc_not_3_ignores_cause(tmp_path):
    assert not _write_block(tmp_path, _blocked("cause=master-traffic; paths=a.txt"), 75).exists()
