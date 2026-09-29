"""Corpus diff for restore_manual_testing_section (backlog#1188).

Runs the pre-#1188 restore (frozen below as _restore_v1) and the live one over
real merged PR bodies and asserts the arming gate's window (armable._manual_section)
and verdict are unchanged by every append shape. Bodies come from
tests/fixtures/pr-bodies-corpus.json, captured with
`gh pr list -R a-jay85/IBL5 --state merged --limit 50 --json number,body`.
Set PR_BODY_CORPUS=<path> to run the same assertions against a fresh capture.
"""
import json
import os

import pytest

from harness.armable import _manual_section, manual_testing_clearance
from harness.classify import (MANUAL_TESTING_SENTINEL, _MANUAL_HEADING_RE,
                              _NEXT_HEADING_RE, _manual_testing_span,
                              restore_manual_testing_section)

FIXTURE = os.environ.get("PR_BODY_CORPUS") or os.path.join(
    os.path.dirname(__file__), "fixtures", "pr-bodies-corpus.json")


def _load() -> list[tuple[int, str]]:
    with open(FIXTURE, encoding="utf-8") as fh:
        rows = json.load(fh)
    return [(r["number"], r["body"]) for r in rows if r.get("body")]


CORPUS = _load()
IDS = [f"pr{n}" for n, _ in CORPUS]


def _is_last_position(body: str) -> bool:
    span = _manual_testing_span(body)
    return span is not None and span[1] == len(body)


LAST = [(n, b) for n, b in CORPUS if _is_last_position(b)]
LAST_IDS = [f"pr{n}" for n, _ in LAST]


def _restore_v1(after: str, before: str) -> tuple[str, bool]:
    """Frozen copy of restore_manual_testing_section as merged in PR #2493:
    span end at any `^#{1,6}\\s` line, no relocation. Kept here as the OLD parse
    for the corpus diff; do not update it when the live function changes."""
    def span(body):
        m = _MANUAL_HEADING_RE.search(body)
        if not m:
            return None
        n = _NEXT_HEADING_RE.search(body[m.end():])
        return m.start(), (m.end() + n.start() if n else len(body))
    b = span(before)
    if b is None:
        return after, False
    section = before[b[0]:b[1]]
    a = span(after)
    if a is None:
        return after.rstrip("\n") + "\n\n" + section.rstrip("\n") + "\n", True
    if after[a[0]:a[1]] == section:
        return after, False
    return after[:a[0]] + section + after[a[1]:], True


SHAPES = {
    "evidence": "\nEvidence: `bin/test-pr-cycle` ran green.\n",
    "closes": "\nCloses a-jay85/IBL5-backlog#1188\n",
    "sentinel": f"\n{MANUAL_TESTING_SENTINEL}\n",
    "ticked-row": "\n- [x] **Row 9** — bar\n",
    "unticked-row": "\n- [ ] **Row 9** — bar\n",
    "counterfeit-nospace": f"\n##Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n",
    "counterfeit-level3": f"\n### Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n",
    "counterfeit-level2": f"\n## Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n",
}


def test_corpus_is_representative():
    assert len(CORPUS) >= 20, FIXTURE
    assert all(_manual_section(b) is not None for _, b in CORPUS)
    assert len(LAST) >= 10


@pytest.mark.parametrize("number,body", CORPUS, ids=IDS)
def test_untouched_body_is_a_noop_for_old_and_new(number, body):
    assert restore_manual_testing_section(body, body) == (body, False)
    assert _restore_v1(body, body) == (body, False)


@pytest.mark.parametrize("shape", list(SHAPES))
@pytest.mark.parametrize("number,body", CORPUS, ids=IDS)
def test_gate_window_and_verdict_unchanged_by_append(number, body, shape):
    new, _ = restore_manual_testing_section(body + SHAPES[shape], body)
    assert _manual_section(new) == _manual_section(body)
    assert manual_testing_clearance(new) == manual_testing_clearance(body)
    b = _manual_testing_span(body)
    r = _manual_testing_span(new)
    assert new[r[0]:r[1]].rstrip("\n") == body[b[0]:b[1]].rstrip("\n")


@pytest.mark.parametrize("shape", ["evidence", "closes"])
@pytest.mark.parametrize("number,body", LAST, ids=LAST_IDS)
def test_old_dropped_the_append_and_new_keeps_it_above_heading(number, body, shape):
    marker = SHAPES[shape].strip()
    old, _ = _restore_v1(body + SHAPES[shape], body)
    new, _ = restore_manual_testing_section(body + SHAPES[shape], body)
    assert marker not in old
    assert marker in new
    assert new.index(marker) < _manual_testing_span(new)[0]


@pytest.mark.parametrize("number,body", LAST, ids=LAST_IDS)
def test_old_let_a_level3_counterfeit_into_the_gate_window(number, body):
    # The closed gap: the OLD span ended at the `### ` line, so the old restore
    # was a no-op and the counterfeit stayed inside the gate's `^## `-bounded window.
    old, _ = _restore_v1(body + SHAPES["counterfeit-level3"], body)
    new, _ = restore_manual_testing_section(body + SHAPES["counterfeit-level3"], body)
    assert _manual_section(old) != _manual_section(body)
    assert _manual_section(new) == _manual_section(body)
