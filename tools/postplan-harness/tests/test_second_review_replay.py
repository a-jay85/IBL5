"""Replay of PR #3874's seat inputs through build_prompt.

On #3874 the seat rejected with "I can't find the plan or the verdict's reasons". This
replays that PR's title, body, diff and NOT READY verdict (committed under
fixtures/replay_3874/) with a captured owner request, and checks that the seat prompt now
carries both the request and the verdict's blocking finding.
"""
import json
import os
import subprocess
import sys

from harness import fidelity, owner_intent, second_review

from tests.test_capture_intent import SCRIPT, _write_session

FIXTURES = os.path.join(os.path.dirname(__file__), "fixtures", "replay_3874")
BRANCH = "live-pr-burndown-merge-line-rebase"
SESSION = "3874abcd-0000-4000-8000-000000003874"
TYPED = ("Rebase pr-burndown onto the merge line; "
         "edit only .claude/skills/pr-burndown/SKILL.md.")
HOLDS = {12: "plan-fidelity verdict NOT READY"}


def _fixture(name):
    with open(os.path.join(FIXTURES, name), encoding="utf-8") as fh:
        return fh.read()


def test_replay_3874_seat_gets_findings_and_owner_request(tmp_path, monkeypatch):
    home = tmp_path / "home"
    home.mkdir()
    monkeypatch.setenv("HOME", str(home))
    _write_session(home, [{
        "type": "user", "sessionId": SESSION, "timestamp": "2026-11-11T09:00:00Z",
        "message": {"role": "user",
                    "content": TYPED + "\n<system-reminder>x</system-reminder>\n"}}], sid=SESSION)
    env = {"HOME": str(home), "PATH": os.environ.get("PATH", "/usr/bin:/bin")}
    r = subprocess.run([sys.executable, SCRIPT, "--branch", BRANCH, "--session", SESSION, "1"],
                       env=env, cwd=str(tmp_path), capture_output=True, text=True)
    assert r.returncode == 0, r.stderr

    pr = json.loads(_fixture("pr.json"))
    diff = _fixture("diff.patch")
    verdict = os.path.join(FIXTURES, "verdict.md")
    findings = tuple(fidelity._verdict_findings(verdict))
    assert findings

    reason = second_review.reason_text(None, pr["title"], pr["body"],
                                       owner_intent.seat_section(BRANCH))
    prompt = second_review.build_prompt(diff, reason, HOLDS, {12: findings})
    assert prompt is not None
    head = prompt.split("## Diff", 1)[0]
    assert "## Owner request" in head
    assert TYPED in head
    assert "## Blocking findings" in head
    first_line = next(ln for ln in findings[0].splitlines() if ln.strip())
    assert first_line in head
    assert "system-reminder" not in prompt

    baseline = second_review.build_prompt(
        diff, second_review.reason_text(None, pr["title"], pr["body"]), HOLDS)
    assert baseline is not None
    assert "## Owner request" not in baseline.split("## Diff", 1)[0]
    assert "## Blocking findings" not in baseline
