"""Closer: trigger predicate, bounded session boundary, gate-path revert, reply parser."""
from __future__ import annotations

import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

from harness import closer
from harness.adapters.llm import MODEL_MAP
from harness.cifix import CI_FIX_DENIED_TOOLS
from harness.closer import (CLOSER_BUDGET_USD, CLOSER_MODEL, CLOSER_PURPOSE,
                            HEURISTIC_CONDITIONS, CloserContext, parse_reply, run_session,
                            should_run)
from harness.state import HarnessError, LlmCallRecord, UsageLedger

# ── trigger predicate ─────────────────────────────────────────────────────────


def _sr(**kw):
    base = dict(terminal="held", error_kind=None, blocked_numbers=set(), ci_red=False)
    base.update(kw)
    return should_run(**base)


@pytest.mark.parametrize("n", sorted(HEURISTIC_CONDITIONS))
def test_should_run_each_heuristic_condition(n):
    assert len(HEURISTIC_CONDITIONS) == 9
    run, reason = _sr(blocked_numbers={n})
    assert run is True
    assert reason == f"heuristic:{n}"


@pytest.mark.parametrize("blocked", [{7}, {8}, {9}, {15}, {16}, {20}, {7, 16}])
def test_should_not_run_hard_only(blocked):
    run, reason = _sr(blocked_numbers=blocked)
    assert run is False
    assert reason.startswith("hard-only:")


def test_should_run_mixed_hard_and_heuristic():
    run, reason = _sr(blocked_numbers={7, 11})
    assert (run, reason) == (True, "heuristic:11")


def test_should_run_rebase_conflict_and_ci_red():
    assert _sr(terminal="failed", error_kind="rebase-conflict") == (True, "rebase-conflict")
    assert _sr(ci_red=True) == (True, "ci-red")


@pytest.mark.parametrize("kind", [
    "local-gate", "gate-path-edit", "usage-pause", "llm-usage-limit", "llm-auth-expired",
    "remote-head-diverged", "subprocess-timeout", "precommit-hang", "gh-api"])
def test_should_not_run_fail_closed_kinds(kind):
    run, reason = _sr(terminal="failed", error_kind=kind, blocked_numbers={11}, ci_red=True)
    assert run is False
    assert reason == f"error:{kind}"


def test_should_not_run_when_armed():
    assert _sr(terminal="armed", blocked_numbers=set()) == (False, "armed")


# ── session boundary ──────────────────────────────────────────────────────────


def _g(cwd, *args):
    return subprocess.run(["git", *args], cwd=cwd, capture_output=True, text=True, check=True)


def _write(cwd, rel, text):
    path = os.path.join(cwd, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w") as fh:
        fh.write(text)


@pytest.fixture
def repo(tmp_path):
    cwd = str(tmp_path / "wt")
    os.makedirs(cwd)
    _g(cwd, "init", "-q", "-b", "master")
    _g(cwd, "config", "user.email", "t@example.com")
    _g(cwd, "config", "user.name", "t")
    _write(cwd, "README.md", "readme\n")
    _write(cwd, ".claude/rules/existing.md", "rule v1\n")
    _g(cwd, "add", "-A")
    _g(cwd, "commit", "-qm", "base")
    _g(cwd, "checkout", "-qb", "feature")
    _write(cwd, "src.txt", "feature work\n")
    _g(cwd, "add", "-A")
    _g(cwd, "commit", "-qm", "feature")
    return cwd


class FakeLlm:
    def __init__(self, action=None, reply='{"verdict":"fixed","summary":"s"}', exc=None,
                 cost=0.0):
        self.ledger = UsageLedger()
        self.action, self.reply, self.exc, self.cost = action, reply, exc, cost
        self.kw = None
        self.calls = 0

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls += 1
        self.kw = dict(kw, purpose=purpose, model=model, prompt=prompt)
        if self.action:
            self.action(kw["cwd"])
        if self.cost:
            self.ledger.add(LlmCallRecord(purpose=purpose, model=model, cost_usd=self.cost))
        if self.exc:
            raise self.exc
        return self.reply


def _ctx(**kw):
    base = dict(pr=7, plan_path="/p/plan.md", trigger_reason="heuristic:11", base_ref="master",
                threads=({"comment_id": 11, "path": "a.php", "line": 3, "body": "fix"},))
    base.update(kw)
    return CloserContext(**base)


def _session(repo, llm, **ctx_kw):
    logs = []
    out = run_session(llm, worktree=repo, base_ref="master", pr=7, plan_path="/p/plan.md",
                      context=_ctx(**ctx_kw), log=logs.append)
    return out, logs


def _head(repo):
    return _g(repo, "rev-parse", "HEAD").stdout.strip()


def test_session_passes_opus_caps_and_deny_list(repo):
    llm = FakeLlm()
    _session(repo, llm)
    kw = llm.kw
    assert kw["purpose"] == CLOSER_PURPOSE
    assert kw["model"] == CLOSER_MODEL
    assert MODEL_MAP[kw["model"]] == "claude-opus-5-5"
    assert closer.CLOSER_MODEL_ID == "claude-opus-5-5"
    assert kw["max_budget_usd"] == 15.0 == CLOSER_BUDGET_USD
    assert kw["timeout"] == 3600
    assert kw["max_retries"] == 0
    for tool in CI_FIX_DENIED_TOOLS + ("Bash(gh:*)", "Bash(git push:*)", "Bash(git commit:*)"):
        assert tool in kw["denied_tools"]
    assert kw["extra_env"]["GH_TOKEN"] is None
    assert kw["extra_env"]["GITHUB_TOKEN"] is None
    assert kw["extra_env"]["GH_CONFIG_DIR"]


_CRED_PARENT_ENV = {
    "PATH": os.environ.get("PATH", ""), "HOME": os.environ.get("HOME", ""),
    "GH_TOKEN": "t", "GITHUB_TOKEN": "t", "GH_ENTERPRISE_TOKEN": "t",
    "SSH_AUTH_SOCK": "/tmp/agent.sock", "GIT_ASKPASS": "/bin/askpass",
    "SSH_ASKPASS": "/bin/askpass", "GIT_SSH": "/usr/bin/ssh",
}


def _session_child_env(repo):
    """The env the closer's claude child gets: the session's extra_env over a parent env
    that carries every credential path."""
    from harness.adapters.llm import _apply_extra_env
    llm = FakeLlm()
    _session(repo, llm)
    return _apply_extra_env(dict(_CRED_PARENT_ENV), llm.kw["extra_env"])


def test_session_env_strips_git_credential_paths(repo):
    env = _session_child_env(repo)
    for key in ("GH_TOKEN", "GITHUB_TOKEN", "GH_ENTERPRISE_TOKEN", "SSH_AUTH_SOCK",
                "GIT_ASKPASS", "SSH_ASKPASS", "GIT_SSH"):
        assert key not in env, key
    assert env["GIT_TERMINAL_PROMPT"] == "0"
    assert env["GIT_SSH_COMMAND"] == "false"
    assert (env["GIT_CONFIG_COUNT"], env["GIT_CONFIG_KEY_0"], env["GIT_CONFIG_VALUE_0"]) \
        == ("1", "credential.helper", "")


def test_session_env_resets_git_credential_helper(repo):
    """git run with the session env sees an empty helper last, which clears osxkeychain."""
    env = _session_child_env(repo)
    subprocess.run(["git", "-C", str(repo), "config", "credential.helper", "osxkeychain"],
                   check=True)
    out = subprocess.run(["git", "-C", str(repo), "config", "--get-all", "credential.helper"],
                         env=env, capture_output=True, text=True, check=True).stdout
    assert out.splitlines()[-1] == ""
    ssh = subprocess.run(["git", "-C", str(repo), "ls-remote", "ssh://git@example.invalid/x"],
                         env=env, capture_output=True, text=True, timeout=20)
    assert ssh.returncode != 0


def test_wall_timeout_ends_session_without_raise(repo):
    def touch(cwd):
        _write(cwd, "half.txt", "partial\n")
    llm = FakeLlm(action=touch, exc=HarnessError("subprocess-timeout", "3600s"))
    out, _ = _session(repo, llm)
    assert out.status == "cap-wall"
    assert out.stop_reason == "subprocess-timeout"
    assert not os.path.exists(os.path.join(repo, "half.txt"))
    assert _head(repo) == out.pre_head


def test_budget_cap_recorded_from_ledger(repo):
    llm = FakeLlm(cost=15.02)
    out, _ = _session(repo, llm)
    assert out.status == "cap-budget"
    assert out.cost_usd == 15.02


def test_gate_path_edit_reverted(repo):
    def edit(cwd):
        _write(cwd, ".claude/rules/zz.md", "new gate file\n")
        _write(cwd, "bin/check-zz", "#!/bin/sh\n")
        _write(cwd, ".claude/rules/existing.md", "rule weakened\n")
        _write(cwd, "ok.txt", "legit\n")
    pre = _head(repo)
    out, _ = _session(repo, FakeLlm(action=edit))
    assert out.status == "gate-path-edit"
    assert _head(repo) == pre
    assert not os.path.exists(os.path.join(repo, ".claude/rules/zz.md"))
    assert not os.path.exists(os.path.join(repo, "bin/check-zz"))
    assert not os.path.exists(os.path.join(repo, "ok.txt"))
    with open(os.path.join(repo, ".claude/rules/existing.md")) as fh:
        assert fh.read() == "rule v1\n"
    assert _g(repo, "status", "--porcelain").stdout.strip() == ""


def test_master_gate_change_via_rebase_not_a_hit(repo):
    _g(repo, "checkout", "-q", "master")
    _write(repo, ".claude/rules/existing.md", "rule v2 from master\n")
    _g(repo, "add", "-A")
    _g(repo, "commit", "-qm", "master gate change")
    _g(repo, "checkout", "-q", "feature")

    def rebase(cwd):
        _g(cwd, "rebase", "master")
    pre = _head(repo)
    out, _ = _session(repo, FakeLlm(action=rebase))
    assert out.status == "edited"
    assert _head(repo) != pre
    with open(os.path.join(repo, ".claude/rules/existing.md")) as fh:
        assert fh.read() == "rule v2 from master\n"


def test_session_no_change_and_edited(repo):
    out, _ = _session(repo, FakeLlm())
    assert out.status == "no-change"

    def edit(cwd):
        _write(cwd, "src.txt", "changed\n")
    out, _ = _session(repo, FakeLlm(action=edit))
    assert out.status == "edited"


def test_untracked_preexisting_files_survive_a_revert(repo):
    _write(repo, "keep-me.txt", "mine\n")

    def edit(cwd):
        _write(cwd, "bin/check-zz", "x\n")
    out, _ = _session(repo, FakeLlm(action=edit))
    assert out.status == "gate-path-edit"
    assert os.path.exists(os.path.join(repo, "keep-me.txt"))


# ── reply parser ──────────────────────────────────────────────────────────────


def test_parse_reply_envelope_variations():
    good = '{"verdict":"fixed","summary":"done","hold_note":"","thread_replies":[]}'
    assert parse_reply(good, set())[0] == "fixed"
    wrapped = '{"result": {"verdict": "false-positive", "hold_note": "why"}}'
    verdict, note, replies, degraded = parse_reply(wrapped, set())
    assert (verdict, note, replies, degraded) == ("false-positive", "why", [], False)
    for bad in ('[1, 2]', "ok", '{"verdict":"great"}', "", None):
        verdict, note, replies, degraded = parse_reply(bad, {1})
        assert degraded is True
        assert verdict == "unparsed"
        assert replies == []
        assert "No issues found" not in note
        assert note == "closer reply unparseable; no thread replies posted"


def test_thread_reply_unknown_id_dropped():
    text = ('{"verdict":"fixed","summary":"s","thread_replies":['
            '{"comment_id":11,"body":"a","resolve":true},'
            '{"comment_id":99,"body":"b","resolve":true},'
            '{"comment_id":"11","body":"c","resolve":false}]}')
    logs = []
    _v, _n, replies, degraded = parse_reply(text, {11}, logs.append)
    assert degraded is False
    assert [r["comment_id"] for r in replies] == [11]
    assert any("unknown thread 99" in m for m in logs)
