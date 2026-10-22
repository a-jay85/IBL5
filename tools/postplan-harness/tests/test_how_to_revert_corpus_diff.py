"""Replay the How to revert field over the real merged-PR corpora.

Uses the same two committed corpora as test_merge_danger_corpus_diff.py:
  * pr-bodies-corpus.json: 50 real PR bodies, all written before the field existed.
  * merge-danger-paths-corpus.json: 100 merged PRs with their changed paths.
"""
import json
import os
import pathlib
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import how_to_revert as htr
from harness.merge_danger import FIELD_PREFIX as MD_PREFIX
from harness.merge_danger import MergeDanger, classify_body, inject_merge_danger

_FIXTURES = pathlib.Path(__file__).parent / "fixtures"

with open(_FIXTURES / "pr-bodies-corpus.json", encoding="utf-8") as _fh:
    _BODIES = json.load(_fh)
with open(_FIXTURES / "merge-danger-paths-corpus.json", encoding="utf-8") as _fh:
    _PATHS = json.load(_fh)

_FIXED = MergeDanger("two-way", "module", "mechanical: no one-way trigger in the realized diff")
_MIGRATION_RE = re.compile(r"^ibl5/migrations/.*\.sql$")


def _after_merge_danger(body):
    b1 = inject_merge_danger(body, _FIXED)
    return b1, htr.inject_how_to_revert(b1, htr.PLAIN + ".")


def test_corpus_has_fifty_bodies():
    assert len(_BODIES) == 50


@pytest.mark.parametrize("entry", _BODIES, ids=[str(e["number"]) for e in _BODIES])
def test_old_bodies_lack_how_to_revert_line(entry):
    assert htr.parse_how_to_revert(entry["body"]) is None
    assert htr.count_field_lines(entry["body"]) == 0


@pytest.mark.parametrize("entry", _BODIES, ids=[str(e["number"]) for e in _BODIES])
def test_injection_yields_exactly_one_line_after_merge_danger(entry):
    _, b2 = _after_merge_danger(entry["body"])
    lines = b2.split("\n")
    assert htr.count_field_lines(b2) == 1
    md_idx = next(i for i, ln in enumerate(lines) if ln.startswith(MD_PREFIX))
    assert lines[md_idx + 1].startswith(htr.FIELD_PREFIX)


@pytest.mark.parametrize("entry", _BODIES, ids=[str(e["number"]) for e in _BODIES])
def test_injection_is_idempotent_over_corpus(entry):
    _, b2 = _after_merge_danger(entry["body"])
    assert htr.inject_how_to_revert(b2, htr.PLAIN + ".") == b2


@pytest.mark.parametrize("entry", _BODIES, ids=[str(e["number"]) for e in _BODIES])
def test_merge_danger_classification_unchanged_by_injection(entry):
    b1, b2 = _after_merge_danger(entry["body"])
    assert classify_body(b2) == classify_body(b1)


def test_mechanical_default_over_paths_corpus():
    assert _PATHS["prs"]
    for e in _PATHS["prs"]:
        out = htr.default_how_to_revert(e["files"])
        assert "\n" not in out
        assert out.startswith(htr.PLAIN)
        has_migration = any(_MIGRATION_RE.match(f) for f in e["files"])
        assert (htr.STEP_MIGRATION in out) == has_migration
