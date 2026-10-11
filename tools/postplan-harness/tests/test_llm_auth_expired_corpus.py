"""Replay real and synthetic failed-login replies through ClaudeCli.call (backlog#1419)."""
import collections
import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import ClaudeCli, _AUTH_FAILURE_RE
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


def test_committed_corpus_kind_before(tmp_path, monkeypatch):
    wrong = [(e["id"], e["kind_before"], _kind_for(tmp_path, monkeypatch, e["reply"]))
             for e in _CORPUS]
    wrong = [w for w in wrong if w[1] != w[2]]
    assert not wrong, wrong


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
                hist[(bool(_AUTH_FAILURE_RE.search(t)), k)] += 1
    print(dict(hist))
    assert hist[(True, "llm-invalid-output")] > 0      # master: auth replies are invalid-output
    assert all(k in ("ok", "llm-invalid-output", "llm-usage-limit") for _, k in hist)
