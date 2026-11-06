"""Which lines of a workflow diff can change when or what the workflow sends.

The merge-danger notify trigger calls `workflow_send_change` once a workflow's whole text names
notify text. It walks the workflow's own diff section against the candidate file and classifies
each added or removed line by its key and its place in the file. No YAML library: an indentation
scanner builds the structure, and any shape it cannot read returns a reason, so the trigger fires.

Pure functions only. No subprocess, network, or file IO lives here.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, replace

_BENIGN_TOP_KEYS = frozenset({"name", "run-name", "concurrency", "permissions", "defaults"})
_BENIGN_SENDER_JOB_KEYS = frozenset({"runs-on", "timeout-minutes"})
_GATING_JOB_KEYS = frozenset({"if", "needs", "strategy", "continue-on-error", "outputs"})
_HUNK_RE = re.compile(r"^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@")
_KEY_RE = re.compile(r"""^([A-Za-z_][\w-]*|"[^"]+"|'[^']+')\s*:(?:\s|$)""")
_BLOCK_SCALAR_RE = re.compile(r":\s*[|>][+-]?[0-9]?\s*(?:#.*)?$")
_ANCHOR_RE = re.compile(r"(?::\s+|^\s*-\s+)[&*][A-Za-z_]|^\s*<<\s*:")
_SECTION_META = ("diff --git ", "index ", "--- ", "+++ ", "new file mode ", "deleted file mode ",
                 "old mode ", "new mode ", "similarity index ", "dissimilarity index ",
                 "rename from ", "rename to ", "copy from ", "copy to ")
_JOB_TOKEN_RE = re.compile(r"[A-Za-z_][\w-]*")

_R_MISALIGNED = "misaligned"
_R_TRUNCATED = "truncated-hunk"
_R_BAD_HUNK = "unparseable-hunk"
_R_BAD_SECTION = "unparseable-section"


@dataclass(frozen=True)
class LineScope:
    kind: str              # "content" | "scalar" | "comment" | "blank"
    top: str | None        # governing top-level key, quotes stripped ("on", "env", "jobs", ...)
    job: str | None        # job id when top == "jobs"
    job_key: str | None    # governing job-level key ("runs-on", "if", "steps", ...)
    is_job_id: bool        # the job id line itself
    indent: int


@dataclass(frozen=True)
class WorkflowShape:
    scopes: tuple[LineScope, ...]       # index = candidate line number - 1
    lines: tuple[str, ...]
    senders: frozenset[str]
    needed: frozenset[str]
    job_indent: int
    job_key_indent: dict[str, int]


def _own_key(raw: str) -> str | None:
    """The mapping key a line opens, quotes stripped. None for a sequence item or a non-key."""
    stripped = raw.lstrip(" ")
    if stripped.startswith("-"):
        return None
    m = _KEY_RE.match(stripped)
    return m.group(1).strip("\"'") if m else None


def _split_lines(text: str) -> list[str]:
    lines = text.split("\n")
    if lines and lines[-1] == "":
        lines.pop()
    return lines


def _job_sets(
    lines: list[str],
    scopes: list[LineScope],
    job_ids: list[str],
    notify_re: re.Pattern[str],
) -> tuple[frozenset[str], frozenset[str]]:
    """(senders, needed). A sender is a job whose span names notify text. Needed is the
    transitive `needs:` closure of the senders."""
    spans: dict[str, list[str]] = {j: [] for j in job_ids}
    needs: dict[str, list[str]] = {j: [] for j in job_ids}
    for raw, scope in zip(lines, scopes):
        if scope.top != "jobs" or scope.job is None:
            continue
        spans[scope.job].append(raw)
        if scope.job_key == "needs" and scope.kind == "content":
            tokens = _JOB_TOKEN_RE.findall(raw.replace("needs:", "", 1))
            needs[scope.job].extend(t for t in tokens if t in job_ids)
    named = frozenset(j for j, span in spans.items() if notify_re.search("\n".join(span)))
    # The candidate names notify text but no job span does: the token lives in top-level env,
    # so every job may send through the inherited env, and every job is a sender.
    senders = named if named else frozenset(job_ids)
    needed: set[str] = set()
    frontier = [n for s in senders for n in needs.get(s, ())]
    while frontier:
        j = frontier.pop()
        if j in job_ids and j not in needed:
            needed.add(j)
            frontier.extend(needs.get(j, ()))
    return senders, frozenset(needed)


def scan_workflow(text: str, notify_re: re.Pattern[str]) -> WorkflowShape | None:
    """One pass over the candidate text. None when the file is not a shape the scanner reads."""
    lines = _split_lines(text)
    scopes: list[LineScope] = []
    top: str | None = None
    job: str | None = None
    job_key: str | None = None
    job_indent: int | None = None
    key_indent: dict[str, int] = {}
    job_ids: list[str] = []
    scalar_owner: tuple[int, LineScope] | None = None
    seen_content = False

    for raw in lines:
        stripped = raw.strip()
        indent = len(raw) - len(raw.lstrip(" "))

        if scalar_owner is not None:
            threshold, owner = scalar_owner
            if not stripped or indent > threshold:
                scopes.append(replace(owner, kind="scalar", is_job_id=False))
                continue
            scalar_owner = None

        if not stripped:
            scopes.append(LineScope("blank", top, job, job_key, False, indent))
            continue
        if raw[indent] == "\t":
            return None
        if stripped.startswith("#"):
            scopes.append(LineScope("comment", top, job, job_key, False, indent))
            continue
        if _ANCHOR_RE.search(raw):
            return None
        if stripped in ("---", "..."):
            if stripped == "---" and not seen_content:
                scopes.append(LineScope("comment", top, job, job_key, False, indent))
                continue
            return None

        seen_content = True
        is_item = stripped.startswith("-")
        is_job_id = False
        if indent == 0:
            if is_item:
                if top is None:
                    return None
            else:
                key = _own_key(raw)
                if key is None:
                    return None
                top, job, job_key = key, None, None
        elif top == "jobs":
            if job_indent is None:
                job_indent = indent
            if indent < job_indent:
                return None
            if indent == job_indent:
                key = _own_key(raw)
                if key is None:
                    return None
                job, job_key, is_job_id = key, None, True
                job_ids.append(key)
            else:
                if job is None:
                    return None
                job_key_at = key_indent.setdefault(job, indent)
                if indent < job_key_at:
                    return None
                if indent == job_key_at and not is_item:
                    key = _own_key(raw)
                    if key is None:
                        return None
                    job_key = key
        elif top is None:
            return None

        scope = LineScope("content", top, job, job_key, is_job_id, indent)
        scopes.append(scope)
        if _BLOCK_SCALAR_RE.search(raw):
            scalar_owner = (indent + 2 if is_item else indent, scope)

    if job_indent is None or not job_ids:
        return None
    senders, needed = _job_sets(lines, scopes, job_ids, notify_re)
    return WorkflowShape(tuple(scopes), tuple(lines), senders, needed, job_indent, key_indent)


def _classify(body: str, scope: LineScope, shape: WorkflowShape, notify_re: re.Pattern[str]) -> str | None:
    if notify_re.search(body):
        return "notify-text"
    if scope.kind in ("comment", "blank"):
        return None
    if scope.top != "jobs":
        return None if scope.top in _BENIGN_TOP_KEYS else f"top-level:{scope.top}"
    if scope.job is None:
        return "top-level:jobs"
    if scope.is_job_id:
        return f"job-id:{scope.job}" if scope.job in shape.senders or scope.job in shape.needed else None
    if scope.job in shape.senders:
        return None if scope.job_key in _BENIGN_SENDER_JOB_KEYS else f"sender-job:{scope.job}"
    if scope.job in shape.needed and scope.job_key in _GATING_JOB_KEYS:
        return f"needed-job:{scope.job}:{scope.job_key}"
    return None


def _nearest_scope(shape: WorkflowShape, start: int, step: int) -> LineScope | None:
    """The first content or scalar scope from index `start`, walking by `step`."""
    i = start
    while 0 <= i < len(shape.scopes):
        if shape.scopes[i].kind in ("content", "scalar"):
            return shape.scopes[i]
        i += step
    return None


def _effective_scope(body: str, s: LineScope, in_scalar: bool, shape: WorkflowShape) -> LineScope:
    """The scope a removed line had, built from one neighbor and the line's own key."""
    if body.strip() == "":
        return replace(s, kind="blank")
    if body.lstrip().startswith("#"):
        # A `#` line inside a `run:` block is content, so it keeps the scalar neighbor's scope.
        return s if in_scalar else replace(s, kind="comment")
    indent = len(body) - len(body.lstrip(" "))
    own = _own_key(body)
    if indent == 0:
        return LineScope("content", own or "?", None, None, False, 0)
    if s.top == "jobs" and indent == shape.job_indent:
        return LineScope("content", "jobs", own or "?", None, True, indent)
    key_indent = shape.job_key_indent.get(s.job or "", -1)
    if own is not None and indent == key_indent:
        return replace(s, kind="content", job_key=own, is_job_id=False)
    return s


def _classify_removed(body: str, cursor: int, shape: WorkflowShape, notify_re: re.Pattern[str]) -> str | None:
    """A removed line sat just before candidate line `cursor` (1-based). Its own key decides the
    key, and either neighbor's scope may fire it, so a block boundary fails closed."""
    neighbors = (_nearest_scope(shape, cursor - 2, -1), _nearest_scope(shape, cursor - 1, 1))
    neighbor_scopes = [s for s in neighbors if s is not None]
    if not neighbor_scopes:
        return _R_MISALIGNED
    in_scalar = any(s.kind == "scalar" for s in neighbor_scopes)
    effective_scopes = [_effective_scope(body, s, in_scalar, shape) for s in neighbor_scopes]
    reasons = [_classify(body, e, shape, notify_re) for e in effective_scopes]
    return next((r for r in reasons if r), None)


def _matches_candidate(shape: WorkflowShape, cursor: int, body: str) -> bool:
    return 1 <= cursor <= len(shape.lines) and shape.lines[cursor - 1] == body


def _walk_hunks(lines: list[str], start: int, shape: WorkflowShape, notify_re: re.Pattern[str]) -> str | None:
    """Classify every changed line from the first hunk on. The first reason wins."""
    i = start
    while i < len(lines):
        if lines[i].startswith("\\"):
            i += 1
            continue
        m = _HUNK_RE.match(lines[i])
        if m is None:
            return _R_BAD_HUNK
        i += 1
        old_left = int(m.group(2)) if m.group(2) is not None else 1
        new_start = int(m.group(3))
        new_left = int(m.group(4)) if m.group(4) is not None else 1
        cursor = new_start if new_left else new_start + 1
        while old_left > 0 or new_left > 0:
            if i >= len(lines):
                return _R_TRUNCATED
            raw = lines[i]
            i += 1
            if raw.startswith("\\"):
                continue
            tag, body = (raw[0], raw[1:]) if raw else (" ", "")
            if tag == " ":
                if old_left <= 0 or new_left <= 0 or not _matches_candidate(shape, cursor, body):
                    return _R_MISALIGNED
                old_left -= 1
                new_left -= 1
                cursor += 1
            elif tag == "+":
                if new_left <= 0 or not _matches_candidate(shape, cursor, body):
                    return _R_MISALIGNED
                reason = _classify(body, shape.scopes[cursor - 1], shape, notify_re)
                if reason:
                    return reason
                new_left -= 1
                cursor += 1
            elif tag == "-":
                if old_left <= 0:
                    return _R_MISALIGNED
                reason = _classify_removed(body, cursor, shape, notify_re)
                if reason:
                    return reason
                old_left -= 1
            else:
                return _R_BAD_HUNK
    return None


def workflow_send_change(candidate: str, section: str | None, notify_re: re.Pattern[str]) -> str | None:
    """A reason when the workflow's own diff section can change when or what it sends, else None.
    Every input this cannot read returns a reason, so the caller fires."""
    if section is None:
        return "no-diff-section"
    shape = scan_workflow(candidate, notify_re)
    if shape is None:
        return "unreadable-candidate"
    lines = _split_lines(section)
    i = 0
    while i < len(lines) and not lines[i].startswith("@@"):
        if not lines[i].startswith(_SECTION_META):
            return _R_BAD_SECTION
        i += 1
    return _walk_hunks(lines, i, shape, notify_re)
