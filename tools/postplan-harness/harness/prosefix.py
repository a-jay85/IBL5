"""Phase 2 pre-push prose-fix pass for `check-prose-since`.

Called only from `run_meta_checks_local` in `runner.py`, after the doc-staleness
retry and before the hold flag is written. When the sole failing meta-check is
`check-prose-since`, a bounded model pass rewrites the flagged lines, and the real
gate re-run decides whether the hold clears.
"""
from __future__ import annotations

import re
import subprocess

from harness.fidelity import denied_gate_edits

_HIT_RE = re.compile(r"^(?P<path>[^\s:]+\.md):(?P<line>\d+): \[(?P<tell>[a-z0-9-]+)\] ")
_HUNK_RE = re.compile(r"^@@ -(\d+)(?:,(\d+))? \+\d+(?:,\d+)? @@", re.M)


def parse_hits(output: str) -> dict[str, set[int]]:
    """Flagged `{path: {line, ...}}` from the gate's own `path:LINE: [tell]` hit lines."""
    hits: dict[str, set[int]] = {}
    for line in (output or "").splitlines():
        m = _HIT_RE.match(line)
        if m:
            hits.setdefault(m.group("path"), set()).add(int(m.group("line")))
    return hits


def gate_owned(flagged: dict[str, set[int]]) -> list[str]:
    """Flagged paths under a gate-owning prefix; a model may never edit these."""
    return sorted(denied_gate_edits(list(flagged)))


def _git_out(repo: str, *args: str) -> str:
    return subprocess.run(["git", "-C", repo, *args], capture_output=True, text=True,
                          check=True).stdout


def scope_violations(repo: str, flagged: dict[str, set[int]]) -> list[str]:
    """Edits in the working tree (vs HEAD) that stray outside the flagged lines."""
    changed = {p for p in _git_out(repo, "diff", "--name-only", "--no-renames",
                                   "HEAD").splitlines() if p}
    changed |= {p for p in _git_out(repo, "ls-files", "--others",
                                    "--exclude-standard").splitlines() if p}
    violations: list[str] = []
    for path in sorted(changed):
        if path not in flagged:
            violations.append(f"{path}: file outside flagged set")
            continue
        diff = _git_out(repo, "diff", "-U0", "--no-renames", "HEAD", "--", path)
        for m in _HUNK_RE.finditer(diff):
            start = int(m.group(1))
            count = 1 if m.group(2) is None else int(m.group(2))
            for n in range(start, start + count):
                if n not in flagged[path]:
                    violations.append(f"{path}:{n}: edit outside flagged lines")
    return violations
