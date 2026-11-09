"""The blind second reviewer: a fresh Opus seat that may clear a clearable arming hold.

armable.second_review_candidates decides whether a PR is eligible. This module runs the
seat and reads its verdict. The seat sees the diff, the stated reason and the hold list.
It never sees the run's review history, so its judgment stays independent of it.

Fail-closed: a spawn error, an oversized diff, or a malformed reply yields no verdict,
and the hold stands. The verdict binds to the tree captured BEFORE the spawn.
"""
from __future__ import annotations

import dataclasses
import os
import re
from typing import Callable

from . import armable, fidelity, planfile

AGENT = "pr-second-reviewer"
ALLOWED_TOOLS = ("Read", "Grep", "Glob")
DENIED_TOOLS = ("Bash", "Write", "Edit", "NotebookEdit", "Agent", "Skill")

# A line matching this is dropped from anything the seat reads outside the diff.
FIXER_FRAMING_RE = re.compile(
    r"addressed|fix(er| round)|remediat|previous(ly)? review|finding|resolved in", re.I)

CONDITION_NAMES = {
    7: "plan-auto-merge-hold",
    10: "pipeline-authored-floor",
    12: "plan-fidelity-verdict",
    17: "gate-backtest",
}

# Only a bare `VERDICT: CLEAN` counts, the same line the shell twin in bin/lib/pr-armable.sh
# demands. REJECT may carry its reason.
_VERDICT_RE = re.compile(r"^VERDICT: (CLEAN|REJECT(?:\s.*)?)$")
_CLEARS_RE = re.compile(r"^CLEARS (7|10|12|17): (.{10,})$")
_TREE_LINE_RE = re.compile(r"^REVIEWED_TREE=")


def _without_framing(text: str) -> str:
    return "\n".join(ln for ln in text.splitlines() if not FIXER_FRAMING_RE.search(ln))


def reason_text(plan_content: str | None, pr_title: str, pr_body: str | None) -> str:
    """The PR title plus the plan's Approach, or the PR body's Summary when plan-blind."""
    if plan_content:
        detail = planfile._section(plan_content, r"Approach")
    else:
        detail = planfile._section(pr_body or "", r"Summary")
    return _without_framing(f"{pr_title}\n\n{detail}".strip())


def build_prompt(diff: str, reason: str, holds: dict[int, str]) -> str | None:
    """Reason, hold list, fenced diff. None when the diff is over the inline budget."""
    if len(diff.encode()) > fidelity.REMEDIATION_DIFF_INLINE_CAP:
        return None
    hold_lines = []
    for number in sorted(holds):
        name = CONDITION_NAMES.get(number, "hold")
        detail = holds[number]
        hold_lines.append(f"- ({number}) {name}" if FIXER_FRAMING_RE.search(detail)
                          else f"- ({number}) {name}: {detail}")
    runs = re.findall(r"`+", diff)
    fence = "`" * max(3, max((len(r) for r in runs), default=0) + 1)
    return (f"## Reason\n\n{reason}\n\n"
            "## Holds\n\n" + "\n".join(hold_lines) + "\n\n"
            f"## Diff\n\n{fence}diff\n{diff}\n{fence}\n")


def verdict_path(pr) -> str:
    return f"/tmp/postplan-second-review-{pr}.md"


def _verdict_lines(path: str) -> list[str] | None:
    try:
        with open(path) as fh:
            text = fh.read()
    except OSError:
        return None
    return [ln for ln in text.splitlines() if ln.strip() and not _TREE_LINE_RE.match(ln)]


def parse_verdict(path: str) -> tuple[str | None, dict[int, str]]:
    """(CLEAN|REJECT|None, {condition number: reviewer reason})."""
    lines = _verdict_lines(path)
    if not lines:
        return None, {}
    m = _VERDICT_RE.match(lines[-1])
    if not m:
        return None, {}
    clears = {}
    for line in lines[:-1]:
        c = _CLEARS_RE.match(line)
        if c:
            clears[int(c.group(1))] = c.group(2)
    return m.group(1).split()[0], clears


def reject_reason(path: str) -> str:
    """The text after `VERDICT: REJECT`, or "" when the file holds no rejection."""
    lines = _verdict_lines(path)
    if not lines:
        return ""
    m = _VERDICT_RE.match(lines[-1])
    if not m or not m.group(1).startswith("REJECT"):
        return ""
    return m.group(1)[len("REJECT"):].strip()


def run_seat(pr, head_tree: Callable[[], str], diff: Callable[[], str], holds: dict[int, str],
             reason: str, spawn: Callable[..., str]) -> tuple[str | None, str | None, dict[int, str]]:
    """Spawn one fresh seat. Returns (word, tree, clears); (None, None, {}) on any failure."""
    path = verdict_path(pr)
    try:
        os.unlink(path)      # a verdict for an older tree must not survive a failed spawn
    except OSError:
        pass
    try:
        tree = head_tree()   # before the spawn: the verdict binds to the tree it reviewed
        diff_text = diff()
        prompt = build_prompt(diff_text, reason, holds) if diff_text and tree else None
        if prompt is None:
            return None, None, {}
        reply = spawn(agent=AGENT, prompt=prompt)
        # record_reviewed_tree keeps an existing line, so a forged one must not reach it.
        kept = [ln for ln in (reply or "").splitlines() if not _TREE_LINE_RE.match(ln)]
        with open(path, "w") as fh:
            fh.write("\n".join(kept) + "\n")
        fidelity.record_reviewed_tree(path, tree)
    except Exception:  # noqa: BLE001 - every failure means no verdict, so the hold stands
        return None, None, {}
    word, clears = parse_verdict(path)
    if word is None:
        return None, None, {}
    return word, tree, clears


def load_fresh(pr, current_tree: str) -> tuple[str, str, dict[int, str]] | None:
    """The verdict already on disk when it reviewed `current_tree`; None otherwise."""
    path = verdict_path(pr)
    if not current_tree or fidelity.read_reviewed_tree(path) != current_tree:
        return None
    word, clears = parse_verdict(path)
    if word is None:
        return None
    return word, current_tree, clears


def digest_clear_lines(decision) -> list[str]:
    """One merge-digest line per hold the seat cleared."""
    return [f"Second reviewer cleared {entry}" for entry in decision.second_review_cleared]


# ---- Arming glue: the runner calls these so the seat's inputs live in an arming path.


def selected_fidelity_path(source, fid, pr):
    """The verdict file behind select_fidelity_verdict's `source` slot."""
    if source == "verdict-2":
        return ((fid.get("same_run_repin") or {}).get("verdict_path")
                or fid.get("verdict_path"))
    return fidelity.verdict_path(pr)


def selected_gate_edit_only(fid, pr, current_tree) -> bool:
    """Condition (12) arm input: fidelity.gate_edit_only on the verdict condition (12) reads."""
    _, source = armable.select_fidelity_verdict(fid.get("verdict_1"), fid.get("verdict_2"),
                                                fid.get("reviewed_tree_2"), current_tree)
    path = selected_fidelity_path(source, fid, pr)
    return bool(path) and fidelity.gate_edit_only(path)


def spawner(llm, worktree):
    """A fresh read-only session for the seat: no resume, no continue, no session id."""
    def spawn(agent, prompt):
        return llm.call_tooled("second-review", "opus", prompt, cwd=worktree, agent=agent,
                               allowed_tools=ALLOWED_TOOLS,
                               denied_tools=DENIED_TOOLS, advisor=False)
    return spawn


def plan_content(plan) -> str | None:
    """The plan's text for the seat's stated reason; None when plan-blind or unreadable."""
    if not getattr(plan, "found", False) or not getattr(plan, "path", ""):
        return None
    try:
        with open(plan.path) as fh:
            return fh.read()
    except OSError:
        return None


def review_pass(inp, pr, head_tree, diff, plan, log, spawn):
    """Fill the seat's verdict into ArmInputs. Zero tokens unless every hold is clearable."""
    cands = armable.evaluate(inp).second_review_candidates
    if not pr or not cands:
        return inp
    fresh = load_fresh(pr, inp.current_tree)
    if fresh is None:
        reason = reason_text(plan_content(plan), inp.pr_title, inp.pr_body)
        word, tree, clears = run_seat(pr, head_tree, diff, cands, reason, spawn)
    else:
        word, tree, clears = fresh
    log(f"second-review: {word or 'absent'} tree={tree} clears={sorted(clears)}")
    current_tree = inp.current_tree
    if tree is not None and fresh is None:
        now = head_tree()
        if now != tree:              # the head moved during the spawn, so the verdict is stale
            current_tree = now
    return dataclasses.replace(
        inp, current_tree=current_tree, second_review_verdict=word, second_review_tree=tree,
        second_review_clears=clears,
        second_review_reject_reason=reject_reason(verdict_path(pr)))
