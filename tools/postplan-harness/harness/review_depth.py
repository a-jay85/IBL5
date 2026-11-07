"""Phase 4 review depth from the PR body's `**Merge danger:**` line. Pure functions only.

Deepen only: `standard` is today's review, byte-for-byte. Anything the parser cannot vouch
for (no body, no line, a malformed line, two lines) selects `deep`. The shell twin is
`bin/lib/review-depth.sh`; both read tests/fixtures/review_depth_cases.json.
"""
from __future__ import annotations

from harness.merge_danger import MalformedMergeDanger, parse_merge_danger

STANDARD = "standard"
DEEP = "deep"
DEPTHS = (STANDARD, DEEP)                       # index = rank
_STANDARD_BLAST = ("localized", "module")


def classify_depth(body: str | None) -> tuple[str, str]:
    """(depth, reason). reason is one of: UNREADABLE, MISSING, MALFORMED, self-governing,
    one-way, blast=<value>, two-way/<blast>.

    A `Self-governing:` marker anywhere on the Merge danger line reviews deep, even when the
    segment is misplaced or carries a bad value: a mangled marker over-reviews, never under."""
    if body is None:
        return DEEP, "UNREADABLE"
    md_line_text = next((ln for ln in body.splitlines() if ln.startswith("**Merge danger:**")), "")
    if "Self-governing:" in md_line_text:
        return DEEP, "self-governing"
    try:
        md = parse_merge_danger(body)
    except MalformedMergeDanger:
        return DEEP, "MALFORMED"
    if md is None:
        return DEEP, "MISSING"
    if md.door != "two-way":
        return DEEP, "one-way"
    if md.blast not in _STANDARD_BLAST:
        return DEEP, f"blast={md.blast}"
    return STANDARD, f"two-way/{md.blast}"


def depth_for_body(body: str | None) -> str:
    return classify_depth(body)[0]


def max_depth(*depths: str) -> str:
    """The deeper of the inputs. Any value that is not exactly `standard` counts as deep,
    so an unknown or empty value fails safe. No inputs means deep."""
    if depths and all(d == STANDARD for d in depths):
        return STANDARD
    return DEEP
