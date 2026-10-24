"""Pure label decisions for review findings: important / nit / pre-existing.

No I/O and no imports from review.py or the adapters, so any module (including ghad.py)
can import it without a cycle. `classify` is the structural core of the hard invariant:
a finding that cleared the score threshold can never be demoted below `important` by a
reviewer hint, and `pre-existing` needs the reviewer, the scorer, and an unchanged line
to agree.
"""
from __future__ import annotations

import re
from typing import Optional

LABEL_IMPORTANT, LABEL_NIT, LABEL_PREEXISTING = "important", "nit", "pre-existing"
LABELS = (LABEL_IMPORTANT, LABEL_NIT, LABEL_PREEXISTING)
NON_HOLDING_LABELS = (LABEL_NIT, LABEL_PREEXISTING)
NIT_FLOOR = 60        # nit band: NIT_FLOOR <= score < CODE_THRESHOLD, code review only
NIT_CAP = 5           # nits posted per PR, highest score first
SEVERITY_ORDER = {LABEL_IMPORTANT: 0, LABEL_NIT: 1, LABEL_PREEXISTING: 2}
LABEL_MARKER_RE = re.compile(r"\A<!-- label: (important|nit|pre-existing) -->")

_HUNK_RE = re.compile(r"^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@")
_FILE_RE = re.compile(r"^\+\+\+ b/(.*)$")
_LABEL_TITLES = {LABEL_IMPORTANT: "Important", LABEL_NIT: "Nit",
                 LABEL_PREEXISTING: "Pre-existing"}


class _AllLines(set):
    """Right-side lines of a file this PR adds: every line counts as changed."""

    def __contains__(self, item):
        return True


def _walk_right_lines(diff_text, include_context):
    out: dict = {}
    path = None
    right = 0
    from_null = False
    for line in (diff_text or "").splitlines():
        if line.startswith("diff --git"):
            path = None
            from_null = False
            continue
        if line.startswith("--- "):
            from_null = line.startswith("--- /dev/null")
            continue
        m = _FILE_RE.match(line)
        if m:
            path = m.group(1)
            out.setdefault(path, _AllLines() if (from_null and not include_context) else set())
            continue
        m = _HUNK_RE.match(line)
        if m:
            right = int(m.group(1))
            continue
        if line.startswith("\\") or path is None:
            continue
        if line.startswith("+"):
            out[path].add(right)
            right += 1
        elif line.startswith(" "):
            if include_context:
                out[path].add(right)
            right += 1
    return out


def changed_right_lines(diff_text) -> dict:
    """{path: {right-side line numbers of added (+) lines}} per `+++ b/<path>`."""
    return _walk_right_lines(diff_text, include_context=False)


def visible_right_lines(diff_text) -> dict:
    """{path: {right-side line numbers of context AND added lines}}."""
    return _walk_right_lines(diff_text, include_context=True)


def classify(f, threshold, changed) -> Optional[str]:
    """The label for a scored finding, or None when it is dropped."""
    score = f.score or 0
    if score >= threshold:
        if (f.preexisting_hint and f.scorer_preexisting and f.line > 0
                and f.path in changed and f.line not in changed[f.path]):
            return LABEL_PREEXISTING
        return LABEL_IMPORTANT
    if f.source == "code-review" and f.severity_hint == "nit" and score >= NIT_FLOOR:
        return LABEL_NIT
    return None


def apply_nit_cap(findings, cap=NIT_CAP) -> list:
    """Keep every non-nit finding and the top `cap` nits by (score desc, original index)."""
    nits = [(i, f) for i, f in enumerate(findings) if f.label == LABEL_NIT]
    nits.sort(key=lambda p: (-(p[1].score or 0), p[0]))
    keep = {i for i, _ in nits[:cap]}
    return [f for i, f in enumerate(findings) if f.label != LABEL_NIT or i in keep]


def sort_by_severity(findings) -> list:
    """Stable sort by (severity order, score desc, original index)."""
    indexed = list(enumerate(findings))
    indexed.sort(key=lambda p: (SEVERITY_ORDER.get(p[1].label, 0), -(p[1].score or 0), p[0]))
    return [f for _, f in indexed]


def marker(label) -> str:
    return f"<!-- label: {label} -->"


def parse_label(body) -> Optional[str]:
    """The label from the marker on the FIRST line of a posted body, else None."""
    m = LABEL_MARKER_RE.match(body or "")
    return m.group(1) if m else None


def render_body(f, limit=1000) -> str:
    """Marker + score + bold label header, then the body cut to fit `limit` in total."""
    header = (f"{marker(f.label or LABEL_IMPORTANT)}\n<!-- score: {f.score or 0} -->\n"
              f"**{_LABEL_TITLES.get(f.label, 'Important')}**")
    if f.bug_class:
        header += f" · bug class: `{f.bug_class}`"
    if f.other_instances:
        header += " · other instances: " + ", ".join(f"`{o}`" for o in f.other_instances)
    header += "\n\n"
    return header + f.body[:max(0, limit - len(header))]
