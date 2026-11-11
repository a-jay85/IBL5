"""Replay the Phase 5.5 notes filter over real Haiku `fidelity-notes` outputs (backlog#1811).

The corpus is committed at tools/postplan-harness/tests/fixtures/fidelity-notes-corpus.json: the
last raw `fidelity-notes` output of each post-plan run, reduced to title, detail, kind and harm per
note. Every parsed run holding a pr-copy note is kept whole, plus every tenth other parsed run.
Each run replays through the whole `extract_notes` chain (kind gate, already-done, plan-artifact,
comment-nit, harmless-tooling), so the counts below predict live filing.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger

_CORPUS_PATH = os.path.join(os.path.dirname(__file__), "fixtures", "fidelity-notes-corpus.json")
with open(_CORPUS_PATH, encoding="utf-8") as _fh:
    _ENTRIES = json.load(_fh)["entries"]

_ENTRY_COUNT = 430
_PR_COPY_NOTES = 524
_KEPT_FOLLOWUPS = 73
_KEPT_PR_COPY = 60

_OMISSION_TITLES = (
    "Mention the jq-failure fallback in the PR body",
    "Add Phase 4.5 rollback loop to PR #3105 body",
    "Document inline dispose_entry move in PR body",
    "Add before and after corpus histograms to the PR body",
    "Mention the two new exit 10 messages in the PR body",
)
_MISSTATEMENT_TITLES = (
    "Remove the stale Residual Phases block from PR body",
    "Regenerate the stale scope-notes block in PR body",
    "Fix false positives in PR body's Unplanned changes block",
    "Reword PR body claim that merge danger derivation changed",
)


@pytest.fixture(scope="module")
def kept_by_run(tmp_path_factory):
    verdict = tmp_path_factory.mktemp("verdict") / "verdict.md"
    verdict.write_text("6d checks\n\nREADY WITH NOTES\n\n- finding one\n\n## DIGEST\nstuff\n")
    out = {}
    for entry in _ENTRIES:
        llm = FixtureLlm(UsageLedger(), {"fidelity-notes": entry["notes"]})
        out[entry["run"]] = fidelity.extract_notes(llm, str(verdict))
    return out


def _count(kept_by_run, kind):
    return sum(1 for notes in kept_by_run.values() for n in notes if n.get("kind") == kind)


def test_corpus_fixture_shape():
    assert len(_ENTRIES) == _ENTRY_COUNT
    pr_copy = [n for e in _ENTRIES for n in e["notes"]
               if n["kind"] == "pr-copy" and n["title"] and n["detail"]]
    assert len(pr_copy) == _PR_COPY_NOTES


def test_corpus_kept_followup_count_pinned(kept_by_run):
    assert _count(kept_by_run, "followup") == _KEPT_FOLLOWUPS


def test_corpus_kept_pr_copy_count(kept_by_run):
    assert _count(kept_by_run, "pr-copy") == _KEPT_PR_COPY


def test_corpus_followups_precede_pr_copy_in_every_run(kept_by_run):
    for run, notes in kept_by_run.items():
        kinds = [n.get("kind") for n in notes]
        if "pr-copy" in kinds:
            first_pc = kinds.index("pr-copy")
            assert "followup" not in kinds[first_pc:], run


def _fixture_titles():
    return {n["title"] for e in _ENTRIES for n in e["notes"]}


def _kept_titles(kept_by_run):
    return {n["title"] for notes in kept_by_run.values() for n in notes}


def test_corpus_omission_notes_never_file(kept_by_run):
    present = _fixture_titles()
    for title in _OMISSION_TITLES:
        assert title in present, title
    kept = _kept_titles(kept_by_run)
    for title in _OMISSION_TITLES:
        assert title not in kept, title


def test_corpus_misstatement_notes_file(kept_by_run):
    present = _fixture_titles()
    for title in _MISSTATEMENT_TITLES:
        assert title in present, title
    kept = _kept_titles(kept_by_run)
    for title in _MISSTATEMENT_TITLES:
        assert title in kept, title
