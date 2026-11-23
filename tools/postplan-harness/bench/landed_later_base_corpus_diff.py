"""Corpus diff for the planned-landed arming filter on stacked review bases (backlog #1852).

Replays every stored shadow record through fidelity.reviewer_arming_holds with the
master-only rule (old) and the review-base rule (new). Diffs are not stored, so each run
uses worst-case inputs: every MISSING item landed in the master diff, and on a stacked
run none landed in the review-base diff. newly-held is an upper bound; new-clear and
parity-break must be 0.

  python3 landed_later_base_corpus_diff.py --harness-root <dir containing harness/> \
      [--out-dir <harness-root>/out]
"""
import argparse
import glob
import json
import os
import re
import sys

BASE_RE = re.compile(r"phase5\.5: review base: (\S+)")


def _base(run_dir):
    """"stacked" when the run's last Phase 5.5 review-base line names a parent, else "master"
    (no audit.log, no match, or any other word: runs before stacked review reviewed master)."""
    try:
        with open(os.path.join(run_dir, "audit.log"), encoding="utf-8", errors="replace") as fh:
            matches = BASE_RE.findall(fh.read())
    except OSError:
        return "master"
    return "stacked" if matches and matches[-1] == "parent" else "master"


def _missing_items(shadow):
    try:
        items = shadow["fields"]["planned_landed"]["missing"]
    except (KeyError, TypeError):
        return []
    return [i for i in items if isinstance(i, dict)] if isinstance(items, list) else []


def _worst_case_master_inputs(shadow):
    """Every MISSING item landed in the master diff: file/test tokens as changed files,
    method tokens as added declarations."""
    files, decls = [], []
    for item in _missing_items(shadow):
        tok = " ".join(str(item.get("token", "")).split()).strip("`")
        if not tok:
            continue
        if item.get("kind") in ("file", "test"):
            files.append(tok)
        elif item.get("kind") == "method":
            decls.append(f"+def {tok}(x):")
    return files, "\n".join(decls)


def _held(parts):
    return any(p.startswith("planned-landed:") for p in parts)


def _fmt(label, prs):
    return f"{label}: {len(prs)}" + (f" {sorted(prs, key=str)}" if prs else "")


def main(argv=None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    ap.add_argument("--out-dir", default=None)
    args = ap.parse_args(argv)
    root = os.path.abspath(args.harness_root)
    sys.path.insert(0, root)
    from harness import fidelity  # noqa: E402  (tree chosen by --harness-root)

    out_dir = args.out_dir or os.path.join(root, "out")
    noop = lambda _m: None  # noqa: E731
    records = master_n = stacked_n = stacked_missing = 0
    newly_held, new_clear, parity_break = [], [], []
    for run in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        try:
            with open(run) as fh:
                result = json.load(fh)
        except (OSError, ValueError):
            continue
        if not isinstance(result, dict):
            continue
        shadow = (result.get("fidelity") or {}).get(fidelity.SHADOW_KEY)
        records += 1
        base = _base(os.path.dirname(run))
        pr = result.get("pr_number")
        files, diff = _worst_case_master_inputs(shadow)
        old = fidelity.reviewer_arming_holds(shadow, files, diff, noop)
        if base == "stacked":
            stacked_n += 1
            if _missing_items(shadow):
                stacked_missing += 1
            new = fidelity.reviewer_arming_holds(shadow, files, diff, noop, review_inputs=((), ""))
            child = fidelity.reviewer_arming_holds(shadow, files, diff, noop,
                                                   review_inputs=(files, diff))
        else:
            master_n += 1
            new = child = fidelity.reviewer_arming_holds(shadow, files, diff, noop,
                                                         review_inputs=None)
        if child != old:
            parity_break.append(pr)
        if _held(new) and not _held(old):
            newly_held.append(pr)
        if _held(old) and not _held(new):
            new_clear.append(pr)
    print(f"records: {records}  master-base: {master_n}  stacked-base: {stacked_n}")
    print(f"stacked with MISSING: {stacked_missing}")
    print(_fmt("newly-held (upper bound)", newly_held))
    print(_fmt("new-clear", new_clear))
    print(_fmt("parity-break", parity_break))
    return 1 if new_clear or parity_break else 0


if __name__ == "__main__":
    sys.exit(main())
