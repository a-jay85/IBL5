"""Phase 6.5 condition (20): the removed-guards block and its Python mirror of pr_removed_guards_hold.

`bin/fixtures/removed-guards/cases.json` is the parity contract. `bin/test-postplan-arm-conditions`
runs the same rows through the shell predicate, so a one-sided change goes red in one of the two.
"""
from __future__ import annotations

import json
import os
import stat
import subprocess
from pathlib import Path

import pytest

from harness import removed_guards as rg

REPO = Path(__file__).resolve().parents[3]
CASES = json.loads((REPO / "bin/fixtures/removed-guards/cases.json").read_text(encoding="utf-8"))
KEY_A = "try@ibl5/classes/Season/Season.php#1a2b3c4d"
KEY_B = "catch@ibl5/classes/Season/Season.php#5e6f7a8b"
ROW_A = (KEY_A, "ibl5/classes/Season/Season.php", "try", "try {")
ROW_B = (KEY_B, "ibl5/classes/Season/Season.php", "catch", "} catch (\\RuntimeException) {")


def test_shared_case_table_is_not_trivial():
    assert len(CASES) >= 16
    assert len({c["name"] for c in CASES}) == len(CASES)


@pytest.mark.parametrize("case", CASES, ids=[c["name"] for c in CASES])
def test_classify_body_matches_shared_case_table(case):
    assert rg.classify_body(case["body"], case["flagged_keys"]) == case["expected"]


def test_render_placeholder_when_reason_missing_holds():
    block = rg.render_block([ROW_A], {})
    assert rg.PLACEHOLDER in block
    assert rg.classify_body(block, [KEY_A]) == [f"removed-guards:UNJUSTIFIED:{KEY_A}"]


def test_render_then_classify_with_reasons_arms():
    reasons = {KEY_A: "Moved into SeasonFactory::build, same catch.",
               KEY_B: "Moved into SeasonFactory::build, same catch."}
    body = rg.upsert_block("## Summary\nx\n", rg.render_block([ROW_A, ROW_B], reasons))
    assert rg.classify_body(body, [KEY_A, KEY_B]) == []


def test_render_empty_rows_is_empty():
    assert rg.render_block([], {KEY_A: "irrelevant reason text"}) == ""


def test_upsert_removes_stale_block_on_empty():
    body = rg.upsert_block("## Summary\nx\n", rg.render_block([ROW_A], {}))
    assert rg.BEGIN in body
    cleared = rg.upsert_block(body, "")
    assert rg.BEGIN not in cleared and rg.END not in cleared
    assert cleared.startswith("## Summary\nx")


def test_upsert_replaces_in_place_and_follows_scope_notes():
    body = "## Summary\nx\n\n<!-- scope-notes:begin -->\nnote\n<!-- scope-notes:end -->\n\n## Manual Testing\nnone\n"
    once = rg.upsert_block(body, rg.render_block([ROW_A], {}))
    assert once.index("<!-- scope-notes:end -->") < once.index(rg.BEGIN) < once.index("## Manual Testing")
    twice = rg.upsert_block(once, rg.render_block([ROW_B], {}))
    assert twice.count(rg.BEGIN) == 1 and KEY_B in twice and KEY_A not in twice


def test_read_reasons_strips_tabs_and_newlines(tmp_path):
    subprocess.run(["git", "init", "-q", str(tmp_path)], check=True)
    gd = subprocess.run(["git", "-C", str(tmp_path), "rev-parse", "--absolute-git-dir"],
                        check=True, capture_output=True, text=True).stdout.strip()
    Path(gd, rg.REASONS_FILE).write_text(
        f"{KEY_A}\tfirst reason, later replaced\r\n"
        f"{KEY_B}\tkept\twith a tab inside\r\n"
        f"{KEY_A}\tmoved to SeasonFactory::build\n"
        "no-tab-line-is-ignored\n", encoding="utf-8")
    reasons = rg.read_reasons(str(tmp_path))
    assert reasons == {KEY_A: "moved to SeasonFactory::build", KEY_B: "kept with a tab inside"}
    assert all("\t" not in v and "\r" not in v and "\n" not in v for v in reasons.values())


def test_read_reasons_missing_file_is_empty(tmp_path):
    subprocess.run(["git", "init", "-q", str(tmp_path)], check=True)
    assert rg.read_reasons(str(tmp_path)) == {}


def _fake_root(tmp_path, script: str) -> str:
    lib = tmp_path / "bin" / "lib"
    lib.mkdir(parents=True)
    det = lib / "deleted-guards"
    det.write_text(script, encoding="utf-8")
    det.chmod(det.stat().st_mode | stat.S_IXUSR)
    return str(tmp_path)


def test_run_detector_missing_binary_fails_closed(tmp_path):
    assert rg.run_detector(str(tmp_path), "", None) == (False, [])


def test_run_detector_exit2_fails_closed(tmp_path):
    root = _fake_root(tmp_path, "#!/bin/sh\necho 'usage' >&2\nexit 2\n")
    assert rg.run_detector(root, "diff", None) == (False, [])


def test_run_detector_parses_rows_and_passes_plan(tmp_path):
    root = _fake_root(tmp_path, "#!/bin/sh\n"
                      "[ \"$3\" = --plan ] || exit 2\n"
                      f"printf 'REMOVED-GUARD\\t{KEY_A}\\tibl5/classes/Season/Season.php\\ttry\\ttry {{\\n'\n"
                      f"printf 'INTENDED\\t{KEY_B}\\tibl5/classes/Season/Season.php\\tcatch\\tx\\n'\n"
                      "exit 1\n")
    ok, rows = rg.run_detector(root, "diff", "/plan.md")
    assert ok and rows == [(KEY_A, "ibl5/classes/Season/Season.php", "try", "try {")]


def test_run_detector_replay_pr3371():
    fixture = REPO / "bin/fixtures/deleted-guards/pr3371-trimmed.diff"
    if not (REPO / "bin/lib/deleted-guards").exists() or not fixture.exists():
        pytest.fail("bin/lib/deleted-guards or its pr3371 fixture is missing")
    ok, rows = rg.run_detector(str(REPO), fixture.read_text(encoding="utf-8"), None)
    assert ok
    keys = [r[0] for r in rows]
    assert any(k.startswith("try@ibl5/classes/Season/Season.php#") for k in keys)
    assert any(k.startswith("catch@ibl5/classes/Season/Season.php#") for k in keys)


@pytest.mark.parametrize("reason,ok", [
    ("Moved to b.php.", True), ("None of these.", False), ("N/A", False), ("TBD.", False),
    ("<one-line reason required>", False), ("---------------------", False),
    ("   Moved to b.php.   ", True),
])
def test_is_substantive_boundaries(reason, ok):
    assert rg.is_substantive(reason) is ok
