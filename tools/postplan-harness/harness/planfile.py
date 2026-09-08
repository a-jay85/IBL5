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

from . import manual_rows
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
_LEGAL_STOP_CONDITIONS = ("tests-green", "evidence-present")
_EVIDENCE_TOKEN = re.compile(r"^[A-Za-z0-9._/-]+$")


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


def frontmatter_autonomy_contract(content: str) -> tuple[str, list[str], str]:
    """(stop_condition, evidence_tokens, error) from line-1 YAML frontmatter only
    (a body documenting the syntax can't self-select).

    error == "" means well-formed OR entirely absent. Mirror of the shell single
    source of truth, bin/lib/plan-autonomy-contract; pinned by
    tests/test_planfile.py::test_contract_lib_sync.
    """
    lines = content.splitlines()
    if not lines or not re.match(r"^---\s*$", lines[0]):
        return ("", [], "")
    sc_raw = None   # None = absent, "" = present-but-empty, other = value
    ev_raw = None   # None = absent, "" = present-but-empty, other = value
    for line in lines[1:]:
        if re.match(r"^---\s*$", line):
            break
        m = re.match(r"^stop_condition:\s*(.*?)\s*$", line)
        if m and sc_raw is None:
            sc_raw = m.group(1)
        m = re.match(r"^evidence:\s*(.*?)\s*$", line)
        if m and ev_raw is None:
            ev_raw = m.group(1)
    # both absent → well-formed absence
    if sc_raw is None and ev_raw is None:
        return ("", [], "")
    # exactly one present (including present-but-empty) → unit error
    if (sc_raw is None) != (ev_raw is None):
        return ("", [], "stop_condition:/evidence: — the two fields are a unit")
    # normalise: remove all whitespace
    sc_norm = re.sub(r"\s", "", sc_raw)
    ev_norm = re.sub(r"\s", "", ev_raw)
    # validate stop_condition enum
    if sc_norm not in _LEGAL_STOP_CONDITIONS:
        return ("", [], f"stop_condition: '{sc_norm}' is not a legal value")
    # split evidence on comma, drop empty tokens
    tokens = [t for t in ev_norm.split(",") if t]
    if not tokens:
        return ("", [], "evidence: is empty")
    # validate each token
    for t in tokens:
        if t.startswith("/") or t.startswith("-"):
            return ("", [], f"evidence: token '{t}' is not a repo-relative path")
        if ".." in t.split("/"):
            return ("", [], f"evidence: token '{t}' is not a repo-relative path")
        if not _EVIDENCE_TOKEN.match(t):
            return ("", [], f"evidence: token '{t}' is not a repo-relative path")
    return (sc_norm, tokens, "")


def _section(content: str, heading_re: str) -> str:
    m = re.search(rf"(^#+ *{heading_re}.*?$)(.*?)(?=^#+ |\Z)", content, re.M | re.S | re.I)
    return (m.group(2).strip() if m else "")


def _is_test_path(tok: str) -> bool:
    """True when `tok` looks like a file path rather than a shell command token.

    Three observed phantom shapes this rejects:
      - `npm test && npm run build` (shell metacharacter &)
      - `pytest -q tools/... ; echo ok` (shell metacharacter ; and leading flag -)
      - `$TEST_CMD tests/foo` (shell variable $)
    """
    if not tok or tok != tok.strip() or re.search(r"\s", tok):
        return False
    if re.search(r"[&|;><$\\]", tok):
        return False
    if tok[0] in ("-", "'", '"', "$"):
        return False
    if "/" not in tok:
        return False
    return True


def parse_matrix(content: str) -> tuple[list[str], list[manual_rows.ManualRow]]:
    """Returns (planned_test_paths, truly_manual_rows) from the Verification Matrix.

    Planned tests: rows whose Test type is PHPUnit / API-test / E2E / Visual-regression;
    path taken from the row's backticked file token.
    Truly-manual rows are returned as ManualRow objects (typed, with noise columns
    stripped) rather than raw pipe-delimited strings.

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
    manual: list[manual_rows.ManualRow] = []
    for line in _strip_fenced(content):
        if not line.strip().startswith("|"):
            continue
        cells = [c.strip() for c in line.strip().strip("|").split("|")]
        row = " | ".join(cells)
        if re.search(r"truly.?manual", row, re.I):
            manual.append(manual_rows.row_from_cells(cells, len(manual) + 1))
        if re.search(r"\b(PHPUnit|API.?test|E2E|Visual.?regression)\b", row, re.I):
            m = re.search(r"`([^`]*(?:test|spec|Test)[^`]*)`", row)
            if m and _is_test_path(m.group(1)):
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


def parse_hold_justification(content: str) -> str:
    """Prose body of `## Automouse Hold Justification`, or "" when absent.

    Fenced blocks are stripped before extraction (same reason as
    parse_required_test_methods): a plan that *documents* the section format
    inside a fence must not yield a phantom hold justification in the PR body.
    Format is a level-2 heading over a prose paragraph — no per-entry bullet
    structure — so the whole section body is returned verbatim.
    """
    return _section("\n".join(_strip_fenced(content)),
                    r"Automouse Hold Justification")[:4000]


def _resolve_drift(slug: str, base_dir: str, entries: list[str], bare: str,
                   info: PlanInfo) -> str:
    """Prefix-drift fallback: `<prefix>-{slug}.md` when no `{slug}.md` exists.

    Observed shape: `/plan` named the file `plan-autonomy-contract-frontmatter.md`
    while `bin/wt-new` named the branch `autonomy-contract-frontmatter`, so the
    exact-name derivation missed and the run went plan-blind — which silently
    zeroes condition (7), because `auto_merge: false` can only be read off a plan
    that was found. The `-{slug}.md` suffix anchor keeps `{slug}-shared-context.md`
    shaped names out; a non-numeric suffix is never a variant.

    Exact match always wins. Two or more candidates is ambiguous and stays blind.
    An adopted match sets `info.slug_drift`, which HOLDS auto-merge via condition
    (11) — adoption is a guess, and a wrong guess can release a hold.
    """
    if os.path.isfile(bare):
        return bare
    pattern = re.compile(rf"^.+-{re.escape(slug)}\.md$")
    cands = sorted(n for n in entries
                   if pattern.match(n) and os.path.isfile(os.path.join(base_dir, n)))
    if len(cands) != 1:
        if cands:
            print(f"post-plan: WARNING — {len(cands)} slug-drift candidates for branch "
                  f"'{slug}': {', '.join(cands)}\n"
                  f"  none adopted (ambiguous); running plan-blind\n"
                  f"  override:   bin/post-plan-now --plan <abs-path>",
                  file=sys.stderr)
        return bare
    selected = cands[0]
    print(f"post-plan: WARNING — no plan at {slug}.md; adopted '{selected}' by slug drift\n"
          f"  branch name and plan filename disagree\n"
          f"  auto-merge is HELD for this run (condition 13)\n"
          f"  override:   bin/post-plan-now --plan <abs-path>",
          file=sys.stderr)
    info.slug_drift = selected
    return os.path.join(base_dir, selected)


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
        return _resolve_drift(slug, base_dir, entries, bare, info)
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
    """Authoritative explicit path (operator --plan override) first, else variant-aware slug derivation."""
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
        # Record HOW the file was chosen, so a cleared condition (13) is distinguishable from
        # one that never fired. `--plan` is the only producer of explicit_path (runner.py's
        # `args.plan`, fed by `bin/post-plan-now --plan`), so an explicit path is always an
        # operator override — never an automouse handoff. See PlanInfo.plan_source for the
        # value contract and for why "override-mismatch" is a string comparison, not a claim
        # about which hold the override displaced.
        if explicit_path:
            info.plan_source = ("override" if os.path.basename(path) == f"{slug}.md"
                                else "override-mismatch")
        elif info.slug_drift:
            info.plan_source = "drift"
        elif info.variant_selection:
            info.plan_source = "variant"
        with open(path) as fh:
            content = fh.read()
    info.found = True
    info.auto_merge_false = frontmatter_auto_merge_false(content)
    info.stop_condition, info.evidence, info.contract_error = frontmatter_autonomy_contract(content)
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
    info.hold_justification = parse_hold_justification(content)
    return info
