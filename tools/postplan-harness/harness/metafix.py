"""Fix-round fixer for arming condition (16), the pre-push meta-checks.

Called only from the runner's fix-round closure. It acts on a failure list made
entirely of FIXABLE_CHECKS, edits only non-gate `.md` files the PR changed, and the
real `bin/run-meta-checks-local --stage pre-push` re-run decides.
"""
from __future__ import annotations

import datetime
import re
import subprocess
import time

from harness import prosefix
from harness.fidelity import denied_gate_edits
from harness.state import HarnessError

DOCS_CHECKS = frozenset({"check-docs-since", "check-docs-no-staleness"})
FIXABLE_CHECKS = frozenset({prosefix.PROSE_CHECK}) | DOCS_CHECKS
META_FIX_PURPOSE = "meta-fix"
META_FIX_MODELS = ("sonnet", "opus")
META_FIX_COMMIT_MSG = "chore: fix failing doc meta-checks"
_STEP_RE = re.compile(r"^[A-Z]+  \S")
_DOCS_STEP_RE = re.compile(r"^FAIL  (check-docs-since|check-docs-no-staleness)\s*$")
_MD_HEADER_RE = re.compile(r"^FAIL (?P<path>\S+\.md)\s*$")
_OTHER_HEADER_RE = re.compile(r"^FAIL (?P<text>\S.*)$")
_MD_BULLET_RE = re.compile(r"^\s+- (?P<path>[^\s:]+\.md): (?P<msg>.+)$")
_BULLET_RE = re.compile(r"^\s+- (?P<msg>.+)$")
_DOC_EVASION_TOKENS = ("(example)", "RETIRED-OK", "slop-ok")


def fixable(names) -> bool:
    """True only when every failing check is in the safe set. A mixed list holds."""
    names = list(names or ())
    return bool(names) and all(n in FIXABLE_CHECKS for n in names)


def parse_doc_failures(output: str) -> tuple[dict[str, list[str]], list[str]]:
    """`{doc path: [message, ...]}` from the check-docs sections, plus refusal reasons.

    Only lines under a `FAIL  check-docs-*` step count. A header or bullet that names
    no `.md` target is a refusal, because the fixer edits markdown only."""
    targets: dict[str, list[str]] = {}
    refusals: list[str] = []
    in_docs = False
    current: str | None = None
    for line in (output or "").splitlines():
        if _DOCS_STEP_RE.match(line):
            in_docs, current = True, None
            continue
        if _STEP_RE.match(line) or line.startswith("META-CHECK-FAILED:"):
            in_docs, current = False, None
            continue
        if not in_docs:
            continue
        m = _MD_HEADER_RE.match(line)
        if m:
            current = m.group("path")
            continue
        m = _OTHER_HEADER_RE.match(line)
        if m:
            current = None
            refusals.append(f"non-doc section: {m.group('text')[:80]}")
            continue
        m = _MD_BULLET_RE.match(line)
        if m:
            targets.setdefault(m.group("path"), []).append(m.group("msg"))
            continue
        m = _BULLET_RE.match(line)
        if m:
            if current is None:
                refusals.append(f"non-doc target: {m.group('msg')[:80]}")
            else:
                targets.setdefault(current, []).append(m.group("msg"))
    return targets, refusals


def plan_targets(output: str, repo: str) -> tuple[dict, dict, str]:
    """(prose lines, doc targets, refusal). A non-empty refusal means no model call."""
    names = prosefix.failed_check_names(output)
    if not fixable(names):
        return {}, {}, "unfixable: " + (" ".join(names) or "unknown")
    base = prosefix.parse_meta_base(output)
    if base is None:
        return {}, {}, "no-meta-base"
    prose = prosefix.parse_hits(output) if prosefix.PROSE_CHECK in names else {}
    if prosefix.PROSE_CHECK in names and not prose:
        return {}, {}, "no-prose-hits"
    wants_docs = bool(DOCS_CHECKS & set(names))
    docs, refusals = parse_doc_failures(output) if wants_docs else ({}, [])
    if refusals:
        return {}, {}, refusals[0]
    if wants_docs and not docs:
        return {}, {}, "no-doc-targets"
    paths = sorted(set(prose) | set(docs))
    owned = prosefix.gate_owned({p: set() for p in paths})
    if owned:
        return {}, {}, "gate-owned: " + " ".join(owned)
    outside = prosefix.outside_pr_diff(repo, {p: set() for p in paths}, base)
    if outside:
        return {}, {}, "outside-pr-diff: " + " ".join(outside)
    return prose, docs, ""


def changed_paths(repo: str) -> list[str]:
    """Tracked edits vs HEAD plus untracked files: everything the model touched."""
    out = prosefix._git_out(repo, "diff", "--name-only", "--no-renames", "HEAD").splitlines()
    out += prosefix._git_out(repo, "ls-files", "--others", "--exclude-standard").splitlines()
    return sorted({p for p in out if p})


def doc_evasion_violations(repo: str, paths) -> list[str]:
    """An added `(example)`, `RETIRED-OK` or `slop-ok` marker silences a check without a fix."""
    bad: list[str] = []
    for path in sorted(paths):
        diff = prosefix._git_out(repo, "diff", "-U0", "--no-renames", "HEAD", "--", path)
        added = [l for l in diff.splitlines() if l.startswith("+") and not l.startswith("+++")]
        removed = [l for l in diff.splitlines() if l.startswith("-") and not l.startswith("---")]
        for token in _DOC_EVASION_TOKENS:
            if sum(l.count(token) for l in added) > sum(l.count(token) for l in removed):
                bad.append(f"{path}: added {token!r} (check escape)")
    return bad


def scope_violations(repo: str, prose: dict, docs: dict) -> list[str]:
    """Doc targets may change anywhere in the file; prose targets only on flagged lines."""
    doc_files = set(docs)
    bad = [v for v in prosefix.scope_violations(repo, prose)
           if v.split(":", 1)[0] not in doc_files]
    bad += prosefix.evasion_violations(repo, prose)
    bad += doc_evasion_violations(repo, doc_files)
    return bad


def build_prompt(prose: dict, docs: dict, gate_output: str, today: str) -> str:
    parts = [prosefix.build_prompt(prose, gate_output)] if prose else []
    if docs:
        listing = "\n".join(f"- {p}: " + "; ".join(msgs) for p, msgs in sorted(docs.items()))
        parts.append(
            "The doc gate `bin/check-docs` reported these problems:\n"
            f"{listing}\n\n"
            "Fix them with the Edit tool, in those files only. Frontmatter edits are allowed "
            f"in these files. A `last_verified` problem means exactly one `last_verified: {today}` "
            "key. A dead reference means pointing it at a file that exists (check with Glob) "
            "or removing it. Do not add `(example)`, `RETIRED-OK` or `<!-- slop-ok -->`.")
    return "\n\n".join(parts)


def _out(verdict: str, reason: str, checks=()) -> dict:
    return {"verdict": verdict, "reason": reason, "checks": list(checks)}


def _run_gate(argv, repo: str, deadline: float):
    return subprocess.run(argv, cwd=repo, capture_output=True, text=True,
                          timeout=max(1, int(deadline - time.monotonic())))


def attempt_meta_fix(*, git, llm, repo: str, argv: list[str], commit, log,
                     timeout: float, today: str | None = None) -> dict:
    """Up to two tooled calls; the pre-push re-run is the only success signal.

    verdict "cleared": committed and the re-run exited 0. "refused": no edit was made.
    "failed": an edit was made and did not clear; the caller discards it."""
    today = today or datetime.date.today().isoformat()
    deadline = time.monotonic() + timeout
    if not repo:
        return _out("refused", "no-worktree")
    try:
        first = _run_gate(argv, repo, deadline)
    except subprocess.TimeoutExpired:
        return _out("refused", "gate-timeout")
    except OSError as e:
        return _out("refused", f"gate-error:{type(e).__name__}")
    if first.returncode == 0:
        return _out("refused", "rerun-passed")
    if first.returncode != 1:
        return _out("refused", f"gate-rc={first.returncode}")
    output = first.stdout or ""
    checks = prosefix.failed_check_names(output)
    prose, docs, refusal = plan_targets(output, repo)
    if refusal:
        log(f"fix-round: meta-fix skipped ({refusal})")
        return _out("refused", refusal, checks)
    if git.is_dirty():
        return _out("refused", "dirty-worktree", checks)
    head_before = git.head()
    for n, model in enumerate(META_FIX_MODELS, 1):
        remaining = deadline - time.monotonic()
        if remaining <= 0:
            return _out("failed", "timeout", checks)
        log(f"fix-round: meta-fix attempt {n} ({model}) on {' '.join(checks)}")
        try:
            llm.call_tooled(META_FIX_PURPOSE, model, build_prompt(prose, docs, output, today),
                            cwd=repo, allowed_tools=prosefix.PROSE_FIX_ALLOWED_TOOLS,
                            denied_tools=prosefix.PROSE_FIX_DENIED_TOOLS,
                            timeout=max(1, int(remaining)))
        except HarnessError as e:
            if e.kind in ("llm-usage-limit", "llm-auth-expired"):
                raise
            edited = git.is_dirty() or git.head() != head_before
            return _out("failed" if edited else "refused", f"model-error:{e.kind}", checks)
        gate = denied_gate_edits(changed_paths(repo))
        if gate:
            log(f"fix-round: meta-fix edited a gate-owning path: {' '.join(gate)}")
            return _out("failed", "gate-path", checks)
        bad = scope_violations(repo, prose, docs)
        if bad:
            log(f"fix-round: meta-fix scope violation: {'; '.join(bad[:3])}")
            return _out("failed", "scope", checks)
        if not git.is_dirty():
            continue
        commit(META_FIX_COMMIT_MSG)
        try:
            rerun = _run_gate(argv, repo, deadline)
        except subprocess.TimeoutExpired:
            return _out("failed", "timeout", checks)
        except OSError:
            return _out("failed", "gate-error", checks)
        if rerun.returncode == 0:
            log(f"fix-round: meta-fix CLEARED {' '.join(checks)} on attempt {n} ({model})")
            return _out("cleared", f"attempt {n} ({model})", checks)
        output = rerun.stdout or ""
        prose, docs, refusal = plan_targets(output, repo)
        if rerun.returncode != 1 or refusal:
            return _out("failed", "still-failing", checks)
    if git.head() == head_before:
        return _out("refused", "no-edit", checks)
    return _out("failed", "still-failing", checks)
