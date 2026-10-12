"""Tests for llm-usage-limit detection in the ClaudeCli adapter.

Four required cases on the `call()` path (toolless JSON call, where the real
manual-classify bug was observed):
  (a) envelope result is a session-limit message → llm-usage-limit, exactly 1 call
  (b) non-JSON CLI output containing rate_limit_error → llm-usage-limit, exactly 1 call
  (c) genuinely malformed non-limit reply → llm-invalid-output after retries (regression)
  (d) valid JSON whose field value contains the signature → NOT misclassified

Plus `call_tooled()` variants:
  (e) is_error envelope with usage-limit text → llm-usage-limit, not llm-tooled-error
  (f) short success envelope with usage-limit text → llm-usage-limit
  (g) long prose verdict quoting the pattern in context → returned as text, not raised
"""
import json
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import ClaudeCli
from harness.state import HarnessError, UsageLedger

# ---------------------------------------------------------------------------
# Shared shim: logs its argv, emits CLAUDE_SHIM_REPLY on stdout, exits 0.
# Identical shape to the shim in test_llm_tooled.py.
# ---------------------------------------------------------------------------
SHIM = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
cat > /dev/null
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit 0
"""

# A shim that exits nonzero and writes to stderr (for the non-JSON/429 variant).
SHIM_ERR = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
cat > /dev/null
printf '%s' "${CLAUDE_SHIM_REPLY_STDERR}" >&2
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit "${CLAUDE_SHIM_EXIT:-0}"
"""


@pytest.fixture()
def shim(tmp_path, monkeypatch):
    """Standard shim: stdout = CLAUDE_SHIM_REPLY, exit 0."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    claude = bindir / "claude"
    claude.write_text(SHIM)
    claude.chmod(claude.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "claude-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("CLAUDE_SHIM_LOG", str(log))
    return log


@pytest.fixture()
def shim_err(tmp_path, monkeypatch):
    """Shim variant: can write to stderr and exit nonzero via env vars."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    claude = bindir / "claude"
    claude.write_text(SHIM_ERR)
    claude.chmod(claude.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "claude-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("CLAUDE_SHIM_LOG", str(log))
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", "")
    monkeypatch.setenv("CLAUDE_SHIM_REPLY_STDERR", "")
    return log


def _cli(tmp_path):
    return ClaudeCli(UsageLedger(), workdir=str(tmp_path))


def _call_count(log):
    return len([l for l in log.read_text().splitlines() if l.strip()])


# ---------------------------------------------------------------------------
# (a) Session-limit text in envelope result → llm-usage-limit, one call
# ---------------------------------------------------------------------------

def test_session_limit_in_result_raises_usage_limit(shim, tmp_path, monkeypatch):
    """The real 2026-09-28 failure: manual-classify reply was the limit message."""
    reply = json.dumps({
        "result": "You've hit your session limit · resets 3pm (America/Los_Angeles)",
        "subtype": "success",
    })
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call("manual-classify", "sonnet", "classify these",
                            validate=lambda d: None, max_retries=1)
    assert exc.value.kind == "llm-usage-limit"
    assert "session limit" in exc.value.detail
    assert "manual-classify" in exc.value.detail
    # exactly one subprocess call — no retry against a limit wall
    assert _call_count(shim) == 1


# ---------------------------------------------------------------------------
# (b) Non-JSON stdout containing rate_limit_error → llm-usage-limit, one call
# ---------------------------------------------------------------------------

def test_nonjson_rate_limit_error_raises_usage_limit(shim_err, tmp_path, monkeypatch):
    """429/rate_limit_error in non-JSON CLI output is an environmental error."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", "rate_limit_error overloaded")
    monkeypatch.setenv("CLAUDE_SHIM_EXIT", "1")
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call("manual-classify", "sonnet", "classify these",
                            validate=lambda d: None, max_retries=1)
    assert exc.value.kind == "llm-usage-limit"
    assert _call_count(shim_err) == 1


def test_api_error_429_in_stderr_raises_usage_limit(shim_err, tmp_path, monkeypatch):
    """API Error: 429 in stderr (non-JSON stdout) is also a usage limit."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", "")
    monkeypatch.setenv("CLAUDE_SHIM_REPLY_STDERR", "API Error: 429 Too Many Requests")
    monkeypatch.setenv("CLAUDE_SHIM_EXIT", "1")
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call("manual-classify", "sonnet", "classify these",
                            validate=lambda d: None, max_retries=1)
    assert exc.value.kind == "llm-usage-limit"
    assert _call_count(shim_err) == 1


# ---------------------------------------------------------------------------
# (c) Genuine malformed reply (not a limit) → llm-invalid-output after retries
# ---------------------------------------------------------------------------

def test_malformed_non_limit_reply_retries_and_raises_invalid_output(shim, tmp_path,
                                                                       monkeypatch):
    """Regression guard: a broken JSON reply (no limit signature) still gets retried
    and eventually raises llm-invalid-output, not llm-usage-limit."""
    reply = json.dumps({"result": "this is not json at all", "subtype": "success"})
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call("manual-classify", "sonnet", "classify these",
                            validate=lambda d: None, max_retries=1)
    assert exc.value.kind == "llm-invalid-output"
    # max_retries=1 → two calls total
    assert _call_count(shim) == 2


# ---------------------------------------------------------------------------
# (d) Valid JSON with the signature in a field value → NOT misclassified
# ---------------------------------------------------------------------------

def test_valid_json_with_limit_text_in_field_not_misclassified(shim, tmp_path,
                                                                monkeypatch):
    """A legitimate reply whose JSON contains the signature string in a field value
    must parse and validate normally — extract_json succeeds, so the limit branch
    in the except block is never reached."""
    data = {"status": "ok",
            "note": "handles rate_limit_error and overloaded_error gracefully"}
    reply = json.dumps({"result": json.dumps(data), "subtype": "success"})
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    result = _cli(tmp_path).call("manual-classify", "sonnet", "classify these",
                                 validate=lambda d: None, max_retries=1)
    # no exception — returns the parsed data
    assert result == data
    assert _call_count(shim) == 1


# ---------------------------------------------------------------------------
# call_tooled() variants
# ---------------------------------------------------------------------------

def test_tooled_is_error_with_limit_text_raises_usage_limit(shim, tmp_path, monkeypatch):
    """(e) is_error envelope whose result is a limit message → llm-usage-limit,
    not llm-tooled-error."""
    reply = json.dumps({
        "is_error": True,
        "result": "You've hit your session limit · resets 3pm (America/Los_Angeles)",
    })
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "review this",
                                   cwd=str(tmp_path), allowed_tools=("Read",),
                                   max_retries=0)
    assert exc.value.kind == "llm-usage-limit"
    assert "session limit" in exc.value.detail
    assert _call_count(shim) == 1


def test_tooled_short_success_with_limit_text_raises_usage_limit(shim, tmp_path,
                                                                   monkeypatch):
    """(f) Success envelope whose result is a short usage-limit reply → llm-usage-limit."""
    reply = json.dumps({
        "result": "Usage limit reached. Resets in 2 hours.",
        "subtype": "success",
    })
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "review this",
                                   cwd=str(tmp_path), allowed_tools=("Read",),
                                   max_retries=0)
    assert exc.value.kind == "llm-usage-limit"
    assert _call_count(shim) == 1


def test_tooled_long_verdict_quoting_signature_not_misclassified(shim, tmp_path,
                                                                   monkeypatch):
    """(g) A long prose verdict that happens to quote 'rate_limit_error' in context
    must NOT be misclassified.  Genuine verdicts are ≥ 1 KB; the limit guard only
    fires on short result texts."""
    # Build a realistic-length verdict that includes the signature string in context.
    verdict = (
        "VERDICT: PASS\n\n"
        "## Summary\n\n"
        "The implementation correctly handles the rate_limit_error and overloaded_error "
        "cases by returning an appropriate HTTP 429 response.  The error handling path "
        "is well-tested and does not leak internal state.\n\n"
        "## Details\n\n"
        + ("The code review found no security issues. " * 20)  # push total past 400 chars
    )
    assert len(verdict.strip()) >= 400, "verdict must be long enough to exceed the guard"
    reply = json.dumps({"result": verdict, "subtype": "success"})
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", reply)
    result = _cli(tmp_path).call_tooled("fidelity", "opus", "review this",
                                        cwd=str(tmp_path), allowed_tools=("Read",),
                                        max_retries=0)
    assert result == verdict
    assert _call_count(shim) == 1


def test_tooled_nonjson_rate_limit_in_stderr_raises_usage_limit(shim_err, tmp_path,
                                                                  monkeypatch):
    """Non-JSON tooled output with rate_limit_error in stderr → llm-usage-limit."""
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", "")
    monkeypatch.setenv("CLAUDE_SHIM_REPLY_STDERR", "overloaded_error: service busy")
    monkeypatch.setenv("CLAUDE_SHIM_EXIT", "1")
    with pytest.raises(HarnessError) as exc:
        _cli(tmp_path).call_tooled("fidelity", "opus", "review this",
                                   cwd=str(tmp_path), allowed_tools=("Read",),
                                   max_retries=0)
    assert exc.value.kind == "llm-usage-limit"
    assert _call_count(shim_err) == 1
