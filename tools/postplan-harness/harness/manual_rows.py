"""Shared normalizer: render Verification Matrix rows as PR-body checkboxes.

Single algorithm called two ways:
  - harness calls render_rows() in-process
  - prose /post-plan skill calls bin/normalize-manual-testing (exec wrapper)
"""
from __future__ import annotations

import re
import sys
from dataclasses import dataclass

from .armable import SENTINEL_RE

NOISE = {"", "-", "--", "—", "n/a", "na", "manual", "none", "human"}
CLASS_RE = re.compile(r"truly.?manual", re.I)
TIMING_RE = re.compile(r"^(pre|post).?impl\b", re.I)
NUM_RE = re.compile(r"^\d+$")


@dataclass
class ManualRow:
    number: str
    text: str
    raw: str


def row_from_cells(cells: list[str], ordinal: int) -> ManualRow:
    raw = " | ".join(cells)
    number = cells[0] if cells and NUM_RE.match(cells[0].strip()) else str(ordinal)
    kept = [
        c for c in cells[1:]
        if not CLASS_RE.search(c)
        and not TIMING_RE.match(c.strip())
        and c.strip().lower() not in NOISE
    ]
    text = " — ".join(kept) if kept else raw
    return ManualRow(number=number, text=text, raw=raw)


def render_rows(rows: list[ManualRow]) -> str:
    result = "\n".join(f"- [ ] **Row {r.number}** — {r.text}" for r in rows)
    _assert_no_sentinel(result)
    return result


def _assert_no_sentinel(text: str) -> None:
    for line in text.splitlines():
        if SENTINEL_RE.match(line):
            raise ValueError("normalizer emitted manual-testing sentinel")


def main(argv: list[str] | None = None) -> int:
    from .planfile import parse_matrix  # local import keeps module-level cycle-free

    args = argv if argv is not None else sys.argv[1:]
    if args:
        try:
            with open(args[0]) as fh:
                content = fh.read()
        except OSError as e:
            print(str(e), file=sys.stderr)
            return 1
    else:
        content = sys.stdin.read()

    _, manual_raw = parse_matrix(content)
    rows = list(manual_raw)
    if not rows:
        return 0
    try:
        rendered = render_rows(rows)
    except ValueError as e:
        print(str(e), file=sys.stderr)
        return 3
    print(rendered)
    return 0


if __name__ == "__main__":
    sys.exit(main())
