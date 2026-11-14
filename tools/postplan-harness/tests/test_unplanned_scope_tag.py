"""The `[unplanned-scope]` class tag rides the fidelity verdict format.

The tag is metadata on a check-3 finding. These tests pin that the existing severity
parser reads a tagged bullet unchanged, that both review contracts name the tag, and
that the reviewer's blocking criteria did not move.
"""
from __future__ import annotations

from pathlib import Path

import pytest

from harness import fidelity, unplanned_scope

REPO_ROOT = Path(__file__).resolve().parents[3]
REVIEW_CONTRACT = REPO_ROOT / ".claude" / "review-shared" / "_plan-fidelity-review.md"
AGENT_DEF = REPO_ROOT / ".claude" / "agents" / "pr-ready-phase6.md"

BLOCKING_CLAUSE = (
    "**Blocking** when an unasked-for change touches a security surface (SQL, a POST/form "
    "endpoint, an auth/authz-gated route, user-facing output rendering), a migration, a "
    "ship-pipeline gate (`.claude/skills`, `.claude/rules`, `~/.claude/hooks`), or "
    "user-visible behavior."
)


def _check3_item() -> str:
    text = REVIEW_CONTRACT.read_text(encoding="utf-8")
    start = text.index("3. **Scope creep.**")
    end = text.index("4. **PR body vs. reality.**")
    return text[start:end]


def test_tag_does_not_change_severity_parse(tmp_path):
    verdict = tmp_path / "verdict.md"
    verdict.write_text(
        "VERDICT: NOT READY\n\n## FINDINGS\n\n"
        "- [BLOCKING] [unplanned-scope] Remove or plan the quick-offer button grid.\n"
        "- [NOTE] [unplanned-scope] Unplanned doc bump.\n",
        encoding="utf-8",
    )

    items = fidelity.verdict_items(str(verdict))
    assert len(items["blocking"]) == 1
    assert len(items["notes"]) == 1

    texts = fidelity.blocking_finding_texts(str(verdict))
    assert len(texts) == 1
    assert "[unplanned-scope]" in texts[0].splitlines()[0]


def test_review_contract_carries_tag():
    assert "[unplanned-scope]" in _check3_item()


def test_review_contract_blocking_clause_unchanged():
    assert BLOCKING_CLAUSE in _check3_item()


def test_agent_def_carries_tag():
    assert "[unplanned-scope]" in AGENT_DEF.read_text(encoding="utf-8")


@pytest.mark.parametrize(
    "line, expected",
    [
        ("- [BLOCKING] [unplanned-scope] Split the check.", True),
        ("- [BLOCKING] [GATE-EDIT] [unplanned-scope] Split the new check-prose tell.", True),
        ("* [NOTE] [GATE-EDIT] [SECURITY] [unplanned-scope] Two extra tags.", True),
        ("1. [BLOCKING] [A] [B] [unplanned-scope]", True),
        ("- [BLOCKING] [GATE-EDIT] Split the check.", False),
        ("- [BLOCKING] Split the check [unplanned-scope] later.", False),
        ("- [BLOCKING] [GATE-EDIT] prose [unplanned-scope] Split.", False),
        ("- [GATE-EDIT] [unplanned-scope] Missing severity marker.", False),
        ("[BLOCKING] [unplanned-scope] No bullet.", False),
        ("- [BLOCKING] [GATE-EDIT] [unplanned-scope]x Glued suffix.", False),
    ],
)
def test_is_unplanned_scope_tag_run(line, expected):
    assert unplanned_scope.is_unplanned_scope(line) is expected
