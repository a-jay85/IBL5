"""Diff unplanned-scope partition buckets: the pre-backlog#1790 rule vs the live rule.

Usage:
  python3 unplanned_ctx_none_corpus_diff.py --harness-root <dir containing harness/>

"new" is the real unplanned_scope.partition. "old" restates the pre-change loop, where a
tagged hold-12 item stayed in `work` whenever ctx was None. The corpus is built here from
committed inputs: four contexts (none, keep, remove, hold), three holds (12, 3, 16), and a
tagged plus an untagged finding, each item run alone and all items run together.
EXPECTED is the one cell the change must move: ctx=none hold=12 tagged, work -> held.
Prints CHANGED, UNEXPECTED and MISSING lines plus a SUMMARY line.
Exits 1 when unexpected>0 or missing>0. Stdlib only.
"""
import argparse
import os
import sys

GRID = "ibl5/design/components/negotiate.css"
FEAT = "feat: confine quick offer presets to three in a row"
TAGGED = f"- [BLOCKING] [unplanned-scope] Remove or plan the button grid in {GRID}. Next action: move it."
UNTAGGED = "- [BLOCKING] Rename the helper in the plan's terms."
HOLDS = ("12", "3", "16")
EXPECTED = {("none", "12", True): ("work", "held")}


def _old_partition(us, items, ctx):
    """The pre-change loop, restated: classify only with a ctx, default to work."""
    work, kept, held = [], [], []
    for item in items:
        branch = None
        if ctx is not None and item["hold"] == "12":
            branch = us.classify(item["text"], **ctx)
        {"keep": kept, "hold": held}.get(branch, work).append(item)
    return work, kept, held


def _contexts(fidelity):
    def ctx(subjects, preserve=(), removed=()):
        return {"changed_paths": [GRID], "subjects_by_path": {GRID: list(subjects)},
                "must_preserve": set(preserve), "removed_guard_paths": set(removed)}
    return {"none": None,
            "keep": ctx([FEAT]),
            "remove": ctx([fidelity.REMEDIATION_COMMIT_MSG]),
            "hold": ctx([FEAT], preserve=(GRID,), removed=(GRID,))}


def _buckets(result):
    out = {}
    for name, bucket in zip(("work", "kept", "held"), result):
        for item in bucket:
            out[(item["hold"], item["text"])] = name
    return out


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    args = ap.parse_args(argv)
    sys.path.insert(0, os.path.abspath(args.harness_root))
    from harness import fidelity, unplanned_scope as us
    items = [{"hold": h, "text": t} for h in HOLDS for t in (TAGGED, UNTAGGED)]
    contexts = _contexts(fidelity)
    changed = {}
    for cname, ctx in contexts.items():
        for run in [[i] for i in items] + [items]:
            old = _buckets(_old_partition(us, list(run), ctx))
            new = _buckets(us.partition(list(run), ctx))
            for key, ob in old.items():
                nb = new.get(key)
                if ob != nb:
                    changed[(cname, key[0], key[1] == TAGGED)] = (ob, nb)
    unexpected = 0
    for cell, move in sorted(changed.items(), key=str):
        tag = "CHANGED" if EXPECTED.get(cell) == move else "UNEXPECTED"
        unexpected += tag == "UNEXPECTED"
        print(f"{tag} ctx={cell[0]} hold={cell[1]} tagged={cell[2]} {move[0]} -> {move[1]}")
    missing = [c for c in EXPECTED if changed.get(c) != EXPECTED[c]]
    for c in missing:
        print(f"MISSING ctx={c[0]} hold={c[1]} tagged={c[2]}")
    print(f"SUMMARY cells={len(contexts) * len(items)} changed={len(changed)} "
          f"unexpected={unexpected} missing={len(missing)}")
    return 1 if unexpected or missing else 0


if __name__ == "__main__":
    sys.exit(main())
