"""Diff the planned token per matrix row: old joined-row first match vs new rightmost valid cell.

Usage:
  python3 planned_token_location_cell_corpus_diff.py --harness-root <dir containing harness/> \
      [--plans-dir ~/claude-plans] [--report PATH]

Both sides run in one process: "old" rebinds `planfile._planned_token` to an embedded
copy of the pre-change body and restores it in a `finally`. The planning gate
(`_has_planning_type_cell` plus a valid first token in the joined row) is unchanged by
the new rule, so only the token a row plans may move. Prints one line per unfenced row
where old and new disagree, plan-level ADDED and DROPPED sets, and a SUMMARY line.
CHANGED is classified by the old token: GLOB, SEED, BARE, or REVIEW (both tokens look
like real test files, so a person classifies the row). NEWLY-PLANNED is a row old
skipped and new plans; LOST is a row old planned and new skips. Either one breaks the
structural invariant of the change.
Files under a `_reports/` directory are skipped: they hold earlier runs of this tool,
and re-reading them feeds their own rows back in.
Exits 1 when lost>0 or newly_planned>0; REVIEW rows never fail the tool, the opt-in
pytest wrapper owns that allowlist.
Stdlib only.
"""
import argparse
import glob
import os
import re
import sys

_TOKEN_RE = re.compile(r"`([^`]*(?:test|spec|Test)[^`]*)`")
_GLOB_CHARS = ("*", "{", "?", "[")
_SEED_RE = re.compile(r"\.sql:\d+(-\d+)?$")


def _old_planned_token(planfile, cells):
    if not planfile._has_planning_type_cell(cells):
        return None
    m = _TOKEN_RE.search(" | ".join(cells))
    if m and planfile._is_test_path(m.group(1)):
        return m.group(1)
    return None


def _cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def _excerpt(line: str) -> str:
    return " ".join(line.split())[:160]


def _classify(token: str) -> str:
    if any(ch in token for ch in _GLOB_CHARS) or token.startswith("^") or token.endswith("/"):
        return "GLOB"
    if token.endswith(".sql") or _SEED_RE.search(token):
        return "SEED"
    if "." not in token.rsplit("/", 1)[-1]:
        return "BARE"
    return "REVIEW"


def _planned(planfile, content: str) -> set[str]:
    return (set(planfile.parse_matrix(content)[0]) | set(planfile.parse_no_change_test_paths(content))
            | set(getattr(planfile, "parse_run_only_test_paths", lambda _c: [])(content)))


def _old_planned(planfile, content: str) -> set[str]:
    real = planfile._planned_token
    planfile._planned_token = lambda cells: _old_planned_token(planfile, cells)
    try:
        return _planned(planfile, content)
    finally:
        planfile._planned_token = real


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    ap.add_argument("--plans-dir", default=os.path.expanduser("~/claude-plans"))
    ap.add_argument("--report", default="")
    args = ap.parse_args(argv)
    sys.path.insert(0, os.path.abspath(args.harness_root))
    from harness import planfile  # noqa: E402  (tree chosen by --harness-root)

    # The report lives under the corpus by default; reading it back would feed its own rows in.
    report = os.path.abspath(os.path.expanduser(args.report)) if args.report else None
    paths = [p for p in sorted(glob.glob(os.path.join(args.plans_dir, "**", "*.md"), recursive=True))
             if os.path.abspath(p) != report
             and "_reports" not in os.path.relpath(p, args.plans_dir).split(os.sep)]
    rows_old = rows_new = 0
    changed: list[tuple] = []
    newly: list[tuple] = []
    lost: list[tuple] = []
    plan_added: list[tuple] = []
    plan_dropped: list[tuple] = []
    for path in paths:
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        rel = os.path.relpath(path, args.plans_dir)
        for line in planfile._strip_fenced(content):
            if not line.strip().startswith("|"):
                continue
            cells = _cells(line)
            old = _old_planned_token(planfile, cells)
            new = planfile._planned_token(cells)
            rows_old += old is not None
            rows_new += new is not None
            if old == new:
                continue
            if old is None:
                newly.append((rel, new, _excerpt(line)))
            elif new is None:
                lost.append((rel, old, _excerpt(line)))
            else:
                changed.append((rel, _classify(old), old, new, _excerpt(line)))
        new_set = _planned(planfile, content)
        old_set = _old_planned(planfile, content)
        plan_added += [(rel, t) for t in sorted(new_set - old_set)]
        plan_dropped += [(rel, t) for t in sorted(old_set - new_set)]

    out = [f"CHANGED\t{r}\t{c}\t{o}\t{n}\t{e}" for r, c, o, n, e in changed]
    out += [f"NEWLY-PLANNED\t{r}\t{n}\t{e}" for r, n, e in newly]
    out += [f"LOST\t{r}\t{o}\t{e}" for r, o, e in lost]
    out += [f"ADDED\t{r}\t{t}" for r, t in plan_added]
    out += [f"DROPPED\t{r}\t{t}" for r, t in plan_dropped]
    review = sum(1 for c in changed if c[1] == "REVIEW")
    out.append(f"SUMMARY\tfiles={len(paths)}\trows_old={rows_old}\trows_new={rows_new}"
               f"\tchanged={len(changed)}\tnewly_planned={len(newly)}\tlost={len(lost)}"
               f"\treview={review}\tplan_added={len(plan_added)}\tplan_dropped={len(plan_dropped)}")
    print("\n".join(out))

    if args.report:
        def esc(s: str) -> str:
            return s.replace("|", "\\|")
        rows = ["| plan | class | old token | new token | row |", "|---|---|---|---|---|"]
        rows += [f"| {esc(r)} | {c} | `{esc(o)}` | `{esc(n)}` | {esc(e)} |" for r, c, o, n, e in changed]
        with open(args.report, "w", encoding="utf-8") as fh:
            fh.write("\n".join(out[-1:] + [""] + rows) + "\n")
    return 1 if lost or newly else 0


if __name__ == "__main__":
    sys.exit(main())
