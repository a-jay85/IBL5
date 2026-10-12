"""Phase 5.0 scope notes: diff-to-plan and plan-to-diff, both directions.

The harness renders these as advisory PR-body notes and holds on none of them.

The parse and the matching live in bin/lib/plan-scope-conformance, the same helper
the skill's Phase 5.0 block calls. This module only writes the helper's input files
and reads its output, so the two engines share one parser and one exempt list.
"""
from __future__ import annotations

import os
import re
import tempfile
from pathlib import PurePosixPath

from .adapters.llm import run_bounded
from .state import PlanInfo

_SCOPE_SCRIPT = str(
    PurePosixPath(os.path.abspath(__file__)).parents[3] / "bin" / "lib" / "plan-scope-conformance")
SCOPE_CHECK_TIMEOUT = 120   # seconds; a hang fails closed (subprocess-timeout, exit 3)
_DIFF_HEADER = re.compile(r"^diff --git a/.+ b/(?P<path>.+)$")
_NOTE_PREFIX = "SCOPE-NOTE: "
_UNPLANNED_PREFIX = "UNPLANNED-FILE: "


def _added_paths(diff_body: str) -> list[str]:
    """b-paths of every `diff --git` section carrying a `new file mode` line before the next header."""
    added: list[str] = []
    current: str | None = None
    for line in diff_body.splitlines():
        m = _DIFF_HEADER.match(line)
        if m:
            current = m.group("path")
            continue
        if current is not None and line.startswith("new file mode"):
            added.append(current)
            current = None
    return added


def scope_notes(plan: PlanInfo, changed_files: list[str], diff_body: str, pr_body: str,
                script: str | None = None) -> list[str]:
    """Advisory note texts from bin/lib/plan-scope-conformance.

    One note per `UNPLANNED-FILE:` or `SCOPE-NOTE:` line, label stripped. An
    `UNPLANNED-FILE: <path> (...)` line renders as `unplanned <path> (...)`, so every
    note has the same shape as `SCOPE-NOTE: unplanned <path> (...)` and
    `SCOPE-NOTE: gap <path> (...)`. Nothing here holds auto-merge.

    An unrunnable script (OSError, exit >= 2 or < 0) yields one note,
    `scope check unavailable (...)`. The notes are advisory, so there is no
    fail-closed hold.

    An empty `plan.path` means the plan came from `content_override` (replay fixtures,
    hand-built PlanInfo in tests): there is no file for the script to read, so the
    check does not apply. A live plan always carries its disk path.

    Added paths come from `diff_body`, never from git in the process cwd, because the
    pinned main-checkout harness runs outside the target worktree. An empty diff body
    yields no added paths, so nothing is exempt.
    """
    if not plan.path:
        return []
    script = script or _SCOPE_SCRIPT
    with tempfile.TemporaryDirectory(prefix="scope-conf-") as td:
        changed_path = os.path.join(td, "changed.txt")
        added_path = os.path.join(td, "added.txt")
        body_path = os.path.join(td, "pr-body.md")
        with open(changed_path, "w") as fh:
            fh.write("".join(f"{p}\n" for p in changed_files))
        with open(added_path, "w") as fh:
            fh.write("".join(f"{p}\n" for p in _added_paths(diff_body)))
        with open(body_path, "w") as fh:
            fh.write(pr_body or "")
        try:
            proc = run_bounded(
                [script, plan.path, changed_path, body_path, added_path],
                step="scope-check", timeout=SCOPE_CHECK_TIMEOUT)
        except OSError as e:
            return [f"scope check unavailable ({e.__class__.__name__})"]
    if proc.returncode >= 2 or proc.returncode < 0:
        first = (proc.stderr.strip().splitlines() or ["no stderr"])[0][:120]
        return [f"scope check unavailable (exit {proc.returncode}: {first})"]
    notes: list[str] = []
    for ln in proc.stdout.splitlines():
        text = ln.strip()
        if text.startswith(_NOTE_PREFIX):
            notes.append(text[len(_NOTE_PREFIX):])
        elif text.startswith(_UNPLANNED_PREFIX):
            notes.append("unplanned " + text[len(_UNPLANNED_PREFIX):])
    return notes
