"""Unit tests for harness/baseline_guard.py and the gh adapter methods it reads.

Run: cd tools/postplan-harness && python3 -m pytest -q tests/test_baseline_guard.py
(repo path: tools/postplan-harness/tests/test_baseline_guard.py).
"""
from __future__ import annotations

import pytest

from harness import baseline_guard as bg
from harness.adapters.ghad import RecordingGh
from harness.state import HarnessError

SHA = "a" * 40


class FakeGh:
    """Logs every call; `raises` maps a method name to an exception to raise."""

    def __init__(self, *, labels=(), runs=(), comments="", raises=None, add_results=(True,)):
        self.labels = list(labels)
        self.runs = list(runs)
        self.comments = comments
        self.raises = raises or {}
        self.add_results = list(add_results)
        self.calls: list[tuple] = []

    def _maybe_raise(self, name):
        if name in self.raises:
            raise self.raises[name]

    def pr_has_label_fresh(self, pr, label):
        self.calls.append(("pr_has_label_fresh", pr, label))
        self._maybe_raise("pr_has_label_fresh")
        return label in self.labels

    def e2e_runs_for_sha(self, sha):
        self.calls.append(("e2e_runs_for_sha", sha))
        self._maybe_raise("e2e_runs_for_sha")
        return list(self.runs)

    def pr_comments_text(self, pr):
        self.calls.append(("pr_comments_text", pr))
        self._maybe_raise("pr_comments_text")
        return self.comments

    def post_review_summary(self, pr, title, body):
        self.calls.append(("post_review_summary", pr, title, body))
        self._maybe_raise("post_review_summary")

    def pr_label_remove(self, pr, label):
        self.calls.append(("pr_label_remove", pr, label))
        return True

    def pr_label_add_checked(self, pr, label):
        self.calls.append(("pr_label_add_checked", pr, label))
        return self.add_results.pop(0) if self.add_results else True

    def mutations(self):
        return [c[0] for c in self.calls
                if c[0] in ("post_review_summary", "pr_label_remove", "pr_label_add_checked")]


def run(status="completed", conclusion="cancelled", created="2026-10-08T01:00:00Z", rid=9):
    return {"databaseId": rid, "status": status, "conclusion": conclusion,
            "event": "pull_request", "createdAt": created}


@pytest.fixture(autouse=True)
def _clear_refired():
    bg._REFIRED.clear()
    yield
    bg._REFIRED.clear()


def test_probe_skipped_when_label_present():
    assert bg.probe_decision(FakeGh(labels=["update-baselines"]), 7, SHA) == (
        False, "update-baselines-label")


@pytest.mark.parametrize("status", ["in_progress", "queued", "waiting", "requested", "pending"])
def test_probe_skipped_when_e2e_run_active(status):
    gh = FakeGh(runs=[run(status=status, conclusion=None)])
    assert bg.probe_decision(gh, 7, SHA) == (False, "e2e-run-active")


def test_probe_skipped_when_e2e_run_queued():
    gh = FakeGh(runs=[run(status="queued", conclusion=None)])
    assert bg.probe_decision(gh, 7, SHA) == (False, "e2e-run-active")


def test_probe_skipped_on_label_read_error():
    gh = FakeGh(raises={"pr_has_label_fresh": HarnessError("gh", "boom")})
    assert bg.probe_decision(gh, 7, SHA) == (False, "label-read-error")


def test_probe_skipped_on_e2e_read_error():
    gh = FakeGh(raises={"e2e_runs_for_sha": ValueError("bad json")})
    assert bg.probe_decision(gh, 7, SHA) == (False, "e2e-read-error")


def test_probe_allowed_when_clear():
    assert bg.probe_decision(FakeGh(runs=[run(conclusion="success")]), 7, SHA) == (True, "clear")
    assert bg.probe_decision(FakeGh(), 7, SHA) == (True, "clear")


def test_refire_when_label_present_and_regen_cancelled():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="cancelled")])
    logs: list[str] = []
    assert bg.maybe_refire(gh, 7, SHA, logs.append) == "refired"
    assert gh.mutations() == ["post_review_summary", "pr_label_remove", "pr_label_add_checked"]
    comment = next(c for c in gh.calls if c[0] == "post_review_summary")
    assert bg.marker(SHA) in comment[3]
    assert comment[2] == "Baseline regen re-fired"
    assert logs == [f"phase7 baseline re-fire: refired sha={SHA[:8]}"]


def test_never_refire_when_label_absent():
    gh = FakeGh(labels=[], runs=[run(conclusion="cancelled")])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "skip-no-label"
    assert gh.mutations() == []


def test_no_refire_when_run_active():
    gh = FakeGh(labels=["update-baselines"],
                runs=[run(status="in_progress", conclusion=None, created="2026-10-08T02:00:00Z"),
                      run(conclusion="cancelled")])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "skip-run-active"
    assert gh.mutations() == []


def test_no_refire_when_newest_run_succeeded():
    gh = FakeGh(labels=["update-baselines"],
                runs=[run(conclusion="cancelled", created="2026-10-08T01:00:00Z"),
                      run(conclusion="success", created="2026-10-08T02:00:00Z", rid=10)])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "skip-regen-not-incomplete"
    assert gh.mutations() == []


def test_no_refire_when_no_runs():
    gh = FakeGh(labels=["update-baselines"], runs=[])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "skip-regen-not-incomplete"
    assert gh.mutations() == []


def test_no_refire_on_read_error():
    gh = FakeGh(labels=["update-baselines"], raises={"e2e_runs_for_sha": HarnessError("gh", "x")})
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "skip-read-error"
    assert gh.mutations() == []


def test_refire_at_most_once_per_head():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "refired"
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "already-this-process"
    # A fresh process has an empty _REFIRED; the marker comment on the PR is the durable record.
    bg._REFIRED.clear()
    gh2 = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")],
                 comments=f"older text\n{bg.marker(SHA)}\nmore")
    assert bg.maybe_refire(gh2, 7, SHA, lambda _m: None) == "skip-marker"
    assert gh2.mutations() == []


def test_marker_for_another_head_does_not_block():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")],
                comments=bg.marker("b" * 40))
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "refired"


def test_remove_failure_stops_before_add():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")])
    gh.pr_label_remove = lambda pr, label: False
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "remove-failed"
    assert "pr_label_add_checked" not in gh.mutations()


def test_readd_failure_retried_once_then_loud_comment():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")],
                add_results=[False, False])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "readd-failed"
    assert gh.mutations() == ["post_review_summary", "pr_label_remove",
                              "pr_label_add_checked", "pr_label_add_checked",
                              "post_review_summary"]
    last = [c for c in gh.calls if c[0] == "post_review_summary"][-1]
    assert last[2] == "update-baselines label lost"


def test_readd_succeeds_on_retry():
    gh = FakeGh(labels=["update-baselines"], runs=[run(conclusion="failure")],
                add_results=[False, True])
    assert bg.maybe_refire(gh, 7, SHA, lambda _m: None) == "refired"
    assert gh.mutations().count("pr_label_add_checked") == 2


def test_recording_gh_label_remove_is_a_mutation(tmp_path):
    assert "label_remove" in RecordingGh.MUTATIONS
    gh = RecordingGh(str(tmp_path))
    assert gh.pr_label_remove(7, "update-baselines") is True
    assert gh.actions()[-1]["action"] == "label_remove"


def test_recording_gh_replay_reads(tmp_path):
    gh = RecordingGh(str(tmp_path), {"fresh_labels": ["update-baselines"],
                                     "e2e_runs": [run()], "pr_comments": ["a", "b"],
                                     "label_add_failures": 1})
    assert gh.pr_has_label_fresh(7, "update-baselines") is True
    assert gh.pr_has_label_fresh(7, "other") is False
    assert gh.e2e_runs_for_sha(SHA)[0]["databaseId"] == 9
    assert gh.pr_comments_text(7) == "a\nb"
    assert gh.pr_label_add_checked(7, "update-baselines") is False
    assert gh.pr_label_add_checked(7, "update-baselines") is True
    assert gh.actions()[-1]["action"] == "label_add"


def test_recording_gh_replay_read_errors_raise(tmp_path):
    gh = RecordingGh(str(tmp_path), {"fresh_labels_error": True, "e2e_runs_error": True})
    with pytest.raises(HarnessError):
        gh.pr_has_label_fresh(7, "update-baselines")
    with pytest.raises(HarnessError):
        gh.e2e_runs_for_sha(SHA)
