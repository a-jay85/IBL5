"""Replay the merge-danger field over real merged-PR bodies and paths.

Two committed corpora under tests/fixtures/:
  * pr-bodies-corpus.json: 50 real PR bodies (a list of {number, body}); exercises the body regex
    and its shell twin `pr_merge_danger_hold` in bin/lib/pr-armable.sh.
  * merge-danger-paths-corpus.json: the 100 newest merged PRs as of 2026-10-11 with their changed
    paths; exercises the path-derived door and blast radius through the pure
    `merge_danger.derive_merge_danger`.
"""
import json
import os
import pathlib
import shutil
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger
from harness.gate_backtest import detect_gate_changes
from harness.merge_danger import BLAST, MergeDanger, classify_body, derive_merge_danger, inject_merge_danger

_FIXTURES = pathlib.Path(__file__).parent / "fixtures"
_REPO_ROOT = pathlib.Path(__file__).parents[3]

with open(_FIXTURES / "pr-bodies-corpus.json", encoding="utf-8") as _fh:
    _BODIES = json.load(_fh)
with open(_FIXTURES / "merge-danger-paths-corpus.json", encoding="utf-8") as _fh:
    _PATHS = json.load(_fh)

_FIXED = MergeDanger("two-way", "module", "mechanical: no one-way trigger in the realized diff")
_ONE_WAY_LINE = ("**Merge danger:** Door: one-way · Blast radius: site-wide · "
                 "Why: mechanical: arming-path:bin/lib/pr-armable.sh")
_TWO_LINES = _ONE_WAY_LINE + "\n" + _ONE_WAY_LINE


def _read_candidate(path):
    p = _REPO_ROOT / path
    return p.read_text(encoding="utf-8", errors="replace") if p.is_file() else None


def _check_sources():
    bin_dir = _REPO_ROOT / "bin"
    return {f"bin/{p.name}": p.read_text(encoding="utf-8", errors="replace")
            for p in sorted(bin_dir.glob("check-*")) if p.is_file()}


_CHECK_SOURCES = _check_sources()


def _derive(entry):
    changed = [("M", p) for p in entry["files"]]
    gates = detect_gate_changes(changed, _read_candidate, _CHECK_SOURCES)
    return derive_merge_danger(changed, "", gates, _read_candidate), gates


_DERIVED = {e["number"]: _derive(e) for e in _PATHS["prs"]}
_GATE_SURFACE = (".github/workflows/", "bin/check-", "bin/lib/", ".claude/hooks/",
                 "tools/postplan-harness/harness/")


def _shell_token(body):
    result = subprocess.run(
        ["bash", "-c", 'source bin/lib/pr-armable.sh; pr_merge_danger_hold "$1"', "_", body],
        cwd=_REPO_ROOT, capture_output=True, text=True, check=True)
    out = result.stdout.strip()
    if not out:
        return "TWO-WAY"
    assert out.startswith("merge-danger:"), out
    return out.split(":", 1)[1]


def test_body_corpus_before_has_no_field_and_no_false_malformed():
    assert len(_BODIES) == 50
    tokens = [classify_body(e["body"]) for e in _BODIES]
    assert tokens.count("MISSING") == 50, [
        (e["number"], t) for e, t in zip(_BODIES, tokens) if t != "MISSING"]
    assert tokens.count("MALFORMED") == 0


def test_body_corpus_after_injection_all_well_formed_and_idempotent():
    for e in _BODIES:
        once = inject_merge_danger(e["body"], _FIXED)
        assert classify_body(once) == "TWO-WAY", e["number"]
        assert inject_merge_danger(once, _FIXED) == once, e["number"]


@pytest.mark.skipif(shutil.which("bash") is None,
                    reason="bash is absent: the shell twin pr_merge_danger_hold cannot be run")
def test_shell_twin_parity_on_body_corpus():
    pairs = 0
    for e in _BODIES:
        for body in (e["body"], inject_merge_danger(e["body"], _FIXED)):
            assert _shell_token(body) == classify_body(body), e["number"]
            pairs += 1
    assert pairs == 100
    assert _shell_token(_ONE_WAY_LINE) == classify_body(_ONE_WAY_LINE) == "ONE-WAY"
    assert _shell_token(_TWO_LINES) == classify_body(_TWO_LINES) == "MALFORMED"


def test_path_corpus_one_way_flip_set():
    assert len(_PATHS["prs"]) == 100
    got = {n for n, (md, _) in _DERIVED.items() if md.door == "one-way"}
    want = set(_PATHS["expected_one_way"])
    assert got == want, f"measured one-way set {sorted(got)} != expected {sorted(want)}"
    for n in want:
        triggers = _DERIVED[n][0].triggers
        assert triggers, n
        assert any(t.startswith(("arming-path:", "notify-workflow:")) for t in triggers), (n, triggers)


def test_path_corpus_broad_rule_would_hold_a_third():
    # The rejected rule "every gate or workflow edit is one-way" would have held about a third of
    # recent merges (38 of 100 on the pinned window). Gate edits stay two-way so the field keeps
    # its signal. A gate edit is any detected gate change or a path on the ship-pipeline gate
    # surface; workflows and gate_changes alone count only 16, too narrow for the rule's sense.
    broad = [e["number"] for e in _PATHS["prs"]
             if _DERIVED[e["number"]][1]
             or any(p.startswith(_GATE_SURFACE) for p in e["files"])]
    assert len(broad) >= 30, f"broad rule would hold only {len(broad)} of 100"


def test_path_corpus_blast_radius_distribution():
    blasts = [md.blast for md, _ in _DERIVED.values()]
    assert all(b in BLAST for b in blasts)
    assert "localized" in blasts
    assert "site-wide" in blasts
