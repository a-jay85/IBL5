"""`fidelity.remediate` appends the budget-trim fix hint to the fixer's copy of a finding.

Row 23 of the trim-budget-gate plan. A tmp repo holds a forced trim of `.claude/rules/x.md`
(the PR cut 300 B, the stub caps script leaves 0 B of headroom), and the verdict carries one
`[BLOCKING] [unplanned-scope]` finding that cites the file.
"""
import os
import pathlib
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger

X_RULES = ".claude/rules/x.md"
PROCEDURE = ".claude/review-shared/_phase65-remediation.md"
HINT_SENTENCE = "Shrink your own added text or move it to a -detail companion."
FINDING = (f"- [BLOCKING] [unplanned-scope] The PR deleted prose from `{X_RULES}` "
           "that the plan never named. Put the phrases back.")


def _g(root, *args):
    return subprocess.run(["git", "-C", str(root), *args], capture_output=True,
                          text=True, check=True).stdout.strip()


def _commit(root, msg, files):
    for rel, text in files.items():
        path = pathlib.Path(root) / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text)
    _g(root, "add", "-A")
    _g(root, "commit", "-q", "-m", msg)
    return _g(root, "rev-parse", "HEAD")


class RepoGit:
    """Worktree-backed git adapter: every call shells out to the tmp repo."""

    def __init__(self, root, base):
        self.root, self.base, self.pushes = root, base, 0

    def is_dirty(self):
        return bool(_g(self.root, "status", "--porcelain"))

    def changed_files(self, base=None):
        return _g(self.root, "diff", "--name-only", base or self.base, "HEAD").splitlines()

    def conformance_files(self, base=None):
        return self.changed_files(base)

    def commit_all(self, message):
        _g(self.root, "add", "-A")
        if not _g(self.root, "diff", "--cached", "--name-only"):
            return ""
        _g(self.root, "commit", "-q", "-m", message)
        return _g(self.root, "rev-parse", "HEAD")

    def push(self):
        self.pushes += 1
        return _g(self.root, "rev-parse", "HEAD")

    def head_tree(self):
        return _g(self.root, "rev-parse", "HEAD^{tree}")

    def diff_vs_base(self, base=None):
        return _g(self.root, "diff", base or self.base, "HEAD")


class RecordingLlm(FixtureLlm):
    def __init__(self):
        super().__init__(UsageLedger(), {"fidelity-remediation": "done"})
        self.prompts = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.prompts.append(prompt)
        return super().call_tooled(purpose, model, prompt, **kw)


@pytest.fixture
def forced_trim(tmp_path):
    root = tmp_path / "repo"
    root.mkdir()
    _g(root, "init", "-q")
    _g(root, "config", "user.name", "t")
    _g(root, "config", "user.email", "t@example.com")
    _g(root, "config", "commit.gpgsign", "false")
    lines = [f"{i}" * 99 + "\n" for i in range(10)]
    base = _commit(root, "base", {X_RULES: "".join(lines), PROCEDURE: "PROCEDURE BODY\n"})
    _commit(root, "pr", {X_RULES: "".join(lines[:7])})        # 300 B cut, 700 B left
    # Cap oracle: cap 700 on a 700 B file leaves no headroom for the 300 B cut.
    stub = root / "bin" / "check-rules-byte-budget"
    stub.parent.mkdir()
    stub.write_text("#!/bin/sh\nprintf '%s\\t%s\\t%s\\t%s\\t%s\\n' "
                    f"'{X_RULES}' lazy 700 700 0\n")
    stub.chmod(0o755)
    _g(root, "add", "-A")
    _g(root, "commit", "-q", "-m", "caps stub")
    verdict = tmp_path / "verdict.md"
    verdict.write_text(f"NOT READY\n\n## FINDINGS\n{FINDING}\n")
    packet = tmp_path / "packet"
    packet.mkdir()
    (packet / "diff.patch").write_text("diff\n")
    return root, base, verdict, packet


def _remediate(forced_trim):
    root, base, verdict, packet = forced_trim
    llm, git = RecordingLlm(), RepoGit(str(root), base)
    fidelity.remediate(llm, git, str(root), str(root), str(packet), str(verdict), base)
    return llm


def test_remediate_prompt_carries_budget_trim_hint(forced_trim):
    llm = _remediate(forced_trim)
    assert len(llm.prompts) == 1
    assert HINT_SENTENCE in llm.prompts[0]
    assert f"bin/check-rules-byte-budget fails on {X_RULES}" in llm.prompts[0]


def test_budget_trim_hint_constant_is_pinned(forced_trim):
    """BUDGET_TRIM_HINT keeps its pre-split wording and fills the rules script name in."""
    from harness import rules_budget_carveout as rbc
    assert rbc.BUDGET_TRIM_HINT == (
        "Fix hint (budget-forced trim): bin/check-rules-byte-budget fails on {path} "
        "with this trim reverted. Shrink your own added text or move it to a -detail "
        "companion. Keep the trim in place."
    )
    llm = _remediate(forced_trim)
    assert rbc.BUDGET_TRIM_HINT.format(path=X_RULES) in llm.prompts[0]


def test_remediate_leaves_verdict_blocking(forced_trim):
    _, _, verdict, _ = forced_trim
    before = verdict.read_bytes()
    _remediate(forced_trim)
    assert verdict.read_bytes() == before
    assert fidelity.parse_verdict(str(verdict)) == "NOT READY"
    assert fidelity.blocking_finding_texts(str(verdict)) == [FINDING]
