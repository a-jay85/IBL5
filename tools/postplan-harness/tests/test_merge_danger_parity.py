"""Held-set parity for condition (18) across the shell and Python twins.

LEGACY_BODIES holds lines the pre-self-governing code rendered (pasted from a master run, never
typed from source). The held set they pin is the bar the new code must meet: every row held
before is held after, in both `pr_merge_danger_hold` and `armable.evaluate`.
"""
import os
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import merge_danger
from harness.armable import evaluate
from test_armable import inputs

REPO = Path(__file__).resolve().parents[3]

_PFX = "**Merge danger:** "
_L1 = _PFX + "Door: two-way · Blast radius: localized · Why: mechanical: no one-way trigger in the realized diff"
_L2 = _PFX + "Door: one-way · Blast radius: site-wide · Why: mechanical: arming-path:bin/lib/pr-armable.sh"

LEGACY_BODIES = [
    ("L1", "## Summary\nx\n\n" + _L1, False),
    ("L2", "## Summary\nx\n\n" + _L2, True),
    ("L3", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: gate-removed:bin/check-foo", True),
    ("L4", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: "
       "destructive-migration:ibl5/migrations/200_drop_col.sql", True),
    ("L5", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: external · Why: mechanical: external-path:bin/db-sync-prod", True),
    ("L6", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: external · Why: mechanical: "
       "notify-workflow:.github/workflows/nightly.yml", True),
    ("L7", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: "
       "destructive-migration:ibl5/migrations/200_drop_col.sql; arming-path:bin/lib/pr-armable.sh", True),
    ("L8", "## Summary\nx\n\nno merge danger line here\n", True),
    ("L9", "## Summary\nx\n\n" + _L1 + "\n" + _L1, True),
    ("L10", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: arming-path:bin/lib/pr-armable.sh. "
       "edits the arming gate itself, so the PR governs its own merge", True),
]


def _shell_token(body):
    return subprocess.run(
        ["bash", "-c", 'source "$1/bin/lib/pr-armable.sh" && pr_merge_danger_hold "$2"', "_", str(REPO), body],
        capture_output=True, text=True, check=True).stdout.strip()


def _shell_state(body):
    tok = _shell_token(body)
    return "TWO-WAY" if tok == "" else tok.split("merge-danger:", 1)[1]


def _python_held(state):
    return any(c.number == 18 for c in evaluate(inputs(merge_danger_state=state)).holds)


def test_legacy_held_set_parity_both_twins():
    for label, body, held_before in LEGACY_BODIES:
        assert _python_held(merge_danger.classify_body(body)) == held_before, label
        assert (_shell_token(body) != "") == held_before, label
