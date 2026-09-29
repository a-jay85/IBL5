"""Phase 5.0 scope conformance: diff-to-plan and plan-to-diff, both directions.

The parse and the matching live in bin/lib/plan-scope-conformance, the same helper
the skill's Phase 5.0 block calls. This module only writes the helper's input files
and reads its output, so the two engines share one parser and one exempt list.
"""
from __future__ import annotations

import os
import re
import subprocess
import tempfile
from pathlib import PurePosixPath

from .state import PlanInfo

_SCOPE_SCRIPT = str(
    PurePosixPath(os.path.abspath(__file__)).parents[3] / "bin" / "lib" / "plan-scope-conformance")
_DIFF_HEADER = re.compile(r"^diff --git a/.+ b/(?P<path>.+)$")
_LABELS = ("UNPLANNED-FILE:", "UNEXPLAINED-GAP:")
_GAP_PATH = re.compile(r"^UNEXPLAINED-GAP: (?P<path>\S+) \(")


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


def scope_items(plan: PlanInfo, changed_files: list[str], diff_body: str, pr_body: str,
                resolve=None, script: str | None = None) -> list[str]:
    """`UNPLANNED-FILE:` / `UNEXPLAINED-GAP:` items from bin/lib/plan-scope-conformance.

    Fail-CLOSED on an unrunnable script (OSError, exit >= 2 or < 0), the same rule as
    `conformance._matrix_assertion_items`: a gate that reports clean when it could not
    run is a silent pass.

    An empty `plan.path` means the plan came from `content_override` (replay fixtures,
    hand-built PlanInfo in tests): there is no file for the script to read, so the
    check does not apply. A live plan always carries its disk path.

    Added paths come from `diff_body`, never from git in the process cwd, because the
    pinned main-checkout harness runs outside the target worktree. An empty diff body
    yields no added paths, so nothing is exempt.

    `resolve`, when given, is the caller's `MISSING-FILE:` resolver. An
    `UNEXPLAINED-GAP:` whose path it resolves (suffix, unique basename, migration
    renumber) is dropped, so both directions share one tolerance. It arrives as an
    argument so this module never imports `conformance`.
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
            proc = subprocess.run(
                [script, plan.path, changed_path, body_path, added_path],
                capture_output=True, text=True, check=False)
        except OSError as e:
            return [f"UNPLANNED-FILE: scope check unavailable ({e.__class__.__name__})"]
    if proc.returncode >= 2 or proc.returncode < 0:
        first = (proc.stderr.strip().splitlines() or ["no stderr"])[0][:120]
        return [f"UNPLANNED-FILE: scope check unavailable (exit {proc.returncode}: {first})"]
    items: list[str] = []
    for ln in proc.stdout.splitlines():
        text = ln.strip()
        if not text.startswith(_LABELS):
            continue
        if resolve is not None:
            m = _GAP_PATH.match(text)
            if m and resolve(m.group("path"), changed_files) is not None:
                continue
        items.append(text)
    return items
