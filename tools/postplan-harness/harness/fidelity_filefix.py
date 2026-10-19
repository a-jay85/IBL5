"""Phase 5.5 remediation carve-out for narrowly scoped `.claude/rules/*.md` fixes.

A fidelity verdict can block on the content of a rules file (PR #2943: a restored
sentence in `.claude/rules/visual-review-prs.md`). `fidelity.remediate` denies every
commit under `.claude/rules/`, so such a finding could never be fixed. This module is
the second, separate carve-out beside ADR-0184's `rules_budget_carveout`: the reviewer's
`[BLOCKING]` bullet names the file, git confirms it is a small modification of a file
the PR already changes, and the three `bin/check-*` post-checks judge the result. The
fixer's own claims never enter the verdict.

It imports nothing from `fidelity.py`: fidelity imports this module, so the dependency
points one way. `snapshot` runs before the fixer spawns, `prompt_text` goes into the
fixer prompt, and `permit` is consulted at the `denied_gate_edits` site.
"""
from __future__ import annotations

import posixpath
import re
import subprocess
from dataclasses import dataclass

from .rules_budget_carveout import RULES_PREFIX, _git, post_check_failures

MAX_LINES_PER_FILE = 20     # numstat added + deleted, per eligible file
MAX_DELETED_PER_FILE = 8    # a fix restores or corrects a sentence; it never guts a section
MAX_LINES_TOTAL = 40        # across every eligible file in the commit
MAX_FILES = 3
# Same bullet shape as fidelity._FINDING_MARKER_RE, BLOCKING only: widening a commit
# permission must fail closed, so an unmarked bullet earns nothing here.
_BLOCKING_RE = re.compile(r"^\s*(?:[-*]|\d+[.)])\s+\[BLOCKING\](?=\s|$)")
_CODE_SPAN_RE = re.compile(r"`([^`\n]+)`")
_LAST_VERIFIED_RE = re.compile(r"^last_verified:")


@dataclass(frozen=True)
class FileFix:
    active: bool
    reason: str                 # "active" or why not
    eligible: frozenset[str]    # .claude/rules/*.md paths the fixer may commit


INACTIVE = FileFix(False, "not evaluated", frozenset())


def _is_rules_md(path: str) -> bool:
    """One directory level only: a future subdirectory does not get the carve-out for free."""
    return (path.startswith(RULES_PREFIX) and path.endswith(".md")
            and "/" not in path[len(RULES_PREFIX):])


def named_paths(finding_text: str, pr_files) -> set[str]:
    """PR-diff files a finding names in code spans: a full path, or a unique basename.

    Bare prose such as `visual-review-prs.md step 3` is ignored, and a basename shared
    by two or more PR files is ambiguous and adds nothing.
    """
    pr_files = list(pr_files)
    out: set[str] = set()
    for token in _CODE_SPAN_RE.findall(finding_text):
        token = token.strip()
        if token in pr_files:
            out.add(token)
        elif token and "/" not in token:
            matches = [p for p in pr_files if posixpath.basename(p) == token]
            if len(matches) == 1:
                out.add(matches[0])
    return out


def eligible_paths(findings, pr_files) -> frozenset[str]:
    """Rules files named by an explicit `[BLOCKING]` finding that the PR already changes."""
    found: set[str] = set()
    for text in findings:
        first = text.splitlines()[0] if text else ""
        if _BLOCKING_RE.match(first):
            found |= named_paths(text, pr_files)
    return frozenset(p for p in found if _is_rules_md(p))


def snapshot(findings, git, worktree) -> FileFix:
    """Decide on the pre-fix tree whether the carve-out applies to this round."""
    if worktree is None:
        return FileFix(False, "no worktree", frozenset())
    findings = list(findings)
    if not any(_BLOCKING_RE.match(t.splitlines()[0] if t else "") for t in findings):
        return FileFix(False, "no [BLOCKING] finding", frozenset())
    try:
        pr_files = list(git.changed_files("origin/master"))
    except Exception as exc:  # noqa: BLE001 - an unreadable PR diff leaves it inactive
        return FileFix(False, f"pr diff read failed: {exc}", frozenset())
    eligible = eligible_paths(findings, pr_files)
    if not eligible:
        return FileFix(False, "no eligible rules file named", frozenset())
    return FileFix(True, "active", eligible)


def _frontmatter_sans_last_verified(text: str) -> list[str]:
    """Frontmatter lines minus `last_verified:`; no frontmatter gives `[]`.

    `description` and `paths:` (residency, per `.claude/rules/doc-freshness.md`) stay in
    the comparison, so a fixer cannot flip a rule between always-loaded and lazy.
    """
    lines = text.splitlines()
    if not lines or lines[0].rstrip() != "---":
        return []
    block: list[str] = []
    for line in lines[1:]:
        if line.rstrip() == "---":
            break
        if not _LAST_VERIFIED_RE.match(line):
            block.append(line)
    return block


def file_verdicts(gate_hits, filefix, worktree, sha) -> list[tuple[str, str]]:
    """`(path, reason)` for every gate hit the carve-out still denies; empty means all allowed."""
    status: dict[str, str] = {}
    out = _git(worktree, "diff", "--name-status", "--no-renames", f"{sha}^", sha)
    for line in out.splitlines():
        parts = line.split("\t", 1)
        if len(parts) == 2:
            status[parts[1]] = parts[0].strip()
    stats: dict[str, tuple[str, str]] = {}
    out = _git(worktree, "diff", "--numstat", "--no-renames", f"{sha}^", sha)
    for line in out.splitlines():
        parts = line.split("\t", 2)
        if len(parts) == 3:
            stats[parts[2]] = (parts[0], parts[1])
    denied: list[tuple[str, str]] = []
    total = 0
    for hit in gate_hits:
        if hit not in filefix.eligible:
            denied.append((hit, "not named by a [BLOCKING] finding or outside "
                                ".claude/rules/*.md"))
            continue
        st = status.get(hit)
        if st is None:
            denied.append((hit, "not present in the fix commit"))
            continue
        if st != "M":
            denied.append((hit, f"only a modification is allowed ({st})"))
            continue
        added_s, deleted_s = stats.get(hit, ("-", "-"))
        if added_s == "-" or deleted_s == "-":
            denied.append((hit, "binary change"))
            continue
        added, deleted = int(added_s), int(deleted_s)
        if deleted > MAX_DELETED_PER_FILE:
            denied.append((hit, f"deleted {deleted} lines (cap {MAX_DELETED_PER_FILE})"))
            continue
        if added + deleted > MAX_LINES_PER_FILE:
            denied.append((hit, f"changed {added + deleted} lines "
                                f"(cap {MAX_LINES_PER_FILE})"))
            continue
        old = _frontmatter_sans_last_verified(_git(worktree, "show", f"{sha}^:{hit}"))
        new = _frontmatter_sans_last_verified(_git(worktree, "show", f"{sha}:{hit}"))
        if old != new:
            denied.append((hit, "frontmatter changed beyond last_verified"))
            continue
        total += added + deleted
    if len(gate_hits) > MAX_FILES:
        denied.append(("*", f"{len(gate_hits)} gate-owning files (cap {MAX_FILES})"))
    if total > MAX_LINES_TOTAL:
        denied.append(("*", f"total {total} lines (cap {MAX_LINES_TOTAL})"))
    return denied


def permit(gate_hits, filefix, worktree, sha, log, *, run=subprocess.run) -> bool:
    """True only when every gate hit is a small legal edit and all post-checks pass."""
    if not filefix.active:
        log(f"phase5.5 remediation: file-fix carve-out inactive ({filefix.reason})")
        return False
    try:
        denied = file_verdicts(gate_hits, filefix, worktree, sha)
    except Exception as exc:  # noqa: BLE001 - fail closed on any git error
        log(f"phase5.5 remediation: file-fix carve-out error: {exc}")
        return False
    if denied:
        for path, reason in denied:
            log(f"phase5.5 remediation: file-fix carve-out refused {path}: {reason}")
        return False
    failures = post_check_failures(worktree, run=run)
    if failures:
        for entry in failures:
            log(f"phase5.5 remediation: file-fix carve-out post-check failed: {entry}")
        return False
    log(f"phase5.5 remediation: file-fix carve-out allowed: {sorted(gate_hits)}")
    return True


def prompt_text(eligible) -> str:
    """Paragraph `remediate` appends after the gate-edit deny when the snapshot is active."""
    files = ", ".join(sorted(eligible))
    return (
        "One file-fix exception to the gate-owning deny above applies to this round. "
        "A `[BLOCKING]` finding names these rules files, which this PR already "
        f"changes: `{files}`. You MAY edit exactly those files to fix that finding, "
        "and bump each one's `last_verified` to today's `date +%F`. The harness checks "
        "the commit with git: each of those files must be a modification of at most "
        f"{MAX_LINES_PER_FILE} changed lines (at most {MAX_DELETED_PER_FILE} deletions) "
        "with no frontmatter change other than `last_verified`; any other "
        "`.claude/rules/` file, any change under `bin/check-*`, `.github/workflows/**` "
        f"or `harness/armable.py`, more than {MAX_FILES} such files, or more than "
        f"{MAX_LINES_TOTAL} lines in total discards the whole commit and ends "
        "remediation. After the commit the harness reruns "
        "`bin/check-rules-byte-budget`, `bin/check-prose --since=origin/master` and "
        "`bin/check-docs --since=origin/master --no-staleness`; a failure discards the "
        "commit. Never edit a `description:` or `paths:` line.\n"
    )
