"""Phase 1 — plan location + parsing (frontmatter, matrix, critical files).

Deterministic port of post-plan SKILL.md Phase 1, Phase 6.5 condition (7)'s
frontmatter awk, and _phase-5-final-verification.md's matrix/Critical-Files parsing.
Single source of truth for Critical Files exemption: bin/lib/critical-files.sh
(Python cannot source shell; this module mirrors it and is pinned by test_planfile.py).
"""
from __future__ import annotations

import os
import re
import sys

from .state import PlanInfo

# Paren-scoped canonical exempt marker. MUST stay behaviorally identical to
# CF_EXEMPT_PATTERN in bin/lib/critical-files.sh — that shell lib is the single
# source of truth; Python cannot source it, so this is a mirror pinned by
# tests/test_planfile.py::test_lib_sync (behavioral agreement over a shared
# fixture) and ::test_lib_pattern_sync (byte-level). Differences in dialect are
# fine ((?:…) vs (…), \( vs [(]); differences in CLASSIFICATION are the bug.
#
# Exempt iff the annotation contains a PARENTHESIZED group whose contents
# include a canonical token as a WHOLE WORD and — for `conditional` — as the
# opening token only. Two scoping rules (measured on the 259-plan corpus):
#   1. WHOLE WORD: `[^0-9A-Za-z]` boundaries prevent substring matches like
#      `references` (noun) or `referenced` (adjective) from exempting a declared
#      change target. Literal-range boundaries keep this string byte-identical to
#      the shell mirror; POSIX `[[:alnum:]]` is absent from Python `re` and
#      `\b` is not portable across BSD/GNU grep.
#   2. `conditional` MUST BE THE MARKER, not a word in prose. It exempts only
#      when it opens the parenthesized group and is immediately followed by `)`
#      or a separator (—/-/:,;). `(conditional — Phase 4 only)` → EXEMPT;
#      `(conditional Phase 4 only)` → MUST_APPEAR.
# Canonical tokens: reference, read-only, read-only reference, verify,
# verification, template, no-edit, no-change, unchanged, context, conditional.
EXEMPT_RE = re.compile(
    r"\((?:(?:[^)]*[^0-9A-Za-z])?(?:reference|read-?only|verif(?:y|ication)|template|"
    r"no[- ]edit|no[- ]change|unchanged|context)(?:[^0-9A-Za-z][^)]*)?|conditional(?:[ ]*[-–—:;,][^)]*)?[ ]*)\)",
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

    Fenced code blocks are skipped (_strip_fenced, defined below — same width-aware
    machine parse_critical_files uses). A plan that *documents* the matrix format —
    a `| 1 | thing works | PHPUnit | post-impl | `tests/X.php` |` scaffold inside a
    ```bash fixture block — otherwise reads as a real declaration, and the phantom
    path then blocks arming forever via condition (3) since no diff can ever contain
    it. Measured on the 260-plan corpus: 3 plans lose a planned path and 2 lose
    truly-manual rows, every one of them fenced illustrative content (`do X`,
    `tests/X.php`, a backslash-escaped heredoc row); no real matrix row is dropped.
    Known fail-open, inherited from parse_critical_files (and accepted there — see
    bin/lib/critical-files.sh's note at cf_fence_unbalanced): an UNCLOSED fence
    swallows the rest of the file, so planned==[] and conformance holds nothing.
    bin/check-plan gate [F] rejects that, but at PLAN-AUTHORING time only ("only
    ever sees newly-authored plans"), so a legacy plan predating the gate can still
    reach Phase 5.0 and silently skip its own conformance check. Live exposure today
    is zero: 1 of 260 corpus plans is unbalanced (sonnet-recipe-completeness-lint)
    and it already shipped. Making the harness report unbalanced-fence as
    INDETERMINATE rather than empty changes a Phase 5.0 contract, so it is filed as
    backlog, not fixed here.
    """
    planned: list[str] = []
    manual: list[str] = []
    for line in _strip_fenced(content):
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


def _strip_fenced(content: str) -> list[str]:
    """Lines outside fenced code blocks — literal port of cf_section()'s awk state
    machine in bin/lib/critical-files.sh. Width-aware: per CommonMark a closing fence
    must be at least as long as the opening one, so this repo's 4-backtick outer block
    wrapping 3-backtick inner ones does not toggle parity once and silently swallow the
    whole section.

    Fences inside a `>` blockquote get a SEPARATE, confined state: quote markers are
    stripped before the backtick run is counted, and any line that does not continue
    the quote (a blank line ends it in CommonMark) drops that state, so an unclosed
    quoted fence cannot swallow the rest of the file. Sharing state with in_fence
    would fail open. Inert at every current consumer — parse_matrix, parse_critical_files
    and parse_required_test_methods all match `^`-anchored shapes a `>`-prefixed line
    can never satisfy — so this is class consistency with the shell mirror, not a fix
    for a live miscount.
    """
    out: list[str] = []
    in_fence = False
    fence_len = 0
    bq_fence = False
    bq_fence_len = 0
    for line in content.splitlines():
        stripped = line.lstrip()
        is_bq = stripped.startswith(">")
        if not is_bq:
            bq_fence = False
            bq_fence_len = 0
        if is_bq and not in_fence:
            body = re.sub(r"^(\s*>)+\s*", "", stripped)
            n = 0
            while n < len(body) and body[n] == "`":
                n += 1
            if n >= 3:
                if not bq_fence:
                    bq_fence = True
                    bq_fence_len = n
                    continue
                if n >= bq_fence_len:
                    bq_fence = False
                    bq_fence_len = 0
                    continue
            if bq_fence:
                continue
        else:
            n = 0
            while n < len(stripped) and stripped[n] == "`":
                n += 1
            if n >= 3:
                if not in_fence:
                    in_fence = True
                    fence_len = n
                    continue
                if n >= fence_len:
                    in_fence = False
                    continue
        if in_fence:
            continue
        out.append(line)
    return out


def parse_critical_files(content: str) -> list[tuple]:
    """[(path, annotation, exempt)] from `## Critical Files` — the Phase 5.0 awk port:
    primary backticked path per bullet; exempt iff the annotation (backticks stripped)
    contains a parenthesized group holding a canonical marker. Fenced code blocks are
    skipped (width-aware fence state machine, see _strip_fenced). Single source of
    truth for the exemption rule: bin/lib/critical-files.sh."""
    lines = _strip_fenced(content)
    in_section = False
    out: list[tuple] = []
    for line in lines:
        if re.match(r"^##\s*Critical Files", line):
            in_section = True
            continue
        if re.match(r"^## ", line):
            in_section = False
            continue
        if not in_section:
            continue
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


def parse_required_test_methods(content: str) -> list[str]:
    """List of bare method names from `## Required Test Methods` (fenced blocks stripped).

    Mirrors the bash idiom in _phase-5-final-verification.md: `cf_section_named` +
    sed bullet extraction. Absent section → []. Fenced examples are stripped by
    _strip_fenced before the section is extracted, so illustrative bullets inside a
    fence never yield phantom entries (the failure `critical-files-parser-unification`
    hit on the old naive parse).
    """
    section = _section("\n".join(_strip_fenced(content)), "Required Test Methods")
    methods = []
    for line in section.splitlines():
        m = re.match(r"^[*\-]\s+`?([A-Za-z_][A-Za-z0-9_]*)`?", line.strip())
        if m:
            methods.append(m.group(1))
    return methods


def _resolve_variant(slug: str, base_dir: str, info: PlanInfo) -> str:
    """Highest-numbered plan variant for `slug` in `base_dir`.

    Variant shape is exactly `{slug}-N.md`: one or more digits and nothing else
    before `.md`. The bare `slug.md` is variant 0 (lowest). No variant found ->
    the bare path, byte-identical to pre-variant behaviour (no stderr, no fields).
    """
    bare = os.path.join(base_dir, f"{slug}.md")
    pattern = re.compile(rf"^{re.escape(slug)}-(\d+)\.md$")
    try:
        entries = os.listdir(base_dir)
    except OSError:
        return bare
    variants = [(int(m.group(1)), os.path.join(base_dir, m.string))
                for m in (pattern.match(n) for n in entries) if m
                and os.path.isfile(os.path.join(base_dir, m.string))]
    if not variants:
        return bare
    candidates = ([(0, bare)] if os.path.isfile(bare) else []) + variants
    candidates.sort(key=lambda c: c[0])
    _, selected = candidates[-1]
    names = [os.path.basename(p) for _, p in candidates]
    print(
        f"post-plan: WARNING — {len(candidates)} plan variants for slug '{slug}':\n"
        f"  candidates: {', '.join(names)}\n"
        f"  SELECTED:   {os.path.basename(selected)} (highest numbered)\n"
        f"  override:   bin/post-plan-now --plan <abs-path>",
        file=sys.stderr)
    info.variant_selection = "highest"
    info.rejected = [os.path.basename(p) for _, p in candidates if p != selected]
    return selected


def locate_plan(slug: str, plans_dir: str | None = None, explicit_path: str | None = None,
                content_override: str | None = None) -> PlanInfo:
    """Authoritative explicit path (automouse handoff) first, else variant-aware slug derivation."""
    info = PlanInfo()
    content = content_override
    if content is None:
        if explicit_path:
            path = explicit_path
        else:
            base_dir = (plans_dir or os.environ.get("PLANS_DIR")
                        or os.path.expanduser("~/claude-plans"))
            path = _resolve_variant(slug, base_dir, info)
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
    info.required_test_methods = parse_required_test_methods(content)
    if info.has_security:
        info.security_section = _section(content, "Security")[:4000]
    if info.has_reuse:
        info.reuse_section = _section(content, r"Reuse[^#\n]*")[:2000]
    return info
