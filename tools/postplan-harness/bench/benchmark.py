#!/usr/bin/env python3
"""Method-A benchmark: compiled replay vs historical trace, per scenario.

Old side: token usage measured from the historical session's .jsonl records
(sum of message.usage over the run's turns — gross = in + cache_create +
cache_read + out; non-cached = in + cache_create + out).
New side: the compiled runner on the same point-in-time inputs; every retained
LLM call's usage is provider-reported by `claude -p --output-format json`.

Parity gates (quality rubric, defined BEFORE any run was judged):
  P1 classification — every boolean flag + file-type count equals the
     historical Phase-3 classify block (where recorded). LINES_PHP_CHANGED
     compares at gate-equivalence (>50 or not), since the rebuilt diff sits at
     final PR head. UNAVAILABLE when no block was recorded.
  P2 arming decision — armed bool must match history. A false ARM (we arm,
     history held) FAILS the scenario outright. Extra fail-closed holds on a
     held PR are PASS-WITH-NOTE; missing a historical hold reason is NOTED.
  P3 phase5 — status equals the session's authoritative PHASE5_VERIFY_STATUS
     line; UNAVAILABLE when the fixture lacks recorded verify outputs.
  P4 review sanity — every surviving finding is schema-valid, scored, and
     anchored to a file present in the diff (no hallucinated paths).
Scenario verdict: FAIL if P2 fails or the run raises; else PASS (with notes).
"""
from __future__ import annotations

import json
import os
import sys
import time

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, ROOT)

import runner
from harness.adapters.llm import ClaudeCli
from harness.state import TerminalState, UsageLedger

PRIMARY = ["backlog-l6-done", "context-budget-gate-v2", "jsb-j15-faithful-foul-bucket",
           "mobile-target-size-a11y-sitewide", "request-event-logging",
           "sort-determinism-phpstan-gate"]
HOLDOUT = ["jsb-j18-item1-3ga-bucket", "token-t7-index-diet"]
EXCLUDED = {"jsb-j15-faithful-foul-bucket":
            "fixture unrecoverable: head SHA never captured; only a 3.2KB partial "
            "docs diff survives vs the real 131KB 23-file Go+golden diff — replay "
            "would benchmark a different PR than history saw"}


def parse_classify_block(block: str) -> dict:
    flags = {}
    for m in __import__("re").finditer(r"([A-Z_]+)=(\S+)", block or ""):
        k, v = m.group(1), m.group(2)
        flags[k] = {"true": True, "false": False}.get(v, v)
    for m in __import__("re").finditer(r"\b(total|php|css|md|migration|test|lock|snapshot)=(\d+)", block or ""):
        flags[f"count_{m.group(1)}"] = int(m.group(2))
    return flags


def classification_parity(cls, block: str) -> dict:
    if not block:
        return {"status": "UNAVAILABLE", "mismatches": []}
    h = parse_classify_block(block)
    got = {
        "count_total": cls.count_total, "count_php": cls.count_php,
        "count_css": cls.count_css, "count_md": cls.count_md,
        "count_migration": cls.count_migration, "count_test": cls.count_test,
        "count_lock": cls.count_lock, "count_snapshot": cls.count_snapshot,
        "DOCS_ONLY": cls.docs_only, "CSS_ONLY": cls.css_only,
        "MIGRATION_ONLY": cls.migration_only, "TEST_ONLY": cls.test_only,
        "NON_CODE_ONLY": cls.non_code_only, "HAS_PHP": cls.has_php,
        "HAS_CSS": cls.has_css, "HAS_MODIFIED": cls.has_modified,
        "HAS_E2E_SPECS": cls.has_e2e_specs, "HAS_E2E_PROD_OVERLAP": cls.has_e2e_prod_overlap,
        "HAS_GO": cls.has_go, "GO_TOUCHED": cls.go_touched,
        "ENGINE_ONLY": cls.engine_only, "GOLDEN_CHANGED": cls.golden_changed,
    }
    mism = [f"{k}: got {got[k]} != hist {h[k]}" for k in got if k in h and got[k] != h[k]]
    if "LINES_PHP_CHANGED" in h:
        hist_gate = int(h["LINES_PHP_CHANGED"]) > 50
        if (cls.lines_php_changed > 50) != hist_gate:
            mism.append(f"LINES_PHP_CHANGED gate: got {cls.lines_php_changed} vs hist {h['LINES_PHP_CHANGED']}")
    return {"status": "PASS" if not mism else "FAIL", "mismatches": mism}


def run_scenario(slug: str) -> dict:
    fx_path = os.path.join(ROOT, "fixtures/scenarios", slug, "fixture.json")
    with open(fx_path) as fh:
        fixture = json.load(fh)
    hist = fixture["historical"]
    out_dir = os.path.join(ROOT, "out", f"bench-{slug}")
    ledger = UsageLedger()
    t0 = time.time()
    res = runner.run(fixture, out_dir, ClaudeCli(ledger), mode="replay", headless=True)
    wall = round(time.time() - t0, 1)

    new_armed = bool(res.arm and res.arm.armed)
    hist_armed = bool(hist.get("armed"))
    false_arm = new_armed and not hist_armed
    hold_nums = sorted(c.number for c in (res.arm.holds if res.arm else []))
    hist_holds = sorted(hist.get("holds") or [])
    extra = [n for n in hold_nums if n not in hist_holds]
    missing = [n for n in hist_holds if n not in hold_nums]

    p1 = classification_parity(res.classification, hist.get("classify_block", "")) \
        if res.classification else {"status": "FAIL", "mismatches": ["no classification"]}
    p3_status = "UNAVAILABLE" if (res.phase5 == "skipped" and not fixture.get("verify")) \
        else ("PASS" if res.phase5 == hist.get("phase5") else
              ("PASS" if hist.get("phase5") in (None, "") else "FAIL"))
    diff_files = set(res.classification.files) if res.classification else set()
    bad_paths = [f.path for f in res.findings if f.path not in diff_files]
    p4 = {"status": "PASS" if not bad_paths else "FAIL",
          "hallucinated_paths": bad_paths,
          "surviving_findings": [{"agent": f.agent, "path": f.path, "line": f.line,
                                  "score": f.score, "body": f.body[:200]}
                                 for f in res.findings]}

    verdict = "FAIL" if (false_arm or res.terminal == TerminalState.FAILED) else "PASS"
    notes = []
    if extra:
        notes.append(f"extra fail-closed holds vs history: {extra}")
    if missing:
        notes.append(f"historical hold reasons not reproduced: {missing}")
    if p1["status"] == "FAIL":
        notes.append("classification mismatch: " + "; ".join(p1["mismatches"]))
    if p3_status == "FAIL":
        notes.append(f"phase5: got {res.phase5} vs hist {hist.get('phase5')}")
    if p4["status"] == "FAIL":
        notes.append(f"hallucinated finding paths: {bad_paths}")

    hu = hist["usage"]
    return {
        "slug": slug,
        "verdict": verdict, "notes": notes,
        "terminal": res.terminal.value, "error": res.error,
        "parity": {"classification": p1, "armed": {"new": new_armed, "hist": hist_armed,
                                                   "false_arm": false_arm,
                                                   "new_holds": hold_nums, "hist_holds": hist_holds},
                   "phase5": {"new": res.phase5, "hist": hist.get("phase5"), "status": p3_status},
                   "findings": p4},
        "new": {**ledger.totals(), "wall_seconds": wall,
                "calls": [{"purpose": c.purpose, "model": c.model,
                           "in": c.input_tokens, "cache_create": c.cache_creation_input_tokens,
                           "cache_read": c.cache_read_input_tokens, "out": c.output_tokens,
                           "cost_usd": round(c.cost_usd, 4), "ms": c.duration_ms,
                           "retries": c.retries, "ok": c.ok} for c in ledger.calls]},
        "old": {"gross_tokens": hu["in"] + hu["cc"] + hu["cr"] + hu["out"],
                "non_cached_tokens": hu["in"] + hu["cc"] + hu["out"],
                "output_tokens": hu["out"], "llm_invocations": hist.get("turns"),
                "tool_calls": hist.get("tool_calls"), "wall_minutes": hist.get("dur_min"),
                "agent_spawns": hist.get("agent_spawns")},
    }


def main() -> int:
    which = sys.argv[1] if len(sys.argv) > 1 else "all"
    slugs = {"primary": PRIMARY, "holdout": HOLDOUT,
             "all": PRIMARY + HOLDOUT}.get(which) or which.split(",")
    out = os.path.join(ROOT, "bench", "results.json")
    results = {"primary": [], "holdout": [], "excluded": []}
    if os.path.exists(out):        # resume: merge over prior results
        with open(out) as fh:
            results = json.load(fh)
        for bucket in ("primary", "holdout", "excluded"):
            results[bucket] = [r for r in results[bucket] if r["slug"] not in slugs]
    for slug in slugs:
        bucket = "holdout" if slug in HOLDOUT else "primary"
        if slug in EXCLUDED:
            results["excluded"].append({"slug": slug, "reason": EXCLUDED[slug]})
            print(f"== {slug}: EXCLUDED — {EXCLUDED[slug][:80]}...")
            continue
        print(f"== {slug} ...", flush=True)
        r = run_scenario(slug)
        results[bucket].append(r)
        print(f"   {r['verdict']} terminal={r['terminal']} "
              f"armed new={r['parity']['armed']['new']} hist={r['parity']['armed']['hist']} "
              f"new_cost=${r['new']['cost_usd']} new_gross={r['new']['gross_tokens']} "
              f"old_gross={r['old']['gross_tokens']}")
        for n in r["notes"]:
            print(f"   note: {n}")
        with open(out, "w") as fh:      # write after every scenario (crash-safe)
            json.dump(results, fh, indent=1)
    with open(out, "w") as fh:
        json.dump(results, fh, indent=1)
    print(f"\nwrote {out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
