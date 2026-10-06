"""Replay the test-count verifier over real merged-PR bodies (backlog#1060).

The corpus is committed at tools/postplan-harness/tests/fixtures/test-count-claims-corpus.json: eleven real PRs with
their claim lines, test-file diff sections, and head copies of modified test files.
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.body_numbers import (annotate_test_count_mismatches,
                                  check_test_count_claims, correct_body_numbers)
from harness.classify import name_status_text, numstat_text

_CORPUS_PATH = os.path.join(os.path.dirname(__file__), "fixtures", "test-count-claims-corpus.json")
with open(_CORPUS_PATH, encoding="utf-8") as _fh:
    _CORPUS = json.load(_fh)
_ENTRIES = _CORPUS["entries"]


def _checks(entry):
    return check_test_count_claims(entry["body"], entry["diff"], entry["head_files"].get)


def _classify(entry):
    checks = _checks(entry)
    if any(c.verdict == "mismatch" for c in checks):
        return "flagged"
    if any(c.verdict == "match" for c in checks):
        return "clean"
    return "unverifiable"


def test_corpus_flagged_set_matches_expected():
    assert len(_ENTRIES) == 11
    for cls in ("flagged", "clean", "unverifiable"):
        got = {e["number"] for e in _ENTRIES if _classify(e) == cls}
        want = {e["number"] for e in _ENTRIES if e["expected"] == cls}
        wrong = [f"#{e['number']} expected {e['expected']} got {_classify(e)}: {_checks(e)}"
                 for e in _ENTRIES if (e["number"] in got) != (e["number"] in want)]
        assert got == want, "\n".join(wrong)


def test_corpus_false_positive_rate_is_zero():
    verifiable = [e for e in _ENTRIES if e["expected"] in ("clean", "flagged")]
    false_positives = [e["number"] for e in _ENTRIES
                       if e["expected"] != "flagged" and _classify(e) == "flagged"]
    rate = f"{len(false_positives)} / {len(verifiable)}"
    assert not false_positives, f"false positives / verifiable entries = {rate}: {false_positives}"


def test_corpus_before_after_flagged_counts():
    before = sum(
        1 for e in _ENTRIES
        if "[harness: measured" in correct_body_numbers(
            e["body"], name_status_text(e["diff"]), numstat_text(e["diff"])))
    assert before == _CORPUS["before_flagged"]

    after = 0
    for e in _ENTRIES:
        annotated, findings = annotate_test_count_mismatches(
            e["body"], e["diff"], e["head_files"].get)
        if not findings:
            continue
        after += 1
        for c in _checks(e):
            if c.verdict == "mismatch":
                assert f"{c.claimed} " in annotated
    assert after == _CORPUS["after_flagged"]
