"""Phase 5.5 carry-forward predicate and supporting helpers.

Tests are ordered by verification-matrix row. The _sticky() helper builds fixtures
through the real compose_sticky + terminal_line so the parser and writer cannot drift
apart — a future edit to either side that breaks the contract fails these tests instead
of silently disabling carry-forward.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

TREE = "a" * 40
DIFF_ID = "e" * 40
PLAN_HASH = "b" * 64


def _sticky(verdict="READY", diff_id=DIFF_ID, plan_hash=PLAN_HASH, tree=TREE, **fid_extra):
    """Build a sticky body through the real composer, so the parser and the writer
    can never drift apart in this test file."""
    fid = {"verdict_1": verdict, "reviewed_tree": tree, "error_kind": None, **fid_extra}
    return fidelity.compose_sticky(
        "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
        fidelity.terminal_line(verdict, None, None, None, None, 0),
        diff_id=diff_id, plan_hash=plan_hash)


# --- row 1: full match --------------------------------------------------------

def test_full_match_carries_the_prior_ready_verdict():
    assert fidelity.carry_forward_predicate(_sticky(), DIFF_ID, PLAN_HASH) == ("READY", "")
