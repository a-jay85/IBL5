"""Corpus diff for the any-cell planned-token gate (backlog#1501).

The real-corpus test is opt-in (POSTPLAN_CORPUS_DIFF=1 and a local plan corpus; CI sets
neither). The synthetic-corpus tests always run, so the bench's classifier and its LOST
exit are pinned by a regression test and do not rest on a one-time run.
"""
import importlib.util
import os
import re

import pytest

HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BENCH = os.path.join(HARNESS_ROOT, "bench", "planned_token_any_cell_corpus_diff.py")
REPO_ROOT = os.path.dirname(os.path.dirname(HARNESS_ROOT))

# Phase 2 run on 2026-10-17: newly_planned=1518 in 222 plans
NEWLY_FLOOR = 1500

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
TRACKED = "tools/postplan-harness/tests/test_planfile.py"
FIXTURE_ROWS = [
    f"| 1 | `testFoo` holds | PHPUnit | post-impl | `{TRACKED}` |",
    "| 2 | `npm test` passes | E2E | post-impl | `ibl5/tests/e2e/nope/absent-1501.spec.ts` |",
    f"| 3 | `testBar` stays green | PHPUnit | post-impl | `{TRACKED}` (run-only) |",
    "| 4 | `testBaz` suite | PHPUnit | post-impl | `ibl5/tests/Draft/` |",
    f"| 5 | plain row | PHPUnit | post-impl | `{TRACKED}` |",
    f"| 6 | `testQux` | CLI-executable | post-impl | `{TRACKED}` |",
    "| 7 | `ibl5/tests/e2e/fixtures/ci-seed.sql` seeds it | E2E | post-impl "
    "| `ibl5/tests/e2e/nope/absent-1501b.spec.ts` |",
]

# Pinned from one run of the fixture above.
EXPECTED_SUMMARY = {
    "newly_planned": 4, "plans": 1, "lost": 0, "changed": 1, "dropped_from_plan": 1,
    "bare_method_first": 3, "command_or_url_first": 1, "other_reason": 0,
    "marked": 1, "glob": 0, "phantom_prone": 1, "tracked_file": 1, "absent": 1,
}


def _load_bench():
    spec = importlib.util.spec_from_file_location("planned_token_any_cell_corpus_diff", BENCH)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def _summary(out: str) -> dict[str, int]:
    line = next(ln for ln in out.splitlines() if ln.startswith("SUMMARY"))
    return {k: int(v) for k, v in re.findall(r"(\w+)=(\d+)", line)}


def _write_fixture(tmp_path, extra_dirs=()):
    text = "# Fixture\n\n" + HDR + "\n".join(FIXTURE_ROWS) + "\n"
    (tmp_path / "p1.md").write_text(text, encoding="utf-8")
    for d in extra_dirs:
        (tmp_path / d).mkdir()
        (tmp_path / d / "p1.md").write_text(text, encoding="utf-8")


def _run(mod, tmp_path, capsys):
    rc = mod.main(["--harness-root", HARNESS_ROOT, "--plans-dir", str(tmp_path),
                   "--repo-root", REPO_ROOT])
    return rc, _summary(capsys.readouterr().out)


def test_local_corpus_any_cell_gate_loses_no_row_and_classifies_every_new_row(capsys):
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    rc = _load_bench().main(["--harness-root", HARNESS_ROOT, "--plans-dir", plans_dir])
    s = _summary(capsys.readouterr().out)
    assert rc == 0
    assert s["lost"] == 0, s
    assert s["newly_planned"] >= NEWLY_FLOOR, s
    assert s["bare_method_first"] >= 1 and s["command_or_url_first"] >= 1, s
    assert (s["bare_method_first"] + s["command_or_url_first"] + s["other_reason"]
            == s["newly_planned"]), s


def test_fixture_corpus_summary_counts(tmp_path, capsys):
    _write_fixture(tmp_path)
    rc, s = _run(_load_bench(), tmp_path, capsys)
    assert rc == 0
    assert s == EXPECTED_SUMMARY, s


def test_fixture_corpus_skips_reports_and_drafts(tmp_path, capsys):
    _write_fixture(tmp_path, extra_dirs=("_reports", ".drafts"))
    rc, s = _run(_load_bench(), tmp_path, capsys)
    assert rc == 0
    assert s["newly_planned"] == EXPECTED_SUMMARY["newly_planned"], s


def test_fixture_corpus_lost_row_exits_1(tmp_path, capsys, monkeypatch):
    _write_fixture(tmp_path)
    mod = _load_bench()
    from harness import planfile
    monkeypatch.setattr(planfile, "_planned_token", lambda cells: None)
    rc, s = _run(mod, tmp_path, capsys)
    assert rc == 1
    assert s["lost"] >= 1, s

