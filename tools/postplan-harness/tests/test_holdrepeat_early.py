"""Tests for the reason-keyed early hold-repeat match (Phase 1) and verdict_line (Phase 2)."""
from __future__ import annotations

import os
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness import holdrepeat as hr
from harness.state import PlanInfo

SLUG = "slug"
ITEM_FILE = "MISSING-FILE: tools/a.py (plan Critical File never appeared in the diff)"
ITEM_TEST = "MISSING: tests/t.py (matrix planned a test the diff never wrote)"
ITEM_NEW = "MISSING-FILE: tools/new.py (plan Critical File never appeared in the diff)"


@pytest.fixture(autouse=True)
def _no_tracked_lookup(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)


def _cond(number, reason):
    return SimpleNamespace(number=number, name=f"cond-{number}", blocked=True, reason=reason)


def _record(tmp_path, spec):
    """spec: {condition number: reason}. Writes the real on-disk record shape."""
    hr.observe(str(tmp_path), SLUG, armed=False,
               conditions=[_cond(n, r) for n, r in spec.items()],
               fingerprint="fp", pr=1, now="2026-10-02T12:00:00Z")


def test_early_repeat_equal_sets_returns_reason(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    out = hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_TEST, ITEM_FILE])
    assert out
    assert "tools/a.py" in out and "tests/t.py" in out


def test_early_repeat_ignores_non_missing_parts(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; UNREALISED-ASSERTION: row 4 -- x; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE, ITEM_TEST])


def test_early_repeat_strict_subset_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_superset_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}; {ITEM_TEST}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE, ITEM_TEST, ITEM_NEW]) is None


def test_early_repeat_no_record_proceeds(tmp_path):
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_prior_cond7_only_proceeds(tmp_path):
    _record(tmp_path, {7: f"{ITEM_FILE}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, [ITEM_FILE]) is None


def test_early_repeat_empty_current_proceeds(tmp_path):
    _record(tmp_path, {3: f"{ITEM_FILE}"})
    assert hr.early_repeat_reason(str(tmp_path), SLUG, []) is None


def test_early_missing_items_filters_kinds():
    plan = PlanInfo(found=True, has_matrix=True,
                    planned_test_paths=["tests/test_zzz_absent.py"],
                    critical_files=[("tools/zzz_absent.py", "", False)],
                    required_test_methods=["test_zzz_method"])
    items = conformance.early_missing_items(plan, [])
    assert items
    assert all(i.startswith(("MISSING:", "MISSING-FILE:")) for i in items)
    assert any(i.startswith("MISSING:") for i in items)
    assert any(i.startswith("MISSING-FILE:") for i in items)
