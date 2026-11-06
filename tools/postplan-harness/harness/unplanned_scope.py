"""Phase 5.5: decide what happens to one tagged unplanned-scope fidelity finding.

The fidelity reviewer tags every check-3 (Scope creep) finding `[unplanned-scope]`. This
module maps a tagged finding to `keep`, `remove` or `hold`:

- `hold`: a cited path is both flagged by `bin/lib/deleted-guards` and listed in the plan's
  `## Must preserve`.
- `remove`: every commit that touched every cited path carries a subject the harness itself
  writes, so the code is post-plan's own and the fixer may delete it.
- `keep`: everything else, including every ambiguous case. A kept finding never reaches the
  fixer.

A failing test is no input here. It already holds through meta-check hold 16 and Phase 7 CI.

This module imports `fidelity` only inside functions, so loading it stays free of cycles.
"""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
from typing import Iterable, Mapping, Optional, Sequence

from . import removed_guards

UNPLANNED_TAG = "[unplanned-scope]"
VR_BASELINE_SUBJECT = "test: update visual regression baselines [auto]"
KEPT_BEGIN = "<!-- kept-unplanned:begin -->"
KEPT_END = "<!-- kept-unplanned:end -->"
_KEPT_VETO = ("Phase 5.5 kept these changes. No plan phase covers them, and they were not "
              "authored by a post-plan fix commit. Veto any of them before merging.")

_TAGGED_RE = re.compile(
    r"^\s*(?:[-*]|\d+[.)])\s+\[(?:BLOCKING|NOTE)\]\s+\[unplanned-scope\](?=\s|$)"
)
_MP_HEADING_RE = re.compile(r"^## Must preserve\s*$")
_INTENDED_HEADING_RE = re.compile(r"^### Intended removals\s*$")
_BACKTICK_RE = re.compile(r"`([^`]+)`")
# re.escape turns a placeholder like {n} into \{n\}. A subject constant's \{name\} span
# becomes a wildcard below, so its formatted commit subject still matches.
_ESCAPED_PLACEHOLDER_RE = re.compile(r"\\\{\w+\\\}")


def is_unplanned_scope(item_text: str) -> bool:
    """True only when the item's first line carries the tag right after its severity marker."""
    lines = item_text.splitlines()
    return bool(lines) and _TAGGED_RE.match(lines[0]) is not None


def machine_subject_patterns() -> list[re.Pattern[str]]:
    """One compiled pattern per subject the harness writes, built from its own constants."""
    from . import adr_draft, cifix, conformance_fix, fidelity, prosefix

    subjects = (
        fidelity.REMEDIATION_COMMIT_MSG,
        cifix.CI_FIX_COMMIT_MSG,
        conformance_fix.COMMIT_MSG,
        prosefix.PROSE_FIX_COMMIT_MSG,
        adr_draft.ADR_COMMIT_MSG,
        VR_BASELINE_SUBJECT,
    )
    return [re.compile(_ESCAPED_PLACEHOLDER_RE.sub(".+", re.escape(s))) for s in subjects]


def is_machine_commit(subject: str) -> bool:
    return any(p.fullmatch(subject) for p in machine_subject_patterns())


def cited_paths(item_text: str, changed_paths: Iterable[str]) -> list[str]:
    """The changed paths whose literal path string occurs in the finding text."""
    return [path for path in changed_paths
            if path and re.search(rf"(?<![\w./-]){re.escape(path)}(?![\w/-])", item_text)]


def must_preserve_paths(plan_text: str) -> set[str]:
    """First backticked token of each `- ` bullet under `## Must preserve`.

    Stops at the next `## ` heading and skips the `### Intended removals` subsection.
    """
    paths: set[str] = set()
    inside = skipping = False
    for line in plan_text.splitlines():
        if not inside:
            inside = _MP_HEADING_RE.match(line) is not None
            continue
        if line.startswith("## "):
            break
        if line.startswith("### "):
            skipping = _INTENDED_HEADING_RE.match(line) is not None
            continue
        if skipping or not line.startswith("- "):
            continue
        token = _BACKTICK_RE.search(line)
        if token:
            paths.add(token.group(1))
    return paths


def classify(
    item_text: str,
    *,
    changed_paths: Iterable[str],
    subjects_by_path: Mapping[str, Sequence[str]],
    must_preserve: Iterable[str],
    removed_guard_paths: Iterable[str],
) -> Optional[str]:
    """`keep`, `remove` or `hold` for a tagged finding, None for an untagged one."""
    if not is_unplanned_scope(item_text):
        return None
    paths = cited_paths(item_text, changed_paths)
    preserved, removed = set(must_preserve), set(removed_guard_paths)
    if any(path in removed and path in preserved for path in paths):
        return "hold"
    if paths and all(_only_machine_commits(subjects_by_path.get(path, ())) for path in paths):
        return "remove"
    return "keep"


def _only_machine_commits(subjects: Sequence[str]) -> bool:
    return bool(subjects) and all(is_machine_commit(subject) for subject in subjects)


def commit_subjects_for(worktree: str, base_sha: str,
                        paths: Iterable[str]) -> dict[str, list[str]]:
    """Commit subjects on `base_sha..HEAD` per path. A failed lookup maps that path to []."""
    return {path: _subjects_for(worktree, base_sha, path) for path in paths}


def _subjects_for(worktree: str, base_sha: str, path: str) -> list[str]:
    cmd = ["git", "-C", worktree, "log", "--follow", "--no-merges", "--format=%s",
           f"{base_sha}..HEAD", "--", path]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    except (OSError, ValueError, subprocess.SubprocessError):
        return []
    if proc.returncode != 0:
        return []
    return proc.stdout.splitlines()


def context_for(worktree: str, base_sha: str, plan_text: str, changed_paths: Iterable[str],
                removed_guard_rows: Iterable[Sequence[str]]) -> dict:
    """The keyword inputs `classify` takes. A removed-guard row's path is its second field."""
    changed = list(changed_paths)
    return {
        "changed_paths": changed,
        "subjects_by_path": commit_subjects_for(worktree, base_sha, changed),
        "must_preserve": must_preserve_paths(plan_text),
        "removed_guard_paths": {row[1] for row in removed_guard_rows},
    }


def partition(items: Iterable[dict], ctx: Optional[dict]) -> tuple[list, list, list]:
    """(work, kept, held). Only a tagged hold-12 item can leave `work`, and only with a ctx."""
    work: list = []
    kept: list = []
    held: list = []
    for item in items:
        branch = None
        if ctx is not None and item["hold"] == "12":
            branch = classify(item["text"], **ctx)
        {"keep": kept, "hold": held}.get(branch, work).append(item)
    return work, kept, held


def title_of(item_text: str) -> str:
    """The first line minus bullet, severity marker and tag, cut before `. Next action`."""
    lines = item_text.splitlines()
    first = lines[0] if lines else ""
    tagged = _TAGGED_RE.match(first)
    title = first[tagged.end():] if tagged else first
    return title.split(". Next action", 1)[0].strip()


def render_kept_block(kept: Sequence[dict], ctx: Optional[dict]) -> str:
    """The PR-body declaration, or "" when nothing was kept."""
    if not kept:
        return ""
    changed = (ctx or {}).get("changed_paths", ())
    lines = [KEPT_BEGIN, "## Kept unplanned changes", _KEPT_VETO]
    for item in kept:
        paths = cited_paths(item["text"], changed)
        cited = ", ".join(f"`{path}`" for path in paths) or "no path cited"
        lines.append(f"- {title_of(item['text'])} ({cited})")
    lines.append(KEPT_END)
    return "\n".join(lines)


def upsert_kept_block(body: str, block: str) -> str:
    return removed_guards.upsert_marked_block(body, block, KEPT_BEGIN, KEPT_END)


def downgrade_applies(verdict_path: str, ctx: Optional[dict], body_text: str) -> bool:
    """True when a NOT READY verdict has only kept blockers and the PR body declares each one."""
    from . import fidelity

    body = body_text or ""
    begin, end = body.find(KEPT_BEGIN), body.find(KEPT_END)
    if ctx is None or begin == -1 or end < begin:
        return False
    declared = body[begin:end]
    if fidelity.parse_verdict(verdict_path) != "NOT READY":
        return False
    texts = fidelity.blocking_finding_texts(verdict_path)
    if not texts or len(fidelity.verdict_items(verdict_path)["blocking"]) != len(texts):
        return False   # an unmarked bullet also blocks, and it can never be classified
    return all(classify(text, **ctx) == "keep" and title_of(text) in declared for text in texts)


class _ReadFailed(Exception):
    """A git call or the removed-guards detector could not answer."""


def _git_output(worktree: str, *args: str) -> str:
    cmd = ["git", "-C", worktree, *args]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    except (OSError, ValueError, subprocess.SubprocessError) as e:
        raise _ReadFailed(f"git {args[0]}: {e}") from e
    if proc.returncode != 0:
        raise _ReadFailed(f"git {args[0]}: {proc.stderr.strip()}")
    return proc.stdout


def _cli_context(args: argparse.Namespace, plan_text: str) -> dict:
    span = f"{args.base}...HEAD"
    changed = _git_output(args.worktree, "diff", "--name-only", span).splitlines()
    diff_text = _git_output(args.worktree, "diff", span)
    ok, rows = removed_guards.run_detector(args.worktree, diff_text, args.plan)
    if not ok:
        raise _ReadFailed("bin/lib/deleted-guards failed")
    return context_for(args.worktree, args.base, plan_text, changed, rows)


def main(argv: Optional[Sequence[str]] = None) -> int:
    """Skill-engine entry point. 0 ok, 2 usage or unreadable file, 3 git or detector failure."""
    from . import fidelity

    parser = argparse.ArgumentParser(prog="harness.unplanned_scope")
    parser.add_argument("--verdict", required=True)
    parser.add_argument("--plan", required=True)
    parser.add_argument("--base", required=True)
    parser.add_argument("--worktree", required=True)
    parser.add_argument("--render-block", action="store_true")
    args = parser.parse_args(argv)

    try:
        with open(args.plan, encoding="utf-8") as fh:
            plan_text = fh.read()
        with open(args.verdict, encoding="utf-8"):
            pass
    except (OSError, ValueError) as e:
        print(f"unplanned_scope: cannot read input: {e}", file=sys.stderr)
        return 2
    try:
        ctx = _cli_context(args, plan_text)
    except _ReadFailed as e:
        print(f"unplanned_scope: {e}", file=sys.stderr)
        return 3

    texts = (fidelity.marked_finding_texts(args.verdict, "BLOCKING")
             + fidelity.marked_finding_texts(args.verdict, "NOTE"))
    tagged = [text for text in texts if is_unplanned_scope(text)]
    if args.render_block:
        kept = [{"hold": "12", "text": t} for t in tagged if classify(t, **ctx) == "keep"]
        block = render_kept_block(kept, ctx)
        if block:
            print(block)
        return 0
    for text in tagged:
        paths = ",".join(cited_paths(text, ctx["changed_paths"]))
        print(f"{classify(text, **ctx).upper()}\t{title_of(text)}\t{paths}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
