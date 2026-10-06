"""Tests for the pr-copy JSON recovery path.

Sections:
  1. Characterization of the shared extract_json against real raw pr-copy replies
  2. extract_pr_copy_json unit tests
  3. End-to-end through the real ClaudeCli.call with a bash shim
"""
import os
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import schemas
from harness.adapters.llm import ClaudeCli, extract_json
from harness.schemas import COMMIT_TYPES, normalize_pr_copy, validate_pr_copy
from harness.state import Classification, HarnessError, UsageLedger

FIXTURES = os.path.join(os.path.dirname(__file__), "fixtures")

# label -> (attempt, today's outcome via extract_json+normalize+validate)
STILL_FAILING = [
    ("unescaped-quote-mixed", 0), ("unescaped-quote", 0), ("unescaped-quote", 1),
    ("nested-fence", 0), ("nested-fence", 1),
    ("prose-backticks", 0), ("prose-backticks", 1),
]
RECOVERS_TODAY = [
    ("type-mismatch-pin", 0), ("type-mismatch-pin", 1),
    ("type-mismatch-ci", 0), ("type-mismatch-ci", 1),
    ("unescaped-quote-mixed", 1),
]


def _raw(label, attempt):
    path = os.path.join(FIXTURES, f"raw-pr-copy-{label}-attempt{attempt}.txt")
    with open(path) as fh:
        return fh.read()


def _raw_type(raw):
    return re.search(r'"type"\s*:\s*"([a-z]+)"', raw).group(1)


# ---------------------------------------------------------------------------
# 1. Characterization (shared parser, normalizer and validator are not edited)
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_characterize_extract_json_rejects_still_failing_raws(label, attempt):
    with pytest.raises(ValueError, match="no parseable JSON"):
        extract_json(_raw(label, attempt))


@pytest.mark.parametrize("label,attempt", RECOVERS_TODAY)
def test_characterize_mismatch_raws_recover_today(label, attempt):
    d = normalize_pr_copy(extract_json(_raw(label, attempt)))
    validate_pr_copy(d)
    assert d["title"].startswith(d["type"])


def test_characterize_mixed_attempt1_normalizes_to_feat():
    d = normalize_pr_copy(extract_json(_raw("unescaped-quote-mixed", 1)))
    assert d["type"] == "feat"


def test_pr_copy_fixture_set_complete():
    pairs = STILL_FAILING + RECOVERS_TODAY
    assert len(set(pairs)) == 12
    for label, attempt in pairs:
        path = os.path.join(FIXTURES, f"raw-pr-copy-{label}-attempt{attempt}.txt")
        assert os.path.isfile(path), path


# ---------------------------------------------------------------------------
# 2. extract_pr_copy_json unit tests
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_extractor_recovers_still_failing_raw(label, attempt):
    raw = _raw(label, attempt)
    d = schemas.extract_pr_copy_json(raw)
    assert d["type"] == _raw_type(raw)
    validate_pr_copy(normalize_pr_copy(d))


@pytest.mark.parametrize("label,attempt", RECOVERS_TODAY)
def test_extractor_matches_extract_json_when_it_parses(label, attempt):
    raw = _raw(label, attempt)
    assert schemas.extract_pr_copy_json(raw) == extract_json(raw)


def test_extractor_salvage_keeps_feat():
    reply = ('{"type": "feat", "title": "feat: add x", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- says "hi" here"}')
    d = schemas.extract_pr_copy_json(reply)
    assert d["type"] == "feat"
    assert d["title"] == "feat: add x"
    assert d["commit_subject"] == "feat: add x"
    assert 'says "hi" here' in d["summary_md"]


def test_extractor_salvage_refuses_quote_in_short_field():
    reply = ('{"type": "feat", "title": "feat: add "x" now", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- a"}')
    with pytest.raises(ValueError):
        schemas.extract_pr_copy_json(reply)


def test_extractor_salvage_refuses_duplicate_anchor():
    reply = ('{"type": "feat", "title": "feat: add x", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- he said "title": "x" oddly"}')
    with pytest.raises(ValueError):
        schemas.extract_pr_copy_json(reply)


@pytest.mark.parametrize("reply", [
    "I can't produce that.",
    '{"type": "fix", "title": "fix: a',
    "",
], ids=["prose-only", "truncated", "empty"])
def test_extractor_raises_with_hint(reply):
    with pytest.raises(ValueError) as e:
        schemas.extract_pr_copy_json(reply)
    assert "summary_md" in str(e.value)
    assert "pr-copy reply" in str(e.value)


def test_extractor_does_not_unwrap_envelope():
    reply = ('{"pr_copy": {"type": "fix", "title": "fix: a", "commit_subject": "fix: a", '
             '"summary_md": "s"}}')
    d = schemas.extract_pr_copy_json(reply)
    assert list(d) == ["pr_copy"]
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(d)
    assert e.value.kind == "schema"
