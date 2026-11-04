"""One ab-outcomes.jsonl line per enrolled run; nothing for a run outside the experiment."""
from __future__ import annotations

import json
import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner
from harness import ab_arm
from test_closer_ab_golden import _RebaseConflictGit, _go
from test_rearm_after_ci_fix import _armable_fixture, _held16_fixture
from test_runner_closer import Recorder, _RED, _TrackedGit, _install
from test_runner_replay import _LiveShapedGit


def _rows(tmp_path):
    path = tmp_path / "state" / ab_arm.OUTCOME_BASENAME
    if not path.exists():
        return []
    return [json.loads(ln) for ln in path.read_text().splitlines() if ln.strip()]


def _env(monkeypatch, arm=None, fired_by=None):
    for k, v in (("POSTPLAN_AB_ARM", arm), ("POSTPLAN_FIRED_BY", fired_by)):
        if v is None:
            monkeypatch.delenv(k, raising=False)
        else:
            monkeypatch.setenv(k, v)


def test_outcome_row_written_for_enrolled_closer_run(monkeypatch, tmp_path):
    _env(monkeypatch, "closer")
    fx = _held16_fixture(monkeypatch, tmp_path, closer={}, ci_fix_rewatch=_RED)[0]
    _install(monkeypatch, Recorder())
    _go(monkeypatch, tmp_path, fx, git_cls=_TrackedGit)
    rows = _rows(tmp_path)
    assert len(rows) == 1
    assert rows[0]["ab_arm"] == "closer"
    assert rows[0]["ab_source"] == "env"
    assert rows[0]["closer_ran"] is True
    assert rows[0]["schema"] == ab_arm.OUTCOME_SCHEMA


def test_outcome_row_written_for_enrolled_harness_run(monkeypatch, tmp_path):
    _env(monkeypatch, "harness")
    fx = _armable_fixture(monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    _go(monkeypatch, tmp_path, fx, canned_extra={"ci-fix": ["edits made"]})
    rows = _rows(tmp_path)
    assert len(rows) == 1
    assert rows[0]["ab_arm"] == "harness"
    assert rows[0]["closer_ran"] is False


def test_outcome_row_not_written_when_not_enrolled(monkeypatch, tmp_path):
    _env(monkeypatch, None, None)
    fx = _armable_fixture(monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    _go(monkeypatch, tmp_path, fx, canned_extra={"ci-fix": ["edits made"]})
    assert not (tmp_path / "state" / ab_arm.OUTCOME_BASENAME).exists()


def test_outcome_row_written_on_failed_terminal(monkeypatch, tmp_path):
    _env(monkeypatch, "harness")
    fx = _armable_fixture(monkeypatch, tmp_path)
    _go(monkeypatch, tmp_path, fx, git_cls=_RebaseConflictGit)
    rows = _rows(tmp_path)
    assert len(rows) == 1
    assert rows[0]["terminal"] == "failed"
    assert rows[0]["error_kind"] == "rebase-conflict"


def test_outcome_row_cost_equals_ledger_sum(monkeypatch, tmp_path):
    _env(monkeypatch, "harness")
    fx = _armable_fixture(monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    _res, result, _out = _go(monkeypatch, tmp_path, fx,
                             canned_extra={"ci-fix": ["edits made"]})
    expected = round(sum(c["cost_usd"] for c in result["ledger"]["calls"]), 4)
    assert _rows(tmp_path)[0]["llm_cost_usd"] == expected


def test_outcome_row_append_failure_keeps_terminal(monkeypatch, tmp_path):
    _env(monkeypatch, "harness")
    fx = _armable_fixture(monkeypatch, tmp_path, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    ok_res, ok_result, _ = _go(monkeypatch, tmp_path, fx,
                               canned_extra={"ci-fix": ["edits made"]})
    bad = tmp_path / "bad"
    bad.mkdir()
    fx2 = _armable_fixture(monkeypatch, bad, ci_fix_rewatch=[{"exit": 0, "failed": []}])
    (bad / "state").mkdir()
    (bad / "state" / ab_arm.OUTCOME_BASENAME).mkdir()
    res, result, out = _go(monkeypatch, bad, fx2, canned_extra={"ci-fix": ["edits made"]})
    assert result["terminal"] == ok_result["terminal"]
    assert runner.exit_code_for(res) == runner.exit_code_for(ok_res)
    with open(os.path.join(out, "audit.log")) as fh:
        assert "outcome-log: append failed" in fh.read()
