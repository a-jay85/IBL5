"""Sweep a plan's `## Out of Scope` deferrals into backlog issues.

The extractor is pure (no I/O, no `gh` call), so every engine shares one definition of
"a deferral". The filer dedups against every existing backlog issue and never raises.
"""
from __future__ import annotations

import hashlib
import re
from dataclasses import dataclass

from .planfile import _section, _strip_fenced
from .state import HarnessError

OOS_LABEL = "maintenance"
MAX_HITS_PER_PLAN = 5
_TAG_RE = re.compile(r"\[oos-[0-9a-f]{10}\]")

DEFERRAL_RE = re.compile(
    r"\bfile[sd]?\s+(?:it\s+|this\s+|them\s+)?separately\b"
    r"|\b(?:its|their)\s+own\s+(?:plan|PR|issue)\b"
    r"|\bseparate\s+(?:PR|plan|issue|ticket)\b"
    r"|\bfollow-?up\s+(?:PR|plan|issue|ticket)\b"
    r"|\b(?:later|future)\s+(?:PR|plan)\b",
    re.I,
)

# The item already points at a tracked issue or PR.
CITED_RE = re.compile(r"(?:\b[\w.-]+/[\w.-]+#\d+\b|(?<![\w/])#\d+\b|/issues/\d+|/pull/\d+)")

# The item states a decision to exclude, not a deferral.
REJECTION_RE = re.compile(
    r"\b(?:not\s+a\s+deferred|resolved\s+decision|rejected|won'?t\s+(?:do|fix)"
    r"|not\s+planned|no\s+plan\s+to|out\s+of\s+scope\s+permanently)\b",
    re.I,
)

_LIST_MARKER_RE = re.compile(r"^\s{0,3}(?:[-*+]|\d+[.)])\s+")
_HEADING_RE = re.compile(r"^#+ *Out of Scope", re.I)


@dataclass(frozen=True)
class DeferralHit:
    text: str  # item text, list marker stripped, whitespace collapsed
    line_no: int  # 1-based line in the ORIGINAL plan of the item's first line
    key: str  # dedup key, see dedup_key()


def normalize(text: str) -> str:
    t = re.sub(r"[`*_]", "", text)  # drop inline-code and emphasis markers
    return re.sub(r"\s+", " ", t).strip().lower()


def dedup_key(slug: str, text: str) -> str:
    digest = hashlib.sha1(f"{slug}\n{normalize(text)}".encode()).hexdigest()[:10]
    return f"oos-{digest}"


def _group_items(section: str) -> list[list[str]]:
    """Split the section into logical items (raw lines), one per bullet or paragraph."""
    items: list[list[str]] = []
    current: list[str] | None = None
    for line in section.splitlines():
        if not line.strip():
            current = None
            continue
        if current is None or _LIST_MARKER_RE.match(line):
            current = [line]
            items.append(current)
        else:
            current.append(line)
    return items


def extract_deferral_hits(content: str, slug: str) -> list[DeferralHit]:
    """Deferral items in `## Out of Scope`, ordered by line, deduped by key."""
    section = _section("\n".join(_strip_fenced(content)), r"Out of Scope")
    if not section:
        return []
    raw_lines = content.splitlines()
    cursor = next((i for i, ln in enumerate(raw_lines) if _HEADING_RE.match(ln)), 0)
    hits: list[DeferralHit] = []
    seen: set[str] = set()
    for item in _group_items(section):
        first = item[0]
        text = " ".join(
            _LIST_MARKER_RE.sub("", ln, count=1) if i == 0 else ln for i, ln in enumerate(item)
        )
        text = re.sub(r"\s+", " ", text).strip()
        # Advance the cursor to this item's first raw line, even when it is dropped,
        # so a later duplicate line resolves to its own position.
        line_idx = cursor
        for j in range(cursor, len(raw_lines)):
            if raw_lines[j] == first:
                line_idx = j
                break
        cursor = line_idx + 1
        if not DEFERRAL_RE.search(text):
            continue
        if CITED_RE.search(text) or REJECTION_RE.search(text):
            continue
        key = dedup_key(slug, text)
        if key in seen:
            continue
        seen.add(key)
        hits.append(DeferralHit(text=text, line_no=line_idx + 1, key=key))
    return sorted(hits, key=lambda h: h.line_no)


def _noop_log(_msg: str) -> None:
    return


def _short(text: str, limit: int = 80) -> str:
    if len(text) <= limit:
        return text
    cut = text[:limit].rsplit(" ", 1)[0] or text[:limit]
    return cut + "..."


def file_deferral_issues(gh, hits: list[DeferralHit], slug: str,
                         pr_number: int, log=None, *,
                         plan_name: str | None = None) -> list[int]:
    """File one backlog issue per hit, deduped by `[oos-<key>]` tag. Never raises.

    A failed dedup read files nothing: a missed filing is recovered by the next rerun,
    while a duplicate never cleans itself up.
    """
    log = log or _noop_log
    if not hits:
        return []
    try:
        existing = gh.issue_titles(None, strict=True)
    except (HarnessError, OSError) as exc:
        log(f"oos-sweep: dedup read failed ({exc}); filing nothing")
        return []
    seen = {m.group(0) for t in existing for m in [_TAG_RE.search(t)] if m}
    if len(hits) > MAX_HITS_PER_PLAN:
        log(f"oos-sweep: {len(hits) - MAX_HITS_PER_PLAN} hits over cap, not filed")
        hits = hits[:MAX_HITS_PER_PLAN]
    nums: list[int] = []
    pr_link = f"https://github.com/a-jay85/IBL5/pull/{pr_number}"
    plan_ref = plan_name or f"{slug}.md"
    for hit in hits:
        tag = f"[{hit.key}]"
        if tag in seen:
            log(f"oos-sweep: skipping duplicate {hit.key}")
            continue
        title = f"Deferred from {slug}: {_short(hit.text)} {tag}"
        body = (
            f"{pr_link}\n\n"
            f"Deferred in the `## Out of Scope` section of plan `{slug}` "
            f"(~/claude-plans/{plan_ref}:{hit.line_no}):\n\n"
            f"> {hit.text}\n\n"
            f"Filed by the post-plan out-of-scope sweep. Dedup key: {hit.key}.\n"
        )
        try:
            n = gh.issue_create(title, body, OOS_LABEL)
        except (HarnessError, OSError) as exc:
            log(f"oos-sweep: issue_create failed for {hit.key} ({exc})")
            continue
        if n is not None:
            nums.append(n)
        seen.add(tag)
        log(f"oos-sweep: filed #{n} {hit.key}")
    return nums
