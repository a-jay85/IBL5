"""Unit tests for harness/body_notes.py: fixing PR-body notes on READY WITH NOTES.

The first three tests characterize behavior this PR relies on. They pass on master.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, reviewcache


def test_terminal_line_bare_for_ready_with_notes():
    assert fidelity.terminal_line("READY WITH NOTES", None, None, None, None, 0) \
        == "READY WITH NOTES"


def test_sticky_prior_verdict_accepts_body_notes_rows():
    terminals = [
        "READY WITH NOTES — all notes remediated in the PR body; reviewer verdict covers "
        "tree 0123456789abcdef0123456789abcdef01234567, not the post-remediation head",
        "READY WITH NOTES — Verification Matrix row 11 is still not done "
        "(left for the human); remediated the rest in the PR body",
        "READY WITH NOTES — Add the Phase 1 red-half failure output to the PR body "
        "(body fix reverted: recheck-unresolved)",
    ]
    for terminal in terminals:
        body = "header line\n\n" + terminal + "\n" + fidelity.STICKY_MARKER
        assert fidelity._sticky_prior_verdict(body) == "READY WITH NOTES"
        assert fidelity._RE_REVIEW_TERMINAL_RE.match(terminal) is None


def test_cache_arm_rejects_body_only_remediation():
    fid = {"diff_id": "a" * 40, "plan_hash": "b" * 64, "verdict_1": "READY WITH NOTES",
           "verdict_2": None, "rounds": [], "rounds_completed": 0,
           "remediation_sha": None}
    assert reviewcache.vet_fidelity(dict(fid), "run-1") is not None
    assert reviewcache.vet_fidelity({**fid, "remediation_sha": "body-only"}, "run-1") is None
