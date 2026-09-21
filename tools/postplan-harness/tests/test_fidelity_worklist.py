"""The tagged work list the Phase 5.5 fixer receives.

Pure functions over inputs the runner already holds upstream of the loop: the
fidelity verdict (hold 12), unresolved conformance entries (hold 3), failing
meta-checks (hold 16), and scored review findings (hold 2).
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity


def _verdict(tmp_path, word, body="- finding one\n- finding two\n"):
    p = tmp_path / "verdict.md"
    p.write_text(f"6d checks\n\n{word}\n\n{body}\n## DIGEST\nstuff\n")
    return str(p)


def _finding(score, path="a.php", line=7, body_head="something wrong"):
    return {"source": "code-review", "agent": "x", "path": path,
            "line": line, "score": score, "body_head": body_head}


def test_union_carries_all_four_holds_tagged(tmp_path):
    items = fidelity.build_work_list(
        _verdict(tmp_path, "NOT READY"),
        ["MISSING-FILE: a.php (plan named it)"],
        [{"name": "check-docs", "output": "FAIL  check-docs"}],
        [_finding(80), _finding(79, path="b.php")],
    )
    assert fidelity.work_list_sizes(items) == {"12": 2, "3": 1, "16": 1, "2": 1}
    assert all(i["hold"] in fidelity.HOLD_SOURCES for i in items)
    assert [i["hold"] for i in items] == ["12", "12", "3", "16", "2"]


def test_ready_verdict_contributes_nothing_to_hold_12(tmp_path):
    items = fidelity.build_work_list(
        _verdict(tmp_path, "READY"),
        [],
        [{"name": "check-prose", "output": "FAIL  check-prose"}],
        [],
    )
    assert fidelity.work_list_sizes(items) == {"12": 0, "3": 0, "16": 1, "2": 0}


def test_prose_verdict_falls_back_to_whole_body(tmp_path):
    path = _verdict(tmp_path, "NOT READY",
                    body="The plan promised a migration and the diff has none.\n")
    items = fidelity.build_work_list(path, [], [], [])
    assert len(items) == 1
    assert items[0]["hold"] == "12"
    assert "promised a migration" in items[0]["text"]
    assert fidelity.DIGEST_CUT not in items[0]["text"]
    assert "stuff" not in items[0]["text"]


def test_unmet_contract_entries_are_excluded(tmp_path):
    items = fidelity.build_work_list(
        _verdict(tmp_path, "READY"),
        ["UNMET-CONTRACT: row 4 evidence absent", "MISSING: t (plan named it)"],
        [], [])
    assert [i["text"] for i in items] == ["MISSING: t (plan named it)"]


def test_plan_blind_union_is_16_and_2_only(tmp_path):
    items = fidelity.build_work_list(
        str(tmp_path / "does-not-exist.md"),
        [],
        [{"name": "check-docs", "output": "o"}],
        [_finding(95)],
    )
    assert fidelity.work_list_sizes(items) == {"12": 0, "3": 0, "16": 1, "2": 1}


def test_empty_union_renders_stable_fences(tmp_path):
    items = fidelity.build_work_list(str(tmp_path / "nope.md"), [], [], [])
    assert items == []
    rendered = fidelity.render_work_list(items)
    assert "=== WORK LIST" in rendered
    assert "=== END WORK LIST ===" in rendered
    assert "(empty)" in rendered
    assert fidelity.work_list_sizes(items) == {"12": 0, "3": 0, "16": 0, "2": 0}


def test_malformed_finding_dict_is_dumped_not_raised(tmp_path):
    items = fidelity.build_work_list(str(tmp_path / "nope.md"), [], [], [{"score": 90}])
    assert len(items) == 1
    assert items[0]["hold"] == "2"
    assert "90" in items[0]["text"]


def test_render_tags_every_item_with_its_hold_label(tmp_path):
    items = fidelity.build_work_list(
        _verdict(tmp_path, "NOT READY", body="- fix it\n"),
        ["MISSING: t (x)"],
        [{"name": "check-docs", "output": "o"}],
        [_finding(90)],
    )
    rendered = fidelity.render_work_list(items)
    for hold, label in fidelity.HOLD_LABELS.items():
        assert f"[hold {hold} — {label}]" in rendered


def test_duplicate_items_are_deduplicated(tmp_path):
    items = fidelity.build_work_list(
        str(tmp_path / "nope.md"),
        ["MISSING: t (x)", "MISSING: t (x)"],
        [], [])
    assert fidelity.work_list_sizes(items) == {"12": 0, "3": 1, "16": 0, "2": 0}
