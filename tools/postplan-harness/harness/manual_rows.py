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
# A `vr:` capture cell (or its `no-vr:` escape) must reach the PR body verbatim —
# it is the only channel CI has to the plan, which lives outside the repo (ADR-0126).
# Exempt it from the noise filters explicitly; today it survives only by luck.
VR_RE = re.compile(r"\bno-vr:|\bvr:", re.I)
NUM_RE = re.compile(r"^\d+$")
# A `post-merge: <reason>` location cell (bin/check-plan gate [PM]). Tagged rows
# render under ## After merge, outside the Phase 6.5 condition (1) window.
POST_MERGE_RE = re.compile(r"(?:^|[^a-z-])post-merge:\s*(?P<reason>.*)$", re.I | re.S)


@dataclass
class ManualRow:
    number: str
    text: str
    raw: str
    post_merge_reason: str = ""


def row_from_cells(cells: list[str], ordinal: int) -> ManualRow:
    raw = " | ".join(cells)
    number = cells[0] if cells and NUM_RE.match(cells[0].strip()) else str(ordinal)
    kept = [
        c for c in cells[1:]
        if VR_RE.search(c) or (
            not CLASS_RE.search(c)
            and not TIMING_RE.match(c.strip())
            and c.strip().lower() not in NOISE
        )
    ]
    text = " — ".join(kept) if kept else raw
    reason = next(
        (m.group("reason").strip() for c in cells[1:] if (m := POST_MERGE_RE.search(c))), ""
    )
    return ManualRow(number=number, text=text, raw=raw, post_merge_reason=reason)


def render_rows(rows: list[ManualRow]) -> str:
    result = "\n".join(f"- [ ] **Row {r.number}** — {r.text}" for r in rows)
    _assert_no_sentinel(result)
    return result


def render_after_merge(rows: list[ManualRow]) -> str:
    """Plain bullets for the ## After merge section; no checkbox brackets."""
    result = "\n".join(f"- **Row {r.number}** — {r.text}" for r in rows)
    _assert_no_sentinel(result)
    return result


def _assert_no_sentinel(text: str) -> None:
    for line in text.splitlines():
        if SENTINEL_RE.match(line):
            raise ValueError("normalizer emitted manual-testing sentinel")


def main(argv: list[str] | None = None) -> int:
    from .planfile import parse_matrix  # local import keeps module-level cycle-free

    args = list(argv if argv is not None else sys.argv[1:])
    after = "--after-merge" in args
    args = [a for a in args if a != "--after-merge"]
    unknown = [a for a in args if a.startswith("--")]
    if unknown:
        print(f"unknown flag: {unknown[0]}", file=sys.stderr)
        return 2
    if args:
        try:
            with open(args[0]) as fh:
                content = fh.read()
        except OSError as e:
            print(str(e), file=sys.stderr)
            return 1
    else:
        content = sys.stdin.read()

    _, manual_raw, post_merge_raw = parse_matrix(content)
    rows = list(post_merge_raw if after else manual_raw)
    if not rows:
        return 0
    try:
        rendered = render_after_merge(rows) if after else render_rows(rows)
    except ValueError as e:
        print(str(e), file=sys.stderr)
        return 3
    print(rendered)
    return 0


if __name__ == "__main__":
    sys.exit(main())
