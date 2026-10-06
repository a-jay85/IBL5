"""Diff planned test paths: old whole-row type match vs new whole-cell label match.

Usage:
  python3 planned_paths_corpus_diff.py --harness-root <dir containing harness/> \
      [--plans-dir ~/claude-plans] [--report PATH]

Both sets are computed in one process: "old" rebinds
`planfile._has_planning_type_cell` to the pre-change whole-row search and restores
it in a `finally`. Prints ADDED, DROPPED and TYPED-LOSS blocks plus a SUMMARY line.
TYPED-LOSS is a row under a Test-type header whose type cell names a planning type,
fails the label rule, and would have planned a path under the old rule. The last
condition narrows the plan's literal definition (header-column type cell matches the
old regex but fails the label rule): without it, rows that never planned a path under
either rule, such as a `CI (E2E gate)` cell or a shifted prose cell, count as losses
and the tool exits 1 for a row the change cannot have dropped.
Files under a `_reports/` directory are skipped: they hold earlier runs of this tool,
and re-reading them feeds their own DROPPED rows back in.
Exits 1 when added>0 or typed_loss>0; the opt-in pytest also requires dropped>=1.
Stdlib only.
"""
import argparse
import glob
import os
import re
import sys

OLD_RE = re.compile(r"\b(PHPUnit|API.?test|E2E|Visual.?regression)\b", re.I)
_TYPE_HEADER_CELL = re.compile(r"^(?:Test )?type$", re.I)


def _cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def _excerpt(line: str) -> str:
    return " ".join(line.split())[:160]


def _planned(planfile, content: str) -> set[str]:
    return set(planfile.parse_matrix(content)[0]) | set(planfile.parse_no_change_test_paths(content))


def _old_planned(planfile, content: str) -> set[str]:
    real = planfile._has_planning_type_cell
    planfile._has_planning_type_cell = lambda cells: bool(OLD_RE.search(" | ".join(cells)))
    try:
        return _planned(planfile, content)
    finally:
        planfile._has_planning_type_cell = real


def _old_row_token(planfile, line: str):
    """The token the pre-change whole-row rule planned from this row, or None."""
    real = planfile._has_planning_type_cell
    planfile._has_planning_type_cell = lambda cells: bool(OLD_RE.search(" | ".join(cells)))
    try:
        return planfile._planned_token(_cells(line))
    finally:
        planfile._has_planning_type_cell = real


def _type_rows(planfile, content: str):
    """Yield (type_cell, line) for every unfenced row under a Test-type header."""
    col = None
    for line in planfile._strip_fenced(content):
        if not line.strip().startswith("|"):
            col = None
            continue
        if planfile._MATRIX_HEADER.match(line):
            hdr = _cells(line)
            col = next((i for i, c in enumerate(hdr) if _TYPE_HEADER_CELL.match(c.strip("*` "))), None)
            continue
        if col is None or planfile._TABLE_SEP.match(line):
            continue
        cells = _cells(line)
        if len(cells) > col:
            yield cells[col], line


def _type_cell_for(planfile, content: str, token: str) -> tuple[str, str]:
    """(type cell, row excerpt) for the first unfenced row the old rule planned `token` from."""
    typed = {line: cell for cell, line in _type_rows(planfile, content)}
    for line in planfile._strip_fenced(content):
        if line.strip().startswith("|") and _old_row_token(planfile, line) == token:
            return typed.get(line, "-"), _excerpt(line)
    return "-", "-"


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
    added: list[tuple] = []
    dropped: list[tuple] = []
    typed_loss: list[tuple] = []
    for path in paths:
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        rel = os.path.relpath(path, args.plans_dir)
        new = _planned(planfile, content)
        old = _old_planned(planfile, content)
        added += [(rel, t) for t in sorted(new - old)]
        for t in sorted(old - new):
            cell, excerpt = _type_cell_for(planfile, content, t)
            dropped.append((rel, t, cell, excerpt))
        for cell, line in _type_rows(planfile, content):
            if (OLD_RE.search(cell)
                    and not planfile._TEST_TYPE_LABEL.match(planfile._norm_cell(cell))
                    and _old_row_token(planfile, line) is not None):
                typed_loss.append((rel, cell, _excerpt(line)))

    out = [f"ADDED\t{r}\t{t}" for r, t in added]
    out += [f"DROPPED\t{r}\t{t}\t{c}\t{e}" for r, t, c, e in dropped]
    out += [f"TYPED-LOSS\t{r}\t{c}\t{e}" for r, c, e in typed_loss]
    out.append(f"SUMMARY\tfiles={len(paths)}\tadded={len(added)}\tdropped={len(dropped)}"
               f"\ttyped_loss={len(typed_loss)}")
    print("\n".join(out))

    if args.report:
        def esc(s: str) -> str:
            return s.replace("|", "\\|")
        rows = ["| plan | token | type cell | row |", "|---|---|---|---|"]
        rows += [f"| {esc(r)} | `{esc(t)}` | {esc(c)} | {esc(e)} |" for r, t, c, e in dropped]
        with open(args.report, "w", encoding="utf-8") as fh:
            fh.write("\n".join(out[-1:] + [""] + rows) + "\n")
    return 1 if added or typed_loss else 0


if __name__ == "__main__":
    sys.exit(main())
