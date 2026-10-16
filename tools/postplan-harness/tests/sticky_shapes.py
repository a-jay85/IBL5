"""Inputs and renderer for the PR #3267 sticky-verdict shape, shared by the sticky tests.

A plain module (no `test_` prefix, so pytest does not collect it). It assembles inputs
only; every rendering decision stays in `harness.fidelity`.
"""
from __future__ import annotations

import json
import os
import types

from harness import fidelity

TREE = "c" * 40
TREE_2 = "d" * 40
REMEDIATION_SHA = "009d537a1b2c"
DIGEST = ["**What changed:** a thing", "**Why:** a reason", "**Watch:** a page",
          "**Touches:** a file", "**Machine-authored fixes:** none"]
BLOCKING_TITLE = "Sticky banner and footer disagree"
NOTE_TITLES = ("Note one", "Note two")


def write_verdict_file(tmp_dir, verdict, blocking, notes, name="verdict.md") -> str:
    """Write a verdict file in the shape `test_fidelity_verdict_findings.py` builds."""
    lines = ["REVIEW-COVERAGE: NONE", "", verdict, "", "## FINDINGS", ""]
    lines.extend(f"- [BLOCKING] {t}" for t in blocking)
    lines.extend(f"- [NOTE] {t}" for t in notes)
    lines.extend(["", "## DIGEST", "stuff", ""])
    path = os.path.join(str(tmp_dir), name)
    with open(path, "w") as fh:
        fh.write("\n".join(lines))
    return path


def pr3267_inputs(tmp_dir, v1="NOT READY", v2="READY WITH NOTES") -> dict:
    """Keyword inputs for `terminal_line` and `compose_sticky` that reproduce PR #3267.

    `v2=None` models a run with no remediation round (verdict 1 only).
    """
    path_1 = write_verdict_file(
        tmp_dir, v1, [BLOCKING_TITLE] if v1 == "NOT READY" else [], NOTE_TITLES,
        name="verdict-1.md")
    fid = {
        "verdict_1": v1, "error_kind": None, "reviewed_tree": TREE,
        "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None,
        "rounds": [], "rounds_completed": 0, "findings_round": 0,
        "backlog_issue_numbers": [1492], "diff_id": "a" * 40, "plan_hash": "b" * 64,
        "verdict_path": path_1,
    }
    if v2 is not None:
        path_2 = write_verdict_file(
            tmp_dir, v2, [BLOCKING_TITLE] if v2 == "NOT READY" else [], NOTE_TITLES,
            name="verdict-2.md")
        fid.update({
            "remediation_sha": REMEDIATION_SHA, "verdict_2": v2,
            "reviewed_tree_2": TREE_2, "rounds_completed": 1, "findings_round": 1,
            "verdict_path": path_2,
            "rounds": [{"remediation_sha": REMEDIATION_SHA, "verdict": v2,
                        "reviewed_tree": "tree2aaaa", "verdict_path": path_2,
                        "model": "opus"}],
        })
    decision = types.SimpleNamespace(armed=False, holds=[types.SimpleNamespace(
        number=3, name="plan-matrix", reason="MISSING: tests/example")])
    return {
        "rebase_line": "REBASE=clean (HEAD already contains origin/master)",
        "ci_line": "CI: local verification pass; GitHub checks are watched after this "
                   "comment",
        "fid": fid, "decision": decision, "digest": list(DIGEST),
        "posted_at": "2026-10-16 00:05:51 PDT",
    }


def render_sticky(inputs: dict) -> str:
    """Render the way `runner.py`'s `_post_sticky` closure does."""
    fid = inputs["fid"]
    terminal = fidelity.terminal_line(
        fid.get("verdict_1"), fid.get("error_kind"), fid.get("remediation_sha"),
        fid.get("verdict_2"), fid.get("reviewed_tree_2"), fid.get("rounds_completed", 0))
    return fidelity.compose_sticky(
        inputs["rebase_line"], inputs["ci_line"], fid, inputs["decision"],
        inputs["digest"], fidelity.findings_excerpt(fid["verdict_path"], True), terminal,
        diff_id=fid.get("diff_id", ""), plan_hash=fid.get("plan_hash", ""),
        posted_at=inputs["posted_at"])


def as_comments_json(bodies: list[str], created: list[str]) -> str:
    """Wrap bodies in the `gh pr view --json comments` shape the shell tests read."""
    comments = [{
        "id": f"IC_fixture{i}", "author": {"login": "a-jay85"},
        "authorAssociation": "OWNER", "body": body, "createdAt": created_at,
        "includesCreatedEdit": False, "isMinimized": False, "minimizedReason": "",
        "reactionGroups": [],
        "url": f"https://github.com/a-jay85/IBL5/pull/3267#issuecomment-{i}",
        "viewerDidAuthor": True,
    } for i, (body, created_at) in enumerate(zip(bodies, created), start=1)]
    return json.dumps({"comments": comments}, separators=(",", ":"))
