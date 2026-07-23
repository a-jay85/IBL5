#!/usr/bin/env python3
"""One-time compilation cost: token usage of the session(s) that BUILT this
harness, measured from the session's own .jsonl. Reported separately from
runtime benchmarks — never amortized into per-run metrics."""
import glob
import json
import os
import sys

# original build session + its post-compaction continuation (one task, two files)
SESSIONS = sys.argv[1].split(",") if len(sys.argv) > 1 else [
    "5f2baff5-8728-4312-ac9f-4ba2be5a24c8",
    "fa495471-2f76-4907-a637-286cf4a7438d",
]


def main():
    proj = os.path.expanduser("~/.claude/projects")
    paths = [p for s in SESSIONS
             for p in glob.glob(os.path.join(proj, "*", f"{s}*.jsonl"))]
    tot = {"in": 0, "cc": 0, "cr": 0, "out": 0}
    turns = 0
    models = {}
    first = last = None
    for p in paths:
        for line in open(p):
            try:
                rec = json.loads(line)
            except json.JSONDecodeError:
                continue
            msg = rec.get("message") or {}
            u = msg.get("usage")
            if not u:
                continue
            turns += 1
            tot["in"] += u.get("input_tokens", 0) or 0
            tot["cc"] += u.get("cache_creation_input_tokens", 0) or 0
            tot["cr"] += u.get("cache_read_input_tokens", 0) or 0
            tot["out"] += u.get("output_tokens", 0) or 0
            m = msg.get("model", "?")
            models[m] = models.get(m, 0) + 1
            ts = rec.get("timestamp")
            if ts:
                first = first or ts
                last = ts
    print(json.dumps({
        "session_files": [os.path.basename(p) for p in paths],
        "assistant_turns": turns,
        "gross_tokens": sum(tot.values()),
        "non_cached_tokens": tot["in"] + tot["cc"] + tot["out"],
        "breakdown": tot,
        "models": models,
        "first_ts": first, "last_ts": last,
        "note": "reasoning tokens not separately reported in traces (unavailable)",
    }, indent=1))


if __name__ == "__main__":
    main()
