"""Root-cause check on `fix:` PR titles in the Agent A prompt."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ROOT_CAUSE_CHECK, agent_a_prompt
from harness.state import Classification, PlanInfo


def _php_cls() -> Classification:
    return Classification(has_php=True, migration_only=False, has_modified=True,
                          lines_php_changed=12, files=["ibl5/classes/X.php"],
                          filtered_diff="+<?php\n+$x = 1;\n")


def _migration_cls() -> Classification:
    return Classification(has_php=False, migration_only=True, has_modified=True,
                          files=["ibl5/migrations/999_x.sql"],
                          filtered_diff="+ALTER TABLE t ADD c INT;\n")


def _prompt(title, cls=None, **kwargs) -> str:
    return agent_a_prompt({"number": 1, "title": title}, cls or _php_cls(), PlanInfo(), **kwargs)


def test_fix_title_adds_root_cause_section():
    prompt = _prompt("fix: wrong cap total")
    assert ROOT_CAUSE_CHECK in prompt
    assert prompt.count(ROOT_CAUSE_CHECK) == 1


@pytest.mark.parametrize("title", ["fix(review): x", "fix!: x", "fix(a)!: x", "  fix: leading space"])
def test_scoped_and_breaking_fix_titles_add_section(title):
    assert ROOT_CAUSE_CHECK in _prompt(title)


@pytest.mark.parametrize("title", [
    "feat: x", "chore: fix typo", "fixup: x", "prefix: fix: x",
    "Fix: x", "fix x", "fix(scope x", "",
])
def test_non_fix_titles_omit_section(title):
    assert ROOT_CAUSE_CHECK not in _prompt(title)


@pytest.mark.parametrize("meta", [{"number": 1}, {"number": 1, "title": None}])
def test_missing_or_none_title_omits_section(meta):
    prompt = agent_a_prompt(meta, _php_cls(), PlanInfo())
    assert ROOT_CAUSE_CHECK not in prompt


def test_migration_only_fix_title_adds_section():
    assert ROOT_CAUSE_CHECK in _prompt("fix: repair rows", _migration_cls())


def test_root_cause_section_skipped_when_core_off():
    prompt = _prompt("fix: x", run_core=False, run_comments=True)
    assert ROOT_CAUSE_CHECK not in prompt


def test_non_fix_prompt_matches_fix_prompt_minus_section():
    fix_prompt = _prompt("fix: x")
    chore_prompt = _prompt("chore: x")
    stripped = fix_prompt.replace("\n\n" + ROOT_CAUSE_CHECK, "", 1).replace("fix: x", "chore: x", 1)
    assert stripped == chore_prompt


def test_review_agents_doc_mirrors_root_cause_check():
    doc = Path(__file__).resolve().parents[3] / ".claude" / "review-shared" / "_review-agents.md"
    text = doc.read_text(encoding="utf-8")
    assert "**Root cause (Agent A, `fix:` PRs only).**" in text
    assert "bug class: root-cause" in text
    assert "ROOT_CAUSE_CHECK" in text
    assert "bug class root-cause" in ROOT_CAUSE_CHECK
