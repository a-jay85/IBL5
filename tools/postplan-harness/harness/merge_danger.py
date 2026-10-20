"""The PR body's `**Merge danger:**` field: mechanical derivation, verdict merge, parse, render.

Pure functions only. No subprocess, network, or git calls live here, apart from the one IO shim
`derive_from_worktree`, which reads candidate files from a checked-out tree. The field format is
the contract between `/post-plan` Phase 2 (writer), the harness body check (validator), Phase 6.5
condition (18) (reader), and `bin/digest-dm-build` (reader). The Phase 6.5 shell twin is
`pr_merge_danger_hold` in `bin/lib/pr-armable.sh`; keep its regex in step with `FIELD_RE`.

The door is derived from the realized diff and only ever raised: the LLM (or a human) may move
`two-way` to `one-way` or widen the blast radius, never the reverse.
"""
from __future__ import annotations

import fnmatch
import posixpath
import re
from collections.abc import Callable, Iterable
from dataclasses import dataclass
from pathlib import Path

from harness.armable import DESTRUCTIVE_SQL
from harness.gate_backtest import GateChange, changes_from_unified_diff, detect_gate_changes
# Private name imported on purpose: the arming paths are the bootstrap-hazard set, and a public
# alias would mean another edit to a file several open PRs touch.
from harness.gate_backtest import _ARMING_PATHS

DOORS = ("two-way", "one-way")                                  # index = rank; higher is more dangerous
BLAST = ("localized", "module", "site-wide", "external")        # index = rank
WHY_MIN, WHY_MAX = 20, 200
FIELD_PREFIX = "**Merge danger:**"
FIELD_RE = re.compile(
    r"^\*\*Merge danger:\*\* Door: (?P<door>[a-z-]+) · Blast radius: (?P<blast>[a-z-]+) · Why: (?P<why>.+)$"
)
_LINE_RE = re.compile(r"^\*\*Merge danger:\*\*")
_EXTERNAL_PATHS = ("bin/db-sync-prod", ".github/workflows/promote-to-production.yml", "bin/discord-dm")
_EXTERNAL_PREFIXES = (".github/actions/notify-discord/", "ibl5/classes/Mail/")
_NOTIFY_TEXT_RE = re.compile(r"notify-discord|DISCORD_")
_SITE_WIDE_PATHS = ("ibl5/.htaccess", "ibl5/router.php", "ibl5/composer.json")
_MIGRATION_RE = re.compile(r"^ibl5/migrations/.*\.sql$")
_WORKFLOW_GLOB = ".github/workflows/*.yml"
_DIFF_HEADER_RE = re.compile(r"^diff --git a/.* b/(.*)$", re.MULTILINE)


@dataclass(frozen=True)
class MergeDanger:
    door: str
    blast: str
    why: str
    triggers: tuple[str, ...] = ()


class MalformedMergeDanger(ValueError):
    """The body carries a `**Merge danger:**` line that does not parse, or more than one."""


def _migration_sections(diff_text: str) -> str:
    """The diff text belonging to migration files. A diff without `diff --git` headers is
    searched whole, so a bare hunk still counts."""
    headers = list(_DIFF_HEADER_RE.finditer(diff_text))
    if not headers:
        return diff_text
    parts: list[str] = []
    for i, m in enumerate(headers):
        end = headers[i + 1].start() if i + 1 < len(headers) else len(diff_text)
        if _MIGRATION_RE.match(m.group(1)):
            parts.append(diff_text[m.start():end])
    return "".join(parts)


def _module_root(path: str) -> str:
    parts = path.split("/")
    if len(parts) >= 3 and parts[0] == "ibl5" and parts[1] in ("classes", "modules"):
        return "/".join(parts[:3])
    if len(parts) >= 2 and parts[0] == "tools":
        return "/".join(parts[:2])
    return parts[0]


def _blast_radius(paths: list[str], gate_changes: list[GateChange], external: bool) -> str:
    if external:
        return "external"
    if (gate_changes
            or any(_MIGRATION_RE.match(p) for p in paths)
            or any(p.startswith(".github/workflows/") for p in paths)
            or any(p in _SITE_WIDE_PATHS or p in _ARMING_PATHS for p in paths)):
        return "site-wide"
    if not paths:
        return "localized"
    if len(paths) <= 3 and len({posixpath.dirname(p) for p in paths}) == 1:
        return "localized"
    if len({_module_root(p) for p in paths}) == 1:
        return "module"
    return "site-wide"


def derive_merge_danger(
    changed: Iterable[tuple[str, str]],
    diff_text: str,
    gate_changes: list[GateChange],
    read_candidate: Callable[[str], str | None],
) -> MergeDanger:
    """The mechanical floor. Any trigger makes the door one-way.

    changed: (status, path) pairs, as `gate_backtest.changes_from_unified_diff` returns them.
    gate_changes: the `detect_gate_changes` result for the same diff.
    read_candidate(path) -> the candidate branch's text for a path, or None.
    """
    pairs = list(changed)
    paths = [p for _, p in pairs]
    triggers: list[str] = []

    def add(trigger: str) -> None:
        if trigger not in triggers:
            triggers.append(trigger)

    # 1. Destructive migration. The `-- destructive-migration[trigger]:` bypass marker is never
    # read: a bypassed destructive migration is still irreversible.
    migrations = [p for p in paths if _MIGRATION_RE.match(p)]
    if migrations and DESTRUCTIVE_SQL.search(_migration_sections(diff_text or "")):
        for p in migrations:
            add(f"destructive-migration:{p}")

    # 2. Prod external state and outbound notification senders.
    external = False
    for status, p in pairs:
        if p in _EXTERNAL_PATHS or p.startswith(_EXTERNAL_PREFIXES):
            add(f"external-path:{p}")
            external = True
        elif fnmatch.fnmatchcase(p, _WORKFLOW_GLOB) and not status.startswith("D"):
            text = read_candidate(p)
            if text is not None and _NOTIFY_TEXT_RE.search(text):
                add(f"notify-workflow:{p}")
                external = True

    # 3. Self-governing merge machinery (the bootstrap hazard).
    for p in paths:
        if p in _ARMING_PATHS:
            add(f"arming-path:{p}")
    for g in gate_changes:
        if g.kind == "arming-harness":
            add(f"arming-path:{g.path}")

    # 4. Gate removal.
    for g in gate_changes:
        if g.state == "removed":
            add(f"gate-removed:{g.path}")

    door = "one-way" if triggers else "two-way"
    blast = _blast_radius(paths, gate_changes, external)
    why = ("mechanical: " + "; ".join(triggers)) if triggers else \
        "mechanical: no one-way trigger in the realized diff"
    return MergeDanger(door, blast, why[:WHY_MAX], tuple(triggers))


def _rank(value: str | None, scale: tuple[str, ...]) -> int:
    return scale.index(value) if value in scale else 0


def _clean_why(text: str | None) -> str:
    return re.sub(r"\s+", " ", (text or "").replace("|", " ")).strip()


def merge_verdicts(
    mechanical: MergeDanger,
    llm_door: str | None,
    llm_blast: str | None,
    llm_why: str | None,
) -> MergeDanger:
    """Rank max on both axes. An unknown or absent LLM value ranks 0, so it never lowers."""
    door = DOORS[max(_rank(mechanical.door, DOORS), _rank(llm_door, DOORS))]
    blast = BLAST[max(_rank(mechanical.blast, BLAST), _rank(llm_blast, BLAST))]
    why = _clean_why(llm_why)[:WHY_MAX]
    if len(why) < WHY_MIN:
        why = mechanical.why
    elif mechanical.door == "one-way":
        why = f"{mechanical.why}. {why}"
    return MergeDanger(door, blast, why[:WHY_MAX], mechanical.triggers)


def render_merge_danger(md: MergeDanger) -> str:
    why = _clean_why(md.why)[:WHY_MAX]
    return f"{FIELD_PREFIX} Door: {md.door} · Blast radius: {md.blast} · Why: {why}"


def parse_merge_danger(body: str) -> MergeDanger | None:
    """None when the body has no field line. Raises MalformedMergeDanger on two or more lines,
    an unknown enum value, or a Why shorter than WHY_MIN. A quoted example inside a code fence
    counts as a line too: that keeps the shell twin simple, and it fails closed."""
    lines = [ln for ln in (body or "").splitlines() if _LINE_RE.match(ln)]
    if not lines:
        return None
    if len(lines) > 1:
        raise MalformedMergeDanger(f"{len(lines)} Merge danger lines; exactly one is allowed")
    m = FIELD_RE.match(lines[0].rstrip())
    if not m:
        raise MalformedMergeDanger("line does not match the Door · Blast radius · Why format")
    door, blast, why = m.group("door"), m.group("blast"), m.group("why").strip()
    if door not in DOORS:
        raise MalformedMergeDanger(f"unknown Door {door!r}")
    if blast not in BLAST:
        raise MalformedMergeDanger(f"unknown Blast radius {blast!r}")
    if len(why) < WHY_MIN:
        raise MalformedMergeDanger(f"Why is shorter than {WHY_MIN} chars")
    return MergeDanger(door, blast, why)


def classify_body(body: str | None) -> str:
    """"ONE-WAY" | "TWO-WAY" | "MISSING" | "MALFORMED" | "UNREADABLE"."""
    if body is None:
        return "UNREADABLE"
    try:
        md = parse_merge_danger(body)
    except MalformedMergeDanger:
        return "MALFORMED"
    if md is None:
        return "MISSING"
    return "ONE-WAY" if md.door == "one-way" else "TWO-WAY"


def validate_merge_danger_field(body: str) -> list[str]:
    """[] when the body carries one well-formed line; one finding string otherwise."""
    try:
        md = parse_merge_danger(body)
    except MalformedMergeDanger as exc:
        return [f"merge-danger: MALFORMED: {exc}"]
    if md is None:
        return ["merge-danger: MISSING: no **Merge danger:** line in the body"]
    return []


def inject_merge_danger(body: str, md: MergeDanger) -> str:
    """Replace the first field line in place (well-formed or not) and drop any others; append
    when there is none. Idempotent."""
    rendered = render_merge_danger(md)
    lines = (body or "").split("\n")
    out: list[str] = []
    placed = False
    for ln in lines:
        if _LINE_RE.match(ln):
            if not placed:
                out.append(rendered)
                placed = True
            continue
        out.append(ln)
    if placed:
        return "\n".join(out)
    base = (body or "").rstrip("\n")
    return f"{base}\n\n{rendered}" if base else rendered


def derive_from_worktree(worktree: str | Path, diff_text: str) -> MergeDanger:
    """IO shim: build the detect_gate_changes inputs from the worktree's files, then delegate
    to the pure derive_merge_danger. It only reads files."""
    root = Path(worktree)
    changed = changes_from_unified_diff(diff_text)

    def read_candidate(path: str) -> str | None:
        p = root / path
        return p.read_text(encoding="utf-8", errors="replace") if p.is_file() else None

    bin_dir = root / "bin"
    check_sources = {f"bin/{p.name}": p.read_text(encoding="utf-8", errors="replace")
                     for p in sorted(bin_dir.glob("check-*")) if p.is_file()} if bin_dir.is_dir() else {}
    gates = detect_gate_changes(changed, read_candidate, check_sources)
    return derive_merge_danger(changed, diff_text, gates, read_candidate)
