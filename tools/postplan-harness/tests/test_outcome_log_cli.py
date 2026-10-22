import io
import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outcome_log as ol


def _write_jsonl(path, rows):
    path.write_text("".join(json.dumps(r) + "\n" for r in rows))


def _record_inputs(tmp_path):
    verdicts = tmp_path / "verdicts.jsonl"
    threads = tmp_path / "threads.jsonl"
    _write_jsonl(verdicts, [
        {"commentId": 11, "verdict": "FIX", "reason": "cached", "sha": "abc123"},
        {"commentId": 12, "verdict": "DECLINE", "reason": "deliberate", "category": "intentional"},
        {"commentId": 99, "verdict": "FIX", "reason": "fabricated id", "sha": "zzz"},
        {"commentId": 11, "verdict": "MAYBE", "reason": "unknown verdict"},
    ])
    _write_jsonl(threads, [
        {"commentId": 11, "score": 85, "agent": "B"},
        {"commentId": 12, "score": 70, "agent": "A"},
    ])
    return verdicts, threads


def test_cli_record_joins_threads_and_logs(tmp_path):
    verdicts, threads = _record_inputs(tmp_path)
    log = tmp_path / "o.jsonl"
    rc = ol.main(["record", "--pr=12", f"--verdicts={verdicts}", f"--threads={threads}",
                  f"--log={log}"])
    assert rc == 0
    events = ol.read_events(str(log))
    assert [(e["findingId"], e["agent"], e["score"], e["outcome"]) for e in events] == [
        (11, "B", 85, "fixed"), (12, "A", 70, "intentional")]
    assert events[0]["sha"] == "abc123"
    assert all(e["pipeline"] == "skill" and e["source"] == "phase4.5" and e["pr"] == 12
               for e in events)


def test_cli_record_missing_input_returns_zero(tmp_path, capsys):
    _, threads = _record_inputs(tmp_path)
    log = tmp_path / "o.jsonl"
    rc = ol.main(["record", "--pr=12", f"--verdicts={tmp_path / 'nope.jsonl'}",
                  f"--threads={threads}", f"--log={log}"])
    assert rc == 0
    assert not log.exists()
    assert "review-outcomes: record failed" in capsys.readouterr().err


@pytest.mark.parametrize("argv", [
    ["record", "--pr", "12"],
    ["record", "--bogus=1"],
    ["frobnicate"],
    [],
    ["sweep", "--pr=x"],
    ["sweep", "--pr=5", "--pipeline=other"],
])
def test_cli_rejects_space_form_and_unknown_flag(argv, capsys):
    assert ol.main(argv) == 2
    assert "usage:" in capsys.readouterr().err


def test_cli_sweep_reads_stdin(tmp_path, monkeypatch):
    row = {"commentId": 5, "score": 80, "agent": "D", "authorLogin": "a-jay85",
           "authorType": "User", "isResolved": False, "isOutdated": True}
    monkeypatch.setattr(sys, "stdin", io.StringIO(json.dumps(row) + "\nnot json\n"))
    log = tmp_path / "o.jsonl"
    assert ol.main(["sweep", "--pr=3", f"--log={log}"]) == 0
    events = ol.read_events(str(log))
    assert [(e["findingId"], e["source"], e["basis"], e["agent"]) for e in events] == [
        (5, "sweep", "outdated", "D")]


def test_cli_log_env_override_empty_is_unset(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-q", str(repo)], check=True)
    path = ol.resolve_log_path(str(repo), env={ol.ENV_OVERRIDE: ""})
    expected = os.path.join(os.path.realpath(str(repo)), "tools", "postplan-harness", "out",
                            "state", ol.LOG_BASENAME)
    assert os.path.realpath(os.path.dirname(path)) == os.path.dirname(expected)
    assert os.path.basename(path) == ol.LOG_BASENAME


def test_cli_record_unknown_comment_id_writes_nothing(tmp_path):
    verdicts = tmp_path / "verdicts.jsonl"
    threads = tmp_path / "threads.jsonl"
    _write_jsonl(verdicts, [{"commentId": 99, "verdict": "FIX", "reason": "fabricated", "sha": "z"}])
    _write_jsonl(threads, [{"commentId": 11, "score": 85, "agent": "B"}])
    log = tmp_path / "o.jsonl"
    seeded = json.dumps(ol.build_event(pr=12, finding_id=1, agent="A", score=80, outcome="fixed",
                                       source="phase4.5", basis="fix-verdict", pipeline="skill")) + "\n"
    log.write_text(seeded)
    rc = ol.main(["record", "--pr=12", f"--verdicts={verdicts}", f"--threads={threads}",
                  f"--log={log}"])
    assert rc == 0
    assert log.read_text() == seeded


def test_cli_record_forwards_origin_and_defaults_pre_existing(tmp_path):
    verdicts = tmp_path / "verdicts.jsonl"
    threads = tmp_path / "threads.jsonl"
    _write_jsonl(verdicts, [
        {"commentId": 11, "verdict": "FIX", "reason": "cached", "sha": "abc123", "origin": "same-run"},
        {"commentId": 12, "verdict": "FIX", "reason": "old-shape line", "sha": "def456"},
    ])
    _write_jsonl(threads, [
        {"commentId": 11, "score": 85, "agent": "B"},
        {"commentId": 12, "score": 70, "agent": "A"},
    ])
    log = tmp_path / "o.jsonl"
    rc = ol.main(["record", "--pr=12", f"--verdicts={verdicts}", f"--threads={threads}",
                  f"--log={log}"])
    assert rc == 0
    events = ol.read_events(str(log))
    assert [(e["findingId"], e["origin"]) for e in events] == [(11, "same-run"), (12, "pre-existing")]
