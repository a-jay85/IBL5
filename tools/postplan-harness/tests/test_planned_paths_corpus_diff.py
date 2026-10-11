"""Opt-in corpus diff for the whole-cell Test-type match (planfile._planned_token).

Runs only with POSTPLAN_CORPUS_DIFF=1 and a local plan corpus; CI sets neither,
so it skips there. Asserts the new planned set is a subset of the old one, no real
Test-type cell is rejected, and at least one phantom pair was dropped (zero drops
means the mutant never bound or the row-10 shape left the corpus).
"""
import importlib.util
import os
import re

import pytest

BENCH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                     "bench", "planned_paths_corpus_diff.py")
HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def test_local_corpus_new_is_subset_of_old_and_drops_no_typed_row(capsys):
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    spec = importlib.util.spec_from_file_location("planned_paths_corpus_diff", BENCH)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    rc = mod.main(["--harness-root", HARNESS_ROOT, "--plans-dir", plans_dir])
    out = capsys.readouterr().out
    summary = next(ln for ln in out.splitlines() if ln.startswith("SUMMARY"))
    assert "added=0" in summary and "typed_loss=0" in summary, summary
    assert int(re.search(r"dropped=(\d+)", summary).group(1)) >= 1, summary
    assert rc == 0
