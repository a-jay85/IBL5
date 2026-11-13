"""runner._plan_checks_step (ADR-0244): block write, label, and the Plan checks CI result."""
from __future__ import annotations

import stat
from types import SimpleNamespace

import runner
from harness.classify import PLAN_CHECKS_BEGIN, PLAN_CHECKS_END

BLOCK = f"{PLAN_CHECKS_BEGIN}\nplan-checks v1 sha256={'a' * 64} rows=1 mutations=0\n{PLAN_CHECKS_END}\n"


class FakeGh:
    def __init__(self, checks=None, label_ok=True, drop_edit=False, body="## Summary\nx\n"):
        self.body = body
        self.checks = checks or []
        self.label_ok = label_ok
        self.drop_edit = drop_edit
        self.calls: list[str] = []

    def pr_body_fresh(self):
        self.calls.append("read")
        return self.body

    def pr_edit_body(self, pr, body):
        self.calls.append("edit")
        if not self.drop_edit:
            self.body = body

    def pr_label_add_checked(self, pr, label):
        self.calls.append(f"label:{label}")
        return self.label_ok

    def pr_checks_json(self, pr):
        self.calls.append("checks")
        return list(self.checks)


def _encoder(tmp_path, rc=0, out=BLOCK):
    p = tmp_path / "encoder"
    out_file = tmp_path / "encoder.out"
    out_file.write_text(out)
    p.write_text(f"#!/bin/sh\ncat '{out_file}'\nexit {rc}\n")
    p.chmod(p.stat().st_mode | stat.S_IEXEC)
    return str(p)


def _plan(tmp_path):
    f = tmp_path / "plan.md"
    f.write_text("# plan\n")
    return SimpleNamespace(found=True, path=str(f))


def _step(tmp_path, gh, **kw):
    logs: list[str] = []
    out = runner._plan_checks_step(gh, kw.pop("plan", _plan(tmp_path)), str(tmp_path), 7,
                                   True, logs.append, None,
                                   encoder=kw.pop("encoder", None) or _encoder(tmp_path))
    return out, logs


def test_plan_checks_step_holds_on_failed_ci_check(tmp_path):
    gh = FakeGh(checks=[{"name": "Plan checks", "state": "FAILURE"},
                        {"name": "PHPUnit", "state": "SUCCESS"}])
    out, _ = _step(tmp_path, gh)
    assert out == ("Plan checks CI job concluded FAILURE",)


def test_plan_checks_step_passes_on_pending_check(tmp_path):
    gh = FakeGh(checks=[{"name": "Plan checks", "state": "PENDING"}])
    out, _ = _step(tmp_path, gh)
    assert out == ()
    assert gh.body.count(PLAN_CHECKS_BEGIN) == 1


def test_plan_checks_step_holds_when_label_add_fails(tmp_path):
    gh = FakeGh(label_ok=False)
    out, _ = _step(tmp_path, gh)
    assert out == ("could not add the plan-checks label",)


def test_plan_checks_step_holds_when_block_missing_after_write(tmp_path):
    gh = FakeGh(drop_edit=True)
    out, _ = _step(tmp_path, gh)
    assert "plan-checks block missing or stale in the PR body" in out


def test_plan_checks_step_holds_when_encoder_fails(tmp_path):
    gh = FakeGh()
    out, _ = _step(tmp_path, gh, encoder=_encoder(tmp_path, rc=3, out=""))
    assert out == ("plan-checks block could not be built (exit 3)",)
    assert "edit" not in gh.calls


def test_plan_checks_step_writes_block_before_label(tmp_path):
    gh = FakeGh()
    out, _ = _step(tmp_path, gh)
    assert out == ()
    assert gh.calls.index("edit") < gh.calls.index("label:plan-checks")
    # The confirming re-read also comes before the label.
    reads = [i for i, c in enumerate(gh.calls) if c == "read"]
    assert len(reads) == 2 and reads[1] < gh.calls.index("label:plan-checks")


def test_plan_checks_step_returns_empty_without_plan(tmp_path):
    gh = FakeGh()
    out, _ = _step(tmp_path, gh, plan=SimpleNamespace(found=False, path=None))
    assert out == ()
    assert gh.calls == []


def test_plan_checks_step_without_pr_returns_none(tmp_path):
    gh = FakeGh()
    out = runner._plan_checks_step(gh, _plan(tmp_path), str(tmp_path), None, True,
                                   lambda m: None, None, encoder=_encoder(tmp_path))
    assert out is None and gh.calls == []


def test_plan_checks_step_replay_reads_fixture():
    assert runner._plan_checks_step(None, None, None, None, False, lambda m: None,
                                    {"plan_checks_holding": ["x"]}) == ("x",)
    assert runner._plan_checks_step(None, None, None, None, False, lambda m: None, None) == ()
