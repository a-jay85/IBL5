"""End to end: harness-rendered comment body -> real shell reader -> outcome event.

The writer (labels.render_body) and the reader (prf_review_threads in
bin/lib/post-review-findings.sh) are both real. Only `gh` is shimmed.
"""
import json
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outcome_log as ol
from harness.labels import parse_label, render_body
from harness.state import Finding

PRF_LIB = Path(__file__).resolve().parents[3] / "bin/lib/post-review-findings.sh"

_SHIM = r"""#!/usr/bin/env bash
# gh api graphql -F k=v -f query=... --jq FILTER
if [ "$1" = "api" ] && [ "$2" = "graphql" ]; then
    jqf=""
    for ((i=1; i<=$#; i++)); do
        case "${!i}" in
            --jq) j=$((i+1)); jqf="${!j}" ;;
        esac
    done
    if [ -n "$jqf" ]; then jq -rc "$jqf" "$GH_THREADS_FIXTURE"; else cat "$GH_THREADS_FIXTURE"; fi
    exit 0
fi
exit 1
"""


def _finding(**kw):
    base = dict(source="code-review", agent="A", path="a.php", line=3, body="Bug body",
                score=85, label="important")
    base.update(kw)
    return Finding(**base)


def _read_row(tmp_path, body, *, resolved=False, author=None):
    assert PRF_LIB.exists(), PRF_LIB
    fixture = tmp_path / "threads.json"
    fixture.write_text(json.dumps({"data": {"repository": {"pullRequest": {"reviewThreads": {"nodes": [{
        "id": "PRRT_a", "isResolved": resolved, "isOutdated": False, "path": "a.php", "line": 3,
        "comments": {"nodes": [{"databaseId": 501, "body": body, "author": author}]},
        "lastComment": {"nodes": [{"body": body, "author": author}]},
    }]}}}}}))
    shim = tmp_path / "gh"
    shim.write_text(_SHIM)
    shim.chmod(0o755)
    proc = subprocess.run(
        ["bash", "-c", 'source "$1"; prf_review_threads 42', "_", str(PRF_LIB)],
        env={**os.environ, "GH_CMD": str(shim), "GH_THREADS_FIXTURE": str(fixture),
             "REPO_SLUG": "a-jay85/IBL5"},
        capture_output=True, text=True, check=True)
    return json.loads(proc.stdout.splitlines()[0])


def test_harness_body_agent_reaches_verdict_event(tmp_path):
    row = _read_row(tmp_path, render_body(_finding(), 1000))
    assert row["agent"] == "A"
    event = ol.event_from_verdict(pr=42, thread=row, verdict="FIX", category=None, reason=None,
                                  sha=None, pipeline="harness")
    assert event["agent"] == "A"


def test_harness_body_agent_reaches_sweep_event(tmp_path):
    row = _read_row(tmp_path, render_body(_finding(), 1000), resolved=True,
                    author={"login": "a-jay85", "__typename": "User"})
    events = ol.sweep_events([row], 42, set(), pipeline="harness")
    assert len(events) == 1
    assert events[0]["agent"] == "A"


@pytest.mark.parametrize("agent", ["A -->x", "x" * 33, "", "a b"])
def test_hostile_agent_id_writes_no_marker(tmp_path, agent):
    body = render_body(_finding(agent=agent), 1000)
    assert "<!-- agent:" not in body
    assert _read_row(tmp_path, body)["agent"] is None


def test_agent_id_at_length_cap_writes_marker(tmp_path):
    agent = "A" * 32
    body = render_body(_finding(agent=agent), 1000)
    assert body.splitlines()[2] == f"<!-- agent: {agent} -->"
    assert _read_row(tmp_path, body)["agent"] == agent


def test_agent_marker_survives_header_cap(tmp_path):
    f = _finding(other_instances=["classes/Some/Long/Path.php:123"] * 60)
    body = render_body(f, 1000)
    assert body.splitlines()[2] == "<!-- agent: A -->"
    assert _read_row(tmp_path, body)["agent"] == "A"


def test_label_marker_stays_first_line():
    body = render_body(_finding(), 1000)
    assert body.splitlines()[0] == "<!-- label: important -->"
    assert parse_label(body) == "important"


def test_report_on_new_rows_has_no_unknown_bucket(tmp_path):
    events = []
    for comment_id, agent in ((501, "A"), (502, "security")):
        row = _read_row(tmp_path, render_body(_finding(agent=agent), 1000))
        row["commentId"] = comment_id  # the fixture serves one comment id; findings need distinct ids
        events.append(ol.event_from_verdict(pr=42, thread=row, verdict="FIX", category=None,
                                            reason=None, sha=None, pipeline="harness"))
    log = tmp_path / "outcomes.jsonl"
    assert ol.append_events(str(log), events, lambda msg: None) == 2
    wrapper = Path(__file__).resolve().parents[3] / "bin" / "review-outcomes"
    proc = subprocess.run(["env", "-i", str(wrapper), "report", f"--log={log}"],
                          capture_output=True, text=True)
    assert proc.returncode == 0, proc.stderr
    rows = {ln.split()[0]: ln.split() for ln in proc.stdout.split("\n\n")[0].splitlines()[1:]}
    assert rows["A"][1] == "1"
    assert rows["security"][1] == "1"
    assert "unknown" not in rows
    assert "note:" not in proc.stderr
