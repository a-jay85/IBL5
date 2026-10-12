"""Old-vs-new corpus diff for `_pair_justifications` (backlog#1442).

The synthetic differential always runs. It replays master's real derived transform
against a grid of resolver wordings and asserts the only verdict changes are reject to
accept on wordings that reproduce the adapted line. The scan over local run dirs runs
only with POSTPLAN_CORPUS_DIFF=1, the same opt-in as test_conflict_refusal_corpus_diff.
"""
import glob
import os
import re
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adaptations import Justification, NearMatch, Transform, _pair_justifications

_REAL = Transform("/tmp", '"$RL"')
ONE = NearMatch("bin/t", "run /tmp/b", 'run "$RL"/b', (_REAL,))
TWO = NearMatch("bin/t", "cp /tmp/a /tmp/b", 'cp "$RL"/a "$RL"/b', (_REAL,))
MULTI = NearMatch("bin/t", "cp /tmp/a OLDV", 'cp "$RL"/a NEWV',
                  (_REAL, Transform("OLDV", "NEWV")))
_WRONG_ADAPTED = 'run "$RL"/c'

# (label, match, old, new, old verdict, justification adapted override)
_GRID = [
    ("stripped", ONE, "/tmp", '"$RL"', True, None),
    ("wide", ONE, "/tmp/", '"$RL"/', False, None),
    ("wider-context", ONE, "run /tmp/", 'run "$RL"/', False, None),
    ("whole-line", ONE, "run /tmp/b", 'run "$RL"/b', False, None),
    ("wrong-old", ONE, "tmp", '"$RL"', False, None),
    ("wrong-new", ONE, "/tmp/", "$RL/", False, None),
    ("absent-old", ONE, "/var/", '"$RL"/', False, None),
    ("wrong-adapted", ONE, "/tmp/", '"$RL"/', False, _WRONG_ADAPTED),
    ("two-stripped", TWO, "/tmp", '"$RL"', True, None),
    ("two-wide", TWO, "/tmp/", '"$RL"/', False, None),
    ("partial-occurrence", TWO, "/tmp/a", '"$RL"/a', False, None),
    ("multi-single", MULTI, "/tmp", '"$RL"', True, None),
    ("multi-whole-line", MULTI, "cp /tmp/a OLDV", 'cp "$RL"/a NEWV', False, None),
]
_EXPECTED_FLIPS = {"wide", "wider-context", "whole-line", "two-wide", "multi-whole-line"}


def _justification(m, old, new, adapted):
    return Justification(m.path, m.original, adapted or m.adapted, old, new)


def _old_accepts(m, j):
    """Master's pairing predicate, restated verbatim."""
    return ((j.path, j.original, j.adapted) == (m.path, m.original, m.adapted)
            and Transform(j.old, j.new) in m.transforms)


def _new_accepts(m, j):
    return bool(_pair_justifications((m,), (j,))[0])


def test_differential_flips_only_same_line_wordings():
    flips = set()
    for label, m, old, new, _was, adapted in _GRID:
        j = _justification(m, old, new, adapted)
        if not _old_accepts(m, j) and _new_accepts(m, j):
            flips.add(label)
            assert j.old in m.original
            assert m.original.replace(j.old, j.new) == m.adapted
    assert flips == _EXPECTED_FLIPS


def test_differential_no_accept_becomes_reject():
    for label, m, old, new, was, adapted in _GRID:
        j = _justification(m, old, new, adapted)
        assert _old_accepts(m, j) is was, label
        assert not (_old_accepts(m, j) and not _new_accepts(m, j)), label


_ACCEPT_LINE = re.compile(
    r"^adapted line accepted: (?P<path>[^:]+): \+(?P<orig>.*) => \+(?P<adpt>.*) "
    r"\[(?P<old>.*) -> (?P<new>.*)\] \(master transformation")


@pytest.mark.skipif(os.environ.get("POSTPLAN_CORPUS_DIFF") != "1",
                    reason="opt-in corpus diff over local out/ runs")
def test_corpus_old_accepts_still_accept():
    logs = glob.glob(str(Path(__file__).resolve().parents[1] / "out" / "*" / "audit.log"))
    if not logs:
        pytest.skip("no local out/*/audit.log corpus")
    accepted = rejects = 0
    for log in logs:
        for raw in Path(log).read_text(errors="replace").splitlines():
            line = re.sub(r"^\[[^\]]*\]\s*", "", raw)
            if "no resolver justification for " in line:
                rejects += 1
                print(f"flip candidate: {log}: {line}")
            if not line.startswith("adapted line accepted: "):
                continue
            g = _ACCEPT_LINE.match(line)
            assert g, f"unparsable accept line in {log}: {line}"
            m = NearMatch(g["path"], g["orig"], g["adpt"], (Transform(g["old"], g["new"]),))
            j = Justification(g["path"], g["orig"], g["adpt"], g["old"], g["new"])
            assert _new_accepts(m, j), f"recorded accept now rejects in {log}: {line}"
            accepted += 1
    print(f"corpus: dirs={len(logs)} accepted={accepted} reject_candidates={rejects}")
