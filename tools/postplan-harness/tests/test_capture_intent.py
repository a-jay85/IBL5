"""bin/capture-intent: copies chosen owner turns from a session transcript into an intent file."""
import hashlib
import json
import os
import subprocess
import sys

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
SCRIPT = os.path.join(REPO, "bin", "capture-intent")
SID = "0123abcd-4567-89ab-cdef-0123456789ab"
BRANCH = "some-branch"


def _user(text, **kw):
    row = {"type": "user", "sessionId": SID, "timestamp": "2026-11-11T10:00:00Z",
           "message": {"role": "user", "content": text}}
    row.update(kw)
    return row


def _write_session(home, rows, sid=SID, project="-Users-x-repo"):
    d = home / ".claude" / "projects" / project
    d.mkdir(parents=True, exist_ok=True)
    path = d / f"{sid}.jsonl"
    path.write_text("".join(json.dumps(r) + "\n" for r in rows))
    return path


def _dirs(tmp_path):
    home = tmp_path / "home"
    cwd = tmp_path / "cwd"
    home.mkdir(exist_ok=True)
    cwd.mkdir(exist_ok=True)
    return home, cwd


def _run(home, cwd, *args, sid=SID, env_extra=None):
    env = {"HOME": str(home), "PATH": os.environ.get("PATH", "/usr/bin:/bin")}
    if sid is not None:
        env["CLAUDE_CODE_SESSION_ID"] = sid
    if env_extra:
        env.update(env_extra)
    return subprocess.run([sys.executable, SCRIPT, *args], env=env, cwd=str(cwd),
                          capture_output=True, text=True)


def _out(home, branch=BRANCH):
    return home / "claude-plans" / "_intent" / f"{branch}.md"


def _payload(home, branch=BRANCH):
    header, payload = _out(home, branch).read_text().splitlines()[:2]
    return header, json.loads(payload)


def test_byte_for_byte(tmp_path):
    home, cwd = _dirs(tmp_path)
    text = "  leading spaces kept\n\n\ninner blank lines kept\n  trailing line  "
    _write_session(home, [_user("first"), _user(text)])
    r = _run(home, cwd, "--branch", BRANCH, "2")
    assert r.returncode == 0, r.stderr
    _, data = _payload(home)
    assert data["turns"][0]["text"] == text
    assert data["turns"][0]["index"] == 2
    assert data["turns"][0]["stripped"] == []
    assert data["turns"][0]["redactions"] == 0


def test_skips_non_user(tmp_path):
    home, cwd = _dirs(tmp_path)
    rows = [
        _user("real one"),
        {"type": "user", "sessionId": SID, "message": {"role": "user", "content": [
            {"type": "tool_result", "tool_use_id": "t", "content": "tool output"}]}},
        {"type": "user", "sessionId": SID, "message": {"role": "user", "content": [
            {"type": "image", "source": {"type": "base64", "data": "AAAA"}},
            {"type": "text", "text": "image paste"}]}},
        {"type": "assistant", "sessionId": SID, "message": {"role": "assistant", "content": "assistant text"}},
        _user("meta row", isMeta=True),
        _user("sidechain row", isSidechain=True),
        _user("real two"),
    ]
    _write_session(home, rows)
    r = _run(home, cwd, "--branch", BRANCH, "--list")
    assert r.returncode == 0, r.stderr
    lines = r.stdout.splitlines()
    assert len(lines) == 2
    assert lines[0].startswith("1\t") and lines[0].endswith("real one")
    assert lines[1].startswith("2\t") and lines[1].endswith("real two")
    for skipped in ("tool output", "image paste", "assistant text", "meta row", "sidechain row"):
        assert skipped not in r.stdout
    r = _run(home, cwd, "--branch", BRANCH, "2")
    assert r.returncode == 0, r.stderr
    assert _payload(home)[1]["turns"][0]["text"] == "real two"


def test_strips_wrappers(tmp_path):
    home, cwd = _dirs(tmp_path)
    raw = ("please fix the thing\n"
           "<system-reminder>\nnoise\n</system-reminder>\n"
           "<t3_context kind=\"x\">\nmore noise\n</t3_context>\n")
    _write_session(home, [_user(raw)])
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 0, r.stderr
    turn = _payload(home)[1]["turns"][0]
    assert turn["text"] == "please fix the thing"
    assert turn["stripped"] == ["system-reminder", "t3_context"]
    assert "noise" not in turn["text"]


def test_unclosed_wrapper_kept(tmp_path):
    home, cwd = _dirs(tmp_path)
    raw = "the <system-reminder> tag shows up mid-sentence and is never closed\n"
    _write_session(home, [_user(raw)])
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 0, r.stderr
    turn = _payload(home)[1]["turns"][0]
    assert turn["text"] == raw
    assert turn["stripped"] == []


def test_session_env_unset(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("hello")])
    r = _run(home, cwd, "--branch", BRANCH, "1", sid=None)
    assert r.returncode == 2
    assert "no session id" in r.stderr
    assert not _out(home).exists()
    r = _run(home, cwd, "--branch", BRANCH, "--session", SID, "1", sid=None)
    assert r.returncode == 0, r.stderr
    assert _out(home).exists()


def test_session_env_empty(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("hello")])
    r = _run(home, cwd, "--branch", BRANCH, "1", sid="")
    assert r.returncode == 2
    assert "no session id" in r.stderr
    assert not _out(home).exists()


def test_session_not_uuid(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("hello")])
    r = _run(home, cwd, "--branch", BRANCH, "--session",
             "../00000000-0000-0000-0000-000000000000", "1", sid=None)
    assert r.returncode == 2
    assert r.stderr.startswith("capture-intent: ")
    assert not _out(home).exists()


def test_bad_turn_index(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("one"), _user("two")])
    for bad in ("0", "3", "abc"):
        r = _run(home, cwd, "--branch", BRANCH, bad)
        assert r.returncode == 2, (bad, r.stderr)
        assert r.stderr.startswith("capture-intent: ")
        assert not _out(home).exists()


def test_transcript_ambiguous_or_missing(tmp_path):
    home, cwd = _dirs(tmp_path)
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 3
    assert "missing" in r.stderr
    _write_session(home, [_user("a")], project="-proj-one")
    _write_session(home, [_user("b")], project="-proj-two")
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 3
    assert "ambiguous" in r.stderr
    assert not _out(home).exists()


def test_redacts_email(tmp_path):
    home, cwd = _dirs(tmp_path)
    addr = "someone" + "@" + "example.com"
    raw = f"mail {addr} and also {addr}; ping @a-jay85 and user@localhost"
    _write_session(home, [_user(raw)])
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 0, r.stderr
    turn = _payload(home)[1]["turns"][0]
    assert turn["text"] == "mail [REDACTED] and also [REDACTED]; ping @a-jay85 and user@localhost"
    assert turn["redactions"] == 2


def test_redacts_token(tmp_path):
    home, cwd = _dirs(tmp_path)
    gh = "gh" + "p_" + "A" * 36
    sk = "s" + "k-" + "b" * 24
    raw = f"token {gh} and key {sk} but sk-short stays"
    _write_session(home, [_user(raw)])
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 0, r.stderr
    turn = _payload(home)[1]["turns"][0]
    assert turn["text"] == "token [REDACTED] and key [REDACTED] but sk-short stays"
    assert turn["redactions"] == 2


def test_over_cap_refused(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("x" * 2500), _user("y" * 1501), _user("z" * 1500)])
    r = _run(home, cwd, "--branch", BRANCH, "1", "2")
    assert r.returncode == 4
    assert "cap" in r.stderr
    assert not _out(home).exists()
    r = _run(home, cwd, "--branch", BRANCH, "1", "3")
    assert r.returncode == 0, r.stderr
    assert sum(len(t["text"].encode()) for t in _payload(home)[1]["turns"]) == 4000


def test_writes_outside_repo(tmp_path):
    home, cwd = _dirs(tmp_path)
    _write_session(home, [_user("ship the thing")])
    r = _run(home, cwd, "--branch", BRANCH, "1")
    assert r.returncode == 0, r.stderr
    out = _out(home)
    assert out.exists()
    assert not (cwd / "claude-plans").exists()
    assert not out.resolve().is_relative_to(REPO)
    lines = out.read_text().split("\n")
    assert lines[2:] == [""]
    header, payload = lines[0], lines[1]
    digest = hashlib.sha256(payload.encode()).hexdigest()
    assert header == f"<!-- capture-intent v1 sha256={digest} -->"
    data = json.loads(payload)
    assert data["version"] == 1 and data["session"] == SID and data["branch"] == BRANCH
