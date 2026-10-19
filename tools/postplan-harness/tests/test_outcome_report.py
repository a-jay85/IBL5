import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outcome_log as ol

REPO = Path(__file__).resolve().parents[3]
FIXTURE = Path(__file__).resolve().parent / "fixtures" / "review_outcomes_sample.jsonl"


def _report(capsys, *extra):
    rc = ol.main(["report", f"--log={FIXTURE}", *extra])
    return rc, capsys.readouterr().out


def _json(capsys, *extra):
    rc, out = _report(capsys, "--json", *extra)
    assert rc == 0
    return json.loads(out)


def test_report_full_range_counts(capsys):
    data = _json(capsys)
    assert data["agents"]["A"] == {
        "total": 3, "fixed": 2, "declined-wrong": 1, "intentional": 0, "didnt-matter": 0,
        "wrong_or_didnt_matter_share": 0.333}
    assert data["agents"]["B"] == {
        "total": 2, "fixed": 0, "declined-wrong": 1, "intentional": 0, "didnt-matter": 1,
        "wrong_or_didnt_matter_share": 1.0}
    assert data["agents"]["unknown"] == {
        "total": 1, "fixed": 1, "declined-wrong": 0, "intentional": 0, "didnt-matter": 0,
        "wrong_or_didnt_matter_share": 0.0}
    assert data["total"] == {
        "total": 6, "fixed": 3, "declined-wrong": 2, "intentional": 0, "didnt-matter": 1,
        "wrong_or_didnt_matter_share": 0.5}


def test_report_last_event_wins_per_pr_finding(capsys):
    b = _json(capsys)["agents"]["B"]
    assert b["intentional"] == 0 and b["declined-wrong"] == 1


def test_report_finding_key_includes_pr(capsys):
    assert _json(capsys)["agents"]["A"]["total"] == 3


def test_report_date_range_inclusive_bounds(capsys):
    data = _json(capsys, "--since=2026-09-05", "--until=2026-09-30")
    assert data["total"]["total"] == 2
    assert data["agents"]["B"]["declined-wrong"] == 1
    assert data["agents"]["unknown"]["fixed"] == 1
    data = _json(capsys, "--since=2026-09-05", "--until=2026-09-10")
    assert data["total"]["total"] == 1
    assert list(data["agents"]) == ["unknown"]


@pytest.mark.parametrize("flag", [["--since=09/01/2026"], ["--since", "2026-09-01"], ["--frob"]])
def test_report_rejects_bad_date_and_space_form(flag, capsys):
    assert ol.main(["report", f"--log={FIXTURE}", *flag]) == 2
    assert "usage:" in capsys.readouterr().err


def test_report_missing_log_is_empty_and_exit_zero(tmp_path, capsys):
    rc = ol.main(["report", f"--log={tmp_path / 'nope.jsonl'}", "--json"])
    assert rc == 0
    data = json.loads(capsys.readouterr().out)
    assert data["total"]["total"] == 0
    assert data["total"]["wrong_or_didnt_matter_share"] is None
    assert data["agents"] == {}


def test_report_unreadable_log_exits_one(tmp_path, capsys):
    assert ol.main(["report", f"--log={tmp_path}"]) == 1
    assert "review-outcomes: report failed" in capsys.readouterr().err


def test_report_text_table_header_and_total_row(capsys):
    rc, out = _report(capsys)
    lines = out.splitlines()
    assert rc == 0
    assert lines[0] == "agent  total  fixed  wrong  intentional  didnt-matter  wrong+dm%"
    assert [ln.split()[0] for ln in lines[1:]] == ["A", "B", "unknown", "TOTAL"]
    assert lines[-1].startswith("TOTAL")
    assert lines[-1].split()[-1] == "50.0"


def test_report_wrapper_runs_under_empty_env():
    proc = subprocess.run(
        ["env", "-i", str(REPO / "bin" / "review-outcomes"), "report", f"--log={FIXTURE}", "--json"],
        capture_output=True, text=True)
    assert proc.returncode == 0, proc.stderr
    assert json.loads(proc.stdout)["total"]["total"] == 6
