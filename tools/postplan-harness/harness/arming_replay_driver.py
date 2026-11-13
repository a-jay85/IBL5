"""Run one side's `evaluate` over the arming replay corpus under a line tracer.

Invoked by harness/arming_replay.py as
`python3 arming_replay_driver.py <corpus> <side>/tools/postplan-harness <side>`. The side's
own `ArmInputs` decodes each record, so a record naming a field the side lacks fails the run.
No try/except: a decode or evaluate exception exits nonzero and the caller keeps the flag.
"""
import sys
if __name__ == "__main__" and len(sys.argv) == 4:
    sys.path[0] = sys.argv[2]      # side package root; must precede every other import
import dataclasses
import json
import os


def decode(record, ArmInputs, Classification, Finding):
    raw = dict(record["inputs"])
    states = raw.pop("dep_states", {})
    default = raw.pop("dep_default", "UNKNOWN")
    names = {f.name for f in dataclasses.fields(ArmInputs)}
    kw = {"dep_state_lookup": lambda n: states.get(str(n), default)}
    for key, value in raw.items():
        if key not in names:
            raise KeyError(f"unknown corpus field: {key}")
        kw[key] = value
    kw["classification"] = Classification(**kw["classification"])
    kw["findings"] = [Finding(**f) for f in kw["findings"]]
    for key in ("exec_mutations_holding", "plan_hold_triggers", "plan_checks_holding"):
        if kw.get(key) is not None:
            kw[key] = tuple(kw[key])
    if "second_review_clears" in kw:
        kw["second_review_clears"] = {int(k): v for k, v in kw["second_review_clears"].items()}
    return ArmInputs(**kw)


def main(argv):                    # argv: driver, corpus path, side package root, side root
    if len(argv) != 4:
        return 2
    root = os.path.realpath(argv[3])
    from harness.armable import ArmInputs, evaluate
    from harness.state import Classification, Finding
    with open(argv[1], encoding="utf-8") as fh:
        records = json.load(fh)
    decoded = [(r["id"], decode(r, ArmInputs, Classification, Finding)) for r in records]
    hits, real = {}, {}

    def tracer(frame, event, arg):
        name = frame.f_code.co_filename
        if name.startswith("<"):   # <string>, <frozen os>: no file, and realpath would root it at cwd
            return None
        path = real.setdefault(name, os.path.realpath(name))
        if not path.startswith(root + os.sep):
            return None
        if event == "line":
            hits.setdefault(os.path.relpath(path, root), set()).add(frame.f_lineno)
        return tracer

    results = []
    sys.settrace(tracer)
    try:
        for rid, inp in decoded:
            decision = evaluate(inp)
            blocked = sorted(c.number for c in decision.conditions if c.blocked)
            results.append({"id": rid, "armed": bool(decision.armed), "blocked": blocked})
    finally:
        sys.settrace(None)
    json.dump({"fields": sorted(f.name for f in dataclasses.fields(ArmInputs)),
               "results": results, "executed": {k: sorted(v) for k, v in hits.items()}}, sys.stdout)
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
