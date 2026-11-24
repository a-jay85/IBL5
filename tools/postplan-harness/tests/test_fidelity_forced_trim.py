"""Size-forced trim leeway: the FORCED_TRIM_PATHS packet line, the check-3b bar in the
fidelity procedure, the remediation bullet, and same-shape verdict fixtures that prove a
3b `[NOTE]` flows through the existing parsers as a non-blocking unplanned-scope note."""
from __future__ import annotations

import os
import pathlib
import sys
import types

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, rules_budget_carveout, unplanned_scope

TREE = "a" * 40
REPO = pathlib.Path(__file__).resolve().parents[3]
PROCEDURE = REPO / ".claude" / "review-shared" / "_plan-fidelity-review.md"
REMEDIATION = REPO / ".claude" / "review-shared" / "_phase65-remediation.md"


def _plan(found=False, path=""):
    return types.SimpleNamespace(found=found, path=path)


def _stub_procedure(monkeypatch):
    monkeypatch.setattr(fidelity, "_find_procedure", lambda *a, **k: "PROCEDURE BODY")


def _context(packet):
    return pathlib.Path(packet, "context.md").read_text()


# --- FORCED_TRIM_PATHS in the packet -------------------------------------------------

def test_packet_lists_forced_paths(tmp_path, monkeypatch):
    _stub_procedure(monkeypatch)
    monkeypatch.setattr(
        rules_budget_carveout, "forced_paths_since",
        lambda wt, sha: frozenset({".claude/skills/plan/_architect-contract.md",
                                   ".claude/rules/x.md"}))
    packet = fidelity.build_packet(str(tmp_path), "deadbeef", TREE, _plan(), "diff",
                                   "body", 42, False, worktree=str(tmp_path))
    assert ("FORCED_TRIM_PATHS: .claude/rules/x.md, "
            ".claude/skills/plan/_architect-contract.md\n") in _context(packet)


def test_packet_forced_line_fails_closed(tmp_path, monkeypatch):
    _stub_procedure(monkeypatch)

    def boom(wt, sha):
        raise RuntimeError("caps script exploded")

    monkeypatch.setattr(rules_budget_carveout, "forced_paths_since", boom)
    packet = fidelity.build_packet(str(tmp_path), "deadbeef", TREE, _plan(), "diff",
                                   "body", 42, False, worktree=str(tmp_path))
    assert "FORCED_TRIM_PATHS: (none)\n" in _context(packet)
    assert os.path.exists(os.path.join(packet, "diff.patch"))


# --- check 3b in the fidelity procedure ----------------------------------------------

def test_procedure_states_two_question_bar():
    text = PROCEDURE.read_text()
    for phrase in (
        "**3b. Size-forced trims.**",
        "`FORCED_TRIM_PATHS`",
        "(a) Was anything critical lost?",
        "(b) Was anything wildly out of scope added?",
        "Never delete a check to get a merge through.",
        "a `(none)` value",
        "`- [NOTE] [unplanned-scope] [GATE-EDIT] <title>`",
    ):
        assert phrase in text, phrase


def test_procedure_excludes_merge_rule_and_one_way():
    text = PROCEDURE.read_text()
    exclusion = "It never touches a `[MERGE-RULE]` or `[ONE-WAY]` finding."
    assert exclusion in text
    a3 = text.index("**3a. Owner-requested scope.**")
    b3 = text.index("**3b. Size-forced trims.**")
    four = text.index("4. **PR body vs. reality.**")
    assert a3 < b3 < text.index(exclusion) < four


# --- the remediation prompt ----------------------------------------------------------

def test_remediation_states_forced_trim_bar():
    text = REMEDIATION.read_text()
    for phrase in (
        "**Size-forced trims.**",
        "`Fix hint (budget-forced trim)`",
        "(b) Was anything wildly out of scope added?",
        "keep the rest of the trim",
    ):
        assert phrase in text, phrase


def test_remediation_hint_prefix_matches_code():
    phrase = "Fix hint (budget-forced trim)"
    assert rules_budget_carveout.BUDGET_TRIM_HINT.startswith(phrase)
    assert rules_budget_carveout._TRIM_HINT.startswith(phrase)
    assert f"`{phrase}`" in REMEDIATION.read_text()


# --- same-shape verdict fixtures -----------------------------------------------------

TITLE = "Trimmed .claude/rules/workflow-continuity.md to fit its byte cap"
_DISPOSITIONS = (
    "1. **Intent coverage.** Passes.\n"
    "2. **Plan-named files.** Passes.\n"
    "3. **Scope creep.** One finding.\n"
    "4. **Body claims.** Passes.\n"
    "5. **Test realisation.** Passes.\n"
    "6. **Conflict-resolution audit.** Passes.\n"
)
MINOR_NOTE = (f"- [NOTE] [unplanned-scope] [GATE-EDIT] {TITLE}. "
              'Lost: "Added 2026-09-25." Nothing critical lost.')
RULE_SENTENCE = "Never delete a check to get a merge through."
DROPPED_RULE = f'- [BLOCKING] [unplanned-scope] [GATE-EDIT] {TITLE}. Lost: "{RULE_SENTENCE}"'


def _verdict_file(tmp_path, word, body):
    p = tmp_path / "verdict.md"
    p.write_text(_DISPOSITIONS + f"\n## FINDINGS\n\n{body}\n{word}\n\n## DIGEST\nstuff\n")
    return str(p)


def test_minor_forced_trim_note_is_not_blocking(tmp_path):
    p = _verdict_file(tmp_path, "READY WITH NOTES", MINOR_NOTE)
    items = fidelity.verdict_items(p)
    assert items["verdict"] == "READY WITH NOTES"
    assert items["blocking"] == []
    assert len(items["notes"]) == 1
    assert "[unplanned-scope]" in items["notes"][0]
    assert fidelity.blocking_finding_texts(p) == []
    assert unplanned_scope.is_unplanned_scope(MINOR_NOTE)


def test_note_and_blocking_titles_match():
    blocking = MINOR_NOTE.replace("[NOTE]", "[BLOCKING]", 1)
    assert blocking != MINOR_NOTE
    assert unplanned_scope.title_of(MINOR_NOTE) == unplanned_scope.title_of(blocking)
    assert TITLE in unplanned_scope.title_of(MINOR_NOTE)
    assert "[NOTE]" not in unplanned_scope.title_of(MINOR_NOTE)


def test_dropped_rule_sentence_stays_blocking(tmp_path):
    p = _verdict_file(tmp_path, "NOT READY", DROPPED_RULE)
    texts = fidelity.blocking_finding_texts(p)
    assert len(texts) == 1
    assert RULE_SENTENCE in texts[0]
    assert fidelity.verdict_items(p)["blocking"] != []
