"""Diff Truly-manual rows: old whole-row search vs new typed-table whole-cell match.

Usage:
  python3 manual_rows_corpus_diff.py --harness-root <dir containing harness/> \
      [--plans-dir ~/claude-plans]

"new" is the real planfile.parse_matrix (manual + post_merge rows). "old" restates
the pre-backlog#1490 loop: every unfenced table row whose joined text matches
`truly.?manual`. The old rule lived inside parse_matrix's loop, so no single helper
can be rebound to restore it. Rows compare per plan as multisets of ManualRow.raw.
TYPED-LOSS is a dropped row that sat in a What-to-verify table AND had a whole
normalized Truly-manual cell: a real manual row the change must never lose.
Files under a `_reports/` directory are skipped.
Prints ADDED, DROPPED and TYPED-LOSS blocks plus a SUMMARY line.
Exits 1 when added>0 or typed_loss>0; the opt-in pytest also requires dropped>=1.
Stdlib only.
"""
import argparse
import collections
import glob
import os
import re
import sys

OLD_RE = re.compile(r"truly.?manual", re.I)


def _cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def _excerpt(text: str) -> str:
    return " ".join(text.split())[:160]


def _old_rows(planfile, content: str) -> list[tuple[str, bool]]:
    """(raw, typed) for every row the pre-change whole-row search read as manual."""
    rows: list[tuple[str, bool]] = []
    in_matrix = False
    for line in planfile._strip_fenced(content):
        if not line.strip().startswith("|"):
            in_matrix = False
            continue
        cells = _cells(line)
        if any(planfile._norm_cell(c).lower() == "what to verify" for c in cells):
            in_matrix = True
        raw = " | ".join(cells)
        if OLD_RE.search(raw):
            typed = in_matrix and any(OLD_RE.fullmatch(planfile._norm_cell(c)) for c in cells)
            rows.append((raw, typed))
    return rows


def _new_rows(planfile, content: str) -> list[str]:
    _, manual, post_merge = planfile.parse_matrix(content)
    return [r.raw for r in manual + post_merge]


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    ap.add_argument("--plans-dir", default=os.path.expanduser("~/claude-plans"))
    args = ap.parse_args(argv)
    sys.path.insert(0, os.path.abspath(args.harness_root))
    from harness import planfile  # noqa: E402  (tree chosen by --harness-root)

    paths = [p for p in sorted(glob.glob(os.path.join(args.plans_dir, "**", "*.md"), recursive=True))
             if "_reports" not in os.path.relpath(p, args.plans_dir).split(os.sep)]
    added: list[tuple[str, str]] = []
    dropped: list[tuple[str, str]] = []
    typed_loss: list[tuple[str, str]] = []
    for path in paths:
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        rel = os.path.relpath(path, args.plans_dir)
        old = _old_rows(planfile, content)
        old_c = collections.Counter(raw for raw, _ in old)
        new_c = collections.Counter(_new_rows(planfile, content))
        typed = {raw for raw, is_typed in old if is_typed}
        added += [(rel, raw) for raw in (new_c - old_c).elements()]
        for raw in (old_c - new_c).elements():
            (typed_loss if raw in typed else dropped).append((rel, raw))

    out = [f"ADDED\t{r}\t{_excerpt(x)}" for r, x in added]
    out += [f"DROPPED\t{r}\t{_excerpt(x)}" for r, x in dropped]
    out += [f"TYPED-LOSS\t{r}\t{_excerpt(x)}" for r, x in typed_loss]
    out.append(f"SUMMARY\tfiles={len(paths)}\tadded={len(added)}\tdropped={len(dropped)}"
               f"\ttyped_loss={len(typed_loss)}")
    print("\n".join(out))
    return 1 if added or typed_loss else 0


if __name__ == "__main__":
    sys.exit(main())
