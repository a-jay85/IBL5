#!/usr/bin/env python3
"""Fake `claude -p` for plan-eval tests and `smoke`. Spends no tokens.

Reads the prompt after `-p`, finds the first absolute `PLAN_FILE:` path in it, writes the
canned plan there, and prints a stream-json transcript whose result ends with that marker.

Env:
  FAKE_CLAUDE_SLEEP  seconds to sleep before answering (default 0)
  FAKE_CLAUDE_MODE   ok (default) | leak | nomarker
  FAKE_CLAUDE_LEAK_DIR  with mode=leak, also write <dir>/<slug>.md
"""
from __future__ import annotations

import json
import os
import re
import sys
import time
from pathlib import Path

CANNED = Path(__file__).resolve().parent / "canned-plan.md"


def main(argv: list) -> int:
    prompt = argv[argv.index("-p") + 1] if "-p" in argv else ""
    time.sleep(float(os.environ.get("FAKE_CLAUDE_SLEEP") or 0))
    m = re.search(r"PLAN_FILE: (/\S+\.md)", prompt)
    if not m:
        print(json.dumps({"type": "result", "result": "NO_PLAN: no plan path in prompt",
                          "total_cost_usd": 0.0}))
        return 0
    plan = Path(m.group(1))
    plan.parent.mkdir(parents=True, exist_ok=True)
    plan.write_text(CANNED.read_text())
    mode = os.environ.get("FAKE_CLAUDE_MODE") or "ok"
    if mode == "leak":
        leak_dir = Path(os.environ["FAKE_CLAUDE_LEAK_DIR"])
        leak_dir.mkdir(parents=True, exist_ok=True)
        (leak_dir / plan.name).write_text("leaked\n")
    events = [
        {"type": "system", "subtype": "init"},
        {"type": "assistant", "message": {
            "id": "msg_1", "usage": {"output_tokens": 10, "cache_read_input_tokens": 100,
                                     "cache_creation_input_tokens": 5},
            "content": [{"type": "tool_use", "name": "Agent",
                         "input": {"subagent_type": "plan-architect",
                                   "prompt": "design it"}}]}},
    ]
    final = "Plan written." if mode == "nomarker" else f"Plan written.\n\nPLAN_FILE: {plan}"
    events.append({"type": "result", "result": final, "total_cost_usd": 0.01})
    for ev in events:
        print(json.dumps(ev))
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
