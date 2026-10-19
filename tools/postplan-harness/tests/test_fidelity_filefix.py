"""Phase 5.5 remediation: narrowly scoped repo-file fixes under `.claude/rules/`.

Phase 1 pins today's behaviour (the PR #2943 verdict-2 replay and the gate-path deny);
later phases add the carve-out's unit, replay and integration tests to this file.
"""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures",
                       "pr2943-verdict2.md")
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


class _TouchedGit(ReplayGit):
    """ReplayGit whose remediation commit touches the paths the test names."""

    def __init__(self, touched):
        super().__init__({"slug": "demo", "worktree_diff": "",
                          "diff": "diff --git a/x b/x\n", "head_trees": [TREE_1, TREE_2]})
        self._touched = list(touched)

    def changed_files(self, base="origin/master"):
        return list(self._touched)


def _packet(tmp_path):
    d = tmp_path / "packet"
    d.mkdir()
    (d / "diff.patch").write_text("diff\n")
    return str(d)


def test_pr2943_fixture_parses_one_blocking_eight_notes():
    assert fidelity.parse_verdict(FIXTURE) == "NOT READY"
    items = fidelity.verdict_items(FIXTURE)
    assert len(items["blocking"]) == 1
    assert "visual-review-prs.md" in items["blocking"][0]
    assert len(items["notes"]) == 8


def test_today_rules_file_commit_is_discarded(tmp_path, git_shim):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "edited"})
    git = _TouchedGit([".claude/rules/visual-review-prs.md"])
    pushes = []
    outcome = {}
    with pytest.raises(HarnessError) as exc:
        fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                           FIXTURE, "deadbeef", commit=lambda m: "abc123",
                           push=lambda: pushes.append(1), outcome=outcome)
    assert exc.value.kind == "gate-path-edit"
    assert outcome["reason"] == "gate-path-edit"
    assert pushes == []


def test_no_file_named_stays_body_only(tmp_path, git_shim):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("NOT READY\n\n## FINDINGS\n"
                       "- [BLOCKING] Scope prose says 3 files; numstat shows 5\n")
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    git = _TouchedGit([])
    pushes = []
    outcome = {}
    sha = fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                             str(verdict), "deadbeef", commit=lambda m: "",
                             push=lambda: pushes.append(1), outcome=outcome)
    assert sha is None
    assert outcome["reason"] == "no-edits"
    assert pushes == []
