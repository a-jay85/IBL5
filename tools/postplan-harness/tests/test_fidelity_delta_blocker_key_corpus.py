"""Corpus check for anchor blocker keying in the fidelity delta re-review.

Runs only with `POSTPLAN_CORPUS_DIFF=1` and a local verdict corpus under
`tools/postplan-harness/out/` (gitignored), so it skips in CI. It applies
`fidelity._verdict_findings` to every recorded NOT READY verdict, the text a delta would
receive as its anchor findings, and checks that keying never changes the blocker count the
frozen regex gives, that keying is line-exact, and that the old word-count forgery is
accepted by the frozen check and declined by `match_restatements`. Asserts invariants
only, because the corpus grows with every harness run.

Env contract. `POSTPLAN_CORPUS_DIFF` is opt-in: unset and empty both skip, which is legal.
`POSTPLAN_OUT_DIR` has a safe default: unset and empty both fall back to
`tools/postplan-harness/out/`, which is legal.
"""
from __future__ import annotations

import glob
import os
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, fidelity_delta  # noqa: E402

# Frozen copies as of the pre-#1692 delta check. Do not import them.
_FROZEN_ANCHOR_BLOCKING_RE = re.compile(r"^.*\[BLOCKING\]", re.M)
_FROZEN_RESTATEMENT_RE = re.compile(r"\b(?:UN)?RESOLVED\b")


def _out_dir():
    return os.environ.get("POSTPLAN_OUT_DIR") or os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "out"
    )


def _corpus_paths():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local verdict corpus")
    out_dir = _out_dir()
    if not os.path.isdir(out_dir):
        pytest.skip(f"SKIP: no verdict corpus at {out_dir}")
    found = set(glob.glob(os.path.join(out_dir, "*", "raw-plan-fidelity-review-attempt0.txt")))
    found |= set(
        glob.glob(os.path.join(out_dir, "*", "raw-plan-fidelity-re-review-*-attempt0.txt"))
    )
    paths = [p for p in sorted(found) if fidelity.parse_verdict(p) == "NOT READY"]
    if not paths:
        pytest.skip(f"SKIP: no NOT READY verdicts under {out_dir}")
    return paths


def test_blocker_keys_match_frozen_count_over_corpus():
    verdicts = zero = total = biggest = forgeries = 0
    failures = []
    for p in _corpus_paths():
        name = os.path.basename(os.path.dirname(p)) + "/" + os.path.basename(p)
        findings = "\n".join(fidelity._verdict_findings(p))
        n_old = len(_FROZEN_ANCHOR_BLOCKING_RE.findall(findings))
        verdicts += 1
        total += n_old
        biggest = max(biggest, n_old)
        keyed, n = fidelity_delta.keyed_anchor_findings(findings)
        if n != n_old or fidelity_delta.anchor_blocker_count(findings) != n_old:
            failures.append(f"{name}: key count {n} != frozen count {n_old}")
        if len(_FROZEN_ANCHOR_BLOCKING_RE.findall(keyed)) != n_old:
            failures.append(f"{name}: keying moved the frozen blocker count")
        before, after = findings.split("\n"), keyed.split("\n")
        if len(before) != len(after):
            failures.append(f"{name}: keying changed the line count")
        else:
            k = 0
            for old_line, new_line in zip(before, after):
                if _FROZEN_ANCHOR_BLOCKING_RE.match(old_line):
                    k += 1
                    expected = f"[B{k}] " + old_line
                else:
                    expected = old_line
                if new_line != expected:
                    failures.append(f"{name}: line not keyed exactly: {old_line!r}")
                    break
        if n_old == 0:
            zero += 1
            continue
        keys = [f"[B{k}] RESOLVED by hunk {k}" for k in range(1, n_old + 1)]
        good = fidelity_delta.match_restatements("\n".join(keys), n_old)
        if good != {k: "RESOLVED" for k in range(1, n_old + 1)}:
            failures.append(f"{name}: well-formed keyed reply did not parse")
        forgery = "- [BLOCKING] x.py — " + ", ".join(["RESOLVED"] * n_old)
        old_accepts = len(_FROZEN_RESTATEMENT_RE.findall(forgery)) >= n_old
        new_declines = fidelity_delta.match_restatements(forgery, n_old) is None
        if old_accepts and new_declines:
            forgeries += 1
        else:
            failures.append(f"{name}: forgery old_accepts={old_accepts} new_declines={new_declines}")
        if fidelity_delta.match_restatements("\n".join(keys[:-1]), n_old) is not None:
            failures.append(f"{name}: reply missing its last key was accepted")
    print(f"blocker-key corpus: {verdicts} NOT READY verdicts, {zero} with 0 blockers, "
          f"{total} blockers total, max {biggest} per verdict, "
          f"{forgeries} forgeries old-accepted/new-declined")
    assert not failures, failures
