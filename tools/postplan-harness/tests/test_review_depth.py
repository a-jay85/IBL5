"""Review depth from the Merge danger line. The shell twin is `bin/lib/review-depth.sh`; both
read tests/fixtures/review_depth_cases.json (see test_review_depth_parity.py)."""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.merge_danger import BLAST, DOORS
from harness.review_depth import classify_depth, depth_for_body, max_depth

CASES_PATH = os.path.join(os.path.dirname(__file__), "fixtures", "review_depth_cases.json")
with open(CASES_PATH, encoding="utf-8") as _f:
    CASES = json.load(_f)
BY_ID = {row["id"]: row for row in CASES}

_MALFORMED_IDS = ("unknown-door", "unknown-blast", "short-why", "two-lines", "fenced-plus-real",
                  "ascii-separator")


@pytest.mark.parametrize("row", CASES, ids=[r["id"] for r in CASES])
def test_depth_case_table(row):
    """Changing the MISSING verdict in classify_depth to standard turns [missing-line] red."""
    assert depth_for_body(row["body"]) == row["depth"]


def test_case_table_covers_every_axis_value():
    ids = set(BY_ID)
    for door in DOORS:
        for blast in BLAST:
            assert f"{door}-{blast}" in ids, f"no well-formed row for {door}/{blast}"
    assert "missing-line" in ids
    assert "unreadable" in ids
    assert ids & set(_MALFORMED_IDS)


def test_classify_depth_reasons():
    assert classify_depth(None) == ("deep", "UNREADABLE")
    assert classify_depth(BY_ID["missing-line"]["body"]) == ("deep", "MISSING")
    assert classify_depth(BY_ID["two-lines"]["body"]) == ("deep", "MALFORMED")
    assert classify_depth(BY_ID["one-way-localized"]["body"]) == ("deep", "one-way")
    assert classify_depth(BY_ID["two-way-external"]["body"]) == ("deep", "blast=external")
    assert classify_depth(BY_ID["two-way-module"]["body"]) == ("standard", "two-way/module")


def test_max_depth():
    """A max_depth that returns depths[0] fails the standard-then-deep case."""
    assert max_depth("standard", "standard") == "standard"
    assert max_depth("standard", "deep") == "deep"
    assert max_depth("deep", "standard") == "deep"
    assert max_depth("standard", "") == "deep"
    assert max_depth("standard", "Standard") == "deep"
    assert max_depth() == "deep"


_RD_SCRIPT = os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.abspath(__file__))))), "bin", "lib", "review-depth.sh")
_SG_BODY = ("## Summary\nx\n\n**Merge danger:** Door: two-way · Blast radius: localized · "
            "Self-governing: yes · Why: mechanical: arming-path:bin/lib/pr-armable.sh")
_SG_AFTER_WHY_BODY = ("## Summary\nx\n\n**Merge danger:** Door: two-way · Blast radius: localized · "
                      "Why: mechanical: arming-path:bin/lib/pr-armable.sh · Self-governing: yes")


def _shell_depth(body):
    import subprocess
    out = subprocess.run(["bash", _RD_SCRIPT], input=body, capture_output=True, text=True, check=True)
    return out.stdout.split()[0]


def test_review_depth_self_governing_is_deep():
    assert _shell_depth(_SG_BODY) == "deep"
    plain = _SG_BODY.replace(" · Self-governing: yes", "")
    assert _shell_depth(plain) == "standard"


def test_review_depth_py_self_governing_is_deep():
    assert classify_depth(_SG_BODY) == ("deep", "self-governing")
    assert depth_for_body(_SG_BODY.replace(" · Self-governing: yes", "")) == "standard"


def test_review_depth_misplaced_marker_is_deep_both_twins():
    assert _shell_depth(_SG_AFTER_WHY_BODY) == "deep"
    assert depth_for_body(_SG_AFTER_WHY_BODY) == "deep"
    assert classify_depth(_SG_AFTER_WHY_BODY) == ("deep", "self-governing")
