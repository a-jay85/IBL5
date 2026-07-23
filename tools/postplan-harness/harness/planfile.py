"""Phase 1 — plan location + parsing (frontmatter, matrix, critical files).

Deterministic port of post-plan SKILL.md Phase 1, Phase 6.5 condition (7)'s
frontmatter awk, and _phase-5-final-verification.md's matrix/Critical-Files parsing.
"""
from __future__ import annotations

import os
import re

from .state import PlanInfo

EXEMPT_RE = re.compile(
    r"reference|read-?only|verif(y|ication)|template|no[- ]edit|no[- ]change|unchanged|context",
    re.IGNORECASE,
)
_MATRIX_HEADER = re.compile(r"^\s*\|.*Test type", re.IGNORECASE)
_SECURITY_H = re.compile(r"^#+ *Security", re.IGNORECASE)
_REUSE = re.compile(r"Reuse", re.IGNORECASE)


def frontmatter_auto_merge_false(content: str) -> bool:
    """Line-1 YAML frontmatter only (a body documenting the syntax can't self-select)."""
    lines = content.splitlines()
    if not lines or not re.match(r"^---\s*$", lines[0]):
        return False
    for line in lines[1:]:
        if re.match(r"^---\s*$", line):
            return False
        m = re.match(r"^auto_merge:\s*(.+?)\s*$", line)
        if m:
            return m.group(1).strip() == "false"
    return False


def _section(content: str, heading_re: str) -> str:
    m = re.search(rf"(^#+ *{heading_re}.*?$)(.*?)(?=^#+ |\Z)", content, re.M | re.S | re.I)
    return (m.group(2).strip() if m else "")


def parse_matrix(content: str) -> tuple[list[str], list[str]]:
    """Returns (planned_test_paths, truly_manual_rows) from the Verification Matrix.

    Planned tests: rows whose Test type is PHPUnit / API-test / E2E / Visual-regression;
    path taken from the row's backticked file token.
    """
    planned: list[str] = []
    manual: list[str] = []
    for line in content.splitlines():
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        row = " | ".join(cells)
        if re.search(r"truly.?manual", row, re.I):
            manual.append(row)
        if re.search(r"\b(PHPUnit|API.?test|E2E|Visual.?regression)\b", row, re.I):
            m = re.search(r"`([^`]*(?:test|spec|Test)[^`]*)`", row)
            if m and "/" in m.group(1):
                p = m.group(1)
                if p not in planned:
                    planned.append(p)
    return planned, manual


def parse_critical_files(content: str) -> list[tuple]:
    """[(path, annotation, exempt)] from `## Critical Files` — the Phase 5.0 awk port:
    primary backticked path per bullet; exempt iff annotation (backticks stripped)
    matches the reference-marker regex."""
    m = re.search(r"^## *Critical Files.*?$(.*?)(?=^## |\Z)", content, re.M | re.S)
    if not m:
        return []
    out: list[tuple] = []
    for line in m.group(1).splitlines():
        if not re.match(r"^\s*-\s*`", line):
            continue
        pm = re.search(r"`([^`]+)`", line)
        if not pm:
            continue
        path = pm.group(1)
        rest = re.sub(r"`[^`]*`", "", line)
        exempt = bool(EXEMPT_RE.search(rest))
        out.append((path, rest.strip(" -—"), exempt))
    return out


def locate_plan(slug: str, plans_dir: str | None = None, explicit_path: str | None = None,
                content_override: str | None = None) -> PlanInfo:
    """Authoritative explicit path (automouse handoff) first, else slug derivation."""
    info = PlanInfo()
    content = content_override
    if content is None:
        path = explicit_path or os.path.join(
            plans_dir or os.environ.get("PLANS_DIR")
            or os.path.expanduser("~/claude-plans"), f"{slug}.md")
        if not os.path.isfile(path) and plans_dir is None and explicit_path is None:
            # Pre-migration archive; retire once no plans remain there.
            legacy = os.path.join(os.path.expanduser("~/.claude/plans"), f"{slug}.md")
            if os.path.isfile(legacy):
                path = legacy
        if not os.path.isfile(path):
            return info
        info.path = path
        with open(path) as fh:
            content = fh.read()
    info.found = True
    info.auto_merge_false = frontmatter_auto_merge_false(content)
    info.has_matrix = any(_MATRIX_HEADER.match(l) for l in content.splitlines())
    info.has_security = any(_SECURITY_H.match(l) for l in content.splitlines())
    info.has_reuse = bool(_REUSE.search(content))
    if info.has_matrix:
        info.planned_test_paths, info.truly_manual_rows = parse_matrix(content)
    info.critical_files = parse_critical_files(content)
    if info.has_security:
        info.security_section = _section(content, "Security")[:4000]
    if info.has_reuse:
        info.reuse_section = _section(content, r"Reuse[^#\n]*")[:2000]
    return info
