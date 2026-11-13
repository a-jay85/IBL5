"""The PR body's `**Merge danger:**` field: mechanical derivation, verdict merge, parse, render.

Pure functions only. No subprocess, network, or git calls live here, apart from the one IO shim
`derive_from_worktree`, which reads candidate files from a checked-out tree. The field format is
the contract between `/post-plan` Phase 2 (writer), the harness body check (validator), Phase 6.5
condition (18) (reader), and `bin/digest-dm-build` (reader). The Phase 6.5 shell twin is
`pr_merge_danger_hold` in `bin/lib/pr-armable.sh`; keep its regex in step with `FIELD_RE`.
The optional ` · Self-governing:` segment before Why accepts only `yes`; any other value is malformed.

The door is derived from the realized diff, and that floor is never lowered: the LLM (or a
human) may move `two-way` to `one-way` or widen the blast radius. An LLM one-way above the floor
must pass the raise bar at Phase 2 and on a later phase that owns the line (its round-start line
equals the harness's saved line, `harness/savedline.py`). Any other later phase is raise-only, so
a human raise survives. `Door: one-way` means an effect `git revert` cannot undo (data loss,
prod external state, a message sent to a GM). A diff that
edits the merge machinery itself (an arming path, a removed gate) sets the Self-governing segment
instead; condition (18) holds on either signal, and nothing may drop a floor marker.
"""
from __future__ import annotations

import posixpath
import re
from collections.abc import Callable, Iterable
from dataclasses import dataclass
from pathlib import Path

from harness import rebuildable
from harness.armable import DESTRUCTIVE_SQL
from harness.sql_comments import strip_added_sql_comments
from harness.external_scope import external_send_change
from harness.gate_backtest import GateChange, changes_from_unified_diff, detect_gate_changes
# Private name imported on purpose: the arming paths are the bootstrap-hazard set, and a public
# alias would mean another edit to a file several open PRs touch.
from harness.gate_backtest import _ARMING_PATHS, _SEAT_FEED_PATH, _SEAT_FEED_RE

DOORS = ("two-way", "one-way")                                  # index = rank; higher is more dangerous
BLAST = ("localized", "module", "site-wide", "external")        # index = rank
WHY_MIN, WHY_MAX = 20, 200
FIELD_PREFIX = "**Merge danger:**"
SELF_GOVERNING_KINDS = ("arming-path", "gate-removed")
FIELD_RE = re.compile(
    r"^\*\*Merge danger:\*\* Door: (?P<door>[a-z-]+) · Blast radius: (?P<blast>[a-z-]+)(?: · Self-governing: (?P<sg>[a-z-]+))? · Why: (?P<why>.+)$"
)
_LINE_RE = re.compile(r"^\*\*Merge danger:\*\*")
_EXTERNAL_PATHS = ("bin/db-sync-prod", ".github/workflows/promote-to-production.yml", "bin/discord-dm")
_EXTERNAL_PREFIXES = (".github/actions/notify-discord/", "ibl5/classes/Mail/")
# Narrowed external paths: their own changed hunks decide (harness/external_scope.py). Every other
# _EXTERNAL_PATHS member, today .github/workflows/promote-to-production.yml, fires on any touch.
_NARROWED_EXTERNAL_PATHS = ("bin/db-sync-prod", "bin/discord-dm")
# Notify text a workflow carries. No floor trigger reads it now; test_workflow_scope does.
_NOTIFY_TEXT_RE = re.compile(r"notify-discord|DISCORD_")
_SITE_WIDE_PATHS = ("ibl5/.htaccess", "ibl5/router.php", "ibl5/composer.json")
_MIGRATION_RE = re.compile(r"^ibl5/migrations/.*\.sql$")
_DIFF_HEADER_RE = re.compile(r"^diff --git a/.* b/(.*)$", re.MULTILINE)
_RENAME_RE = re.compile(r"^rename from (.+)\nrename to (.+)$", re.MULTILINE)


@dataclass(frozen=True)
class MergeDanger:
    door: str
    blast: str
    why: str
    triggers: tuple[str, ...] = ()
    self_governing: bool = False


class MalformedMergeDanger(ValueError):
    """The body carries a `**Merge danger:**` line that does not parse, or more than one."""


def _diff_sections(diff_text: str) -> list[tuple[str, str]] | None:
    """(b-path, section text) per `diff --git` header, in order. None when the text has no
    header at all: an empty diff or a bare hunk."""
    headers = list(_DIFF_HEADER_RE.finditer(diff_text))
    if not headers:
        return None
    sections: list[tuple[str, str]] = []
    for i, m in enumerate(headers):
        end = headers[i + 1].start() if i + 1 < len(headers) else len(diff_text)
        sections.append((m.group(1), diff_text[m.start():end]))
    return sections


def _migration_sections(diff_text: str) -> str:
    """The diff text belonging to migration files. A diff without `diff --git` headers is
    searched whole, so a bare hunk still counts."""
    sections = _diff_sections(diff_text)
    if sections is None:
        return diff_text
    return "".join(text for path, text in sections if _MIGRATION_RE.match(path))


def _section_for(diff_text: str, path: str) -> str | None:
    """The section whose `b/` path is `path`. None when the diff has no header, when the
    file's header is quoted (`diff --git "a/x y" "b/x y"` matches no header regex), or when
    the file has no section. The caller treats None as unreadable and fires."""
    for section_path, text in _diff_sections(diff_text) or []:
        if section_path == path:
            return text
    return None


def _touches_seat_feed(section: str | None) -> bool:
    """True when a changed line of runner.py's section names the second reviewer's feed.
    An unreadable section fires (fail closed)."""
    if section is None:
        return True
    for line in section.splitlines():
        if line.startswith(("+++", "---")) or not line.startswith(("+", "-")):
            continue
        if _SEAT_FEED_RE.search(line):
            return True
    return False


def _external_path_fires(status: str, parts: list[str], diff_text: str) -> bool:
    """True when an external path's diff can change what is synced, promoted or sent. A delete,
    a rename to or from the path, a path outside the narrowed set, or a section the parser
    cannot read fires (fail closed)."""
    if not status.startswith(("M", "A")) or len(parts) > 1:
        return True
    path = parts[0]
    if path not in _NARROWED_EXTERNAL_PATHS and not path.startswith(_EXTERNAL_PREFIXES):
        return True
    return external_send_change(path, _section_for(diff_text, path)) is not None


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
    """The mechanical floor. A destructive, external or notify trigger makes the door one-way;
    an arming-path or gate-removed trigger sets self_governing and leaves the door alone.

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

    # 1. Destructive migration. SQL comments on added lines are blanked first, so a rollback
    # note or a `-- G1: ... would truncate` remark does not force the door; an unterminated
    # block or quote is matched raw (fail closed). The `-- destructive-migration[trigger]:`
    # bypass marker is never read: a bypassed destructive migration is still irreversible.
    migrations = [p for p in paths if _MIGRATION_RE.match(p)]
    if migrations and DESTRUCTIVE_SQL.search(
            strip_added_sql_comments(_migration_sections(diff_text or ""))):
        for p in migrations:
            add(f"destructive-migration:{p}")

    # 2. Prod external state and outbound senders that can reach a GM.
    external = False
    # changes_from_unified_diff reports a rename by its new path only; the old path comes from
    # the diff's `rename from` lines. A rename away from an external path is still a touch of it.
    renamed_from = {new: old for old, new in _RENAME_RE.findall(diff_text or "")}
    for status, p in pairs:
        parts = p.split(" → ")
        wf = parts[-1]
        if status.startswith("R") and len(parts) == 1 and wf in renamed_from:
            parts = [renamed_from[wf], wf]
        if any(q in _EXTERNAL_PATHS or q.startswith(_EXTERNAL_PREFIXES) for q in parts):
            # The path's own changed hunks decide; an unreadable diff fires (fail closed).
            if _external_path_fires(status, parts, diff_text or ""):
                add(f"external-path:{p}")
                external = True
        # A workflow that calls notify-discord posts only to the owner's #dev, #merged and
        # #reviews channels, so editing its send lines is two-way. The action itself stays an
        # external path above: one edit there could add a GM-reaching webhook.

    # 3. Self-governing merge machinery (the bootstrap hazard). These triggers set the
    # Self-governing segment and leave the Door alone: `git revert` undoes them.
    for p in paths:
        if p in _ARMING_PATHS:
            add(f"arming-path:{p}")
        elif p == _SEAT_FEED_PATH and _touches_seat_feed(_section_for(diff_text or "", p)):
            add(f"arming-path:{p}")
    for g in gate_changes:
        if g.kind == "arming-harness":
            add(f"arming-path:{g.path}")

    # 4. Gate removal.
    for g in gate_changes:
        if g.state == "removed":
            add(f"gate-removed:{g.path}")

    door_triggers = [t for t in triggers if t.split(":", 1)[0] not in SELF_GOVERNING_KINDS]
    door = "one-way" if door_triggers else "two-way"
    self_governing = len(door_triggers) < len(triggers)
    blast = _blast_radius(paths, gate_changes, external)
    why = ("mechanical: " + "; ".join(triggers)) if triggers else \
        "mechanical: no one-way trigger in the realized diff"
    return MergeDanger(door, blast, why[:WHY_MAX].rstrip(), tuple(triggers),
                       self_governing=self_governing)


def _rank(value: str | None, scale: tuple[str, ...]) -> int:
    return scale.index(value) if value in scale else 0


def _clean_why(text: str | None) -> str:
    return re.sub(r"\s+", " ", (text or "").replace("|", " ")).strip()


_MECH_PREFIX_RE = re.compile(r"^(?:mechanical: .*?(?:\. |$))+")


def _llm_core(text: str | None) -> str:
    """The LLM's or a human's own words: the Why with every leading `mechanical: ...` prefix
    removed. A prior phase's line is `mechanical: <triggers>. <words>`, so re-applying the
    floor must strip that prefix before composing, or the prefix compounds once per phase.
    A line an older harness already doubled loses both copies."""
    return _MECH_PREFIX_RE.sub("", _clean_why(text), count=1).strip()


# Raise bar on an LLM-raised door, run at Phase 2 and on a harness-owned re-judge. A one-way the
# floor did not raise is kept only when the Why names an effect `git revert` cannot undo. A later
# phase whose round-start line differs from the harness's saved line passes llm_raise_bar=False,
# so a human raise survives.
_IRREVERSIBLE_RE = re.compile(
    r"\b(drop(s|ped|ping)?|delet(e|es|ed|ing|ion)|truncat(e|es|ed|ing|ion)|renam(e|es|ed|ing)"
    r"|overwr(ite|ites|iting|itten|ote)|purg(e|es|ed|ing)|destroy(s|ed|ing)?|wip(e|es|ed|ing)"
    r"|los(e|es|ing|t|s)|irreversibl[ey]|unrecoverabl[ey]|cannot be (undone|restored|recalled|reverted)"
    r"|send(s|ing)?|sent|recall(s|ed|ing)?|notif(y|ies|ied|ying|ication)|email(s|ed|ing)?|discord"
    r"|prod(uction)?[- ]?(sync(s|ed|ing)?|data|db|database|rows?))\b",
    re.IGNORECASE,
)


# Exemptions from the bar. A one-way Why whose only cited loss is rows of a registered
# rebuildable table, or an alert to an owner-only channel, is refused: a refresh step rebuilds
# the rows, and only the owner reads #dev, #merged and #reviews. Any schema verb, GM-reaching
# sender, prod token or unregistered table keeps the raise.
_OWNER_CHANNEL_RE = re.compile(
    r"DISCORD_(DEV|MERGED|REVIEWS)_WEBHOOK_URL|notify-discord|(?<![\w#])#(dev|merged|reviews)\b"
    r"|\.github/workflows/|\b(alert|notification)s?\s+(job|step|message)s?\b", re.IGNORECASE)
_KEEP_ONE_WAY_RE = re.compile(
    r"\bdrop(s|ped|ping)?\s+(table|column|index)\b|\balter(s|ed|ing)?\b|\brenam\w*|\bschema\b"
    r"|\bcolumns?\b|discord-dm|\bDMs?\b|direct message|\bGMs?\b|e-?mail|classes/Mail"
    r"|\bfiles?\b|\bphotos?\b|\bartifacts?\b|db-sync-prod|promote-to-production|\bprod(uction)?[- ]?(sync|data|db|database)\b",
    re.IGNORECASE)
# Any table-like token: an `ibl_`/`nuke_` name, the shared `cache` table, or a backticked
# snake_case name. An unregistered one keeps the raise.
_TABLE_TOKEN_RE = re.compile(r"\b(?:ibl|nuke)_\w+\b|\bcache\b|(?<=`)\w+_\w+(?=`)")


# Irreversible words an owner-channel alert may use without losing the exemption.
_ALERT_WORD_RE = re.compile(
    r"send(s|ing)?|sent|recall(s|ed|ing)?|cannot be recalled|notif(y|ies|ied|ying|ication)|discord",
    re.IGNORECASE)


def _bar_exempt(core: str) -> bool:
    """True when the Why's only cited loss is rebuildable-table rows or an owner-only alert."""
    names_registered = any(t in rebuildable.REBUILDABLE_TABLES for t in _TABLE_TOKEN_RE.findall(core))
    if not (names_registered or _OWNER_CHANNEL_RE.search(core)):
        return False
    if not names_registered and any(
            not _ALERT_WORD_RE.fullmatch(m.group(0)) for m in _IRREVERSIBLE_RE.finditer(core)):
        return False  # owner-channel mention alone: any other irreversible loss keeps the raise
    if _KEEP_ONE_WAY_RE.search(core):
        return False
    if any(t not in rebuildable.REBUILDABLE_TABLES for t in _TABLE_TOKEN_RE.findall(core)):
        return False
    return True


def merge_verdicts(
    mechanical: MergeDanger,
    llm_door: str | None,
    llm_blast: str | None,
    llm_why: str | None,
    *,
    llm_raise_bar: bool = False,
    llm_self_governing: bool = False,
) -> MergeDanger:
    """Rank max on both axes. An unknown or absent LLM value ranks 0, so it never lowers.

    llm_raise_bar: True at Phase 2 and on a harness-owned re-judge in a later phase. A one-way
    the floor did not raise is kept only when the Why (prefix stripped) names a concrete
    irreversible effect per _IRREVERSIBLE_RE and is not bar-exempt; otherwise the door stays at
    the mechanical floor. Blast radius is never subject to the bar.

    llm_self_governing: the marker the LLM or the live body carries. The floor marker is never
    dropped. With llm_raise_bar=True an LLM marker above the floor is ignored, mirroring the door
    bar (the owned re-judge caller restores a marker its round-start line carried); a raise-only
    later phase keeps a human-added marker."""
    # Every [:WHY_MAX] cut is re-stripped: a cut landing after a space would leave a trailing
    # space that the next phase's _clean_why drops, so the line would change between phases.
    core = _llm_core(llm_why)[:WHY_MAX].rstrip()
    llm_rank = _rank(llm_door, DOORS)
    if (llm_raise_bar and mechanical.door == "two-way" and llm_door == "one-way"
            and (not _IRREVERSIBLE_RE.search(core) or _bar_exempt(core))):
        llm_rank = 0
    door = DOORS[max(_rank(mechanical.door, DOORS), llm_rank)]
    blast = BLAST[max(_rank(mechanical.blast, BLAST), _rank(llm_blast, BLAST))]
    if len(core) < WHY_MIN:
        why = mechanical.why
    elif mechanical.door == "one-way" or mechanical.self_governing:
        # Cut first, then re-check: a long prefix can leave under WHY_MIN core chars, and a
        # later re-application would see that short core and fall back, breaking the fixed point.
        why = f"{mechanical.why}. {core}"[:WHY_MAX].rstrip()
        if len(why) - len(mechanical.why) - 2 < WHY_MIN:
            why = mechanical.why
    else:
        why = core
    return MergeDanger(door, blast, why[:WHY_MAX], mechanical.triggers,
                       self_governing=mechanical.self_governing or (llm_self_governing and not llm_raise_bar))


def render_merge_danger(md: MergeDanger) -> str:
    why = _clean_why(md.why)[:WHY_MAX]
    seg = " · Self-governing: yes" if md.self_governing else ""
    return f"{FIELD_PREFIX} Door: {md.door} · Blast radius: {md.blast}{seg} · Why: {why}"


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
    sg = m.group("sg")
    why_text = m.group("why")
    if sg is not None and sg != "yes":
        raise MalformedMergeDanger(f"Self-governing must be 'yes' when present, got {sg!r}")
    if " · Self-governing:" in why_text:
        raise MalformedMergeDanger("Self-governing segment must sit before Why")
    return MergeDanger(door, blast, why, self_governing=(sg == "yes"))


def classify_body(body: str | None) -> str:
    """"ONE-WAY" | "SELF-GOVERNING" | "TWO-WAY" | "MISSING" | "MALFORMED" | "UNREADABLE".

    SELF-GOVERNING is a two-way door carrying the Self-governing segment; a one-way door with
    the segment is ONE-WAY."""
    if body is None:
        return "UNREADABLE"
    try:
        md = parse_merge_danger(body)
    except MalformedMergeDanger:
        return "MALFORMED"
    if md is None:
        return "MISSING"
    if md.door == "two-way" and md.self_governing:
        return "SELF-GOVERNING"
    return "ONE-WAY" if md.door == "one-way" else "TWO-WAY"


def classify_with_floor(body: str | None, floor: MergeDanger) -> str:
    """classify_body re-checked against this diff's mechanical floor (raise-only).

    A TWO-WAY body over a one-way floor is ONE-WAY; over a self-governing floor it is
    SELF-GOVERNING. Any other state already holds and is returned unchanged."""
    state = classify_body(body)
    if state != "TWO-WAY":
        return state
    if floor.door == "one-way":
        return "ONE-WAY"
    return "SELF-GOVERNING" if floor.self_governing else state


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
