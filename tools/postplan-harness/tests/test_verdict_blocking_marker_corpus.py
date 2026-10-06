"""Corpus check for the `[BLOCKING]` / `[NOTE]` finding marker.

Runs only with `POSTPLAN_CORPUS_DIFF=1` and a local verdict corpus under
`tools/postplan-harness/out/` (gitignored), so the corpus test skips in CI. It proves the
marker tokens never occur in recorded verdicts, and that marking every kept bullet of
every NOT READY verdict leaves the unchanged parser's items equal to marker plus old item.
Asserts invariants only, because the corpus grows with every harness run.

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
from harness.fidelity import _FINDING_BULLET_RE  # noqa: E402

_MARK_RE = re.compile(r"^(\s*(?:[-*]|\d+[.)])\s+)")
_MARK_ITEM_RE = re.compile(r"^((?:[-*]|\d+[.)])\s+)")


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


def test_marker_tokens_absent_and_prefix_invariant(tmp_path):
    paths = _corpus_paths()
    token_hits = 0
    for p in glob.glob(os.path.join(_out_dir(), "*", "raw-plan-fidelity-*")):
        with open(p, encoding="utf-8", errors="replace") as fh:
            text = fh.read()
        token_hits += text.count("[BLOCKING]") + text.count("[NOTE]")
    assert token_hits == 0, f"marker tokens already occur in the corpus: {token_hits}"

    bullets = 0
    for n, path in enumerate(paths):
        old = fidelity._verdict_findings(path)
        bullets += len(old)
        with open(path, encoding="utf-8", errors="replace") as fh:
            lines = fh.read().splitlines()
        marked = [
            _MARK_RE.sub(r"\1[BLOCKING] ", ln, count=1) if _FINDING_BULLET_RE.match(ln) else ln
            for ln in lines
        ]
        mp = tmp_path / f"{n}.txt"
        mp.write_text("\n".join(marked) + "\n")
        new = fidelity._verdict_findings(str(mp))
        assert len(new) == len(old), path
        for old_item, new_item in zip(old, new):
            if _FINDING_BULLET_RE.match(old_item):
                assert new_item == _MARK_ITEM_RE.sub(r"\1[BLOCKING] ", old_item, count=1), path
            else:
                assert new_item == old_item, path
    print(f"verdicts={len(paths)} bullets={bullets} token_hits={token_hits}")


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
