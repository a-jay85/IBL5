"""Phase 5.5 body-only round detection: helper unit tests and end-to-end cases."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.classify import FILES_CHANGED_BEGIN, FILES_CHANGED_END


# ---------------------------------------------------------------------------
# _body_signature unit tests (matrix rows 1-4)
# ---------------------------------------------------------------------------

BEGIN = FILES_CHANGED_BEGIN
END = FILES_CHANGED_END

_BLOCK_V1 = f"{BEGIN}\n- file_a.py\n{END}"
_BLOCK_V2 = f"{BEGIN}\n- file_a.py\n- file_b.py\n{END}"


def test_body_signature_ignores_the_files_changed_block():
    body_a = f"## Summary\n\nSome prose.\n\n{_BLOCK_V1}\n"
    body_b = f"## Summary\n\nSome prose.\n\n{_BLOCK_V2}\n"
    assert runner._body_signature(body_a) == runner._body_signature(body_b)


def test_body_signature_sees_prose_edits_outside_the_block():
    body_a = f"## Summary\n\nOriginal prose.\n\n{_BLOCK_V1}\n"
    body_b = f"## Summary\n\nOriginal prose.\n\nExtra paragraph added.\n\n{_BLOCK_V1}\n"
    assert runner._body_signature(body_a) != runner._body_signature(body_b)


def test_body_signature_normalizes_absent_and_blank_bodies():
    assert runner._body_signature(None) == ""
    assert runner._body_signature("") == ""
    assert runner._body_signature("   \n\n ") == ""


def test_body_signature_leaves_an_unbalanced_marker_pair_intact():
    # BEGIN with no END
    body_begin_only = f"Some text {BEGIN} more text"
    assert runner._body_signature(body_begin_only) == body_begin_only.strip()

    # END before BEGIN (reversed order)
    body_reversed = f"Some text {END} middle {BEGIN} end"
    assert runner._body_signature(body_reversed) == body_reversed.strip()


# ---------------------------------------------------------------------------
# Constants pin (matrix row 5)
# ---------------------------------------------------------------------------

def test_body_outcomes_are_not_transient():
    assert runner.BODY_ONLY_SHA == "body-only"
    assert runner.BODY_ONLY_SHA not in runner._TRANSIENT_ROUND_REASONS
    assert runner.BODY_FETCH_FAILED_REASON not in runner._TRANSIENT_ROUND_REASONS
    assert "no-edits" in runner._TRANSIENT_ROUND_REASONS   # unchanged by this plan
