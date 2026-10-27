"""The security agent is Sonnet 5.5 in skill text and in the harness.

The harness half lives in
``test_review_parallel.py::test_security_audit_tier_is_sonnet_scoring_stays_haiku``.
This file pins the skill-text sites that launch the security agent
(``_security-agents.md`` and the ``/security-audit`` skill), and guards that
the Step 4 scorer and the Step 1 eligibility check stay Haiku.
"""

from __future__ import annotations

from pathlib import Path

import pytest

REPO_ROOT = Path(__file__).resolve().parents[3]

SECURITY_AGENTS = ".claude/review-shared/_security-agents.md"
SECURITY_AUDIT = ".claude/skills/security-audit/SKILL.md"


def _lines(rel_path: str) -> list[str]:
    return (REPO_ROOT / rel_path).read_text(encoding="utf-8").splitlines()


def _find(lines: list[str], prefix: str, rel_path: str, begin: int = 0) -> int:
    for i in range(begin, len(lines)):
        if lines[i].startswith(prefix):
            return i
    raise AssertionError(f"anchor {prefix!r} not found in {rel_path}")


def _region(rel_path: str, start_prefix: str, end_prefix: str | None) -> str:
    """Lines from the start anchor (inclusive) to the end anchor (exclusive).

    A missing anchor raises: an empty region would make every "no Haiku"
    assertion pass vacuously. ``end_prefix=None`` runs to end of file.
    """
    lines = _lines(rel_path)
    start = _find(lines, start_prefix, rel_path)
    end = len(lines) if end_prefix is None else _find(lines, end_prefix, rel_path, start + 1)
    return "\n".join(lines[start:end])


def _heading(rel_path: str, start_prefix: str) -> str:
    lines = _lines(rel_path)
    return lines[_find(lines, start_prefix, rel_path)]


def test_security_agents_doc_names_sonnet_tier() -> None:
    region = _region(SECURITY_AGENTS, "## Token-efficiency design", "## Pattern detection")
    assert "Sonnet" in region
    assert "Haiku" not in region
    heading = _heading(SECURITY_AGENTS, "## Single Security Agent")
    assert "Sonnet" in heading
    assert "Haiku" not in heading


def test_security_audit_step3_launches_sonnet_agent() -> None:
    region = _region(SECURITY_AUDIT, "## Step 3:", "## Step 4:")
    assert "Haiku" not in region
    assert 'subagent_type: "sonnet-5-5"' in region


def test_scorer_and_eligibility_tiers() -> None:
    assert "single Haiku agent" in _region(SECURITY_AUDIT, "## Step 4:", "## Step 5:")
    assert "**Haiku**" in _region(SECURITY_AUDIT, "## Step 1:", "## Step 2:")


def test_region_helper_fails_on_missing_anchor() -> None:
    with pytest.raises(AssertionError, match="anchor"):
        _region(SECURITY_AUDIT, "## Step Z:", "## Step 4:")
