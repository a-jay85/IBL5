"""Opt-in corpus diff for the location-cell token choice (planfile._planned_token).

Runs only with POSTPLAN_CORPUS_DIFF=1 and a local plan corpus; CI sets neither,
so it skips there. Asserts the planning gate did not move (no row lost, no row newly
planned), that at least one token moved (zero means the mutant never bound), and that
the only row where both tokens look like real test files is the one recorded verdict.
"""
import importlib.util
import os
import re

import pytest

BENCH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                     "bench", "planned_token_location_cell_corpus_diff.py")
HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

REVIEW_ALLOWLIST = {
    ("phpstan-baseline-module-index-batch.md",
     "ibl5/tests/e2e/smoke/auth-pages-extended.spec.ts",
     "ibl5/tests/e2e/flows/free-agency.spec.ts"),
}


def test_local_corpus_moves_tokens_without_losing_or_adding_planned_rows(capsys):
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    spec = importlib.util.spec_from_file_location("planned_token_location_cell_corpus_diff", BENCH)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    rc = mod.main(["--harness-root", HARNESS_ROOT, "--plans-dir", plans_dir])
    out = capsys.readouterr().out
    summary = next(ln for ln in out.splitlines() if ln.startswith("SUMMARY"))
    assert "lost=0" in summary and "newly_planned=0" in summary, summary
    assert int(re.search(r"changed=(\d+)", summary).group(1)) >= 1, summary
    review = {(p[1], p[3], p[4]) for p in (ln.split("\t") for ln in out.splitlines())
              if p[0] == "CHANGED" and p[2] == "REVIEW"}
    assert review == REVIEW_ALLOWLIST, review
    assert rc == 0
