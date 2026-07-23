#!/usr/bin/env python3
"""Rebuild bench/results.json entries from persisted out/bench-<slug>/result.json
runs (crash recovery — avoids re-paying for already-measured LLM calls)."""
import json
import os
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, ROOT)

from benchmark import HOLDOUT, classification_parity  # noqa: E402
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))


def rebuild(slug: str) -> dict:
    with open(os.path.join(ROOT, "out", f"bench-{slug}", "result.json")) as fh:
        d = json.load(fh)
    with open(os.path.join(ROOT, "fixtures/scenarios", slug, "fixture.json")) as fh:
        hist = json.load(fh)["historical"]

    class C:  # adapt dict -> attribute access for classification_parity
        pass
    cls = C()
    for k, v in (d["classification"] or {}).items():
        setattr(cls, k, v)

    arm = d["arm"] or {}
    new_armed = bool(arm.get("armed"))
    hist_armed = bool(hist.get("armed"))
    hold_nums = sorted(c["number"] for c in arm.get("conditions", []) if c["blocked"])
    hist_holds = sorted(hist.get("holds") or [])
    false_arm = new_armed and not hist_armed
    extra = [n for n in hold_nums if n not in hist_holds]
    missing = [n for n in hist_holds if n not in hold_nums]

    p1 = classification_parity(cls, hist.get("classify_block", ""))
    p3_status = ("PASS" if d["phase5"] == hist.get("phase5")
                 else ("UNAVAILABLE" if d["phase5"] == "skipped" else "FAIL"))
    diff_files = set(d["classification"]["files"]) if d["classification"] else set()
    bad = [f["path"] for f in d["findings"] if f["path"] not in diff_files]
    led = d["ledger"] or {"calls": []}
    calls = led["calls"]
    gross = sum(c["input_tokens"] + c["cache_creation_input_tokens"]
                + c["cache_read_input_tokens"] + c["output_tokens"] for c in calls)
    nc = sum(c["input_tokens"] + c["cache_creation_input_tokens"] + c["output_tokens"]
             for c in calls)
    wall = round(float(led.get("finished_at") or 0) - float(led.get("started_at") or 0), 1)
    hu = hist["usage"]
    notes = []
    if extra:
        notes.append(f"extra fail-closed holds vs history: {extra}")
    if missing:
        notes.append(f"historical hold reasons not reproduced: {missing}")
    if p1["status"] == "FAIL":
        notes.append("classification mismatch: " + "; ".join(p1["mismatches"]))
    if p3_status == "FAIL":
        notes.append(f"phase5: got {d['phase5']} vs hist {hist.get('phase5')}")
    if bad:
        notes.append(f"hallucinated finding paths: {bad}")
    return {
        "slug": slug,
        "verdict": "FAIL" if (false_arm or d["terminal"] == "failed") else "PASS",
        "notes": notes, "terminal": d["terminal"], "error": d.get("error"),
        "parity": {"classification": p1,
                   "armed": {"new": new_armed, "hist": hist_armed, "false_arm": false_arm,
                             "new_holds": hold_nums, "hist_holds": hist_holds},
                   "phase5": {"new": d["phase5"], "hist": hist.get("phase5"), "status": p3_status},
                   "findings": {"status": "PASS" if not bad else "FAIL",
                                "hallucinated_paths": bad,
                                "surviving_findings": [
                                    {"agent": f["agent"], "path": f["path"], "line": f["line"],
                                     "score": f["score"], "body": f["body"][:200]}
                                    for f in d["findings"]]}},
        "new": {"llm_invocations": len(calls), "gross_tokens": gross,
                "non_cached_tokens": nc,
                "output_tokens": sum(c["output_tokens"] for c in calls),
                "cost_usd": round(sum(c["cost_usd"] for c in calls), 4),
                "retries": sum(c["retries"] for c in calls), "wall_seconds": wall,
                "calls": [{"purpose": c["purpose"], "model": c["model"],
                           "in": c["input_tokens"], "cache_create": c["cache_creation_input_tokens"],
                           "cache_read": c["cache_read_input_tokens"], "out": c["output_tokens"],
                           "cost_usd": round(c["cost_usd"], 4), "ms": c["duration_ms"],
                           "retries": c["retries"], "ok": c["ok"]} for c in calls]},
        "old": {"gross_tokens": hu["in"] + hu["cc"] + hu["cr"] + hu["out"],
                "non_cached_tokens": hu["in"] + hu["cc"] + hu["out"],
                "output_tokens": hu["out"], "llm_invocations": hist.get("turns"),
                "tool_calls": hist.get("tool_calls"), "wall_minutes": hist.get("dur_min"),
                "agent_spawns": hist.get("agent_spawns")},
    }


def main():
    slugs = sys.argv[1].split(",")
    out = os.path.join(ROOT, "bench", "results.json")
    results = {"primary": [], "holdout": [], "excluded": []}
    if os.path.exists(out):
        results = json.load(open(out))
    for slug in slugs:
        bucket = "holdout" if slug in HOLDOUT else "primary"
        results[bucket] = [r for r in results[bucket] if r["slug"] != slug]
        r = rebuild(slug)
        results[bucket].append(r)
        print(slug, r["verdict"], r["notes"])
    json.dump(results, open(out, "w"), indent=1)
    print("wrote", out)


if __name__ == "__main__":
    main()
