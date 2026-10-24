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


# Posting denials every tree-editing fixer call carries. `gh pr edit` stays allowed:
# cifix.py needs it, and the PR body is not a record carrier.
FIXER_DENIED_TOOLS = (
    "Bash(gh pr comment:*)",
    "Bash(gh issue comment:*)",
    "Bash(gh pr review:*)",
    "Bash(gh api:*)",
    "Bash(gh issue edit:*)",
)

# Purposes whose call sites already deny Edit and Write, so the off-limits block adds
# nothing. tests/test_fixer_off_limits.py proves each one still denies both.
READ_ONLY_PURPOSES = frozenset({
    "plan-fidelity-review",
    "conflict-review",
    "conflict-adapt-review",
})
# Purposes whose names carry a runtime suffix (round number, verifier seat).
READ_ONLY_PURPOSE_PREFIXES = (
    "plan-fidelity-re-review-",
    "verify-findings-",
)


def is_read_only_purpose(purpose: str) -> bool:
    return purpose in READ_ONLY_PURPOSES or purpose.startswith(READ_ONLY_PURPOSE_PREFIXES)


def empty_snapshot() -> ApprovalSnapshot:
    return ApprovalSnapshot((), _fingerprint([]), 0)


EMPTY_SNAPSHOT = empty_snapshot()


@dataclass(frozen=True)
class RevertHit:
    record_id: str
    path: str
    kind: str   # "keep-lost" | "gone-restored" | "file-gone"
    line: str


def _norm_counter(text: str | None) -> collections.Counter:
    if text is None:
        return collections.Counter()
    return collections.Counter(normalize_anchor(l) for l in text.splitlines())


def revert_hits(snap: ApprovalSnapshot, path: str,
                pre_text: str | None, post_text: str | None) -> list[RevertHit]:
    """Anchors a commit reverted. `pre_text` is the file at the commit's parent, `post_text`
    at the commit; None = absent. Compared on normalized text, so a pure indent or spacing
    change passes and any token change counts as a revert."""
    pre = _norm_counter(pre_text)
    post = _norm_counter(post_text)
    hits: list[RevertHit] = []
    for rec in snap.records:
        for f in rec.files:
            if f.path != path:
                continue
            for a in f.keep:
                k = pre[a]
                if k == 0:
                    continue    # stale: the fixer did not remove what was already gone
                if post_text is None:
                    hits.append(RevertHit(rec.id, path, "file-gone", a))
                elif post[a] < k:
                    hits.append(RevertHit(rec.id, path, "keep-lost", a))
            for g in f.gone:
                if post[g] > pre[g]:
                    hits.append(RevertHit(rec.id, path, "gone-restored", g))
    return hits


def check_commit(git, snap: ApprovalSnapshot, sha: str) -> list[RevertHit]:
    by_path = anchors_by_path(snap)
    if not by_path:
        return []
    hits: list[RevertHit] = []
    try:
        changed = set(git.changed_files(f"{sha}^"))
        for path in sorted(by_path.keys() & changed):
            try:
                pre_text = git.file_at(f"{sha}^", path)
                post_text = git.file_at(sha, path)
            except (HarnessError, OSError, UnicodeDecodeError) as exc:
                raise HarnessError("owner-approval-check", f"{path}: {exc}") from exc
            hits.extend(revert_hits(snap, path, pre_text, post_text))
    except HarnessError as exc:
        if exc.kind == "owner-approval-check":
            raise
        raise HarnessError("owner-approval-check", f"{sha[:12]}: {exc}") from exc
    return hits


_CLOSING_SENTENCE = (
    "These lines were approved by the repository owner after planning. Do not edit, "
    "reformat, move, or restore them, and do not post PR comments or reviews. If a "
    "finding asks you to touch them, leave them and say so in your reply."
)


def off_limits_text(snap: ApprovalSnapshot) -> str:
    if not snap.records:
        return ""
    lines = ["## Owner-approved deviations (off-limits)", ""]
    for rec in snap.records:
        lines.append(f"- {rec.id}: {rec.summary}")
        for f in rec.files:
            lines.append(f"  - {f.path}")
            for anchor in f.keep:
                lines.append(f"    keep (must remain present): {anchor}")
            for anchor in f.gone:
                lines.append(f"    gone (must remain absent): {anchor}")
    lines.append("")
    lines.append(_CLOSING_SENTENCE)
    return "\n".join(lines)


_REVIEW_INSTRUCTION = (
    "A plan deviation that one of these records covers is still a [BLOCKING] finding. "
    "Put the line approved-by-owner: <id> directly under its bullet and name the file "
    "path. The harness downgrades it to a note after checking the record against HEAD; "
    "you never downgrade it yourself. A deviation with no record here is BLOCKING as before."
)


def _record_listing(snap: ApprovalSnapshot) -> list[str]:
    lines: list[str] = []
    for rec in snap.records:
        lines.append(f"- {rec.id}: {rec.summary}")
        for f in rec.files:
            lines.append(f"  - {f.path}")
            for anchor in f.keep:
                lines.append(f"    keep (must remain present): {anchor}")
            for anchor in f.gone:
                lines.append(f"    gone (must remain absent): {anchor}")
    return lines


def review_context(snap: ApprovalSnapshot | None) -> str:
    if snap is None or not snap.records:
        return ""
    lines = ["## Owner-approved deviations (trusted, read from the PR by the harness)", ""]
    lines.extend(_record_listing(snap))
    lines.append("")
    lines.append(_REVIEW_INSTRUCTION)
    return "\n".join(lines)


class OwnerGuardLlm:
    """Wraps the run's LLM adapter. The snapshot lives here, not in fixer signatures,
    so no fixer can omit or widen it."""

    def __init__(self, inner, snap: ApprovalSnapshot, *, gh=None, pr=None, res=None):
        self._inner = inner
        self.snap = snap
        self._gh = gh
        self._pr = pr
        self._res = res

    def __getattr__(self, name: str):
        # Only called for attributes not found normally; never recurse when _inner is unset.
        if name == "_inner":
            raise AttributeError(name)
        return getattr(self._inner, name)

    def call(self, *a, **k):
        return self._inner.call(*a, **k)

    def call_tooled(self, purpose, model, prompt, *, denied_tools=("Bash", "Agent"), **kw):
        if is_read_only_purpose(purpose):
            return self._inner.call_tooled(purpose, model, prompt,
                                           denied_tools=denied_tools, **kw)
        block = off_limits_text(self.snap)
        if block:
            prompt = prompt + "\n\n" + block
        denied = tuple(dict.fromkeys([*denied_tools, *FIXER_DENIED_TOOLS]))
        out = self._inner.call_tooled(purpose, model, prompt, denied_tools=denied, **kw)
        # A fixer that posts a record mid-call must not get it into the snapshot or its
        # commit pushed: any drift fails the run before a commit is attempted.
        if self._gh is not None and self._pr is not None:
            verify_unchanged(self._gh, self._pr, self.snap)
        return out
