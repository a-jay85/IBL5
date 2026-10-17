"""Opt-in corpus diff for the typed-table whole-cell Truly-manual match (backlog#1490).

The corpus test runs only with POSTPLAN_CORPUS_DIFF=1 and a local plan corpus; CI
sets neither, so it skips there. It asserts no row was added, no real What-to-verify
Truly-manual row was lost, and at least one phantom was dropped (zero drops means
parse_matrix still runs the old whole-row search). The flag tests run everywhere.
"""
import importlib.util
import os
import re

import pytest

BENCH = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                     "bench", "manual_rows_corpus_diff.py")
HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def _load():
    spec = importlib.util.spec_from_file_location("manual_rows_corpus_diff", BENCH)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def test_unknown_flag_exits_2():
    with pytest.raises(SystemExit) as exc:
        _load().main(["--harness-root", HARNESS_ROOT, "--bogus"])
    assert exc.value.code == 2


def test_missing_harness_root_exits_2():
    with pytest.raises(SystemExit) as exc:
        _load().main([])
    assert exc.value.code == 2


def test_local_corpus_drops_phantoms_and_loses_no_typed_row(capsys):
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    rc = _load().main(["--harness-root", HARNESS_ROOT, "--plans-dir", plans_dir])
    out = capsys.readouterr().out
    summary = next(ln for ln in out.splitlines() if ln.startswith("SUMMARY"))
    assert "added=0" in summary and "typed_loss=0" in summary, summary
    assert int(re.search(r"dropped=(\d+)", summary).group(1)) >= 1, summary
    assert rc == 0
