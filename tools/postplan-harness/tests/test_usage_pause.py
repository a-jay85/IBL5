"""Usage-gate pause for the post-plan harness (ADR-0143 addendum).

Adapter-level and runner-level tests. They run beside the existing suites they must
not disturb: test_runner_replay.py, test_llm_tooled.py, test_llm_usage_limit.py and
test_runner_exit_codes.py. Covered here: the gate context, the gate-lib bridge, the
pre-spawn gate and shared pause flag in ClaudeCli, the exit-75 map in runner.main,
and the resume effect ledger. The bash library is faked by a stub `usage-gate.sh`
whose functions read env vars, except in the real-library row at the end.
"""
import concurrent.futures
import json
import os
import shutil
import stat
import subprocess
import sys
import threading
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import usage_pause
from harness.adapters.llm import ClaudeCli, MODEL_MAP
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger
from harness.usage_pause import GateContext, UsagePause

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
S = "5a5a5a5a-1111-4222-8333-444444444444"
PAUSE_JSON = '{"continue":false,"stopReason":"usage-pause"}'
LIMIT_TEXT = "You've hit your limit"

# Fake `claude`: logs its argv, dumps its env when asked, optionally sleeps or
# writes a file into cwd (a tooled edit), then prints CLAUDE_SHIM_REPLY.
SHIM = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
if [ -n "${CLAUDE_SHIM_ENV_LOG:-}" ]; then
  env > "$CLAUDE_SHIM_ENV_LOG"
fi
cat > /dev/null
[ -z "${CLAUDE_SHIM_SLEEP:-}" ] || sleep "$CLAUDE_SHIM_SLEEP"
[ -z "${CLAUDE_SHIM_TOUCH:-}" ] || printf 'edit\\n' > "$CLAUDE_SHIM_TOUCH"
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit "${CLAUDE_SHIM_EXIT:-0}"
"""

# Stub gate library. Each function's behavior comes from FAKE_* env vars.
FAKE_LIB = r"""
usage_gate_decide() {
    cat > /dev/null
    if [ -n "${FAKE_DECIDE_ONCE:-}" ]; then
        if mkdir "$FAKE_DECIDE_ONCE" 2>/dev/null; then
            printf '%s' "$FAKE_DECIDE_OUT"
        fi
        return 0
    fi
    [ -z "${FAKE_DECIDE_SLEEP:-}" ] || sleep "$FAKE_DECIDE_SLEEP"
    printf '%s' "${FAKE_DECIDE_OUT:-}"
    return "${FAKE_DECIDE_RC:-0}"
}
usage_marker_exists() { return "${FAKE_MARKER_EXISTS_RC:-1}"; }
usage_marker_clear() { printf 'clear %s\n' "$1" >> "${FAKE_CALL_LOG:-/dev/null}"; return 0; }
usage_limit_hit_marker() { printf 'limit %s\n' "$*" >> "${FAKE_CALL_LOG:-/dev/null}"; return "${FAKE_LIMIT_RC:-1}"; }
usage_state_dir() { [ -n "${FAKE_STATE_DIR:-}" ] || return 1; printf '%s\n' "$FAKE_STATE_DIR"; }
"""

GATE_VARS = ("IBL5_USAGE_GATE", "IBL5_USAGE_GATE_RUNNER", "IBL5_USAGE_GATE_RESUME_BIN",
             "IBL5_USAGE_GATE_SESSION_ID", "IBL5_USAGE_GATE_MODEL")


@pytest.fixture(autouse=True)
def _no_ambient_gate(monkeypatch):
    """A developer shell must not leak gate context into these tests."""
    for k in GATE_VARS:
        monkeypatch.delenv(k, raising=False)
    for k in list(os.environ):
        if k.startswith("FAKE_"):
            monkeypatch.delenv(k, raising=False)


@pytest.fixture()
def shim(tmp_path, monkeypatch):
    bindir = tmp_path / "shimbin"
    bindir.mkdir()
    claude = bindir / "claude"
    claude.write_text(SHIM)
    claude.chmod(claude.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "claude-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("CLAUDE_SHIM_LOG", str(log))
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope('{"ok": 1}'))
    return log


def _envelope(result, **extra):
    return json.dumps({"type": "result", "subtype": "success", "is_error": False,
                       "result": result, "usage": {}, **extra})


def _fake_root(tmp_path, lib_text=FAKE_LIB):
    root = tmp_path / "root"
    (root / "bin" / "lib").mkdir(parents=True)
    ppn = root / "bin" / "post-plan-now"
    ppn.write_text("#!/bin/sh\nexit 0\n")
    ppn.chmod(0o755)
    (root / "bin" / "lib" / "usage-gate.sh").write_text(lib_text)
    return root


@pytest.fixture()
def gate(tmp_path, monkeypatch):
    """A valid gate context in the env, pointed at the stub library."""
    root = _fake_root(tmp_path)
    monkeypatch.setenv("IBL5_USAGE_GATE_RUNNER", "post-plan-now")
    monkeypatch.setenv("IBL5_USAGE_GATE_RESUME_BIN", str(root / "bin" / "post-plan-now"))
    monkeypatch.setenv("IBL5_USAGE_GATE_SESSION_ID", S)
    calls = tmp_path / "lib-calls.log"
    monkeypatch.setenv("FAKE_CALL_LOG", str(calls))
    return calls


def _git_repo(path):
    path.mkdir(parents=True, exist_ok=True)
    run = lambda *a: subprocess.run(["git", *a], cwd=str(path), check=True, capture_output=True)
    run("init", "-q", "-b", "master")
    run("config", "user.email", "t@example.com")
    run("config", "user.name", "T")
    (path / "f.txt").write_text("one\n")
    run("add", "-A")
    run("commit", "-qm", "base")
    return path


def _cli(tmp_path):
    return ClaudeCli(UsageLedger(), workdir=str(tmp_path / "llm-cwd"))


def _call(cli):
    (cli.workdir and os.makedirs(cli.workdir, exist_ok=True))
    return cli.call("p", "haiku", "prompt", lambda d: None, max_retries=0)


def _call_tooled(cli, cwd, tools=("Read",)):
    return cli.call_tooled("t", "sonnet", "prompt", cwd=str(cwd), allowed_tools=tools,
                           max_retries=0)


def _shim_lines(log):
    return [ln for ln in log.read_text().splitlines() if ln]


# ---------------------------------------------------------------- Phase 1

def test_usage_limit_without_gate_context_stays_exit3(shim, tmp_path, monkeypatch):
    """Pre-impl characterization: no gate context, limit text is today's exit 3."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", LIMIT_TEXT)
    cli = _cli(tmp_path)
    with pytest.raises(HarnessError) as ei:
        _call(cli)
    assert ei.value.kind == "llm-usage-limit"
    res = RunResult(terminal=TerminalState.FAILED, slug="x")
    res.terminal = TerminalState.FAILED
    res.error_kind = "llm-usage-limit"
    assert runner.exit_code_for(res) == 3


# ---------------------------------------------------------------- Phase 4

def test_context_unset_session_id_fails_open(tmp_path):
    root = _fake_root(tmp_path)
    ctx, why = usage_pause.context_from_env({
        "IBL5_USAGE_GATE_RUNNER": "post-plan-now",
        "IBL5_USAGE_GATE_RESUME_BIN": str(root / "bin" / "post-plan-now")})
    assert ctx is None and why == "no-session-id"


def test_context_empty_session_id_fails_open(tmp_path):
    root = _fake_root(tmp_path)
    ctx, why = usage_pause.context_from_env({
        "IBL5_USAGE_GATE_SESSION_ID": "",
        "IBL5_USAGE_GATE_RUNNER": "post-plan-now",
        "IBL5_USAGE_GATE_RESUME_BIN": str(root / "bin" / "post-plan-now")})
    assert ctx is None and why == "empty-session-id"


@pytest.mark.parametrize("case,reason", [
    ("bad-sid", "bad-session-id"),
    ("runner", "bad-runner"),
    ("relative-bin", "bad-resume-bin"),
    ("nonexec-bin", "bad-resume-bin"),
    ("no-lib", "no-lib"),
])
def test_context_rejects_bad_sid_runner_bin_lib(tmp_path, case, reason):
    root = _fake_root(tmp_path)
    rbin = root / "bin" / "post-plan-now"
    env = {"IBL5_USAGE_GATE_SESSION_ID": S, "IBL5_USAGE_GATE_RUNNER": "post-plan-now",
           "IBL5_USAGE_GATE_RESUME_BIN": str(rbin)}
    if case == "bad-sid":
        env["IBL5_USAGE_GATE_SESSION_ID"] = "../not a uuid"
    elif case == "runner":
        env["IBL5_USAGE_GATE_RUNNER"] = "automouse"
    elif case == "relative-bin":
        env["IBL5_USAGE_GATE_RESUME_BIN"] = "bin/post-plan-now"
    elif case == "nonexec-bin":
        rbin.chmod(0o644)
    elif case == "no-lib":
        (root / "bin" / "lib" / "usage-gate.sh").unlink()
    ctx, why = usage_pause.context_from_env(env)
    assert ctx is None and why == reason


def test_context_valid(tmp_path):
    root = _fake_root(tmp_path)
    ctx, why = usage_pause.context_from_env({
        "IBL5_USAGE_GATE_SESSION_ID": S, "IBL5_USAGE_GATE_RUNNER": "post-plan-now",
        "IBL5_USAGE_GATE_RESUME_BIN": str(root / "bin" / "post-plan-now")})
    assert why == "ok"
    assert ctx.session_id == S and ctx.runner == "post-plan-now"
    assert ctx.lib == str(root / "bin" / "lib" / "usage-gate.sh")
    assert ctx.lib.endswith("bin/lib/usage-gate.sh")


def _ctx(root):
    return GateContext("post-plan-now", str(root / "bin" / "post-plan-now"), S,
                       str(root / "bin" / "lib" / "usage-gate.sh"))


def test_prespawn_decide_pause_json_pauses(tmp_path, monkeypatch):
    root = _fake_root(tmp_path)
    monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
    assert usage_pause.prespawn_decide(_ctx(root), str(tmp_path)) is True


@pytest.mark.parametrize("case", ["empty", "garbage", "allow-json", "rc1", "no-source",
                                  "timeout"])
def test_prespawn_decide_fails_open(tmp_path, monkeypatch, case):
    root = _fake_root(tmp_path)
    kw = {}
    if case == "garbage":
        monkeypatch.setenv("FAKE_DECIDE_OUT", "not json {")
    elif case == "allow-json":
        monkeypatch.setenv("FAKE_DECIDE_OUT", '{"continue": true}')
    elif case == "rc1":
        monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
        monkeypatch.setenv("FAKE_DECIDE_RC", "1")
    elif case == "no-source":
        (root / "bin" / "lib" / "usage-gate.sh").write_text("return 1\n")
        monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
    elif case == "timeout":
        monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
        monkeypatch.setenv("FAKE_DECIDE_SLEEP", "5")
        kw["timeout"] = 1
    assert usage_pause.prespawn_decide(_ctx(root), str(tmp_path), **kw) is False


def test_fingerprint_detects_changes(tmp_path):
    repo = _git_repo(tmp_path / "repo")
    (repo / "pre.txt").write_text("untracked\n")
    fp = usage_pause.worktree_fingerprint(str(repo))
    assert fp is not None
    assert usage_pause.worktree_fingerprint(str(repo)) == fp

    (repo / "f.txt").write_text("two\n")                       # tracked edit
    fp_tracked = usage_pause.worktree_fingerprint(str(repo))
    assert fp_tracked != fp
    (repo / "new.txt").write_text("x\n")                       # new untracked file
    fp_new = usage_pause.worktree_fingerprint(str(repo))
    assert fp_new != fp_tracked
    (repo / "pre.txt").write_text("edited\n")                  # edit a pre-existing untracked
    fp_pre = usage_pause.worktree_fingerprint(str(repo))
    assert fp_pre != fp_new
    head = subprocess.run(["git", "rev-parse", "HEAD"], cwd=str(repo), capture_output=True,
                          text=True, check=True).stdout
    (repo / ".git" / "MERGE_HEAD").write_text(head)            # in-progress merge
    assert usage_pause.worktree_fingerprint(str(repo)) != fp_pre
    (repo / ".git" / "MERGE_HEAD").unlink()
    subprocess.run(["git", "commit", "-qam", "c2"], cwd=str(repo), check=True)
    assert usage_pause.worktree_fingerprint(str(repo)) not in (fp, fp_pre)
    assert usage_pause.worktree_fingerprint(str(tmp_path / "not-a-repo-dir")) is None


def test_usage_pause_is_not_an_exception():
    assert not issubclass(UsagePause, Exception)
    assert issubclass(UsagePause, BaseException)
    with pytest.raises(UsagePause):
        try:
            raise UsagePause("x")
        except Exception:  # noqa: BLE001
            pytest.fail("except Exception caught a UsagePause")


# ---------------------------------------------------------------- Phase 5

def _env_dump(path):
    out = {}
    for ln in path.read_text().splitlines():
        if "=" in ln:
            k, v = ln.split("=", 1)
            out[k] = v
    return out


def test_toolless_call_env_carries_gate(shim, gate, tmp_path, monkeypatch):
    env_log = tmp_path / "env.log"
    monkeypatch.setenv("CLAUDE_SHIM_ENV_LOG", str(env_log))
    _call(_cli(tmp_path))
    env = _env_dump(env_log)
    assert env["IBL5_USAGE_GATE"] == "1"
    assert env["IBL5_USAGE_GATE_SESSION_ID"] == S
    assert env["IBL5_USAGE_GATE_RUNNER"] == "post-plan-now"
    assert env["IBL5_USAGE_GATE_MODEL"] == MODEL_MAP["haiku"]


def test_tooled_call_env_carries_gate(shim, gate, tmp_path, monkeypatch):
    repo = _git_repo(tmp_path / "wt")
    env_log = tmp_path / "env.log"
    monkeypatch.setenv("CLAUDE_SHIM_ENV_LOG", str(env_log))
    _call_tooled(_cli(tmp_path), repo)
    env = _env_dump(env_log)
    assert env["IBL5_USAGE_GATE"] == "1"
    assert env["IBL5_USAGE_GATE_SESSION_ID"] == S
    assert env["IBL5_USAGE_GATE_RUNNER"] == "post-plan-now"
    assert env["IBL5_USAGE_GATE_MODEL"] == MODEL_MAP["sonnet"]


@pytest.mark.parametrize("sid", [None, ""])
def test_child_env_without_context_drops_gate_flag(shim, tmp_path, monkeypatch, sid):
    monkeypatch.setenv("IBL5_USAGE_GATE", "1")
    if sid is not None:
        monkeypatch.setenv("IBL5_USAGE_GATE_SESSION_ID", sid)
    cli = _cli(tmp_path)
    assert cli.gate is None
    env_log = tmp_path / "env.log"
    monkeypatch.setenv("CLAUDE_SHIM_ENV_LOG", str(env_log))
    _call(cli)
    assert "IBL5_USAGE_GATE" not in _env_dump(env_log)
    repo = _git_repo(tmp_path / "wt")
    _call_tooled(cli, repo)
    assert "IBL5_USAGE_GATE" not in _env_dump(env_log)


def test_prespawn_pause_spawns_nothing(shim, gate, tmp_path, monkeypatch):
    monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
    cli = _cli(tmp_path)
    with pytest.raises(UsagePause):
        _call(cli)
    assert _shim_lines(shim) == []
    assert cli.paused.is_set()


def test_paused_flag_short_circuits_both_paths(shim, tmp_path):
    cli = _cli(tmp_path)
    cli.paused.set()
    with pytest.raises(UsagePause):
        _call(cli)
    with pytest.raises(UsagePause):
        _call_tooled(cli, _git_repo(tmp_path / "wt"))
    assert _shim_lines(shim) == []


def test_parallel_calls_one_pause(shim, gate, tmp_path, monkeypatch):
    """The first decide pauses; siblings either never spawn or discard their result."""
    monkeypatch.setenv("FAKE_DECIDE_ONCE", str(tmp_path / "decided-once"))
    monkeypatch.setenv("FAKE_DECIDE_OUT", PAUSE_JSON)
    monkeypatch.setenv("CLAUDE_SHIM_SLEEP", "1")
    cli = _cli(tmp_path)
    os.makedirs(cli.workdir, exist_ok=True)
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as ex:
        futs = [ex.submit(_call, cli) for _ in range(3)]
        for f in futs:
            with pytest.raises(UsagePause):
                f.result()
    assert cli.paused.is_set()
    before = len(_shim_lines(shim))
    with pytest.raises(UsagePause):
        _call(cli)
    assert len(_shim_lines(shim)) == before


def test_tooled_hook_pause_clean_tree(shim, gate, tmp_path, monkeypatch):
    """The child hook wrote an S marker and left the tree alone: UsagePause(dirty=False)."""
    repo = _git_repo(tmp_path / "wt")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    with pytest.raises(UsagePause) as ei:
        _call_tooled(_cli(tmp_path), repo)
    assert ei.value.dirty is False


def test_tooled_hook_pause_dirty_tree(shim, gate, tmp_path, monkeypatch):
    """The same pause after the child edited the tree: UsagePause(dirty=True)."""
    repo = _git_repo(tmp_path / "wt")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    monkeypatch.setenv("CLAUDE_SHIM_TOUCH", str(repo / "half-edit.txt"))
    with pytest.raises(UsagePause) as ei:
        _call_tooled(_cli(tmp_path), repo, ("Read", "Edit"))
    assert ei.value.dirty is True


# The four llm-usage-limit sites: toolless non-JSON, toolless JSON reply that fails
# validation, tooled non-JSON, tooled error envelope.
LIMIT_SITES = [
    ("toolless-nonjson", LIMIT_TEXT),
    ("toolless-json", _envelope(LIMIT_TEXT)),
    ("tooled-nonjson", LIMIT_TEXT),
    ("tooled-envelope", _envelope(LIMIT_TEXT, is_error=True)),
]


def _limit_call(cli, site, tmp_path):
    if site.startswith("toolless"):
        def bad(_):
            raise ValueError("schema")
        os.makedirs(cli.workdir, exist_ok=True)
        return cli.call("p", "haiku", "prompt", bad, max_retries=0)
    return _call_tooled(cli, _git_repo(tmp_path / "wt"))


@pytest.mark.parametrize("site,reply", LIMIT_SITES)
def test_limit_text_pauses_when_marker_written(shim, gate, tmp_path, monkeypatch, site, reply):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    monkeypatch.setenv("FAKE_LIMIT_RC", "0")
    cli = _cli(tmp_path)
    with pytest.raises(UsagePause):
        _limit_call(cli, site, tmp_path)
    assert cli.paused.is_set()
    assert f"limit post-plan-now {S} " in gate.read_text()


@pytest.mark.parametrize("site,reply", LIMIT_SITES)
def test_limit_text_marker_write_failure_stays_exit3(shim, gate, tmp_path, monkeypatch, site,
                                                     reply):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    monkeypatch.setenv("FAKE_LIMIT_RC", "1")
    cli = _cli(tmp_path)
    with pytest.raises(HarnessError) as ei:
        _limit_call(cli, site, tmp_path)
    assert ei.value.kind == "llm-usage-limit"
    assert not cli.paused.is_set()


# ---------------------------------------------------------------- Phase 6

def _res(kind, error=None):
    res = RunResult(terminal=TerminalState.FAILED, slug="feat/x")
    res.terminal = TerminalState.FAILED
    res.error_kind = kind
    res.error = error or f"{kind}: pr-copy"
    return res


def test_exit_code_for_usage_pause_is_75():
    assert runner.exit_code_for(_res("usage-pause")) == 75


def test_exit_code_for_unconfirmed_and_dirty_are_3():
    assert runner.exit_code_for(_res("usage-pause-unconfirmed")) == 3
    assert runner.exit_code_for(_res("usage-pause-dirty")) == 3


CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit",
                "summary_md": "## Summary\n- x\n"},
    "body-check": {"corrected_body": "## Summary\n- replay body\n", "findings": []},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _fixture(**over):
    """Synthetic replay fixture (the test_runner_review_owed.py shape)."""
    fx = {
        "slug": "usage-pause-test",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9999,
        "pr_meta": {"number": 9999, "title": "fix: synthetic",
                    "body": "## Summary\n- 1 file changed\n\n## Manual Testing\n\nNone\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody.\n",
        "current_tree": "a" * 40,
    }
    fx.update(over)
    return fx


def _main(tmp_path, monkeypatch, capsys, canned_over=None, fixture=None):
    """Drive runner.main() through a canned replay run; return (rc, stdout, out_dir)."""
    canned = dict(CANNED)
    canned.update(canned_over or {})
    cpath = tmp_path / "canned.json"
    cpath.write_text(json.dumps(canned))
    fpath = tmp_path / "fixture.json"
    fpath.write_text(json.dumps(fixture or _fixture()))
    out = tmp_path / "out"
    monkeypatch.setattr(sys, "argv", ["runner.py", "--mode", "replay", "--fixture", str(fpath),
                                      "--out", str(out), "--canned", str(cpath)])
    monkeypatch.setattr(runner, "_install_sigterm_handler", lambda: None)
    rc = runner.main()
    return rc, capsys.readouterr().out, out


def _result_lines(stdout):
    return [ln for ln in stdout.splitlines() if ln.startswith("RESULT:")]


@pytest.fixture()
def ambient(stub_ambient_git_show):
    return None


def test_run_pause_with_marker_exits_75(gate, tmp_path, monkeypatch, capsys, ambient):
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    rc, stdout, out = _main(tmp_path, monkeypatch, capsys, {"pr-copy": {"kind": "usage-pause"}})
    assert rc == 75
    assert stdout.splitlines()[0].startswith("RESULT: post-plan PAUSED")
    assert len(_result_lines(stdout)) == 1
    assert not (out / "blocked-ship.txt").exists()


def test_run_pause_without_marker_exits_3(gate, tmp_path, monkeypatch, capsys, ambient):
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "1")
    rc, stdout, out = _main(tmp_path, monkeypatch, capsys, {"pr-copy": {"kind": "usage-pause"}})
    assert rc == 3
    assert (out / "blocked-ship.txt").exists()
    assert "usage-pause-unconfirmed" in stdout.splitlines()[0]


def test_run_pause_without_gate_context_exits_3(tmp_path, monkeypatch, capsys, ambient):
    rc, stdout, _ = _main(tmp_path, monkeypatch, capsys, {"pr-copy": {"kind": "usage-pause"}})
    assert rc == 3
    assert "RESULT: post-plan PAUSED" not in stdout


def test_dirty_pause_clears_marker_exits_3(gate, tmp_path, monkeypatch, capsys, ambient):
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    rc, stdout, _ = _main(tmp_path, monkeypatch, capsys,
                          {"pr-copy": {"kind": "usage-pause", "dirty": True}})
    assert rc == 3
    assert f"clear {S}" in gate.read_text()
    assert "usage-pause-dirty" in stdout.splitlines()[0]


@pytest.mark.parametrize("over,want_rc", [
    ({}, 0),
    ({"pr-copy": {"raise": {"kind": "local-gate", "detail": "hook denied"}}}, None),
])
def test_non_pause_exit_clears_stray_marker(gate, tmp_path, monkeypatch, capsys, ambient,
                                            over, want_rc):
    """A stray S marker at a non-75 exit is cleared: exit 75 if and only if marker."""
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    if want_rc is None:
        # pr-copy goes through FixtureLlm.call, which has no raise spec: drive a
        # local-gate failure through run() directly.
        res = _res("local-gate", "local-gate: hook denied")
        monkeypatch.setattr(runner, "run", lambda *a, **k: res)
        want_rc = 3
    rc, _stdout, _ = _main(tmp_path, monkeypatch, capsys, over if want_rc == 0 else {})
    assert rc == want_rc
    assert gate.read_text().count(f"clear {S}") == 1


def test_review_thread_pause_exits_75_once(gate, tmp_path, monkeypatch, capsys, ambient):
    """A pause on the review worker reaches the review join; one verdict, rc 75.

    A plan-blind run with no Manual Testing section calls manual-classify on the main
    thread before the inline join. It pauses too, so the run fails first and the
    review pause surfaces at the `finally` join, the clause this pins."""
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    pause = {"kind": "usage-pause"}
    fx = _fixture(plan_content=None)
    fx["pr_meta"] = dict(fx["pr_meta"], body="## Summary\n- 1 file changed\n")
    rc, stdout, out = _main(tmp_path, monkeypatch, capsys, {
        "review-agent-a": pause, "review-agent-b": pause, "review-agent-d": pause,
        "security-audit": pause, "manual-classify": pause}, fixture=fx)
    assert rc == 75
    assert len(_result_lines(stdout)) == 1
    assert stdout.splitlines()[0].startswith("RESULT: post-plan PAUSED")
    audit = (out / "audit.log").read_text()
    assert "PAUSED: usage-pause: manual-classify" in audit
    assert "background review failed after the run failed" in audit


# ---------------------------------------------------------------- Phase 8

class FakeGh:
    def __init__(self):
        self.calls = []
        self._lock = threading.Lock()

    def _rec(self, name, *args):
        with self._lock:
            self.calls.append((name, args))

    def post_review_summary(self, pr, title, body):
        self._rec("post_review_summary", pr, title, body)
        return {"posted": len(self.calls)}

    def post_review_findings(self, pr, head_sha, title, findings):
        self._rec("post_review_findings", pr, head_sha, title)
        return {"posted": len(self.calls)}

    def pr_merge_auto(self, pr):
        self._rec("pr_merge_auto", pr)


@pytest.fixture()
def ledger_env(gate, tmp_path, monkeypatch):
    state = tmp_path / "ugstate"
    monkeypatch.setenv("FAKE_STATE_DIR", str(state))
    return state / "runs" / f"{S}.effects.json"


def test_ledger_skips_post_from_earlier_run(ledger_env, tmp_path):
    repo = _git_repo(tmp_path / "wt")
    fake = FakeGh()
    a = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "a"))
    first = a.post_review_summary(5, "Review", "body")
    b = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "b"))
    second = b.post_review_summary(5, "Review", "body")
    assert len(fake.calls) == 1
    assert second == first


def test_ledger_allows_repeat_within_same_run(ledger_env, tmp_path):
    repo = _git_repo(tmp_path / "wt")
    fake = FakeGh()
    a = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "a"))
    a.post_review_summary(5, "Review", "body")
    a.post_review_summary(5, "Review", "body")
    assert len(fake.calls) == 2


def test_ledger_posts_again_on_new_head(ledger_env, tmp_path):
    repo = _git_repo(tmp_path / "wt")
    fake = FakeGh()
    usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "a")).post_review_summary(
        5, "Review", "body")
    (repo / "f.txt").write_text("next\n")
    subprocess.run(["git", "commit", "-qam", "next"], cwd=str(repo), check=True)
    usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "b")).post_review_summary(
        5, "Review", "body")
    assert len(fake.calls) == 2


@pytest.mark.parametrize("sid", [None, ""])
def test_ledger_inactive_without_context(tmp_path, monkeypatch, sid):
    root = _fake_root(tmp_path)
    monkeypatch.setenv("IBL5_USAGE_GATE_RUNNER", "post-plan-now")
    monkeypatch.setenv("IBL5_USAGE_GATE_RESUME_BIN", str(root / "bin" / "post-plan-now"))
    monkeypatch.setenv("FAKE_STATE_DIR", str(tmp_path / "ugstate"))
    if sid is not None:
        monkeypatch.setenv("IBL5_USAGE_GATE_SESSION_ID", sid)
    fake = FakeGh()
    assert usage_pause.dedupe_on_resume(fake, str(tmp_path), str(tmp_path / "a")) is fake
    assert not (tmp_path / "ugstate").exists()


def test_ledger_head_lookup_failure_posts(ledger_env, tmp_path):
    not_repo = tmp_path / "plain"
    not_repo.mkdir()
    fake = FakeGh()
    a = usage_pause.dedupe_on_resume(fake, str(not_repo), str(tmp_path / "a"))
    b = usage_pause.dedupe_on_resume(fake, str(not_repo), str(tmp_path / "b"))
    a.post_review_summary(5, "Review", "body")
    b.post_review_summary(5, "Review", "body")
    assert len(fake.calls) == 2
    assert not ledger_env.exists()


def test_ledger_passthrough_other_methods(ledger_env, tmp_path):
    repo = _git_repo(tmp_path / "wt")
    fake = FakeGh()
    a = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "a"))
    b = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "b"))
    a.pr_merge_auto(5)
    b.pr_merge_auto(5)
    assert [c[0] for c in fake.calls] == ["pr_merge_auto", "pr_merge_auto"]
    assert not ledger_env.exists()


def test_ledger_concurrent_records(ledger_env, tmp_path):
    repo = _git_repo(tmp_path / "wt")
    fake = FakeGh()
    a = usage_pause.dedupe_on_resume(fake, str(repo), str(tmp_path / "a"))
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as ex:
        list(ex.map(lambda i: a.post_review_summary(5, f"T{i}", "b"), range(8)))
    entries = json.loads(ledger_env.read_text())
    assert len(entries) == 8


def test_ledger_cleared_on_non_pause_exit(ledger_env, tmp_path, monkeypatch, capsys, ambient):
    ledger_env.parent.mkdir(parents=True)
    ledger_env.write_text("[]")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "1")
    rc, _, _ = _main(tmp_path, monkeypatch, capsys)
    assert rc == 0
    assert not ledger_env.exists()

    ledger_env.write_text("[]")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    shutil.rmtree(tmp_path / "out", ignore_errors=True)
    rc, _, _ = _main(tmp_path, monkeypatch, capsys, {"pr-copy": {"kind": "usage-pause"}})
    assert rc == 75
    assert ledger_env.exists()


# ---------------------------------------------------------------- Phase 9

def test_real_lib_prespawn_decide_keys_run_sid(tmp_path, monkeypatch):
    """The Phase 4 call shape against the real usage_gate_decide, stop zone."""
    state = tmp_path / "state"
    (state / "markers").mkdir(parents=True)
    body = {"five_hour": {"utilization": 99.0, "resets_at": "2026-09-28T20:00:00+00:00"},
            "seven_day": {"utilization": 10.0, "resets_at": "2026-10-02T00:00:00+00:00"},
            "fetched_at": int(time.time())}
    (state / "cache.json").write_text(json.dumps(body))
    monkeypatch.setenv("IBL5_USAGE_GATE_STATE_DIR", str(state))
    monkeypatch.setenv("HOME", str(tmp_path / "home"))
    ctx = GateContext("post-plan-now", os.path.join(REPO, "bin", "post-plan-now"), S,
                      os.path.join(REPO, "bin", "lib", "usage-gate.sh"))
    assert usage_pause.prespawn_decide(ctx, str(tmp_path)) is True
    marker = json.loads((state / "markers" / f"{S}.json").read_text())
    assert marker["runner"] == "post-plan-now"
    assert usage_pause.marker_exists(ctx) is True


# ---------------------------------------------------------------- dirty-resume Phase 1

def _sh(cwd, *args):
    return subprocess.run(["git", *args], cwd=str(cwd), check=True, capture_output=True,
                          text=True).stdout.strip()


def test_tools_can_write_allowlist():
    assert usage_pause.tools_can_write("Read,Grep,Glob") is False
    assert usage_pause.tools_can_write("Read,Edit") is True
    assert usage_pause.tools_can_write(["Read", "mcp__x__y"]) is True
    assert usage_pause.tools_can_write("Bash(git:*)") is True
    assert usage_pause.tools_can_write("Read,Bash", "Bash") is False
    assert usage_pause.tools_can_write("") is True
    assert usage_pause.tools_can_write(("Read", "Grep"), ("Bash", "Agent")) is False


def test_capture_prespawn_none_outside_repo(tmp_path):
    plain = tmp_path / "plain"
    plain.mkdir()
    assert usage_pause.capture_prespawn(str(plain)) is None
    assert usage_pause.edit_since(str(plain), None) is True


def test_edit_since_untracked_file_is_edit(tmp_path):
    repo = _git_repo(tmp_path / "wt")
    pre = usage_pause.capture_prespawn(str(repo))
    assert pre is not None
    (repo / "new.txt").write_text("x\n")
    assert usage_pause.edit_since(str(repo), pre) is True
    (repo / "new.txt").unlink()
    assert usage_pause.edit_since(str(repo), pre) is False


def test_edit_since_head_move_is_edit(tmp_path):
    repo = _git_repo(tmp_path / "wt")
    pre = usage_pause.capture_prespawn(str(repo))
    _sh(repo, "commit", "-q", "--allow-empty", "-m", "moved")
    assert usage_pause.edit_since(str(repo), pre) is True


def test_edit_since_merge_head_is_edit(tmp_path):
    repo = _git_repo(tmp_path / "wt")
    pre = usage_pause.capture_prespawn(str(repo))
    (repo / ".git" / "MERGE_HEAD").write_text(_sh(repo, "rev-parse", "HEAD") + "\n")
    assert usage_pause.edit_since(str(repo), pre) is True


# ---------------------------------------------------------------- dirty-resume Phase 2

READ_ONLY = ("Read", "Grep", "Glob")


def test_readonly_tooled_pause_fp_none_not_dirty(shim, gate, tmp_path, monkeypatch):
    """The nested-repo false dirty: git fails, yet a read-only call is never dirty."""
    repo = _git_repo(tmp_path / "wt")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    monkeypatch.setattr(usage_pause, "capture_prespawn", lambda cwd: None)
    monkeypatch.setattr(usage_pause, "worktree_fingerprint", lambda cwd: None)
    with pytest.raises(UsagePause) as ei:
        _call_tooled(_cli(tmp_path), repo, READ_ONLY)
    assert ei.value.dirty is False
    assert ei.value.prespawn is None


def test_readonly_tooled_pause_with_touch_not_dirty(shim, gate, tmp_path, monkeypatch):
    """A concurrent writer cannot make a read-only review pause dirty."""
    repo = _git_repo(tmp_path / "wt")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    monkeypatch.setenv("CLAUDE_SHIM_TOUCH", str(repo / "stray.txt"))
    with pytest.raises(UsagePause) as ei:
        _call_tooled(_cli(tmp_path), repo, READ_ONLY)
    assert ei.value.dirty is False


def test_tooled_hook_pause_carries_prespawn(shim, gate, tmp_path, monkeypatch):
    repo = _git_repo(tmp_path / "wt")
    head = _sh(repo, "rev-parse", "HEAD")
    monkeypatch.setenv("FAKE_MARKER_EXISTS_RC", "0")
    monkeypatch.setenv("CLAUDE_SHIM_TOUCH", str(repo / "half-edit.txt"))
    with pytest.raises(UsagePause) as ei:
        _call_tooled(_cli(tmp_path), repo, ("Read", "Edit", "Write"))
    assert ei.value.dirty is True
    assert ei.value.prespawn is not None
    assert ei.value.prespawn.head == head
    assert ei.value.cwd == str(repo)


def test_write_overlap_flag_set_on_concurrent_calls(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("CLAUDE_SHIM_SLEEP", "1")
    repo = _git_repo(tmp_path / "wt")

    def both(tools_a, tools_b):
        cli = _cli(tmp_path)
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as ex:
            futs = [ex.submit(_call_tooled, cli, repo, t) for t in (tools_a, tools_b)]
            for f in futs:
                f.result()
        return cli.write_overlap

    assert both(("Read", "Edit"), ("Read", "Edit")) is True
    assert both(("Read",), ("Read", "Edit")) is False


# ---------------------------------------------------------------- dirty-resume Phase 3

@pytest.fixture()
def rec_gate(gate, tmp_path, monkeypatch):
    """Gate env plus a state dir, so the pause record has somewhere to live."""
    state = tmp_path / "gate-state"
    monkeypatch.setenv("FAKE_STATE_DIR", str(state))
    ctx, why = usage_pause.context_from_env()
    assert why == "ok"
    return ctx, state / "runs" / f"{S}.pause.json"


def _edit_repo(tmp_path):
    """A repo with tracked b.txt, plus a pre-spawn staged change a.txt."""
    repo = _git_repo(tmp_path / "wt")
    (repo / "b.txt").write_text("b\n")
    _sh(repo, "add", "b.txt")
    _sh(repo, "commit", "-qm", "b")
    (repo / "a.txt").write_text("staged\n")
    _sh(repo, "add", "a.txt")
    return repo


def _partial_edit(repo):
    (repo / "b.txt").write_text("half\n")
    (repo / "c.txt").write_text("new\n")


def _set_merge_head(repo):
    (repo / ".git" / "MERGE_HEAD").write_text(_sh(repo, "rev-parse", "HEAD") + "\n")


def test_pause_record_roundtrip(rec_gate, tmp_path):
    ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    assert usage_pause.pause_record_write(ctx, "gate-fix", pre, str(repo)) is None
    status, rec = usage_pause.pause_record_load(ctx)
    assert status == "ok" and path.exists()
    assert rec["pre_tree"] == pre.tree
    assert rec["post_tree"] != pre.tree
    assert rec["post_tree"] == usage_pause.capture_prespawn(str(repo)).tree
    assert sorted(p for _, p in rec["changed"]) == ["b.txt", "c.txt"]
    assert rec["session_id"] == S and rec["head"] == pre.head


def test_pause_record_refuses_head_move(rec_gate, tmp_path):
    ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    _sh(repo, "commit", "-q", "--allow-empty", "-m", "moved")
    assert usage_pause.pause_record_write(ctx, "ci-fix", pre, str(repo)) == "head-moved"
    assert not path.exists()


def test_pause_record_refuses_merge_head(rec_gate, tmp_path):
    ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    _set_merge_head(repo)
    assert usage_pause.pause_record_write(ctx, "conflict", pre, str(repo)) == "op-in-progress"
    assert not path.exists()


def _recorded(rec_gate, tmp_path):
    ctx, _ = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    assert usage_pause.pause_record_write(ctx, "gate-fix", pre, str(repo)) is None
    return ctx, repo, usage_pause.pause_record_load(ctx)[1]


def test_restore_discards_only_the_edit(rec_gate, tmp_path):
    _ctx, repo, rec = _recorded(rec_gate, tmp_path)
    assert usage_pause.restore_from_record(str(repo), rec, S) is None
    assert (repo / "b.txt").read_text() == "b\n"
    assert not (repo / "c.txt").exists()
    assert (repo / "a.txt").read_text() == "staged\n"
    assert "a.txt" in _sh(repo, "diff", "--cached", "--name-only")


def test_restore_refuses_unrelated_dirt(rec_gate, tmp_path):
    _ctx, repo, rec = _recorded(rec_gate, tmp_path)
    (repo / "d.txt").write_text("stranger\n")
    before = {n: (repo / n).read_bytes() for n in ("a.txt", "b.txt", "c.txt", "d.txt")}
    assert usage_pause.restore_from_record(str(repo), rec, S) == "tree-mismatch"
    assert {n: (repo / n).read_bytes() for n in before} == before


def test_restore_refuses_session_mismatch_and_malformed(rec_gate, tmp_path):
    ctx, repo, rec = _recorded(rec_gate, tmp_path)
    other = "6b6b6b6b-1111-4222-8333-444444444444"
    assert usage_pause.restore_from_record(str(repo), rec, other) == "session-mismatch"
    assert (repo / "b.txt").read_text() == "half\n"
    _, path = rec_gate
    path.write_text(json.dumps({"version": 2}))
    assert usage_pause.pause_record_load(ctx) == ("malformed", None)


def test_ledger_clear_removes_pause_record(rec_gate, tmp_path):
    _ctx, path = rec_gate
    path.parent.mkdir(parents=True)
    path.write_text("{}")
    effects = path.parent / f"{S}.effects.json"
    effects.write_text("[]")
    usage_pause.ledger_clear()
    assert not path.exists()
    assert not effects.exists()


# ---------------------------------------------------------------- dirty-resume Phase 4

class _StubLlm:
    write_overlap = False


def _classify(p, llm=None):
    res = RunResult(terminal=TerminalState.FAILED, slug="x")
    logs = []
    kind = runner._classify_pause(p, llm or _StubLlm(), res, logs.append)
    return kind, res, logs


def _dirty_pause(repo, pre):
    return UsagePause("gate-fix", dirty=True, prespawn=pre, cwd=str(repo))


def test_classify_pause_records_edit(rec_gate, tmp_path):
    _ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    kind, res, logs = _classify(_dirty_pause(repo, pre))
    assert kind == "usage-pause"
    assert res.pause_edit_sid == S
    assert path.exists()
    assert any("partial edit recorded (2 paths)" in ln for ln in logs)


def test_classify_pause_site_restored_is_clean(rec_gate, tmp_path):
    _ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    (repo / "c.txt").write_text("new\n")
    (repo / "c.txt").unlink()
    kind, res, logs = _classify(_dirty_pause(repo, pre))
    assert kind == "usage-pause"
    assert res.pause_edit_sid is None
    assert not path.exists()
    assert any("site cleanup already restored" in ln for ln in logs)


def test_classify_pause_overlap_stays_dirty(rec_gate, tmp_path):
    _ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    llm = _StubLlm()
    llm.write_overlap = True
    kind, res, _ = _classify(_dirty_pause(repo, pre), llm)
    assert kind == "usage-pause-dirty"
    assert res.pause_edit_sid is None
    assert not path.exists()


def test_classify_pause_no_prespawn_stays_dirty(rec_gate):
    kind, res, _ = _classify(UsagePause("x", dirty=True))
    assert kind == "usage-pause-dirty"
    assert res.pause_edit_sid is None


def test_classify_pause_head_move_stays_dirty(rec_gate, tmp_path):
    _ctx, path = rec_gate
    repo = _edit_repo(tmp_path)
    pre = usage_pause.capture_prespawn(str(repo))
    _partial_edit(repo)
    _sh(repo, "commit", "-q", "--allow-empty", "-m", "moved")
    kind, res, logs = _classify(_dirty_pause(repo, pre))
    assert kind == "usage-pause-dirty"
    assert res.pause_edit_sid is None
    assert not path.exists()
    assert any("not recordable (head-moved)" in ln for ln in logs)


# ---------------------------------------------------------------- dirty-resume Phase 5

class _StubState:
    def __init__(self, previous):
        self.previous = previous


def _reconcile(repo, previous):
    res = RunResult(terminal=TerminalState.FAILED, slug="x")
    logs = []
    runner._reconcile_paused_edit(_StubState(previous), res, str(repo), logs.append)
    return res, logs


def test_reconcile_no_witness_is_noop(rec_gate, tmp_path):
    repo = _edit_repo(tmp_path)
    _partial_edit(repo)
    before = {n: (repo / n).read_bytes() for n in ("a.txt", "b.txt", "c.txt")}
    res, _ = _reconcile(repo, {})
    assert res.pause_edit_sid is None
    assert {n: (repo / n).read_bytes() for n in before} == before


def test_reconcile_restores_and_clears(rec_gate, tmp_path):
    _ctx, repo, rec = _recorded(rec_gate, tmp_path)
    _, path = rec_gate
    res, logs = _reconcile(repo, {"pause_edit_sid": S})
    assert usage_pause.capture_prespawn(str(repo)).tree == rec["pre_tree"]
    assert (repo / "b.txt").read_text() == "b\n"
    assert not (repo / "c.txt").exists()
    assert not path.exists()
    assert res.pause_edit_sid is None
    assert any("discarded interrupted edit from gate-fix (2 paths)" in ln for ln in logs)


def test_reconcile_missing_record_fails_closed(rec_gate, tmp_path):
    repo = _edit_repo(tmp_path)
    _partial_edit(repo)
    with pytest.raises(HarnessError) as ei:
        _reconcile(repo, {"pause_edit_sid": S})
    assert ei.value.kind == "usage-pause-dirty"
    assert "pause record missing" in ei.value.detail
    assert (repo / "b.txt").read_text() == "half\n"
    assert (repo / "c.txt").exists()


OLD_S = "7c7c7c7c-1111-4222-8333-444444444444"


def test_reconcile_session_mismatch_keeps_witness(rec_gate, tmp_path):
    _ctx, path = rec_gate
    old = path.parent / f"{OLD_S}.pause.json"
    old.parent.mkdir(parents=True)
    old.write_text("{}")
    repo = _edit_repo(tmp_path)
    _partial_edit(repo)
    res = RunResult(terminal=TerminalState.FAILED, slug="x")
    with pytest.raises(HarnessError) as ei:
        runner._reconcile_paused_edit(_StubState({"pause_edit_sid": OLD_S}), res,
                                      str(repo), lambda m: None)
    assert ei.value.kind == "usage-pause-dirty"
    assert f"--resume-paused {OLD_S}" in ei.value.detail
    assert res.pause_edit_sid == OLD_S
    assert (repo / "b.txt").read_text() == "half\n"


def test_reconcile_session_mismatch_record_gone_drops_witness(rec_gate, tmp_path):
    repo = _edit_repo(tmp_path)
    _partial_edit(repo)
    res = RunResult(terminal=TerminalState.FAILED, slug="x")
    with pytest.raises(HarnessError) as ei:
        runner._reconcile_paused_edit(_StubState({"pause_edit_sid": OLD_S}), res,
                                      str(repo), lambda m: None)
    assert ei.value.kind == "usage-pause-dirty"
    assert res.pause_edit_sid is None


def test_reconcile_head_moved_fails_closed(rec_gate, tmp_path):
    _ctx, repo, _rec = _recorded(rec_gate, tmp_path)
    _sh(repo, "commit", "-q", "--allow-empty", "-m", "moved")
    with pytest.raises(HarnessError) as ei:
        _reconcile(repo, {"pause_edit_sid": S})
    assert ei.value.kind == "usage-pause-dirty"
    assert "head-moved" in ei.value.detail
    assert (repo / "b.txt").read_text() == "half\n"
