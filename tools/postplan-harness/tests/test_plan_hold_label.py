"""Unit tests for harness/plan_hold_label.py.

Run: cd tools/postplan-harness && python3 -m pytest -q tests/test_plan_hold_label.py
(repo path: tools/postplan-harness/tests/test_plan_hold_label.py).
"""
from __future__ import annotations

from types import SimpleNamespace

import pytest

from harness import plan_hold_label as phl
from harness.adapters.ghad import RecordingGh


class FakeGh:
    def __init__(self, add_ok=True, raise_on=None):
        self.calls: list[tuple] = []
        self.add_ok = add_ok
        self.raise_on = raise_on

    def label_ensure(self, label, color, description):
        self.calls.append(("ensure", label, color))
        if self.raise_on == "ensure":
            raise RuntimeError("boom")

    def pr_label_add_checked(self, pr, label):
        self.calls.append(("add", pr, label))
        return self.add_ok

    def pr_label_remove(self, pr, label):
        self.calls.append(("remove", pr, label))
        return True


def plan(found=True, held=True, triggers=()):
    return SimpleNamespace(found=found, auto_merge_false=held,
                           auto_merge_value="false" if held else None,
                           hold_triggers=tuple(triggers))


def run(gh, pr, p):
    logs: list[str] = []
    return phl.sync(gh, pr, p, logs.append), logs


@pytest.mark.parametrize("triggers", [(), ("ui",), ("security-surface", "ui"), ("bogus",)])
def test_owner_held_plan_adds_orange_label(triggers):
    gh = FakeGh()
    action, _ = run(gh, 12, plan(triggers=triggers))
    assert action == "added"
    assert gh.calls == [("ensure", phl.LABEL, phl.COLOR), ("add", 12, phl.LABEL)]


@pytest.mark.parametrize("triggers", [("security-surface",), ("gate-weakening", "golden-snapshot"),
                                      ("sql-equivalent",)])
def test_reviewer_clearable_only_plan_gets_no_label(triggers):
    gh = FakeGh()
    action, _ = run(gh, 12, plan(triggers=triggers))
    assert action == "removed"
    assert gh.calls == [("remove", 12, phl.LABEL)]


def test_found_plan_without_hold_removes_label():
    gh = FakeGh()
    action, _ = run(gh, 12, plan(held=False))
    assert action == "removed"
    assert gh.calls == [("remove", 12, phl.LABEL)]


def test_plan_blind_run_leaves_label_alone():
    gh = FakeGh()
    action, _ = run(gh, 12, plan(found=False))
    assert action == "skip"
    assert gh.calls == []


def test_no_pr_number_does_nothing():
    gh = FakeGh()
    assert run(gh, None, plan())[0] == "skip"
    assert gh.calls == []


def test_add_failure_is_logged_not_raised():
    action, logs = run(FakeGh(add_ok=False), 12, plan())
    assert action == "add-failed"
    assert any("add failed" in line for line in logs)


def test_error_never_escapes():
    action, logs = run(FakeGh(raise_on="ensure"), 12, plan())
    assert action == "error"
    assert any("ignored" in line for line in logs)


def test_recording_gh_label_ensure_records_nothing(tmp_path):
    gh = RecordingGh(str(tmp_path))
    gh.label_ensure(phl.LABEL, phl.COLOR, phl.DESCRIPTION)
    assert gh.actions() == []
