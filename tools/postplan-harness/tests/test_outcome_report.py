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
_REAL_READER = ol.read_master_subjects


@pytest.fixture(autouse=True)
def _stub_master_subjects(monkeypatch):
    monkeypatch.setattr(ol, "read_master_subjects", lambda *a, **k: [])


_FIXTURE_TABLE = """agent    total  fixed  wrong  intentional  didnt-matter  wrong+dm%
A            3      2      1             0             0       33.3
D            0      0      0             0             0          -
security     0      0      0             0             0          -
B            2      0      1             0             1      100.0
unknown      1      1      0             0             0        0.0
TOTAL        6      3      2             0             1       50.0"""


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
    table = out.split("\n\n")[0].splitlines()
    assert rc == 0
    assert table[0] == "agent    total  fixed  wrong  intentional  didnt-matter  wrong+dm%"
    assert [ln.split()[0] for ln in table[1:]] == ["A", "D", "security", "B", "unknown", "TOTAL"]
    assert table[-1].startswith("TOTAL")
    assert table[-1].split()[-1] == "50.0"
    assert table == _FIXTURE_TABLE.splitlines()


_RANGE = ("--since=2026-09-05", "--until=2026-10-31")

_FIXTURE_RANGE_TABLE = """agent    total  fixed  wrong  intentional  didnt-matter  wrong+dm%
A            1      1      0             0             0        0.0
D            0      0      0             0             0          -
security     0      0      0             0             0          -
B            1      0      1             0             0      100.0
unknown      1      1      0             0             0        0.0
TOTAL        3      2      1             0             0       33.3"""

# Master-code `--json` output for the fixture, full and ranged, captured before the
# cycles and reverts keys existed. The existing keys must stay byte-identical.
_FIXTURE_JSON = '{"agents": {"A": {"declined-wrong": 1, "didnt-matter": 0, "fixed": 2, "intentional": 0, "total": 3, "wrong_or_didnt_matter_share": 0.333}, "B": {"declined-wrong": 1, "didnt-matter": 1, "fixed": 0, "intentional": 0, "total": 2, "wrong_or_didnt_matter_share": 1.0}, "unknown": {"declined-wrong": 0, "didnt-matter": 0, "fixed": 1, "intentional": 0, "total": 1, "wrong_or_didnt_matter_share": 0.0}}, "since": null, "total": {"declined-wrong": 2, "didnt-matter": 1, "fixed": 3, "intentional": 0, "total": 6, "wrong_or_didnt_matter_share": 0.5}, "until": null}'
_FIXTURE_RANGE_JSON = '{"agents": {"A": {"declined-wrong": 0, "didnt-matter": 0, "fixed": 1, "intentional": 0, "total": 1, "wrong_or_didnt_matter_share": 0.0}, "B": {"declined-wrong": 1, "didnt-matter": 0, "fixed": 0, "intentional": 0, "total": 1, "wrong_or_didnt_matter_share": 1.0}, "unknown": {"declined-wrong": 0, "didnt-matter": 0, "fixed": 1, "intentional": 0, "total": 1, "wrong_or_didnt_matter_share": 0.0}}, "since": "2026-09-05", "total": {"declined-wrong": 1, "didnt-matter": 0, "fixed": 2, "intentional": 0, "total": 3, "wrong_or_didnt_matter_share": 0.333}, "until": "2026-10-31"}'


@pytest.mark.parametrize("extra,table,old_json", [
    ((), _FIXTURE_TABLE, _FIXTURE_JSON),
    (_RANGE, _FIXTURE_RANGE_TABLE, _FIXTURE_RANGE_JSON),
])
def test_report_corpus_matches_master_baseline(extra, table, old_json, capsys):
    rc, out = _report(capsys, *extra)
    assert rc == 0
    lines = out.split("\n")
    n = len(table.splitlines())
    assert lines[:n] == table.splitlines()
    assert lines[n] == ""
    new = _json(capsys, *extra)
    new.pop("cycles")
    new.pop("reverts")
    assert json.dumps(new, sort_keys=True) == old_json


def test_report_text_lists_live_agents_with_zero_rows(capsys):
    rc, out = _report(capsys)
    assert rc == 0
    rows = {ln.split()[0]: ln.split() for ln in out.split("\n\n")[0].splitlines()[1:]}
    for agent in ("D", "security"):
        assert rows[agent][1] == "0"
        assert rows[agent][-1] == "-"


def test_report_notes_unknown_rows_on_stderr(capsys):
    rc = ol.main(["report", f"--log={FIXTURE}"])
    captured = capsys.readouterr()
    assert rc == 0
    assert 'outcome(s) under "unknown"' in captured.err
    assert "note: 1 outcome(s)" in captured.err
    assert "outcome(s)" not in captured.out


def test_report_no_unknown_note_when_all_attributed(tmp_path, capsys):
    log = tmp_path / "outcomes.jsonl"
    events = [ol.build_event(pr=7, finding_id=i, agent="A", score=80, outcome="fixed",
                             source="phase4.5", basis="fix-verdict", pipeline="harness")
              for i in (1, 2)]
    assert ol.append_events(str(log), events, lambda msg: None) == 2
    rc = ol.main(["report", f"--log={log}"])
    captured = capsys.readouterr()
    assert rc == 0
    assert "note:" not in captured.err
    assert not any(ln.startswith("unknown") for ln in captured.out.splitlines())


def test_report_wrapper_runs_under_empty_env():
    proc = subprocess.run(
        ["env", "-i", str(REPO / "bin" / "review-outcomes"), "report", f"--log={FIXTURE}", "--json"],
        capture_output=True, text=True)
    assert proc.returncode == 0, proc.stderr
    assert json.loads(proc.stdout)["total"]["total"] == 6


def _p45(pr, ts, source="phase4.5"):
    return {"pr": pr, "findingId": 1, "agent": "A", "outcome": "fixed", "source": source, "ts": ts}


def test_cycles_minute_boundary_is_one_batch():
    c = ol.build_report([_p45(1, "2026-10-01T12:00:59Z"), _p45(1, "2026-10-01T12:01:02Z")])["cycles"]
    assert c["prs"] == 1
    assert c["histogram"]["1"] == 1


def test_cycles_gap_at_threshold_stays_one_batch():
    events = [_p45(1, "2026-10-01T12:00:00Z"), _p45(1, "2026-10-01T12:05:00Z"),
              _p45(2, "2026-10-01T12:00:00Z"), _p45(2, "2026-10-01T12:05:01Z")]
    c = ol.build_report(events)["cycles"]
    assert c["histogram"] == {"1": 1, "2": 1, "3": 0, "4+": 0}


def test_cycles_ignore_sweep_events():
    c = ol.build_report([_p45(1, "2026-10-01T12:00:00Z", source="sweep")])["cycles"]
    assert c["prs"] == 0
    assert c["mean"] is None and c["median"] is None and c["max"] is None


def test_cycles_range_uses_last_phase45_day():
    events = [_p45(1, "2026-09-30T12:00:00Z"), _p45(1, "2026-10-02T12:00:00Z")]
    c = ol.build_report(events, since="2026-10-01")["cycles"]
    assert c["prs"] == 1 and c["max"] == 2
    assert ol.build_report(events, until="2026-09-30")["cycles"]["prs"] == 0


def test_cycles_histogram_and_stats():
    events = []
    for pr, n in ((1, 1), (2, 2), (3, 2), (4, 5)):
        for i in range(n):
            events.append(_p45(pr, f"2026-10-01T{10 + i:02d}:00:00Z"))
    c = ol.build_report(events)["cycles"]
    assert c["histogram"] == {"1": 1, "2": 2, "3": 0, "4+": 1}
    assert c["mean"] == 2.5
    assert c["median"] == 2
    assert c["max"] == 5
    # A PR with exactly 4 cycles lands in the 4+ bucket and a 3-cycle PR in bucket 3.
    # The 5-cycle PR above sits in 4+ under any bound from 4 to 5, so it cannot see the bound.
    edge = []
    for pr, n in ((5, 3), (6, 4)):
        for i in range(n):
            edge.append(_p45(pr, f"2026-10-01T{10 + i:02d}:00:00Z"))
    assert ol.build_report(edge)["cycles"]["histogram"] == {"1": 0, "2": 0, "3": 1, "4+": 1}


def test_cycles_skip_unparseable_ts():
    events = [_p45(1, "garbage"), _p45(1, "2026-10-01T12:00:00Z")]
    c = ol.build_report(events)["cycles"]
    assert c["prs"] == 1 and c["max"] == 1


def test_report_text_shows_cycles_section(capsys):
    rc, out = _report(capsys)
    assert rc == 0
    block = out.split("\n\n")[1].splitlines()
    assert block[0].startswith("cycles-to-pass:")
    assert any(ln.startswith("note: cycles = phase4.5") for ln in block)


def test_reverts_subject_rule_near_misses():
    subjects = [("2026-10-01", x) for x in (
        'Revert "feat: x"', 'Revert "Revert "fix: y""', 'Revert "chore: z" (#123)',
        "revert: x", "Revert foo", 'Reverted "x"', 'fix: Revert "x" handling')]
    r = ol.build_reverts(subjects)
    assert r["months"]["2026-10"] == {"commits": 7, "reverts": 3}
    assert r["total"] == 3


def test_reverts_bucket_by_month_and_range():
    subjects = [(d, 'Revert "x"') for d in ("2026-09-30", "2026-10-01", "2026-10-31", "2026-11-01")]
    r = ol.build_reverts(subjects, since="2026-10-01", until="2026-10-31")
    assert list(r["months"]) == ["2026-10"]
    assert r["months"]["2026-10"]["reverts"] == 2


def test_report_git_failure_degrades_loudly(monkeypatch, capsys):
    def boom(*a, **k):
        raise RuntimeError("boom")

    monkeypatch.setattr(ol, "read_master_subjects", boom)
    rc = ol.main(["report", f"--log={FIXTURE}", "--json"])
    captured = capsys.readouterr()
    data = json.loads(captured.out)
    assert rc == 0
    assert data["reverts"] is None
    assert data["total"]["total"] == 6
    assert "revert count unavailable (boom)" in captured.err
    rc, out = _report(capsys)
    assert rc == 0
    assert "reverts: revert count unavailable" in out


def test_report_unreadable_log_still_exits_one_with_git_stubbed(monkeypatch, tmp_path):
    def boom(*a, **k):
        raise RuntimeError("boom")

    monkeypatch.setattr(ol, "read_master_subjects", boom)
    assert ol.main(["report", f"--log={tmp_path}"]) == 1


def _raise(exc):
    def fake(*a, **k):
        raise exc

    return fake


@pytest.mark.parametrize("fake", [
    _raise(FileNotFoundError("git")),
    _raise(subprocess.TimeoutExpired(["git"], 20)),
    lambda *a, **k: subprocess.CompletedProcess([], 128, "", "fatal: bad revision"),
], ids=["missing-git", "timeout", "nonzero"])
def test_read_master_subjects_failures_raise(monkeypatch, fake):
    monkeypatch.setattr(ol.subprocess, "run", fake)
    with pytest.raises(RuntimeError):
        _REAL_READER()


def test_read_master_subjects_parses_rows_utc(monkeypatch):
    seen = {}

    def fake(argv, **kwargs):
        seen["argv"], seen["kwargs"] = argv, kwargs
        return subprocess.CompletedProcess(
            argv, 0, '2026-10-01\tRevert "x"\nno-tab-line\n2026-10-02\tfix: a\tb\n', "")

    monkeypatch.setattr(ol.subprocess, "run", fake)
    assert _REAL_READER() == [("2026-10-01", 'Revert "x"'), ("2026-10-02", "fix: a\tb")]
    assert "--date=format-local:%Y-%m-%d" in seen["argv"]
    assert seen["kwargs"]["env"]["TZ"] == "UTC"
