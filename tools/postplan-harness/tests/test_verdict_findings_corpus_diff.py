"""Corpus diff for the hold (12) work list (backlog#929).

Runs only with `POSTPLAN_CORPUS_DIFF=1` and a local verdict corpus under
`tools/postplan-harness/out/` (gitignored), so it skips in CI. It compares the
section-scoped `fidelity._verdict_findings` against a frozen copy of the pre-change
extractor over every recorded NOT READY verdict, and asserts invariants rather than
totals because the corpus grows with every harness run.
"""
from __future__ import annotations

import glob
import os
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity  # noqa: E402

_LEGACY_VERDICT_RE = re.compile(r"^(READY WITH NOTES|NOT READY|READY)[ \t]*$", re.M)
_LEGACY_DIGEST_CUT = "## DIGEST"
_LEGACY_BULLET_RE = re.compile(r"^\s*(?:[-*]|\d+[.)])\s+\S")

_DISPOSITION_HEAD_RE = re.compile(
    r"^(passes|passed|not assessable|no finding|does not fire|skipped)\b", re.I
)


def _legacy_verdict_findings(path):
    if fidelity.parse_verdict(path) != "NOT READY":
        return []
    with open(path, encoding="utf-8", errors="replace") as fh:
        lines = fh.read().splitlines()
    for i, line in enumerate(lines):
        if line.strip() == _LEGACY_DIGEST_CUT:
            lines = lines[:i]
            break
    body = [ln for ln in lines if not _LEGACY_VERDICT_RE.match(ln)]
    bullets = [ln.strip() for ln in body if _LEGACY_BULLET_RE.match(ln)]
    if bullets:
        return bullets
    whole = "\n".join(body).strip()
    return [whole] if whole else []


def _corpus_paths():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local verdict corpus")
    out_dir = os.environ.get("POSTPLAN_OUT_DIR") or os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "out"
    )
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


def _has_heading(path):
    with open(path, encoding="utf-8", errors="replace") as fh:
        lines = fh.read().splitlines()
    for i, line in enumerate(lines):
        if line.strip() == _LEGACY_DIGEST_CUT:
            lines = lines[:i]
            break
    return any(ln.rstrip() == fidelity.FINDINGS_HEADING for ln in lines)


def _normalized_head(item):
    return re.sub(r"^\s*(?:[-*]|\d+[.)])\s+", "", item).replace("**", "").lstrip()


def test_verdict_findings_corpus_invariants():
    before = after = verdicts = heading = legacy = 0
    not_subset, went_zero, kept_disposition, legacy_changed = [], [], [], []
    for p in _corpus_paths():
        old = _legacy_verdict_findings(p)
        new = fidelity._verdict_findings(p)
        h = _has_heading(p)
        before += len(old)
        after += len(new)
        heading += h
        legacy += not h
        verdicts += 1
        name = os.path.basename(os.path.dirname(p)) + "/" + os.path.basename(p)
        if not set(new) <= set(old):
            not_subset.append(name)
        if len(old) >= 1 and len(new) == 0:
            went_zero.append(name)
        if any(_DISPOSITION_HEAD_RE.match(_normalized_head(i)) for i in new):
            kept_disposition.append(name)
        if not h and new != old:
            legacy_changed.append(name)
    print(
        f"before={before} after={after} verdicts={verdicts} "
        f"heading={heading} legacy={legacy}"
    )
    assert not not_subset, f"new items not a subset of old: {not_subset}"
    assert not went_zero, f"verdicts dropped to zero items: {went_zero}"
    assert not kept_disposition, f"disposition bullets kept: {kept_disposition}"
    assert not legacy_changed, f"legacy-shape verdicts changed: {legacy_changed}"
    if heading > 0:
        assert after < before, f"no reduction: before={before} after={after}"
