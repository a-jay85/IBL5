"""Phase 1 — the bounded, tool-enabled `claude -p` call path.

Driven by a fake `claude` executable placed first on PATH, the same shape
tests/test_ghad_live.py uses for `gh`.
"""
import json
import os
import stat
import sys
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import (
    ENVELOPE_ERROR_TEXT_CAP,
    MODEL_MAP,
    TOOLED_MAX_TURNS,
    ClaudeCli,
    FixtureLlm,
    _tooled_argv,
    _tooled_env,
)
from harness.fidelity import (
    REMEDIATION_ALLOWED_TOOLS,
    REMEDIATION_DENIED_TOOLS,
    REVIEW_ALLOWED_TOOLS,
    REVIEW_DENIED_TOOLS,
)
from harness.state import HarnessError, UsageLedger

# Fake `claude`: logs its argv, then emits whatever CLAUDE_SHIM_REPLY holds.
SHIM = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
if [ -n "${CLAUDE_SHIM_ENV_LOG:-}" ]; then
  printf '%s\\n' "${PERSIST_GATE_SKIP:-unset}" >> "$CLAUDE_SHIM_ENV_LOG"
fi
cat > /dev/null
if [ -n "${CLAUDE_SHIM_SPAWN_PID_FILE:-}" ]; then
  bash -c 'echo $$ > "$1"; exec sleep 60' _ "$CLAUDE_SHIM_SPAWN_PID_FILE" &
  sleep 60
fi
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit 0
"""

OK_ENVELOPE = json.dumps({
    "result": "VERDICT: PASS",
    "subtype": "success",
    "usage": {"input_tokens": 11, "output_tokens": 22},
    "duration_ms": 4242,
    "total_cost_usd": 0.5,
})


@pytest.fixture()
def shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    claude = bindir / "claude"
    claude.write_text(SHIM)
    claude.chmod(claude.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "claude-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("CLAUDE_SHIM_LOG", str(log))
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", OK_ENVELOPE)
    return log


def _cli(tmp_path):
    return ClaudeCli(UsageLedger(), workdir=str(tmp_path))


# --- argv contract -----------------------------------------------------------

def test_tooled_argv_is_bounded_and_narrowed():
    argv = _tooled_argv(
        "opus", agent="pr-ready-phase6", allowed_tools=("Read", "Grep", "Glob"),
        denied_tools=("Bash", "Agent", "Edit"), add_dirs=("/tmp/wt",),
        append_system_prompt=None, setting_sources="user,project",
        max_turns=TOOLED_MAX_TURNS,
    )
    joined = " ".join(argv)
    assert "--agent" in argv and "pr-ready-phase6" in argv
    assert "--model" not in argv          # the def's own pin must win
    assert "--allowedTools" in argv
    assert argv[argv.index("--permission-prompts") + 1] == "none"
    assert argv[argv.index("--setting-sources") + 1] == "user,project"
    assert argv[argv.index("--max-turns") + 1] == "60"
    tools_value = argv[argv.index("--tools") + 1]
    assert tools_value == "Read,Grep,Glob"
    assert tools_value not in ("", "default")
    denied = argv[argv.index("--disallowedTools") + 1]
    assert "Bash" in denied and "Agent" in denied
    assert "--max-turns 1" not in joined
    assert argv[argv.index("--add-dir") + 1] == "/tmp/wt"


def test_tooled_argv_uses_model_when_no_agent():
    argv = _tooled_argv(
        "opus", agent=None, allowed_tools=("Read",), denied_tools=("Bash",),
        add_dirs=(), append_system_prompt="extra", setting_sources="user",
        max_turns=30,
    )
    assert argv[argv.index("--model") + 1] == "claude-opus-5"
    assert "--agent" not in argv
    assert argv[argv.index("--append-system-prompt") + 1] == "extra"


# --- persist-gate opt-out ----------------------------------------------------
# A write-less session cannot satisfy ~/.claude/hooks/subagent-persist-gate.py. When
# the gate blocked the fidelity reviewer's stop, its forced "no Write tool" reply
# replaced the verdict in the envelope `result` (PR #2297).

def test_write_less_review_skips_persist_gate():
    env = _tooled_env(REVIEW_ALLOWED_TOOLS, REVIEW_DENIED_TOOLS)
    assert env["PERSIST_GATE_SKIP"] == "1"
    assert env["CLAUDE_HEADLESS"] == "1"


def test_writing_remediation_keeps_persist_gate(monkeypatch):
    monkeypatch.setenv("PERSIST_GATE_SKIP", "1")   # never inherited onto a writer
    env = _tooled_env(REMEDIATION_ALLOWED_TOOLS, REMEDIATION_DENIED_TOOLS)
    assert "PERSIST_GATE_SKIP" not in env


@pytest.mark.parametrize("allowed,denied,skips", [
    (("Read", "Bash"), ("Agent",), False),     # Bash can redirect to a file
    (("Read", "Bash"), ("Bash",), True),       # denied wins over allowed
    (("Read", "Edit"), (), False),
    (("Read",), None, True),
])
def test_persist_gate_skip_tracks_usable_write_tools(allowed, denied, skips):
    assert ("PERSIST_GATE_SKIP" in _tooled_env(allowed, denied)) is skips


def test_skip_reaches_the_cli_subprocess(shim, tmp_path, monkeypatch):
    env_log = tmp_path / "env.log"
    monkeypatch.setenv("CLAUDE_SHIM_ENV_LOG", str(env_log))
    monkeypatch.delenv("PERSIST_GATE_SKIP", raising=False)
    cli = _cli(tmp_path)
    cli.call_tooled("r", "opus", "p", cwd=str(tmp_path),
                    allowed_tools=REVIEW_ALLOWED_TOOLS, denied_tools=REVIEW_DENIED_TOOLS)
    cli.call_tooled("m", "sonnet", "p", cwd=str(tmp_path),
                    allowed_tools=REMEDIATION_ALLOWED_TOOLS,
                    denied_tools=REMEDIATION_DENIED_TOOLS)
    assert env_log.read_text().splitlines() == ["1", "unset"]


# --- empty allowlist is a rejected form --------------------------------------

def test_empty_allowlist_rejected_without_launching(shim, tmp_path):
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=())
    assert exc.value.kind == "llm-tooled-no-tools"
    assert shim.read_text() == ""          # zero invocations


def test_fixture_empty_allowlist_rejected():
    fx = FixtureLlm(UsageLedger(), {"fidelity": "VERDICT: PASS"})
    with pytest.raises(HarnessError) as exc:
        fx.call_tooled("fidelity", "opus", "p", cwd="/tmp", allowed_tools=())
    assert exc.value.kind == "llm-tooled-no-tools"


# --- toolless regression ------------------------------------------------------

def test_toolless_call_still_single_turn_no_tools(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({"result": '{"ok": true}'}))
    _cli(tmp_path).call("probe", "sonnet", "p", validate=lambda d: None)
    logged = shim.read_text()
    assert "--max-turns 1" in logged
    assert "--tools" in logged
    assert "--model claude-sonnet-4-6" in logged


# --- envelope degradation (forced integration) --------------------------------

@pytest.mark.parametrize("reply,kind", [
    ("not json at all", "llm-tooled-cli"),
    (json.dumps({"is_error": True, "result": "READY"}), "llm-tooled-error"),
    (json.dumps({"subtype": "error_max_turns", "result": "READY"}), "llm-tooled-error"),
    (json.dumps({"result": ""}), "llm-tooled-empty"),
])
def test_degraded_envelope_is_fail_closed(shim, tmp_path, monkeypatch, reply, kind):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), max_retries=0)
    assert exc.value.kind == kind


def test_missing_usage_key_still_returns_text(shim, tmp_path, monkeypatch):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY",
                       json.dumps({"result": "VERDICT: PASS", "subtype": "success"}))
    ledger = UsageLedger()
    cli = ClaudeCli(ledger, workdir=str(tmp_path))
    out = cli.call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                          allowed_tools=("Read",))
    assert out == "VERDICT: PASS"
    assert ledger.calls[0].input_tokens == 0
    assert ledger.calls[0].output_tokens == 0


# --- process-group reap -------------------------------------------------------

def test_timeout_reaps_the_process_group(shim, tmp_path, monkeypatch):
    pid_file = tmp_path / "grandchild.pid"
    monkeypatch.setenv("CLAUDE_SHIM_SPAWN_PID_FILE", str(pid_file))
    with pytest.raises(HarnessError):
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), timeout=2, max_retries=0)
    assert pid_file.exists() and pid_file.read_text().strip(), \
        "shim never spawned its grandchild"
    pid = int(pid_file.read_text().strip())
    deadline = time.time() + 5
    while time.time() < deadline:
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return
        time.sleep(0.1)
    pytest.fail(f"grandchild {pid} survived the process-group reap")


# --- model allowlist ----------------------------------------------------------

@pytest.mark.parametrize("model", ["fable", "haiku", "claude-opus-5", "opuss"])
def test_model_allowlist_rejects_without_launching(shim, tmp_path, model):
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", model, "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",))
    assert exc.value.kind == "llm-model-not-allowed"
    assert shim.read_text() == ""


def test_opus_resolves_to_claude_opus_5():
    assert MODEL_MAP["opus"] == "claude-opus-5"


# --- ledger -------------------------------------------------------------------

def test_ledger_records_usage_and_duration(shim, tmp_path):
    ledger = UsageLedger()
    cli = ClaudeCli(ledger, workdir=str(tmp_path))
    out = cli.call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                          allowed_tools=("Read", "Grep"))
    assert out == "VERDICT: PASS"
    assert len(ledger.calls) == 1
    rec = ledger.calls[0]
    assert rec.duration_ms == 4242
    assert rec.input_tokens == 11
    assert rec.output_tokens == 22
    assert rec.model == "claude-opus-5"
    assert rec.ok is True


def test_fixture_tooled_records_argv_and_serves_canned():
    fx = FixtureLlm(UsageLedger(), {"fidelity": "VERDICT: PASS"})
    out = fx.call_tooled("fidelity", "opus", "p", cwd="/tmp",
                         allowed_tools=("Read",), agent="pr-ready-phase6")
    assert out == "VERDICT: PASS"
    purpose, argv = fx.tooled_argvs[0]
    assert purpose == "fidelity"
    assert "--agent" in argv and "--max-turns 1" not in " ".join(argv)


# --- Phase 11: close() -----------------------------------------------------------

def test_close_removes_only_owned_workdir(tmp_path):
    # Owned workdir: created by ClaudeCli itself (no workdir arg)
    cli_owned = ClaudeCli(UsageLedger())
    owned_dir = cli_owned.workdir
    assert os.path.exists(owned_dir)
    cli_owned.close()
    assert not os.path.exists(owned_dir)
    # Idempotent: second call must not raise
    cli_owned.close()

    # Not-owned workdir: caller supplies the path; close() must not remove it
    cli_external = ClaudeCli(UsageLedger(), workdir=str(tmp_path))
    assert os.path.exists(str(tmp_path))
    cli_external.close()
    assert os.path.exists(str(tmp_path))


# --- Phase 9 retrospective envelope variations (forced-integration) ---------------

def test_retrospective_envelope_variation(shim, tmp_path, monkeypatch):
    """Covers all four schema outcomes for the retrospective call path."""
    import sys
    sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    from harness import schemas

    # Case 1: ```json-fenced {"save": false} -> returns {"save": False}
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "result": '```json\n{"save": false}\n```', "subtype": "success",
    }))
    result = ClaudeCli(UsageLedger(), workdir=str(tmp_path)).call(
        "retrospective", "haiku", "p", schemas.validate_retrospective)
    assert result == {"save": False}

    def _new_calls(before_count):
        lines = [l for l in shim.read_text().splitlines() if l.strip()]
        return len(lines) - before_count

    def _before():
        return len([l for l in shim.read_text().splitlines() if l.strip()])

    # Case 2: {"save": "false"} (string value) -> llm-invalid-output, 2 invocations
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "result": '{"save": "false"}', "subtype": "success",
    }))
    b = _before()
    with pytest.raises(HarnessError) as exc:
        ClaudeCli(UsageLedger(), workdir=str(tmp_path)).call(
            "retrospective", "haiku", "p", schemas.validate_retrospective)
    assert exc.value.kind == "llm-invalid-output"
    assert _new_calls(b) == 2

    # Case 3: {"save": true, "name": "x"} with no body -> llm-invalid-output, 2 invocations
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "result": '{"save": true, "name": "x"}', "subtype": "success",
    }))
    b = _before()
    with pytest.raises(HarnessError) as exc:
        ClaudeCli(UsageLedger(), workdir=str(tmp_path)).call(
            "retrospective", "haiku", "p", schemas.validate_retrospective)
    assert exc.value.kind == "llm-invalid-output"
    assert _new_calls(b) == 2

    # Case 4: non-JSON stdout -> llm-invalid-output, 2 invocations
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", "not json at all")
    b = _before()
    with pytest.raises(HarnessError) as exc:
        ClaudeCli(UsageLedger(), workdir=str(tmp_path)).call(
            "retrospective", "haiku", "p", schemas.validate_retrospective)
    assert exc.value.kind == "llm-invalid-output"
    assert _new_calls(b) == 2


# --- Phase 4: envelope error detail (subtype + bounded result text) -------------

def test_error_envelope_names_subtype_and_result(shim, tmp_path, monkeypatch):
    """is_error envelope carries subtype and result text in HarnessError.detail."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "is_error": True, "subtype": "error_max_turns", "result": "hit limit",
    }))
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), max_retries=0)
    assert exc.value.kind == "llm-tooled-error"
    assert "error_max_turns" in str(exc.value.detail)
    assert "hit limit" in str(exc.value.detail)


def test_error_envelope_without_subtype_still_raises(shim, tmp_path, monkeypatch):
    """is_error without a subtype still raises with kind llm-tooled-error."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({"is_error": True}))
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), max_retries=0)
    assert exc.value.kind == "llm-tooled-error"


def test_nonsuccess_subtype_without_is_error_still_raises(shim, tmp_path, monkeypatch):
    """A non-success subtype without is_error raises HarnessError."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({"subtype": "error_max_turns"}))
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), max_retries=0)
    assert exc.value.kind == "llm-tooled-error"


def test_error_result_text_is_bounded(shim, tmp_path, monkeypatch):
    """Result text in error detail is bounded by ENVELOPE_ERROR_TEXT_CAP."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "is_error": True, "result": "X" * 500,
    }))
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                                   allowed_tools=("Read",), max_retries=0)
    assert len(str(exc.value.detail)) <= ENVELOPE_ERROR_TEXT_CAP + 100


def test_error_envelope_records_ledger_once(shim, tmp_path, monkeypatch):
    """Ledger is incremented exactly once even when an error envelope is raised."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", json.dumps({
        "is_error": True, "subtype": "error_max_turns", "result": "hit limit",
        "usage": {"input_tokens": 5, "output_tokens": 3},
    }))
    ledger = UsageLedger()
    cli = ClaudeCli(ledger, workdir=str(tmp_path))
    with pytest.raises(HarnessError):
        cli.call_tooled("fidelity", "opus", "p", cwd=str(tmp_path),
                        allowed_tools=("Read",), max_retries=0)
    assert len(ledger.calls) == 1
