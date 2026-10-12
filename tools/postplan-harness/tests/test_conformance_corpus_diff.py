"""Opt-in corpus diff for the ADR renumber tier of conformance._resolve (backlog#1169).

Runs only with POSTPLAN_CORPUS_DIFF=1. CI never sets it and has no plan corpus, so
the test skips there. With the flag set the run asked for the diff, so a missing
corpus is a hard failure, never a skip.

BEFORE = _resolve with _renumbered_adr stubbed to None (byte-identical to the
pre-#1169 code path). AFTER = the real _resolve. Every token whose resolution
differs must be an ADR path that matches _ADR_RENUMBER; every other token must
resolve identically. The flip set is printed, never pinned: the corpus grows.
"""
import glob
import os
import subprocess
from pathlib import PurePosixPath

import pytest

from harness import conformance
from harness.planfile import locate_plan

_REPO_ROOT = str(PurePosixPath(os.path.abspath(__file__)).parents[3])
_ADR_PREFIX = "ibl5/docs/decisions/"


def _corpus_tokens(plans_dir: str) -> tuple[int, set[str]]:
    paths = [p for p in sorted(glob.glob(os.path.join(plans_dir, "*.md")))
             if not p.endswith("-shared-context.md")]
    toks: set[str] = set()
    for p in paths:
        info = locate_plan("corpus", explicit_path=p)
        if not info.found:
            continue
        toks.update(path for path, _ann, exempt in info.critical_files if not exempt)
        toks.update(info.planned_test_paths)
    return len(paths), toks


def _tracked_files() -> list[str]:
    out = subprocess.run(["git", "ls-files"], cwd=_REPO_ROOT,
                         capture_output=True, text=True, check=True).stdout
    return [f for f in out.split("\n") if f]


def test_adr_tier_flips_only_adr_tokens(monkeypatch):
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.environ.get("PLANS_DIR") or os.path.expanduser("~/claude-plans")
    # Explicit opt-in: a missing corpus is a failed run, never a silent skip.
    assert os.path.isdir(plans_dir), f"POSTPLAN_CORPUS_DIFF=1 but no plan corpus at {plans_dir}"

    scanned, toks = _corpus_tokens(plans_dir)
    assert scanned > 0, f"no *.md plans under {plans_dir}"
    files = _tracked_files()

    real_adr = conformance._renumbered_adr
    monkeypatch.setattr(conformance, "_renumbered_adr", lambda *a, **k: None)
    before = {t: conformance._resolve(t, files) for t in toks}
    monkeypatch.setattr(conformance, "_renumbered_adr", real_adr)
    after = {t: conformance._resolve(t, files) for t in toks}

    flips = sorted(t for t in toks if before[t] != after[t])
    print(f"scanned={scanned} tokens={len(toks)} flips={len(flips)}")
    for t in flips:
        print(f"  {t} -> {after[t]}")

    non_adr = [t for t in flips if not t.strip().startswith(_ADR_PREFIX)
               or not conformance._ADR_RENUMBER.match(t.strip().strip("/"))]
    assert non_adr == [], f"non-ADR tokens changed resolution: {non_adr}"
    for t in flips:
        assert before[t] is None, f"{t} resolved before the ADR tier and changed: {before[t]} -> {after[t]}"
        assert after[t] is not None and after[t].startswith(_ADR_PREFIX)
    same = [t for t in toks if t not in flips]
    assert all(before[t] == after[t] for t in same)
