"""Owner-approved deviation records: parse, owner-only trust, anchors, snapshot.

The only input is the REST issue-comment list. Author fields come from
GitHub's `user.login` / `user.type`, never from text inside a body, and the PR
body is never read, so a bot-written body block has no effect.
"""
from __future__ import annotations

import collections
import hashlib
import json
import re
from dataclasses import dataclass

from .state import HarnessError

APPROVAL_MARKER = "<!-- postplan-owner-approval -->"
OWNER_LOGIN = "a-jay85"
RECORD_SCHEMA = 1
RECORD_ID_RE = re.compile(r"^oad-[0-9a-f]{8,16}$")

TRIVIAL_ANCHORS = frozenset({
    "}", "{", "};", ");", "]);", "end", "else", "else {", "} else {",
    "return;", "*/", "/**", "<?php", "?>", "EOF",
})

_FENCE_RE = re.compile(r"```json[ \t]*\n(.*?)```", re.DOTALL)


def is_owner(login: str, typename: str) -> bool:
    return login == OWNER_LOGIN and typename == "User"


def normalize_anchor(line: str) -> str:
    return " ".join(line.split())


def is_trivial_anchor(norm: str) -> bool:
    if len(norm) < 8 or norm in TRIVIAL_ANCHORS:
        return True
    return re.search(r"[A-Za-z0-9_]{3,}", norm) is None


@dataclass(frozen=True)
class ApprovedFile:
    path: str
    keep: tuple[str, ...]
    gone: tuple[str, ...]


@dataclass(frozen=True)
class Record:
    id: str
    summary: str
    comment_id: int
    files: tuple[ApprovedFile, ...]


@dataclass(frozen=True)
class ApprovalSnapshot:
    records: tuple[Record, ...]
    fingerprint: str
    ignored_untrusted: int


def _fail(comment_id: int, reason: str) -> HarnessError:
    return HarnessError("owner-approval-record", f"comment {comment_id}: {reason}")


def _clean_anchors(raw: object, comment_id: int, field: str) -> list[str]:
    if raw is None:
        return []
    if not isinstance(raw, list) or not all(isinstance(a, str) for a in raw):
        raise _fail(comment_id, f"{field} must be a list of strings")
    out = []
    for a in raw:
        norm = normalize_anchor(a)
        if not is_trivial_anchor(norm):
            out.append(norm)
    return out


def _valid_path(path: object) -> bool:
    if not isinstance(path, str) or not path or path.startswith("/"):
        return False
    return all(seg not in ("", "..") for seg in path.split("/"))


def parse_record(body: str, comment_id: int) -> Record:
    if APPROVAL_MARKER not in [ln.strip() for ln in body.splitlines()]:
        raise _fail(comment_id, "marker missing on its own line")
    fences = _FENCE_RE.findall(body)
    if len(fences) != 1:
        raise _fail(comment_id, f"expected exactly one json fence, found {len(fences)}")
    try:
        data = json.loads(fences[0])
    except json.JSONDecodeError as exc:
        raise _fail(comment_id, f"json unparsable ({exc.msg})") from None
    if not isinstance(data, dict):
        raise _fail(comment_id, "record must be a JSON object")
    if data.get("schema") != RECORD_SCHEMA:
        raise _fail(comment_id, "unsupported schema")
    rid = data.get("id")
    if not isinstance(rid, str) or not RECORD_ID_RE.match(rid):
        raise _fail(comment_id, "invalid id")
    summary = data.get("summary")
    if not isinstance(summary, str) or not summary.strip():
        raise _fail(comment_id, "empty summary")
    raw_files = data.get("files")
    if not isinstance(raw_files, list) or not raw_files:
        raise _fail(comment_id, "files must be a non-empty list")
    files = []
    for entry in raw_files:
        if not isinstance(entry, dict):
            raise _fail(comment_id, "file entry must be an object")
        path = entry.get("path")
        if not _valid_path(path):
            raise _fail(comment_id, f"invalid path {path!r}")
        keep = _clean_anchors(entry.get("keep"), comment_id, "keep")
        gone = _clean_anchors(entry.get("gone"), comment_id, "gone")
        if not keep and not gone:
            raise _fail(comment_id, f"no non-trivial anchors for {path}")
        files.append(ApprovedFile(path, tuple(keep), tuple(gone)))
    return Record(rid, summary, comment_id, tuple(files))


def _fingerprint(owner_comments: list[dict]) -> str:
    parts = sorted(
        f"{c['id']}:{hashlib.sha256(c['body'].encode('utf-8')).hexdigest()}"
        for c in owner_comments
    )
    return hashlib.sha256("\n".join(parts).encode("utf-8")).hexdigest()


def snapshot(gh, pr: int) -> ApprovalSnapshot:
    comments = [c for c in gh.pr_issue_comments(pr)
                if APPROVAL_MARKER in (c.get("body") or "")]
    owner = [c for c in comments if is_owner(c.get("login", ""), c.get("type", ""))]
    ignored = len(comments) - len(owner)
    records: list[Record] = []
    seen: set[str] = set()
    for c in owner:
        rec = parse_record(c["body"], int(c["id"]))
        if rec.id in seen:
            raise HarnessError("owner-approval-record", f"duplicate id {rec.id}")
        seen.add(rec.id)
        records.append(rec)
    return ApprovalSnapshot(tuple(records), _fingerprint(owner), ignored)


def verify_unchanged(gh, pr: int, snap: ApprovalSnapshot) -> None:
    if snapshot(gh, pr).fingerprint != snap.fingerprint:
        raise HarnessError("owner-approval-drift",
                           "owner-approval comments changed during the run")


def anchors_by_path(snap: ApprovalSnapshot) -> dict[str, tuple[collections.Counter, collections.Counter]]:
    out: dict[str, tuple[collections.Counter, collections.Counter]] = {}
    for rec in snap.records:
        for f in rec.files:
            keep, gone = out.setdefault(f.path, (collections.Counter(), collections.Counter()))
            keep.update(f.keep)
            gone.update(f.gone)
    return out
