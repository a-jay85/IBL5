"""Bounded LLM adapter — every retained model call goes through here.

Contract for a retained call:
  - one named purpose;
  - smallest sufficient input packet (caller-truncated, byte-capped here);
  - toolless calls: no model-controlled tools (--max-turns 1, run from a
    context-free cwd);
  - tool-enabled calls: built-in tools restricted to an explicit --tools allowlist
    plus a --disallowedTools deny list, run in the worktree, bounded by a turn
    ceiling + wall clock + process-group reap;
  - defined model;
  - typed JSON result validated by harness/schemas.py;
  - bounded retries (1 re-ask on invalid JSON);
  - usage + trace recorded (provider-reported by `claude -p --output-format json`).
"""
from __future__ import annotations

import json
import os
import re
import shutil
import signal
import subprocess
import tempfile

from ..state import HarnessError, LlmCallRecord, UsageLedger

MAX_PROMPT_BYTES = 120_000        # hard cap on any single call's input packet
DEFAULT_TIMEOUT = 1500            # sonnet 4.6 thinks long on large diffs; observed >600s
TOOLED_TIMEOUT = 2400             # a repo-reading reviewer needs many turns of tool I/O
TOOLED_MAX_TURNS = 60             # NEVER 1: a tool-enabled call must be able to iterate
ENVELOPE_ERROR_TEXT_CAP = 600     # bound result text in error details for diagnosis

MODEL_MAP = {
    "haiku": "claude-haiku-4-5-20251001",
    "sonnet": "claude-sonnet-4-6",     # matches the historical review-agent tier
    "opus": "claude-opus-5",
}

# Explicit allowlist, NOT MODEL_MAP.get(model, model) pass-through: a typo or `fable`
# must never reach the CLI on a tool-enabled (expensive, repo-reading) call path.
TOOLED_MODELS = {"opus", "sonnet"}


def extract_json(text: str):
    """Pull the first JSON value out of a model reply (handles ``` fences)."""
    fence = re.search(r"```(?:json)?\s*(.*?)```", text, re.S)
    if fence:
        text = fence.group(1)
    text = text.strip()
    # take whichever bracketed value STARTS first, so an object wrapping an
    # inner array is parsed as the object (not sliced down to the array)
    candidates = [(text.find("["), text.rfind("]")), (text.find("{"), text.rfind("}"))]
    candidates = [(s, e) for s, e in candidates if s != -1 and e > s]
    for start, end in sorted(candidates):
        try:
            return json.loads(text[start:end + 1])
        except json.JSONDecodeError:
            continue
    raise ValueError("no parseable JSON in model reply")


def _run_reaped(argv, stdin_text, timeout, cwd, env):
    """Run argv with its own process group, always reaping the group on exit.

    `claude -p` spawns children that a plain subprocess timeout never touches, so the
    killpg lives in `finally` and fires on the success path too. TimeoutExpired is
    re-raised so callers keep today's exception contract.
    """
    proc = subprocess.Popen(
        argv, stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        text=True, cwd=cwd, env=env, start_new_session=True,
    )
    try:
        out, err = proc.communicate(stdin_text, timeout=timeout)
        return subprocess.CompletedProcess(argv, proc.returncode, out, err)
    except subprocess.TimeoutExpired:
        raise
    finally:
        try:
            os.killpg(os.getpgid(proc.pid), signal.SIGKILL)
        except (ProcessLookupError, PermissionError):
            pass


# Tools that let a session persist a file. A tooled call granted none of them cannot
# satisfy ~/.claude/hooks/subagent-persist-gate.py, which then blocks the stop and
# forces an extra "I have no Write tool" turn. That forced turn REPLACES the envelope's
# `result`, so a fidelity reviewer's verdict document was lost behind the complaint.
PERSIST_TOOLS = ("Write", "Edit", "NotebookEdit", "Bash")


def _tooled_env(allowed_tools, denied_tools) -> dict:
    """Subprocess env for a tooled call. Write-less calls opt out of the persist gate."""
    env = {**os.environ, "CLAUDE_HEADLESS": "1"}
    usable = set(allowed_tools) - set(denied_tools or ())
    if usable.isdisjoint(PERSIST_TOOLS):
        env["PERSIST_GATE_SKIP"] = "1"
    else:
        # never inherit a skip from the parent onto a call that can write
        env.pop("PERSIST_GATE_SKIP", None)
    return env


def _tooled_argv(model, *, agent, allowed_tools, denied_tools, add_dirs,
                 append_system_prompt, setting_sources, max_turns) -> list[str]:
    """Pure argv builder so replay can record a live run's exact command line."""
    argv = ["claude", "-p", "--output-format", "json",
            "--max-turns", str(max_turns),
            "--permission-prompts", "none",
            "--setting-sources", setting_sources]
    if agent:
        # no --model: the agent def's own `model:` pin wins, mirroring the skill's spawn
        argv += ["--agent", agent]
    else:
        argv += ["--model", MODEL_MAP[model]]
    tools_csv = ",".join(allowed_tools)
    # --allowedTools only auto-approves; --tools is what restricts the built-in set.
    # Both are always passed, and --tools is never "" nor "default".
    argv += ["--allowedTools", tools_csv]
    argv += ["--tools", tools_csv]
    if denied_tools:
        argv += ["--disallowedTools", ",".join(denied_tools)]
    for d in add_dirs:
        argv += ["--add-dir", d]
    if append_system_prompt:
        argv += ["--append-system-prompt", append_system_prompt]
    return argv


class ClaudeCli:
    """Live adapter for `claude -p`, with two call paths that differ on every axis.

    `call` is the cheap one: single turn, no tools, neutral temp cwd. `call_tooled` is
    the expensive one: up to TOOLED_MAX_TURNS turns, an explicit --tools allowlist, and
    cwd set to the worktree under review. Read the method you are calling before
    assuming which set of constraints applies.
    """

    def __init__(self, ledger: UsageLedger, workdir: str | None = None,
                 out_dir: str | None = None):
        self.ledger = ledger
        self._owns_workdir = workdir is None
        self.workdir = workdir or tempfile.mkdtemp(prefix="postplan-llm-")
        self.out_dir = out_dir      # None => raw-attempt persistence disabled

    def close(self) -> None:
        """Phase 11: remove the temp cwd this adapter created. Idempotent; never raises."""
        if self._owns_workdir:
            shutil.rmtree(self.workdir, ignore_errors=True)

    def _persist_raw(self, purpose: str, attempt: int, text: str) -> None:
        """Best-effort raw-reply dump for post-mortem; never fails a run."""
        if not self.out_dir:
            return
        try:
            os.makedirs(self.out_dir, exist_ok=True)
            path = os.path.join(self.out_dir, f"raw-{purpose}-attempt{attempt}.txt")
            with open(path, "w") as fh:
                fh.write(text)
        except OSError:
            pass

    def call(self, purpose: str, model: str, prompt: str, validate,
             max_retries: int = 1, normalizer=None):
        if len(prompt.encode()) > MAX_PROMPT_BYTES:
            prompt = prompt.encode()[:MAX_PROMPT_BYTES].decode(errors="ignore") + "\n[TRUNCATED]"
        model_id = MODEL_MAP.get(model, model)
        rec = LlmCallRecord(purpose=purpose, model=model_id)
        attempt_prompt = prompt
        last_err = ""
        for attempt in range(max_retries + 1):
            try:
                proc = _run_reaped(
                    ["claude", "-p", "--model", model_id, "--output-format", "json",
                     "--max-turns", "1", "--tools", ""],
                    attempt_prompt, DEFAULT_TIMEOUT, self.workdir,
                    {**os.environ, "CLAUDE_HEADLESS": "1"},
                )
            except subprocess.TimeoutExpired:
                last_err = f"call exceeded {DEFAULT_TIMEOUT}s wall clock"
                rec.retries = attempt
                continue
            try:
                envelope = json.loads(proc.stdout)
            except json.JSONDecodeError:
                last_err = f"CLI non-JSON output (rc={proc.returncode}): {proc.stdout[:200]} {proc.stderr[:200]}"
                rec.retries = attempt
                continue
            u = envelope.get("usage") or {}
            rec.input_tokens += u.get("input_tokens", 0) or 0
            rec.cache_creation_input_tokens += u.get("cache_creation_input_tokens", 0) or 0
            rec.cache_read_input_tokens += u.get("cache_read_input_tokens", 0) or 0
            rec.output_tokens += u.get("output_tokens", 0) or 0
            rec.duration_ms += envelope.get("duration_ms", 0) or 0
            rec.cost_usd += envelope.get("total_cost_usd", 0.0) or 0.0
            result_text = envelope.get("result", "") or ""
            self._persist_raw(purpose, attempt, result_text)
            try:
                data = extract_json(result_text)
                if normalizer is not None:
                    data = normalizer(data)
                validate(data)
                rec.retries = attempt
                self.ledger.add(rec)
                return data
            except (ValueError, HarnessError) as e:
                last_err = str(e)
                attempt_prompt = (prompt + "\n\nYour previous reply was not valid per the "
                                  f"required JSON schema ({e}). Reply with ONLY the JSON.")
        rec.ok = False
        rec.retries = max_retries
        self.ledger.add(rec)
        raise HarnessError("llm-invalid-output", f"{purpose}: {last_err}")

    def call_tooled(self, purpose: str, model: str, prompt: str, *, cwd: str,
                    agent: str | None = None, allowed_tools=(),
                    denied_tools=("Bash", "Agent"), add_dirs=(),
                    append_system_prompt: str | None = None,
                    setting_sources: str = "user,project",
                    timeout: int = TOOLED_TIMEOUT,
                    max_turns: int = TOOLED_MAX_TURNS,
                    max_retries: int = 1) -> str:
        """Bounded, tool-enabled call. Returns the envelope's RAW `result` text.

        The verdict this exists to fetch is a prose document, so parsing belongs to the
        caller. Degradation is fail-closed and typed: a degraded envelope raises rather
        than returning text that could be mistaken for a clean verdict.
        """
        if model not in TOOLED_MODELS:
            raise HarnessError("llm-model-not-allowed", model)
        if not allowed_tools:
            # An empty --tools is the toolless call wearing a tool-enabled budget; a
            # silently toolless reviewer returns a verdict it never grounded in the repo.
            raise HarnessError("llm-tooled-no-tools", purpose)
        if len(prompt.encode()) > MAX_PROMPT_BYTES:
            prompt = prompt.encode()[:MAX_PROMPT_BYTES].decode(errors="ignore") + "\n[TRUNCATED]"
        argv = _tooled_argv(model, agent=agent, allowed_tools=allowed_tools,
                            denied_tools=denied_tools, add_dirs=add_dirs,
                            append_system_prompt=append_system_prompt,
                            setting_sources=setting_sources, max_turns=max_turns)
        rec = LlmCallRecord(purpose=purpose, model=MODEL_MAP[model])
        last_err = ""
        for attempt in range(max_retries + 1):
            try:
                proc = _run_reaped(argv, prompt, timeout, cwd,
                                   _tooled_env(allowed_tools, denied_tools))
            except subprocess.TimeoutExpired:
                last_err = f"tooled call exceeded {timeout}s wall clock"
                rec.retries = attempt
                continue
            try:
                envelope = json.loads(proc.stdout)
            except json.JSONDecodeError:
                last_err = (f"CLI non-JSON output (rc={proc.returncode}): "
                            f"{proc.stdout[:200]} {proc.stderr[:200]}")
                rec.retries = attempt
                continue
            u = envelope.get("usage") or {}
            rec.input_tokens += u.get("input_tokens", 0) or 0
            rec.cache_creation_input_tokens += u.get("cache_creation_input_tokens", 0) or 0
            rec.cache_read_input_tokens += u.get("cache_read_input_tokens", 0) or 0
            rec.output_tokens += u.get("output_tokens", 0) or 0
            rec.duration_ms += envelope.get("duration_ms", 0) or 0
            rec.cost_usd += envelope.get("total_cost_usd", 0.0) or 0.0
            result_text = envelope.get("result", "") or ""
            self._persist_raw(purpose, attempt, result_text)
            rec.retries = attempt
            # Content failures are never re-asked: a reviewer that errored out mid-review
            # would only error again, and its partial text must not escape as a verdict.
            subtype = envelope.get("subtype")
            if envelope.get("is_error") or subtype not in (None, "success"):
                rec.ok = False
                self.ledger.add(rec)
                # Name the cause. The bare "envelope is_error" string hid error_max_turns
                # across three dead remediation rounds. `result` on an error envelope is
                # the CLI's own explanation and is the only place the turn count or the
                # denied tool appears, so carry a bounded slice of it.
                why = " ".join(result_text.split())[:ENVELOPE_ERROR_TEXT_CAP]
                raise HarnessError(
                    "llm-tooled-error",
                    f"{purpose}: envelope is_error={bool(envelope.get('is_error'))} "
                    f"subtype={subtype or 'none'}"
                    + (f" result={why!r}" if why else ""))
            if not result_text.strip():
                rec.ok = False
                self.ledger.add(rec)
                raise HarnessError("llm-tooled-empty", purpose)
            self.ledger.add(rec)
            return result_text
        rec.ok = False
        rec.retries = max_retries
        self.ledger.add(rec)
        raise HarnessError("llm-tooled-cli", f"{purpose}: {last_err}")



class FixtureLlm:
    """Test adapter: serves canned responses by purpose; records zero-usage calls."""

    def __init__(self, ledger: UsageLedger, canned: dict):
        self.ledger = ledger
        self.canned = canned
        self.tooled_argvs: list[tuple[str, list[str]]] = []

    def call(self, purpose: str, model: str, prompt: str, validate,
             max_retries: int = 1, normalizer=None):
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fixture:{model}"))
        return data

    def call_tooled(self, purpose: str, model: str, prompt: str, *, cwd: str,
                    agent: str | None = None, allowed_tools=(),
                    denied_tools=("Bash", "Agent"), add_dirs=(),
                    append_system_prompt: str | None = None,
                    setting_sources: str = "user,project",
                    timeout: int = TOOLED_TIMEOUT,
                    max_turns: int = TOOLED_MAX_TURNS,
                    max_retries: int = 1) -> str:
        if not allowed_tools:
            raise HarnessError("llm-tooled-no-tools", purpose)
        self.tooled_argvs.append((purpose, _tooled_argv(
            model, agent=agent, allowed_tools=allowed_tools, denied_tools=denied_tools,
            add_dirs=add_dirs, append_system_prompt=append_system_prompt,
            setting_sources=setting_sources, max_turns=max_turns)))
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        val = self.canned[purpose]
        if isinstance(val, dict) and "raise" in val:
            spec = val["raise"]
            raise HarnessError(spec["kind"], spec.get("detail", ""))
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fixture:{model}"))
        return val
