"""Corpus check for the `[BLOCKING]` / `[NOTE]` finding marker.

Runs only with `POSTPLAN_CORPUS_DIFF=1` and a local verdict corpus under
`tools/postplan-harness/out/` (gitignored), so the corpus test skips in CI. It diffs the
marker-aware _verdict_findings over every recorded NOT READY verdict: unmarked verdicts
match a frozen copy of the pre-change extractor, marked verdicts match an independent
item-2a reference, and no verdict loses all its work. Asserts invariants only, because the
corpus grows with every harness run.

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

from harness import fidelity  # noqa: E402

# Frozen copy of the bullet regex as of the pre-#1335 extractor. Do not import it.
_FROZEN_BULLET_RE = re.compile(r"^\s*(?:[-*]|\d+[.)])\s+\S")
# Item-2a marker, read off an already-stripped bullet line. Written independently of
# fidelity._FINDING_MARKER_RE on purpose.
_REF_MARKER_RE = re.compile(r"^(?:[-*]|\d+[.)])\s+\[(BLOCKING|NOTE)\](?:\s|$)")


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


def _scope(path):
    """(body, scope) exactly as _verdict_findings cuts them. These helpers are unchanged
    by #1335, so reusing them keeps the diff focused on bullet extraction."""
    with open(path, encoding="utf-8", errors="replace") as fh:
        lines = fh.read().splitlines()
    for i, line in enumerate(lines):
        if line.strip() == fidelity.DIGEST_CUT:
            lines = lines[:i]
            break
    body = [ln for ln in lines if not fidelity.VERDICT_RE.match(ln)]
    section = fidelity._findings_section(body)
    return body, (body if section is None else section)


def _whole(body):
    whole = "\n".join(body).strip()
    return [whole] if whole else []


def _frozen_verdict_findings(path):
    """Pre-#1335 extractor: one stripped item per bullet line, markers ignored."""
    if fidelity.parse_verdict(path) != "NOT READY":
        return []
    body, scope = _scope(path)
    bullets = [ln.strip() for ln in scope if _FROZEN_BULLET_RE.match(ln)]
    return bullets or _whole(body)


def _reference_findings(path):
    """Item-2a reading. Top level is the shallowest bullet indent in scope. A marked
    top-level bullet owns every deeper bullet up to the next top-level bullet; deeper
    bullets after an unmarked one stay separate. [NOTE] groups drop unless every group
    is [NOTE]."""
    if fidelity.parse_verdict(path) != "NOT READY":
        return []
    body, scope = _scope(path)
    rows = [(len(ln) - len(ln.lstrip()), ln.strip())
            for ln in scope if _FROZEN_BULLET_RE.match(ln)]
    if not rows:
        return _whole(body)
    top = min(indent for indent, _ in rows)
    groups = []
    for indent, text in rows:
        if indent > top and groups and groups[-1][0] is not None:
            groups[-1][1].append(text)
            continue
        m = _REF_MARKER_RE.match(text) if indent == top else None
        groups.append([m.group(1) if m else None, [text]])
    kept = ["\n".join(ls) for mk, ls in groups if mk != "NOTE"]
    return kept or ["\n".join(ls) for _, ls in groups]


def test_reference_and_frozen_extractors_on_fixture(tmp_path):
    """Runs without a corpus (CI too): pins both oracles so a broken oracle cannot make
    the corpus test pass vacuously."""
    p = tmp_path / "v.txt"
    p.write_text(
        "## FINDINGS\n\n"
        "- [BLOCKING] head\n  - nested\n- [NOTE] note\n  - note detail\n- unmarked\n"
        "\nNOT READY\n\n## DIGEST\nx\n"
    )
    path = str(p)
    expected = ["- [BLOCKING] head\n- nested", "- unmarked"]
    assert _reference_findings(path) == expected
    assert fidelity._verdict_findings(path) == expected
    assert _frozen_verdict_findings(path) == [
        "- [BLOCKING] head", "- nested", "- [NOTE] note", "- note detail", "- unmarked",
    ]


def test_marked_corpus_invariants():
    unmarked = marked = frozen_items = new_items = 0
    unmarked_changed, marked_mismatch, went_empty = [], [], []
    for p in _corpus_paths():
        name = os.path.basename(os.path.dirname(p)) + "/" + os.path.basename(p)
        with open(p, encoding="utf-8", errors="replace") as fh:
            text = fh.read()
        frozen = _frozen_verdict_findings(p)
        new = fidelity._verdict_findings(p)
        frozen_items += len(frozen)
        new_items += len(new)
        if "[BLOCKING]" in text or "[NOTE]" in text:
            marked += 1
            if new != _reference_findings(p):
                marked_mismatch.append(name)
        else:
            unmarked += 1
            if new != frozen:
                unmarked_changed.append(name)
        if frozen and not new:
            went_empty.append(name)
    print(f"verdicts={unmarked + marked} unmarked={unmarked} marked={marked} "
          f"frozen_items={frozen_items} new_items={new_items}")
    assert not unmarked_changed, f"(a) unmarked verdicts changed: {unmarked_changed}"
    assert not marked_mismatch, f"(b) marked verdicts off the item-2a reference: {marked_mismatch}"
    assert not went_empty, f"(c) verdicts that lost all work: {went_empty}"
    assert marked > 0, "corpus has no marked NOT READY verdict; invariant (b) ran on nothing"


def test_corpus_gate_skips_when_env_unset(monkeypatch):
    monkeypatch.delenv("POSTPLAN_CORPUS_DIFF", raising=False)
    with pytest.raises(pytest.skip.Exception, match="SKIP: set POSTPLAN_CORPUS_DIFF=1"):
        _corpus_paths()


def test_corpus_gate_skips_when_env_empty(monkeypatch):
    monkeypatch.setenv("POSTPLAN_CORPUS_DIFF", "")
    with pytest.raises(pytest.skip.Exception, match="SKIP: set POSTPLAN_CORPUS_DIFF=1"):
        _corpus_paths()


def test_empty_out_dir_env_falls_back_to_default(monkeypatch):
    default = os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "out"
    )
    monkeypatch.setenv("POSTPLAN_OUT_DIR", "")
    assert _out_dir() == default
    monkeypatch.delenv("POSTPLAN_OUT_DIR", raising=False)
    assert _out_dir() == default
