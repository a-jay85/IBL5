"""Bounded LLM adapter — every retained model call goes through here.

Contract for a retained call:
  - one named purpose;
  - smallest sufficient input packet (caller-truncated, byte-capped here);
  - no model-controlled tools (--max-turns 1, run from a context-free cwd);
  - defined model;
  - typed JSON result validated by harness/schemas.py;
  - bounded retries (1 re-ask on invalid JSON);
  - usage + trace recorded (provider-reported by `claude -p --output-format json`).
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import tempfile

from ..state import HarnessError, LlmCallRecord, UsageLedger

MAX_PROMPT_BYTES = 120_000        # hard cap on any single call's input packet
DEFAULT_TIMEOUT = 1500            # sonnet 4.6 thinks long on large diffs; observed >600s

MODEL_MAP = {
    "haiku": "claude-haiku-4-5-20251001",
    "sonnet": "claude-sonnet-4-6",     # matches the historical review-agent tier
}


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


class ClaudeCli:
    """Live adapter: claude -p, single turn, no tools, neutral cwd."""

    def __init__(self, ledger: UsageLedger, workdir: str | None = None):
        self.ledger = ledger
        self.workdir = workdir or tempfile.mkdtemp(prefix="postplan-llm-")

    def call(self, purpose: str, model: str, prompt: str, validate, max_retries: int = 1):
        if len(prompt.encode()) > MAX_PROMPT_BYTES:
            prompt = prompt.encode()[:MAX_PROMPT_BYTES].decode(errors="ignore") + "\n[TRUNCATED]"
        model_id = MODEL_MAP.get(model, model)
        rec = LlmCallRecord(purpose=purpose, model=model_id)
        attempt_prompt = prompt
        last_err = ""
        for attempt in range(max_retries + 1):
            try:
                proc = subprocess.run(
                    ["claude", "-p", "--model", model_id, "--output-format", "json",
                     "--max-turns", "1", "--tools", ""],
                    input=attempt_prompt, capture_output=True, text=True,
                    timeout=DEFAULT_TIMEOUT, cwd=self.workdir,
                    env={**os.environ, "CLAUDE_HEADLESS": "1"},
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
            try:
                data = extract_json(result_text)
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


class FixtureLlm:
    """Test adapter: serves canned responses by purpose; records zero-usage calls."""

    def __init__(self, ledger: UsageLedger, canned: dict):
        self.ledger = ledger
        self.canned = canned

    def call(self, purpose: str, model: str, prompt: str, validate, max_retries: int = 1):
        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"fixture:{model}"))
        return data
