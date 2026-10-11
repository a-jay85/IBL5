"""Replay real and synthetic failed-login replies through ClaudeCli.call (backlog#1419)."""
import collections
import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import TOOLED_MODELS, ClaudeCli, _is_auth_failure
from harness.state import HarnessError, UsageLedger

_CORPUS = json.load(open(os.path.join(os.path.dirname(__file__), "fixtures",
                                      "llm-auth-reply-corpus.json"), encoding="utf-8"))["entries"]


def _scripted(replies, seen):
    """Fake _run_reaped: returns each scripted model reply wrapped in the
    claude --output-format json envelope, and records every prompt sent."""
    def fake(argv, stdin_text, timeout, cwd, env, errors=None):
        seen.append(stdin_text)
        text = replies.pop(0)
        return subprocess.CompletedProcess(
            argv, 0, stdout=json.dumps({"result": text, "usage": {}}), stderr="")
    return fake


def _cli(tmp_path, monkeypatch, replies, seen):
    monkeypatch.setattr("harness.adapters.llm._run_reaped", _scripted(replies, seen))
    monkeypatch.setattr(ClaudeCli, "_gate_before_spawn", lambda self, purpose, cwd: None)
    cli = ClaudeCli(UsageLedger(), workdir=str(tmp_path / "llm-cwd"))
    os.makedirs(cli.workdir, exist_ok=True)
    return cli


def _kind_for(tmp_path, monkeypatch, reply):
    """Both attempts return `reply`; 'ok' on success, else the raised HarnessError kind."""
    cli = _cli(tmp_path, monkeypatch, [reply, reply], [])
    try:
        cli.call("safety-verdict", "haiku", "Return ONLY JSON.", lambda d: None)
        return "ok"
    except HarnessError as e:
        return e.kind


def test_committed_corpus_kind_after(tmp_path, monkeypatch):
    wrong = [(e["id"], e["kind_after"], _kind_for(tmp_path, monkeypatch, e["reply"]))
             for e in _CORPUS]
    wrong = [w for w in wrong if w[1] != w[2]]
    assert not wrong, wrong


def test_only_auth_failures_change_kind():
    changed = {e["id"] for e in _CORPUS if e["kind_before"] != e["kind_after"]}
    expected = {e["id"] for e in _CORPUS
                if e["kind_before"] == "llm-invalid-output" and _is_auth_failure(e["reply"])}
    assert changed == expected
    assert changed
    assert all(e["kind_after"] == "llm-auth-expired" for e in _CORPUS if e["id"] in changed)


def _main_checkout_out():
    common = subprocess.run(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"],
                            capture_output=True, text=True).stdout.strip()
    return os.path.join(os.path.dirname(common), "tools", "postplan-harness", "out")


def test_local_out_corpus_kinds(tmp_path, monkeypatch):
    out = _main_checkout_out()
    if not os.path.isdir(out):
        pytest.skip("no local tools/postplan-harness/out corpus (gitignored; CI has none)")
    hist = collections.Counter()
    for root, _, files in os.walk(out):
        for f in files:
            if f.startswith("raw-") and f.endswith(".txt"):
                t = open(os.path.join(root, f), encoding="utf-8", errors="replace").read()
                k = _kind_for(tmp_path, monkeypatch, t)
                hist[(_is_auth_failure(t), k)] += 1
    print(dict(hist))
    assert hist.get((True, "llm-invalid-output"), 0) == 0
    assert hist.get((False, "llm-auth-expired"), 0) == 0
    assert hist.get((True, "llm-auth-expired"), 0) > 0


AUTH = "Failed to authenticate: OAuth session expired and could not be refreshed"


def _tooled_cli(tmp_path, monkeypatch, stdouts, seen):
    """Fake _run_reaped returning each scripted string as bare stdout."""
    def fake(argv, stdin_text, timeout, cwd, env, errors=None):
        seen.append(stdin_text)
        return subprocess.CompletedProcess(argv, 0, stdout=stdouts.pop(0), stderr="")
    monkeypatch.setattr("harness.adapters.llm._run_reaped", fake)
    monkeypatch.setattr(ClaudeCli, "_gate_before_spawn", lambda self, purpose, cwd: None)
    return ClaudeCli(UsageLedger(), workdir=str(tmp_path / "llm-cwd"))


def _call_tooled(cli, tmp_path):
    return cli.call_tooled("plan-fidelity-review", sorted(TOOLED_MODELS)[0], "p",
                           cwd=str(tmp_path), allowed_tools=("Read",))


def test_tooled_error_envelope_auth_is_auth_expired(tmp_path, monkeypatch):
    seen = []
    cli = _tooled_cli(tmp_path, monkeypatch,
                      [json.dumps({"is_error": True, "result": AUTH})], seen)
    with pytest.raises(HarnessError) as ei:
        _call_tooled(cli, tmp_path)
    assert ei.value.kind == "llm-auth-expired"
    assert len(seen) == 1


def test_tooled_non_json_auth_twice_is_auth_expired(tmp_path, monkeypatch):
    seen = []
    cli = _tooled_cli(tmp_path, monkeypatch, [AUTH, AUTH], seen)
    with pytest.raises(HarnessError) as ei:
        _call_tooled(cli, tmp_path)
    assert ei.value.kind == "llm-auth-expired"
    assert len(seen) == 2


def test_tooled_non_json_auth_then_garbage_is_tooled_cli(tmp_path, monkeypatch):
    seen = []
    cli = _tooled_cli(tmp_path, monkeypatch, [AUTH, "garbage"], seen)
    with pytest.raises(HarnessError) as ei:
        _call_tooled(cli, tmp_path)
    assert ei.value.kind == "llm-tooled-cli"


def test_tooled_error_envelope_non_auth_stays_tooled_error(tmp_path, monkeypatch):
    seen = []
    cli = _tooled_cli(tmp_path, monkeypatch,
                      [json.dumps({"is_error": True, "result": "Reached max turns"})], seen)
    with pytest.raises(HarnessError) as ei:
        _call_tooled(cli, tmp_path)
    assert ei.value.kind == "llm-tooled-error"


def test_tooled_long_verdict_quoting_auth_returns_text(tmp_path, monkeypatch):
    seen = []
    text = "x" * 1200 + AUTH
    cli = _tooled_cli(tmp_path, monkeypatch, [json.dumps({"result": text, "usage": {}})], seen)
    assert _call_tooled(cli, tmp_path) == text
