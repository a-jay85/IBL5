"""Rules byte-budget carve-out for the Phase 7 ci-fix fixer.

Phase 1 pins today's gate-edit deny at every `denied_gate_edits` call site and in the
ci-fix prompt. Later phases add the module unit tests, prompt wiring and runner wiring
for `harness.rules_budget_carveout`.
"""
from __future__ import annotations

import os
import pathlib
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.fidelity as fidelity
from harness import cifix
from test_cifix_ship import CiFixGh, CiFixGit, ScriptedLlm, _has, _run


class RulesEditGit(CiFixGit):
    """The fixer's commit touches a rules file."""

    def changed_files(self, ref): return [".claude/rules/work-triage.md"]


class TestCharacterizationTodayDeny:
    def test_ci_fix_rules_edit_is_discarded_today(self, monkeypatch, tmp_path):
        git, gh, llm = RulesEditGit(), CiFixGh(), ScriptedLlm()
        r = _run(monkeypatch, tmp_path, git, gh, llm, failed=["Static guards"],
                 commit=("b" * 40,))
        assert _has(r.lines, "gate-path edit detected in fix commit")
        assert git.pushes == 0
        assert _has(r.lines, "outcome=error:gate-path-edit")

    def test_prompt_carries_deny_text_and_no_carveout_by_default(self):
        prompt = cifix.ci_fix_prompt(4242, 1, ["Static guards"],
                                     {"Static guards": "/tmp/x.log"},
                                     "/tmp/diff.patch", [])
        assert fidelity.GATE_EDIT_DENY_TEXT in prompt
        assert "byte budget" not in prompt.lower()

    def test_non_cifix_call_sites_have_no_carveout_hook(self):
        base = pathlib.Path(fidelity.__file__)
        for name in ("fidelity.py", "thread_ingestion.py", "prosefix.py"):
            text = base.with_name(name).read_text()
            assert "denied_gate_edits(" in text, name
            assert "rules_budget_carveout" not in text, name
        paths = [".claude/rules/x.md", "bin/check-prose", ".github/workflows/t.yml",
                 "tools/postplan-harness/harness/armable.py", "ibl5/x.php"]
        assert fidelity.denied_gate_edits(paths) == paths[:4]
