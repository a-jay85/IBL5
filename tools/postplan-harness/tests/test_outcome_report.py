import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outcome_log as ol
from harness.adapters.ghad import RecordingGh

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
    new.pop("blamed_fixes", None)
    new.pop("blamed_fixes_root", None)
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


# -- later fixes blamed on auto-merged PRs ---------------------------------

_DAY = 86400
_T0 = 1788220800  # 2026-09-01T00:00:00Z


def _git_c(repo, msg, ts, files):
    for name, text in files.items():
        p = repo / name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text)
    env = {**os.environ, "GIT_COMMITTER_DATE": f"{ts} +0000", "GIT_AUTHOR_DATE": f"{ts} +0000"}
    subprocess.run(["git", "-C", str(repo), "add", "-A"], check=True, env=env)
    subprocess.run(["git", "-C", str(repo), "-c", "user.email=t@t", "-c", "user.name=t",
                    "-c", "commit.gpgsign=false", "commit", "-q", "--no-verify", "-m", msg],
                   check=True, env=env)
    return subprocess.run(["git", "-C", str(repo), "rev-parse", "HEAD"], check=True,
                          capture_output=True, text=True).stdout.strip()


def _blame_repo(tmp_path, fix_subject="fix: off by one (#14)", fix_days=5):
    """PR #12 adds a.txt, human-merged #13 adds b.txt, one commit then rewrites both."""
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    base = _git_c(repo, "chore: base (#1)", _T0, {"keep.txt": "k\n"})
    pr12 = _git_c(repo, "feat: add a (#12)", _T0 + 9 * _DAY, {"a.txt": "a1\na2\na3\n"})
    pr13 = _git_c(repo, "feat: add b (#13)", _T0 + 9 * _DAY + 3600, {"b.txt": "b1\nb2\nb3\n"})
    fix = _git_c(repo, fix_subject, _T0 + (9 + fix_days) * _DAY,
                 {"a.txt": "a1\nA2\na3\n", "b.txt": "b1\nB2\nb3\n"})
    subprocess.run(["git", "-C", str(repo), "update-ref", "refs/remotes/origin/master", "HEAD"],
                   check=True)
    return {"root": repo, "base": base, "pr12": pr12, "pr13": pr13, "fix": fix}


def _actions(tmp_path, *prs, executed=True):
    out = tmp_path / "out" / "run1"
    gh = RecordingGh(str(out))
    for pr in prs:
        if executed:
            gh.record("pr_merge_auto", pr=pr, args="--squash --auto", executed=True)
        else:
            gh.record("pr_merge_auto", pr=pr, args="--squash --auto")
    return tmp_path / "out"


_RANGE = ("2026-09-01", "2026-10-31")


def test_blamed_fixes_counts_fix_blaming_auto_merged_pr(tmp_path):
    r = _blame_repo(tmp_path)
    out_root = _actions(tmp_path, 12)
    merged = ol.auto_merged_prs(out_root)
    assert merged == {12}
    b = ol.build_blamed_fixes(r["root"], *_RANGE, merged)
    assert b["auto_merged"] == 1
    assert b["fix_commits"] == 1
    assert [x["pr"] for x in b["blamed"]] == [12]
    assert b["blamed"][0]["fixes"][0]["sha"] == r["fix"]
    text = "\n".join(ol._format_blamed_fixes(b))
    assert "1 of 1" in text
    assert "#12" in text
    assert "#13" not in text
    assert f"#12 {r['pr12'][:7]} <- {r['fix'][:7]} fix: off by one (#14)" in text


def test_blamed_fixes_ignores_chore_subject(tmp_path):
    r = _blame_repo(tmp_path, fix_subject="chore: tidy (#14)")
    b = ol.build_blamed_fixes(r["root"], *_RANGE, {12})
    assert b["blamed"] == []
    assert b["fix_commits"] == 0


def test_blamed_fixes_ignores_human_merged_pr(tmp_path):
    r = _blame_repo(tmp_path)
    b = ol.build_blamed_fixes(r["root"], *_RANGE, {99})
    assert b == {"auto_merged": 0, "blamed": [], "fix_commits": 0}


def test_blamed_fixes_ignores_unexecuted_merge_intent(tmp_path):
    out_root = _actions(tmp_path, 12, executed=False)
    assert ol.auto_merged_prs(out_root) == set()
    (out_root / "run1" / "actions.jsonl").write_text(
        '{"action": "pr_merge_auto", "pr": 12, "executed": "yes"}\nnot json\n'
        '{"action": "pr_merge_auto", "pr": 12, "executed": false}\n'
        '{"action": "pr_comment", "pr": 12, "executed": true}\n')
    assert ol.auto_merged_prs(out_root) == set()


def test_blamed_fixes_ignores_fix_outside_window(tmp_path):
    r = _blame_repo(tmp_path, fix_days=31)
    b = ol.build_blamed_fixes(r["root"], *_RANGE, {12})
    assert b["auto_merged"] == 1
    assert b["blamed"] == []


def test_blamed_fixes_reports_missing_action_logs(tmp_path):
    (tmp_path / "out" / "cache").mkdir(parents=True)
    (tmp_path / "out" / "cache" / "actions.jsonl").write_text(
        '{"action": "pr_merge_auto", "pr": 12, "executed": true}\n')
    assert ol.auto_merged_prs(tmp_path / "out") is None
    text = "\n".join(ol._format_blamed_fixes(None, str(tmp_path / "out")))
    assert "no auto-merge records under" in text
    assert str(tmp_path / "out") in text
    assert "0 of 0" not in text


def test_pr_suffix_regex_takes_trailing_number_only():
    assert ol._pr_number("fix: handle (#12) in title (#13)") == 13
    assert ol._pr_number("feat: foo #12") is None
    assert ol._pr_number("feat: (#12) tail") is None
    assert ol._pr_number("feat: foo (#12)  ") == 12
    assert ol._PR_SUFFIX_RE.search("fix: handle (#12) in title (#13)").group(1) == "13"
    assert ol._PR_SUFFIX_RE.search("feat: (#12) tail") is None


def test_format_report_places_blamed_fixes_after_reverts():
    report = {"agents": {}, "total": ol._agent_row({}),
              "reverts": {"months": {}, "commits": 0, "total": 0},
              "blamed_fixes": {"auto_merged": 2, "blamed": [], "fix_commits": 0}}
    out = ol.format_report(report)
    assert out.index("reverts on origin/master") < out.index(
        "later fixes blamed on auto-merged PRs: 0 of 2")
    err = ol.format_report({**report, "blamed_fixes": {"error": "boom"}})
    assert "later fixes blamed on auto-merged PRs: unavailable (boom)" in err
