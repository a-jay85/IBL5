"""bin/owner-approve's kept-scope fingerprint matches what harness/fidelity.py renders."""
import re
import subprocess
from pathlib import Path

from harness import fidelity

ROOT = Path(__file__).resolve().parents[3]
SRC = (ROOT / "bin" / "owner-approve").read_text()
HELD_PRS = ROOT / "bin" / "lib" / "held-prs.sh"

ITEMS = [
    {"title": "Add the roster export", "quote": "add the export too", "source": "owner-approval"},
    {"title": "Rename the export button", "quote": "call it Download", "source": "owner-approval"},
]


def _const(name):
    m = re.search(rf"^{name}='([^']*)'$", SRC, re.M)
    assert m, f"{name} is missing from bin/owner-approve"
    return m.group(1)


def _script():
    fn = re.search(r"^_oa_kept_scope_fingerprint\(\) \{.*?^\}$", SRC, re.M | re.S)
    assert fn, "_oa_kept_scope_fingerprint is missing from bin/owner-approve"
    consts = "".join(f"{n}='{_const(n)}'\n" for n in ("OA_KEPT_SCOPE_HEADING", "OA_KEPT_SCOPE_PHRASE"))
    return consts + fn.group(0)


def _fingerprint_rc(body, word):
    return subprocess.run(
        ["bash", "-c", 'source "$0" && eval "$1" && _oa_kept_scope_fingerprint "$2" "$3"',
         str(HELD_PRS), _script(), body, word],
        capture_output=True, text=True, timeout=60, check=False).returncode


def _fid(word):
    return {"posted_verdict": word, "kept_owner_scope": ITEMS}


def _sticky(word, middle, last):
    lines = [f"**{word}** (re-review, round 1) — 2026-11-24 · auto-merge held", "",
             *middle,
             f"**Re-reviewed tree:** {'a' * 40} (NOT READY)", "",
             last,
             "<!-- pr-ready-verdict -->"]
    return "\n".join(lines)


def test_phrase_matches_renderer():
    phrase = _const("OA_KEPT_SCOPE_PHRASE")
    for w in ("READY", "READY WITH NOTES"):
        assert fidelity.owner_scope_terminal(_fid(w)) == f"{w} — {phrase}"


def test_heading_matches_renderer():
    assert _const("OA_KEPT_SCOPE_HEADING") == fidelity.KEPT_SCOPE_HEADING


def test_shell_accepts_rendered_sticky():
    word = "READY WITH NOTES"
    middle = [fidelity.KEPT_SCOPE_HEADING, *map(fidelity._kept_scope_line, ITEMS), ""]
    body = _sticky(word, middle, fidelity.owner_scope_terminal(_fid(word)))
    assert _fingerprint_rc(body, word) == 0


def test_shell_refuses_phrase_spoofed_mid_body():
    word = "READY WITH NOTES"
    terminal = fidelity.owner_scope_terminal(_fid(word))
    spoofed = [dict(ITEMS[0], quote=terminal), ITEMS[1]]
    middle = [fidelity.KEPT_SCOPE_HEADING, *map(fidelity._kept_scope_line, spoofed), "",
              terminal, ""]
    body = _sticky(word, middle, "Reviewer: opus")
    assert _fingerprint_rc(body, word) == 1
