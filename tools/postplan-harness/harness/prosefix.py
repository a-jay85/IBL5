"""Phase 2 pre-push prose-fix pass for `check-prose-since`.

Called only from `run_meta_checks_local` in `runner.py`, after the doc-staleness
retry and before the hold flag is written. When the sole failing meta-check is
`check-prose-since`, a bounded model pass rewrites the flagged lines, and the real
gate re-run decides whether the hold clears.
"""
from __future__ import annotations

import re
import subprocess

from harness.fidelity import REMEDIATION_DENIED_TOOLS, denied_gate_edits
from harness.state import HarnessError

PROSE_CHECK = "check-prose-since"
PROSE_FIX_PURPOSE = "prose-fix"
PROSE_FIX_MODELS = ("sonnet", "opus")
PROSE_FIX_COMMIT_MSG = "chore: rewrite flagged prose tells"
PROSE_FIX_ALLOWED_TOOLS = ["Read", "Grep", "Glob", "Edit"]
PROSE_FIX_DENIED_TOOLS = list(dict.fromkeys(
    [*REMEDIATION_DENIED_TOOLS, "Bash", "Write", "Agent", "NotebookEdit"]))

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


def failed_check_names(output: str) -> list[str]:
    """Names from the `META-CHECK-FAILED:` marker lines (the summary line is excluded)."""
    return [line.split("META-CHECK-FAILED:", 1)[1].strip()
            for line in (output or "").splitlines()
            if line.startswith("META-CHECK-FAILED:")]


def evasion_violations(repo: str, flagged: dict[str, set[int]]) -> list[str]:
    """Edits that hide a tell in a scanner skip zone instead of rewriting it.

    `bin/check-prose` skips `<!-- slop-ok -->` lines, code spans, HTML comments, and
    blockquotes, so more of those tokens on the added side than the removed side is
    an escape hatch, not a rewrite.
    """
    violations: list[str] = []
    for path in sorted(flagged):
        diff = _git_out(repo, "diff", "-U0", "--no-renames", "HEAD", "--", path)
        added = [l[1:] for l in diff.splitlines() if l.startswith("+") and not l.startswith("+++")]
        removed = [l[1:] for l in diff.splitlines() if l.startswith("-") and not l.startswith("---")]
        for token in ("`", "<!--", "slop-ok"):
            if sum(l.count(token) for l in added) > sum(l.count(token) for l in removed):
                violations.append(f"{path}: added {token!r} (scanner skip zone)")
        quote = re.compile(r"^\s*>")
        if sum(bool(quote.match(l)) for l in added) > sum(bool(quote.match(l)) for l in removed):
            violations.append(f"{path}: added blockquote (scanner skip zone)")
    return violations


def _reset(repo: str, sha: str) -> None:
    subprocess.run(["git", "-C", repo, "reset", "--hard", sha], capture_output=True, text=True,
                   check=True)
    subprocess.run(["git", "-C", repo, "clean", "-fd"], capture_output=True, text=True,
                   check=True)


def build_prompt(flagged: dict[str, set[int]], gate_output: str) -> str:
    files = "\n".join(f"- {p}: lines {', '.join(str(n) for n in sorted(ns))}"
                      for p, ns in sorted(flagged.items()))
    hits = "\n".join(l for l in (gate_output or "").splitlines() if _HIT_RE.match(l))
    return (
        "The prose gate `bin/check-prose` flagged these lines:\n"
        f"{files}\n\nHit lines from the gate:\n{hits}\n\n"
        "Rewrite only those lines, in place, with the Edit tool, following "
        ".claude/rules/prose-style.md. Splitting a sentence onto a new line is fine. "
        "Do not touch frontmatter or any other line. Do not add `<!-- slop-ok -->`, "
        "code spans, HTML comments, or blockquotes to hide a tell. Keep the meaning."
    )


def attempt_prose_fix(*, git, llm, repo: str, argv: list[str], first_stdout: str,
                      commit, log) -> bool:
    """Bounded pass: up to two tooled calls, the gate re-run is the only success signal."""
    flagged = parse_hits(first_stdout)
    if not flagged:
        log("phase2: prose-fix skipped (no parseable check-prose hits)")
        return False
    owned = gate_owned(flagged)
    if owned:
        log(f"phase2: prose-fix skipped (gate-owning path: {', '.join(owned)})")
        return False
    if git.is_dirty():
        log("phase2: prose-fix skipped (dirty worktree)")
        return False
    head_before = git.head()
    original = (flagged, first_stdout)
    gate_output = first_stdout
    for n, model in enumerate(PROSE_FIX_MODELS, 1):
        log(f"phase2: prose-fix attempt {n} ({model}) on {len(flagged)} file(s), "
            f"{sum(map(len, flagged.values()))} line(s)")
        try:
            llm.call_tooled(PROSE_FIX_PURPOSE, model, build_prompt(flagged, gate_output),
                            cwd=repo, allowed_tools=PROSE_FIX_ALLOWED_TOOLS,
                            denied_tools=PROSE_FIX_DENIED_TOOLS)
        except HarnessError as e:
            _reset(repo, head_before)
            if e.kind == "llm-usage-limit":
                raise
            log(f"phase2: prose-fix attempt {n} ({model}) model error {e.kind}")
            break
        bad = scope_violations(repo, flagged) + evasion_violations(repo, flagged)
        if bad:
            log(f"phase2: prose-fix attempt {n} ({model}) scope violation: {'; '.join(bad[:3])}")
            _reset(repo, head_before)
            flagged, gate_output = original
            continue
        if not git.is_dirty():
            log(f"phase2: prose-fix attempt {n} ({model}) made no edit")
            continue
        try:
            commit(PROSE_FIX_COMMIT_MSG)
        except HarnessError as e:
            log(f"phase2: prose-fix attempt {n} ({model}) commit denied ({e.kind})")
            _reset(repo, head_before)
            flagged, gate_output = original
            continue
        rerun = subprocess.run(argv, cwd=repo, capture_output=True, text=True)
        if rerun.returncode == 0:
            log(f"phase2: prose-fix CLEARED {PROSE_CHECK} on attempt {n} ({model})")
            return True
        names = failed_check_names(rerun.stdout or "")
        if rerun.returncode != 1 or names != [PROSE_CHECK]:
            log(f"phase2: prose-fix attempt {n} ({model}) re-run rc={rerun.returncode} "
                f"failed={' '.join(names) or 'unknown'}; stopping")
            break
        flagged = parse_hits(rerun.stdout)
        gate_output = rerun.stdout
        if not flagged or gate_owned(flagged):
            break
        log(f"phase2: prose-fix attempt {n} ({model}) left "
            f"{sum(map(len, flagged.values()))} hit line(s)")
    _reset(repo, head_before)
    log(f"phase2: prose-fix FAILED, reverted to {head_before[:12]}; hold stands")
    return False
