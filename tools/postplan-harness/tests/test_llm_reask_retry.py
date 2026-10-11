"""Characterization pins for the LlmAdapter.call re-ask loop.

Drive the real `ClaudeCli.call` and replace only the subprocess seam, so the retry,
the re-ask prompt, and the failure kind are exercised end to end.
"""
import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import schemas
from harness.adapters.llm import ClaudeCli
from harness.state import HarnessError, UsageLedger

PROMPT = 'Decide holds. Return ONLY JSON {"holds": [str]}.'
LIMIT_TEXT = "You've hit your limit"


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


def test_bad_then_good_returns_second_reply(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, ["I think it is fine.", '{"holds": ["x"]}'], seen)
    data = cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert data == {"holds": ["x"]}
    assert len(seen) == 2


def test_reask_prompt_carries_original_prompt_and_parse_error(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, ["I think it is fine.", '{"holds": ["x"]}'], seen)
    cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert seen[0] == PROMPT
    assert seen[1].startswith(PROMPT)
    assert "Your previous reply was not valid per the required JSON schema" in seen[1]
    assert "no parseable JSON in model reply" in seen[1]


def test_bad_twice_raises_invalid_output(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, ["no json here", "still no json"], seen)
    with pytest.raises(HarnessError) as ei:
        cli.call("score-findings", "haiku", PROMPT, lambda d: None)
    assert ei.value.kind == "llm-invalid-output"
    assert ei.value.detail.startswith("score-findings: ")
    assert len(seen) == 2


def test_safety_verdict_bad_twice_never_defaults_to_empty_holds(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch,
               ["Looks safe to me.", '{"verdict": {"holds": []}}'], seen)
    with pytest.raises(HarnessError) as ei:
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
        pytest.fail("safety-verdict returned a value after two invalid replies")
    assert ei.value.kind == "llm-invalid-output"
    assert len(seen) == 2


def test_schema_invalid_second_reply_still_rejected(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, ["nope", '{"holds": [1]}'], seen)
    with pytest.raises(HarnessError) as ei:
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert ei.value.kind == "llm-invalid-output"
    assert "each hold must be a string" in ei.value.detail


def test_usage_limit_reply_pauses_without_reask(tmp_path, monkeypatch):
    class _Paused(Exception):
        pass

    def _raise_paused(self, purpose, cwd=None, fp_before=None):
        raise _Paused()

    seen = []
    cli = _cli(tmp_path, monkeypatch, [LIMIT_TEXT, '{"holds": []}'], seen)
    monkeypatch.setattr(ClaudeCli, "_usage_limit_pause", _raise_paused)
    with pytest.raises(_Paused):
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert len(seen) == 1


AUTH = "Failed to authenticate: OAuth session expired and could not be refreshed"


def _raw_stdout(stdouts, seen):
    """Fake _run_reaped: returns each scripted string as bare stdout, rc=1, no JSON envelope."""
    def fake(argv, stdin_text, timeout, cwd, env, errors=None):
        seen.append(stdin_text)
        return subprocess.CompletedProcess(argv, 1, stdout=stdouts.pop(0), stderr="")
    return fake


def test_auth_expired_reply_relabels_detail(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, [AUTH, AUTH], seen)
    with pytest.raises(HarnessError) as ei:
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert ei.value.kind == "llm-invalid-output"
    assert ei.value.detail.startswith("safety-verdict: auth-expired: Failed to authenticate")
    assert "no parseable JSON" not in ei.value.detail
    assert len(seen) == 2


def test_auth_text_in_non_json_stdout_relabels(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, [], seen)
    monkeypatch.setattr("harness.adapters.llm._run_reaped", _raw_stdout([AUTH, AUTH], seen))
    with pytest.raises(HarnessError) as ei:
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert ei.value.kind == "llm-invalid-output"
    assert ei.value.detail.startswith("safety-verdict: auth-expired: Failed to authenticate")
    assert "CLI non-JSON output" not in ei.value.detail
    assert len(seen) == 2


def test_auth_expired_then_valid_returns_data(tmp_path, monkeypatch):
    seen = []
    cli = _cli(tmp_path, monkeypatch, [AUTH, '{"holds": []}'], seen)
    data = cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert data == {"holds": []}
    assert len(seen) == 2


def test_auth_reply_does_not_trigger_usage_pause(tmp_path, monkeypatch):
    class _Paused(Exception):
        pass

    def _raise_paused(self, purpose, cwd=None, fp_before=None):
        raise _Paused()

    seen = []
    cli = _cli(tmp_path, monkeypatch, [AUTH, AUTH], seen)
    monkeypatch.setattr(ClaudeCli, "_usage_limit_pause", _raise_paused)
    with pytest.raises(HarnessError) as ei:
        cli.call("safety-verdict", "haiku", PROMPT, schemas.validate_safety_verdict)
    assert ei.value.kind == "llm-invalid-output"


def test_invalid_output_kind_stays_out_of_fail_closed_kinds():
    import runner
    assert "llm-invalid-output" not in runner._FAIL_CLOSED_KINDS
