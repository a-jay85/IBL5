"""Compare the Phase 5.5 shadow verdict (conditions 3 and 18, body numbers) with the old code.

  python3 compare_fidelity_shadow.py [--out-dir DIR] [--json PATH] [--gate]
  python3 compare_fidelity_shadow.py --seeded (--live | --canned DIR) [...]
  python3 compare_fidelity_shadow.py --live-sample N [--seed S] [...]

Default mode walks `<out-dir>/live-*/result.json` and makes zero LLM calls. For conditions
(3) and (18) it buckets each run by the NEW state (`fidelity.shadow`, via
`schemas.shadow_condition_states`) against the OLD outcome (`fidelity.shadow.old`, else
`arm.conditions`). An unknown new state never counts as agreement. Runs recorded before the
shadow existed land in `pre-shadow`.

--seeded scores the Phase 6 cases under tests/fixtures/fidelity-shadow/seeded instead of the
corpus. --live calls the real reviewer (7 Opus calls); --canned DIR reads `<DIR>/<case>.md`
verdict replies and costs nothing. --live-sample N (1..25) re-runs the new reviewer on N
corpus runs. Its packets live under `<out-dir>/shadow-compare/<run>/` with pr id
`shadow-<run>`, so no real PR's verdict file is touched.

Prints a FLIP EXIT CRITERIA block (E1-E7). PENDING means not enough data and E7 is always
MANUAL. With --gate the exit code is 1 unless E1-E6 are all PASS. Exit 2 on a bad
flag. Stdlib plus the harness package.
"""
import argparse
import glob
import json
import os
import random
import re
import shutil
import sys
import types

HARNESS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO_ROOT = os.path.dirname(os.path.dirname(HARNESS))
if HARNESS not in sys.path:
    sys.path.insert(0, HARNESS)

from harness import fidelity, schemas  # noqa: E402
from harness.adapters.llm import ClaudeCli, FixtureLlm  # noqa: E402
from harness.state import UsageLedger  # noqa: E402

SEEDED_DIR = os.path.join(HARNESS, "tests", "fixtures", "fidelity-shadow", "seeded")
PROCEDURE_REL = os.path.join(".claude", "review-shared", "_plan-fidelity-review.md")
REVIEW_PURPOSE = "plan-fidelity-review"

CONDS = ("3", "18")
BUCKETS = ("agree-hold", "agree-clear", "old-hold-only", "new-hold-only",
           "unknown", "pre-shadow", "not-evaluated")
LIST_CAP = 50
MIN_SAMPLE, MAX_SAMPLE = 1, 25
COST_PER_CALL = 1.1

E3_FLOOR = 200
E4_MAX = 0.02
E5_MIN = 0.95
E6_MAX = 0.03


# --- state and bucketing -----------------------------------------------------------------

def old_outcomes(result: dict) -> dict:
    """{"3","18"} -> hold / clear / not-evaluated. `fidelity.shadow.old` wins per key; a key
    it lacks falls back to `arm.conditions`, matched by the condition `number`."""
    shadow = (result.get("fidelity") or {}).get("shadow")
    stored = shadow.get("old") if isinstance(shadow, dict) else None
    stored = stored if isinstance(stored, dict) else {}
    arm = result.get("arm")
    by_number: dict[int, dict] = {}
    if isinstance(arm, dict):
        for c in arm.get("conditions") or []:
            if not isinstance(c, dict):
                continue
            try:
                by_number[int(c.get("number"))] = c
            except (TypeError, ValueError):
                continue
    out = {}
    for n in CONDS:
        if stored.get(n) in ("hold", "clear", "not-evaluated"):
            out[n] = stored[n]
            continue
        cond = by_number.get(int(n))
        if cond is None:
            out[n] = "not-evaluated"
        else:
            flag = cond["blocked"] if "blocked" in cond else cond.get("holds")
            out[n] = "hold" if flag else "clear"
    return out


def bucket(new: str, old: str) -> str:
    """One condition's bucket. Unknown (or any non hold/clear) new state is never agreement."""
    if new not in ("hold", "clear"):
        return "unknown"
    if old not in ("hold", "clear"):
        return "not-evaluated"
    if new == "hold":
        return "agree-hold" if old == "hold" else "new-hold-only"
    return "agree-clear" if old == "clear" else "old-hold-only"


def _rate(num: int, den: int):
    return None if den == 0 else num / den


# --- corpus mode -------------------------------------------------------------------------

def _empty_cond() -> dict:
    return {"buckets": {b: 0 for b in BUCKETS}, "old_hold": 0, "old_clear": 0,
            "old_not_evaluated": 0, "lists": {"old-hold-only": [], "new-hold-only": []}}


def scan_corpus(out_dir: str) -> dict:
    conds = {c: _empty_cond() for c in CONDS}
    status_hist: dict[str, int] = {}
    body = {"consistent": 0, "mismatch": 0, "unknown": 0}
    skipped: dict[str, int] = {}
    totals = {"runs": 0, "pre_shadow": 0, "plan_blind_excluded_cond3": 0,
              "usable_shadow": 0, "reached_5_5": 0, "typed_failures": 0}

    def skip(reason: str) -> None:
        skipped[reason] = skipped.get(reason, 0) + 1

    for result_path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        name = os.path.basename(os.path.dirname(result_path))
        try:
            with open(result_path, encoding="utf-8") as fh:
                result = json.load(fh)
        except (OSError, ValueError):
            skip("unreadable")
            continue
        if not isinstance(result, dict):
            skip("not-an-object")
            continue
        totals["runs"] += 1
        old = old_outcomes(result)
        for n in CONDS:
            conds[n]["old_" + {"hold": "hold", "clear": "clear"}.get(old[n], "not_evaluated")] += 1
        fid = result.get("fidelity")
        shadow = fid.get("shadow") if isinstance(fid, dict) else None
        if not isinstance(shadow, dict):
            totals["pre_shadow"] += 1
            for n in CONDS:
                conds[n]["buckets"]["pre-shadow"] += 1
            continue
        status = str(shadow.get("status"))
        status_hist[status] = status_hist.get(status, 0) + 1
        if status in ("present", "carried"):
            totals["usable_shadow"] += 1
        if status != "absent:not-reached":
            totals["reached_5_5"] += 1
            if status.startswith("invalid:") or status.startswith("absent:review-error:"):
                totals["typed_failures"] += 1
        states = schemas.shadow_condition_states(shadow)
        body_state = states.get("body_numbers")
        body[body_state if body_state in body else "unknown"] += 1
        for n in CONDS:
            if n == "3" and shadow.get("plan_blind") is True:
                totals["plan_blind_excluded_cond3"] += 1
                continue
            b = bucket(states.get(n, "unknown"), old[n])
            conds[n]["buckets"][b] += 1
            if b in conds[n]["lists"] and len(conds[n]["lists"][b]) < LIST_CAP:
                conds[n]["lists"][b].append(name)
    for n in CONDS:
        b = conds[n]["buckets"]
        conds[n]["compared"] = b["agree-hold"] + b["agree-clear"] + b["old-hold-only"] + b["new-hold-only"]
    return {"totals": totals, "conditions": conds, "status_histogram": status_hist,
            "body_numbers": body, "skipped": skipped}


# --- seeded mode -------------------------------------------------------------------------

def load_cases(seeded_dir: str = SEEDED_DIR) -> list[dict]:
    cases = []
    for expect_path in sorted(glob.glob(os.path.join(seeded_dir, "*", "expect.json"))):
        with open(expect_path, encoding="utf-8") as fh:
            data = json.load(fh)
        cases.append({"name": os.path.basename(os.path.dirname(expect_path)),
                      "dir": os.path.dirname(expect_path), "expect": data["expect"]})
    return cases


def score(expected: str, got: str) -> str:
    """hit / miss / false-hold / unknown. A wanted hold or mismatch answered with anything
    else (unknown included) is a miss. A wanted clear or consistent answered hold or mismatch
    is a false-hold; answered unknown it is `unknown`, which is not a hit."""
    if expected in ("hold", "mismatch"):
        return "hit" if got == expected else "miss"
    if got == expected:
        return "hit"
    return "false-hold" if got in ("hold", "mismatch") else "unknown"


def is_control(expect: dict) -> bool:
    return not any(v in ("hold", "mismatch") for v in expect.values())


def _run_review(llm, out_dir: str, packet_dir: str, pr: str) -> dict:
    """Run the reviewer on a built packet and read the shadow record it wrote. The verdict
    file is removed first so a stale one from an earlier run can never be read."""
    path = fidelity.verdict_path(pr)
    if os.path.exists(path):
        os.remove(path)
    _verdict, err = fidelity.review(llm, out_dir, REPO_ROOT, packet_dir, pr)
    if err:
        return fidelity.shadow_absent(f"review-error:{err}")
    return fidelity.read_shadow(path, source="shadow-compare", reviewed_tree=None,
                                plan_blind=False)


def _overwrite_procedure(packet_dir: str) -> None:
    """Use the working-tree procedure, not the committed one, so an unmerged wording change
    is what gets measured."""
    src = os.path.join(REPO_ROOT, PROCEDURE_REL)
    if os.path.isfile(src):
        shutil.copyfile(src, os.path.join(packet_dir, "procedure.md"))


def run_seeded(out_dir: str, live: bool, canned_dir, ledger: UsageLedger) -> dict:
    cases_out = {}
    counts = {"hit": 0, "miss": 0, "false-hold": 0, "unknown": 0}
    control_bad = 0
    for case in load_cases():
        name = case["name"]
        diff = _read(os.path.join(case["dir"], "diff.patch"))
        body = _read(os.path.join(case["dir"], "pr-body.md"))
        shadow_dir = os.path.join(out_dir, "shadow-compare")
        os.makedirs(shadow_dir, exist_ok=True)
        plan = types.SimpleNamespace(found=True, path=os.path.join(case["dir"], "plan.md"))
        pr = f"shadow-seeded-{name}"
        packet = fidelity.build_packet(
            shadow_dir, "HEAD", "0" * 40, plan, diff, body, pr, False,
            worktree=REPO_ROOT, packet_name=f"seeded-{name}", shadow_request=True)
        _overwrite_procedure(packet)
        if live:
            llm = ClaudeCli(ledger, out_dir=packet)
        else:
            reply = os.path.join(canned_dir, f"{name}.md")
            if os.path.isfile(reply):
                with open(reply, encoding="utf-8") as fh:
                    canned = fh.read()
                llm = FixtureLlm(ledger, {REVIEW_PURPOSE: canned})
            else:
                llm = None
        if llm is None:
            rec = fidelity.shadow_absent("canned-missing")
        else:
            try:
                rec = _run_review(llm, out_dir, packet, pr)
            finally:
                if live and hasattr(llm, "close"):
                    llm.close()
        states = schemas.shadow_condition_states(rec)
        keys = {}
        for key, expected in case["expect"].items():
            got = states.get(key, "unknown")
            s = score(expected, got)
            counts[s] += 1
            if is_control(case["expect"]) and s != "hit":
                control_bad += 1
            keys[key] = {"expected": expected, "got": got, "score": s}
        cases_out[name] = {"status": rec.get("status"), "control": is_control(case["expect"]),
                           "keys": keys}
    return {"mode": "live" if live else "canned", "cases": cases_out, "counts": counts,
            "control_not_hit": control_bad}


# --- live-sample mode --------------------------------------------------------------------

def eligible_runs(out_dir: str) -> list[str]:
    """Run dirs that stored a plan packet and an arm decision naming conditions 3 and 18."""
    names = []
    for result_path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        run_dir = os.path.dirname(result_path)
        if not os.path.isfile(os.path.join(run_dir, "fidelity-packet", "plan.md")):
            continue
        try:
            with open(result_path, encoding="utf-8") as fh:
                result = json.load(fh)
        except (OSError, ValueError):
            continue
        if not isinstance(result, dict) or not isinstance(result.get("arm"), dict):
            continue
        numbers = set()
        for c in result["arm"].get("conditions") or []:
            try:
                numbers.add(int(c.get("number")))
            except (AttributeError, TypeError, ValueError):
                continue
        if {3, 18} <= numbers:
            names.append(os.path.basename(run_dir))
    return names


def _shadow_context_text(stored_context: str, diff: str, pr: str) -> str:
    text = re.sub(r"^PR_NUMBER:.*$", f"PR_NUMBER: {pr}", stored_context, flags=re.M)
    text = re.sub(r"^VERDICT_OUTPUT_PATH:.*$",
                  f"VERDICT_OUTPUT_PATH: {fidelity.verdict_path(pr)}", text, flags=re.M)
    if fidelity.SHADOW_REQUEST_LINE not in text:
        if text and not text.endswith("\n"):
            text += "\n"
        text += fidelity.shadow_context(diff)
    return text


def run_live_sample(out_dir: str, n: int, seed: int, ledger: UsageLedger) -> dict:
    pool = eligible_runs(out_dir)
    picked = random.Random(seed).sample(pool, min(n, len(pool)))
    print(f"live re-run: {n} Opus calls, about ${COST_PER_CALL * n:.1f}", file=sys.stderr)
    runs = {}
    for name in picked:
        run_dir = os.path.join(out_dir, name)
        with open(os.path.join(run_dir, "result.json"), encoding="utf-8") as fh:
            result = json.load(fh)
        src = os.path.join(run_dir, "fidelity-packet")
        pr = f"shadow-{name}"
        packet = os.path.join(out_dir, "shadow-compare", name)
        os.makedirs(packet, exist_ok=True)
        for fname in ("plan.md", "plan-index.txt", "diff.patch", "pr-body.md"):
            if os.path.isfile(os.path.join(src, fname)):
                shutil.copyfile(os.path.join(src, fname), os.path.join(packet, fname))
        _overwrite_procedure(packet)
        diff = _read(os.path.join(packet, "diff.patch"))
        with open(os.path.join(packet, "context.md"), "w", encoding="utf-8") as fh:
            fh.write(_shadow_context_text(_read(os.path.join(src, "context.md")), diff, pr))
        llm = ClaudeCli(ledger, out_dir=packet)
        try:
            rec = _run_review(llm, out_dir, packet, pr)
        finally:
            if hasattr(llm, "close"):
                llm.close()
        plan_meta = result.get("plan")
        plan_blind = isinstance(plan_meta, dict) and plan_meta.get("found") is False
        states = schemas.shadow_condition_states(rec)
        old = old_outcomes(result)
        buckets = {}
        for c in CONDS:
            if c == "3" and plan_blind:
                continue
            buckets[c] = bucket(states.get(c, "unknown"), old[c])
        runs[name] = {"pr": pr, "status": rec.get("status"), "states": states,
                      "old": old, "buckets": buckets}
    table = {c: {b: 0 for b in BUCKETS} for c in CONDS}
    for r in runs.values():
        for c, b in r["buckets"].items():
            table[c][b] += 1
    return {"requested": n, "seed": seed, "eligible": len(pool), "runs": runs,
            "table": table, "spend": ledger.totals()}


def _read(path: str) -> str:
    try:
        with open(path, encoding="utf-8") as fh:
            return fh.read()
    except OSError:
        return ""


# --- flip exit criteria ------------------------------------------------------------------

def _worst(statuses) -> str:
    for s in ("FAIL", "PENDING"):
        if s in statuses:
            return s
    return "PASS"


def pct(x) -> str:
    return "n/a" if x is None else f"{100 * x:.1f}%"


def evaluate(res: dict) -> list[dict]:
    out = []
    seeded = res.get("seeded")
    if seeded is None:
        out.append({"id": "E1", "name": "seeded recall", "status": "PENDING",
                    "detail": "no --seeded run"})
        out.append({"id": "E2", "name": "seeded controls", "status": "PENDING",
                    "detail": "no --seeded run"})
    else:
        c = seeded["counts"]
        note = "" if seeded["mode"] == "live" else " (canned replies, not a live run)"
        out.append({"id": "E1", "name": "seeded recall",
                    "status": "PASS" if c["miss"] == 0 else "FAIL",
                    "detail": f"miss={c['miss']} hit={c['hit']}{note}"})
        bad = seeded["control_not_hit"] + sum(
            1 for case in seeded["cases"].values() if not case["control"]
            for k in case["keys"].values() if k["score"] == "false-hold")
        out.append({"id": "E2", "name": "seeded controls",
                    "status": "PASS" if bad == 0 else "FAIL",
                    "detail": f"false-hold={c['false-hold']} unknown={c['unknown']} "
                              f"control-or-clear-key failures={bad}{note}"})
    corpus = res.get("corpus")
    if corpus is None:
        for eid, name in (("E3", "sample floor"), ("E4", "typed-failure rate"),
                          ("E5", "agreement"), ("E6", "new false holds")):
            out.append({"id": eid, "name": name, "status": "PENDING",
                        "detail": "no corpus scan in --seeded mode"})
    else:
        t = corpus["totals"]
        out.append({"id": "E3", "name": "sample floor",
                    "status": "PASS" if t["usable_shadow"] >= E3_FLOOR else "PENDING",
                    "detail": f"{t['usable_shadow']} present/carried runs, need {E3_FLOOR}"})
        rate = _rate(t["typed_failures"], t["reached_5_5"])
        out.append({"id": "E4", "name": "typed-failure rate",
                    "status": ("PENDING" if rate is None
                               else "PASS" if rate <= E4_MAX else "FAIL"),
                    "detail": f"{t['typed_failures']}/{t['reached_5_5']} = {pct(rate)}, "
                              f"max {pct(E4_MAX)}"})
        e5, e6, d5, d6 = [], [], [], []
        for n in CONDS:
            cond = corpus["conditions"][n]
            b = cond["buckets"]
            agree = b["agree-hold"] + b["agree-clear"]
            den = cond["compared"]
            r5 = _rate(agree, den)
            r6 = _rate(b["new-hold-only"], den)
            e5.append("PENDING" if r5 is None else "PASS" if r5 >= E5_MIN else "FAIL")
            e6.append("PENDING" if r6 is None else "PASS" if r6 <= E6_MAX else "FAIL")
            d5.append(f"({n}) {agree}/{den} = {pct(r5)}")
            d6.append(f"({n}) {b['new-hold-only']}/{den} = {pct(r6)}")
        out.append({"id": "E5", "name": "agreement", "status": _worst(e5),
                    "detail": "; ".join(d5) + f"; min {pct(E5_MIN)}"})
        out.append({"id": "E6", "name": "new false holds", "status": _worst(e6),
                    "detail": "; ".join(d6) + f"; max {pct(E6_MAX)}"})
    out.append({"id": "E7", "name": "human sign-off", "status": "MANUAL",
                "detail": "the flip PR reviews every listed old-hold-only run"})
    return out


# --- rendering ---------------------------------------------------------------------------

def render(res: dict) -> str:
    lines = []
    corpus = res.get("corpus")
    if corpus is not None:
        t = corpus["totals"]
        lines.append(f"Corpus: runs={t['runs']} pre-shadow={t['pre_shadow']} "
                     f"plan-blind excluded from condition (3)={t['plan_blind_excluded_cond3']}")
        if corpus["skipped"]:
            lines.append("skipped: " + ", ".join(f"{k}={v}" for k, v in sorted(corpus["skipped"].items())))
        for n in CONDS:
            c = corpus["conditions"][n]
            lines.append(f"Condition ({n}) old outcomes: hold={c['old_hold']} clear={c['old_clear']} "
                         f"not-evaluated={c['old_not_evaluated']}")
            lines.append("  " + " ".join(f"{b}={c['buckets'][b]}" for b in BUCKETS))
        lines.append("Shadow status: " + (", ".join(
            f"{k}={v}" for k, v in sorted(corpus["status_histogram"].items())) or "(none)"))
        bn = corpus["body_numbers"]
        lines.append(f"body_numbers: consistent={bn['consistent']} mismatch={bn['mismatch']} "
                     f"unknown={bn['unknown']} (no old outcome is recorded, so no agreement figure)")
        for n in CONDS:
            for kind in ("old-hold-only", "new-hold-only"):
                names = corpus["conditions"][n]["lists"][kind]
                if names:
                    lines.append(f"{kind} runs, condition ({n}) (first {LIST_CAP}):")
                    lines += [f"  {x}" for x in names]
    seeded = res.get("seeded")
    if seeded is not None:
        lines.append(f"Seeded ({seeded['mode']}): " + " ".join(
            f"{k}={v}" for k, v in seeded["counts"].items()))
        for name, case in seeded["cases"].items():
            parts = " ".join(f"{k}:{v['expected']}->{v['got']}={v['score']}"
                             for k, v in case["keys"].items())
            lines.append(f"  {name} [{case['status']}] {parts}")
    live = res.get("live_sample")
    if live is not None:
        lines.append(f"Live sample ({len(live['runs'])} of {live['eligible']} eligible runs, "
                     f"seed {live['seed']}, labeled a {len(live['runs'])}-run sample):")
        for n in CONDS:
            lines.append(f"  ({n}) " + " ".join(f"{b}={live['table'][n][b]}" for b in BUCKETS))
        lines.append(f"  spend: ${live['spend']['cost_usd']} over "
                     f"{live['spend']['llm_invocations']} calls")
    lines.append("")
    lines.append("FLIP EXIT CRITERIA")
    for c in res["criteria"]:
        lines.append(f"  {c['id']} {c['name']}: {c['status']} ({c['detail']})")
    return "\n".join(lines) + "\n"


# --- CLI ---------------------------------------------------------------------------------

def default_out_dir() -> str:
    return os.path.join(HARNESS, "out")


def parse_args(argv):
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--out-dir", help="default <harness>/out")
    ap.add_argument("--json", help="write the full result dict here")
    ap.add_argument("--gate", action="store_true",
                    help="exit 1 unless E1-E6 are all PASS (PENDING fails; E7 is MANUAL)")
    ap.add_argument("--seeded", action="store_true", help="score the Phase 6 cases")
    ap.add_argument("--live", action="store_true", help="with --seeded: call the real reviewer")
    ap.add_argument("--canned", metavar="DIR", help="with --seeded: <DIR>/<case>.md replies")
    ap.add_argument("--live-sample", type=int, metavar="N",
                    help=f"re-run the reviewer on N corpus runs ({MIN_SAMPLE}..{MAX_SAMPLE})")
    ap.add_argument("--seed", type=int, default=0, help="sampling seed (default 0)")
    ns = ap.parse_args(argv)
    if ns.live_sample is not None and not MIN_SAMPLE <= ns.live_sample <= MAX_SAMPLE:
        ap.error(f"--live-sample must be {MIN_SAMPLE}..{MAX_SAMPLE}")
    if ns.seeded and not (ns.live or ns.canned):
        ap.error("--seeded requires --live or --canned DIR")
    if ns.live and not ns.seeded:
        ap.error("--live only applies with --seeded")
    if ns.canned and not ns.seeded:
        ap.error("--canned only applies with --seeded")
    if ns.live and ns.canned:
        ap.error("--live and --canned are mutually exclusive")
    if ns.out_dir is None:
        ns.out_dir = default_out_dir()
    return ns


def main(argv=None) -> int:
    ns = parse_args(sys.argv[1:] if argv is None else argv)
    out_dir = os.path.abspath(ns.out_dir)
    ledger = UsageLedger()
    res: dict = {"corpus": None, "seeded": None, "live_sample": None}
    if ns.seeded:
        res["seeded"] = run_seeded(out_dir, ns.live, ns.canned, ledger)
    else:
        res["corpus"] = scan_corpus(out_dir)
    if ns.live_sample is not None:
        res["live_sample"] = run_live_sample(out_dir, ns.live_sample, ns.seed, ledger)
    res["criteria"] = evaluate(res)
    sys.stdout.write(render(res))
    if ns.json:
        with open(ns.json, "w", encoding="utf-8") as fh:
            json.dump(res, fh, indent=2)
    if ns.gate and any(c["status"] != "PASS" for c in res["criteria"] if c["status"] != "MANUAL"):
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
