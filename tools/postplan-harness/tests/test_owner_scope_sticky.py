"""The sticky shows the posted word and the kept new scope the owner asked for."""
from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

REPO = Path(__file__).resolve().parents[3]
HELD_PRS = REPO / "bin" / "lib" / "held-prs.sh"
TITLE = "Adds a json flag to bin/foo"
QUOTE = "please also add a json flag to bin/foo"


def _fid(word="READY", **extra):
    fid = {"verdict_1": "NOT READY",
           "kept_owner_scope": [{"title": TITLE, "quote": QUOTE}]}
    if word is not None:
        fid["posted_verdict"] = word
    fid.update(extra)
    return fid


def _sticky(fid):
    return fidelity.compose_sticky("", "", fid, None, [], "", fidelity.sticky_terminal(fid))


def _banner(sticky):
    out = subprocess.run(["bash", "-c", 'source "$1" && hp_verdict_banner "$2"', "_",
                          str(HELD_PRS), sticky],
                         capture_output=True, text=True, check=True).stdout
    return out.replace("*", "").strip()


def test_owner_scope_sticky_banner_parses_ready():
    sticky = _sticky(_fid("READY"))
    assert sticky.splitlines()[0].startswith("**READY**")
    banner = _banner(sticky)
    assert banner.startswith("READY ")
    assert not banner.startswith("NOT READY ")
    assert not banner.startswith("READY WITH NOTES ")

    notes = _sticky(_fid("READY WITH NOTES"))
    assert notes.splitlines()[0].startswith("**READY WITH NOTES**")
    assert _banner(notes).startswith("READY WITH NOTES ")


def test_owner_scope_sticky_lists_kept_scope():
    sticky = _sticky(_fid("READY"))
    lines = sticky.splitlines()
    assert fidelity.KEPT_SCOPE_HEADING in lines
    assert lines.index(fidelity.KEPT_SCOPE_HEADING) < lines.index(fidelity.MERGE_DIGEST_HEADING)
    assert f"- `{TITLE}` (you said: `{QUOTE}`)" in lines
    assert "Kept new scope" in fidelity.sticky_terminal(_fid("READY"))


def test_sticky_terminal_without_posted_word_is_unchanged():
    fid = _fid(None, verdict_2=None, reviewed_tree_2=None, rounds_completed=0)
    assert fidelity.sticky_terminal(fid) == fidelity.terminal_line(
        "NOT READY", None, None, None, None, 0)
    assert fidelity.owner_scope_terminal(fid) == ""
    sticky = _sticky(fid)
    assert fidelity.KEPT_SCOPE_HEADING not in sticky
    assert sticky.splitlines()[0].startswith("**NOT READY**")
    assert fidelity.owner_scope_terminal({"posted_verdict": "READY"}) == ""
