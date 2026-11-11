"""harness/owner_intent.py: the verified reader of the bin/capture-intent intent file."""
import hashlib
import json
import os
import subprocess
import sys

import pytest

from harness import owner_intent

from tests.test_capture_intent import SCRIPT, SID, _user, _write_session

BRANCH = "some-branch"
BODY = ("## Summary\n\nDoes the thing.\n\n"
        "## Declared scope\n\n- `bin/foo`\n- `bin/bar`\n\n"
        "## Test plan\n\n- [x] ran it\n")


@pytest.fixture
def home(tmp_path, monkeypatch):
    h = tmp_path / "home"
    h.mkdir()
    monkeypatch.setenv("HOME", str(h))
    return h


def _capture(home, texts, *picks, branch=BRANCH):
    """Write a valid intent file through the real writer."""
    _write_session(home, [_user(t) for t in texts])
    env = {"HOME": str(home), "PATH": os.environ.get("PATH", "/usr/bin:/bin"),
           "CLAUDE_CODE_SESSION_ID": SID}
    r = subprocess.run([sys.executable, SCRIPT, "--branch", branch,
                        *[str(p) for p in (picks or range(1, len(texts) + 1))]],
                       env=env, cwd=str(home), capture_output=True, text=True)
    assert r.returncode == 0, r.stderr
    return owner_intent.intent_path(branch)


def _forge(home, data, branch=BRANCH):
    """Write an intent file with a valid hash over an arbitrary payload."""
    payload = json.dumps(data, ensure_ascii=False, separators=(",", ":"))
    path = owner_intent.intent_path(branch)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    digest = hashlib.sha256(payload.encode()).hexdigest()
    with open(path, "w") as fh:
        fh.write(f"<!-- capture-intent v1 sha256={digest} -->\n{payload}\n")
    return path


def _turn(text, **kw):
    t = {"index": 1, "timestamp": "2026-11-11T10:00:00Z", "text": text,
         "redactions": 0, "stripped": []}
    t.update(kw)
    return t


def test_writer_reader_agree(home):
    _capture(home, ["please ship the seat fix"])
    data = owner_intent.load(BRANCH)
    assert data is not None
    assert data["turns"][0]["text"] == "please ship the seat fix"
    assert "please ship the seat fix" in owner_intent.seat_section(BRANCH)


def test_tampered_hash(home):
    path = _capture(home, ["please ship the seat fix"])
    assert owner_intent.load(BRANCH) is not None
    original = open(path).read()
    with open(path, "w") as fh:
        fh.write(original.replace("ship the", "ship  the", 1))
    assert owner_intent.load(BRANCH) is None
    assert owner_intent.seat_section(BRANCH) == ""
    with open(path, "w") as fh:
        fh.write(original.replace("capture-intent v1", "capture-intent v2", 1))
    assert owner_intent.load(BRANCH) is None


def test_branch_mismatch(home):
    path = _capture(home, ["please ship the seat fix"], branch="other-branch")
    assert owner_intent.load("other-branch") is not None
    os.replace(path, owner_intent.intent_path(BRANCH))
    assert owner_intent.load(BRANCH) is None
    assert owner_intent.load("other-branch") is None
    _forge(home, {"version": 1, "session": SID, "branch": "a/../b", "turns": [_turn("x")]},
           branch="a-b")
    assert owner_intent.load("a/../b") is None


def test_load_rejects_over_cap(home):
    _forge(home, {"version": 1, "session": SID, "branch": BRANCH,
                  "turns": [_turn("x" * 2000), _turn("y" * 2001, index=2)]})
    assert owner_intent.load(BRANCH) is None
    assert owner_intent.seat_section(BRANCH) == ""
    _forge(home, {"version": 1, "session": SID, "branch": BRANCH,
                  "turns": [_turn("x" * 2000), _turn("y" * 2000, index=2)]})
    assert owner_intent.load(BRANCH) is not None


def test_missing_file_is_none(home):
    assert owner_intent.load(BRANCH) is None
    assert owner_intent.seat_section(BRANCH) == ""
    assert owner_intent.pr_section(BRANCH) == ""
    path = _capture(home, ["please ship the seat fix"])
    os.chmod(path, 0)
    try:
        if os.access(path, os.R_OK):
            pytest.skip("running as a user who can read a chmod 000 file")
        assert owner_intent.load(BRANCH) is None
        assert owner_intent.seat_section(BRANCH) == ""
        assert owner_intent.pr_section(BRANCH) == ""
    finally:
        os.chmod(path, 0o600)


def test_pr_section_provenance(home):
    _capture(home, ["line one\n\nline three"])
    section = owner_intent.pr_section(BRANCH)
    assert section.startswith("## Owner request\n\n" + owner_intent.PR_MARKER)
    assert f"session `{SID}`" in section
    assert "The text below is the owner's typed text, byte for byte." in section
    assert "> line one\n>\n> line three" in section
    _forge(home, {"version": 1, "session": SID, "branch": BRANCH,
                  "turns": [_turn("a [REDACTED] b", redactions=1, stripped=["system-reminder"])]})
    section = owner_intent.pr_section(BRANCH)
    assert "byte for byte, except for these changes." in section
    assert "It removed `system-reminder` blocks." in section
    assert "It replaced 1 spans with [REDACTED]" in section


def test_upsert_after_summary(home):
    _capture(home, ["please ship the seat fix"])
    section = owner_intent.pr_section(BRANCH)
    out = owner_intent.upsert_owner_request(BODY, section)
    summary = out.index("## Summary")
    owner = out.index("## Owner request")
    scope = out.index("## Declared scope")
    assert summary < owner < scope
    assert out[scope:] == BODY[BODY.index("## Declared scope"):]
    assert out.endswith("## Test plan\n\n- [x] ran it\n")
    assert owner_intent.upsert_owner_request(out, section) == out
    appended = owner_intent.upsert_owner_request("no summary here\n", section)
    assert appended.startswith("no summary here\n\n## Owner request")
    assert owner_intent.upsert_owner_request(appended, section) == appended


def test_strips_stale_harness_section(home):
    stale = ("## Owner request\n\n" + owner_intent.PR_MARKER + "\n\nold quoted text\n")
    body = owner_intent.upsert_owner_request(BODY, stale)
    assert "old quoted text" in body
    out = owner_intent.upsert_owner_request(body, owner_intent.pr_section(BRANCH))
    assert "## Owner request" not in out
    assert "old quoted text" not in out
    assert out == BODY


def test_keeps_model_section_without_file(home):
    body = BODY.replace("## Declared scope", "## Owner request\n\nThe owner asked for X.\n\n"
                        "## Declared scope", 1)
    assert owner_intent.upsert_owner_request(body, owner_intent.pr_section(BRANCH)) == body


RUNNER = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runner.py")


def _line_index(lines, needle):
    hits = [i for i, ln in enumerate(lines) if needle in ln]
    assert len(hits) == 1, (needle, hits)
    return hits[0]


def test_runner_wires_owner_section():
    lines = open(RUNNER).read().splitlines()
    create = _line_index(lines, 'pr = gh.pr_create(copy["title"], create_body, pr_base)')
    phase6 = _line_index(lines, 'body = _merge_danger_body(body, worktree, diff, log, "phase6")')
    upsert_create = _line_index(
        lines, "create_body = owner_intent.upsert_owner_request(create_body, _owner)")
    upsert_phase6 = _line_index(lines, "body = owner_intent.upsert_owner_request(body, _owner)")
    assert upsert_create < create and create - upsert_create < 10
    assert upsert_phase6 < phase6 and phase6 - upsert_phase6 < 5
    gates = [i for i, ln in enumerate(lines)
             if '_owner = "" if plan.found else owner_intent.pr_section(res.slug)' in ln]
    assert len(gates) == 2
    assert gates[0] == upsert_create - 1 and gates[1] == upsert_phase6 - 1
