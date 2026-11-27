"""Phase 5.5: decide what happens to one tagged unplanned-scope fidelity finding.

The fidelity reviewer tags every check-3 (Scope creep) finding `[unplanned-scope]`. This
module maps a tagged finding to `keep`, `remove` or `hold`:

- `hold`: a cited path is both flagged by `bin/lib/deleted-guards` and listed in the plan's
  `## Must preserve`.
- `remove`: every commit that touched every cited path carries a subject the harness itself
  writes, so the code is post-plan's own and the fixer may delete it.
- `keep`: everything else, including every ambiguous case. A kept finding never reaches the
  fixer.

A failing test is no input here. It already holds through meta-check hold 16 and Phase 7 CI.

This module imports `fidelity` only inside functions, so loading it stays free of cycles.
"""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
from dataclasses import dataclass
from typing import Iterable, Mapping, Optional, Sequence

from . import removed_guards

UNPLANNED_TAG = "[unplanned-scope]"
VR_BASELINE_SUBJECT = "test: update visual regression baselines [auto]"
KEPT_BEGIN = "<!-- kept-unplanned:begin -->"
KEPT_END = "<!-- kept-unplanned:end -->"
_KEPT_VETO = ("Phase 5.5 kept these changes. No plan phase covers them, and they were not "
              "authored by a post-plan fix commit. Veto any of them before merging.")

_TAGGED_RE = re.compile(
    r"^\s*(?:[-*]|\d+[.)])\s+\[(?:BLOCKING|NOTE)\]\s+(?:\[[^\[\]\s][^\[\]]*\]\s+)*\[unplanned-scope\](?=\s|$)"
)
_MP_HEADING_RE = re.compile(r"^## Must preserve\s*$")
_INTENDED_HEADING_RE = re.compile(r"^### Intended removals\s*$")
_BACKTICK_RE = re.compile(r"`([^`]+)`")
# re.escape turns a placeholder like {n} into \{n\}. A subject constant's \{name\} span
# becomes a wildcard below, so its formatted commit subject still matches.
_ESCAPED_PLACEHOLDER_RE = re.compile(r"\\\{\w+\\\}")


def is_unplanned_scope(item_text: str) -> bool:
    """True only when the item's first line carries the tag among the bracketed tags after its severity marker."""
    lines = item_text.splitlines()
    return bool(lines) and _TAGGED_RE.match(lines[0]) is not None


def machine_subject_patterns() -> list[re.Pattern[str]]:
    """One compiled pattern per subject the harness writes, built from its own constants."""
    from . import adr_draft, cifix, conformance_fix, fidelity, prosefix

    subjects = (
        fidelity.REMEDIATION_COMMIT_MSG,
        cifix.CI_FIX_COMMIT_MSG,
        conformance_fix.COMMIT_MSG,
        prosefix.PROSE_FIX_COMMIT_MSG,
        adr_draft.ADR_COMMIT_MSG,
        VR_BASELINE_SUBJECT,
    )
    return [re.compile(_ESCAPED_PLACEHOLDER_RE.sub(".+", re.escape(s))) for s in subjects]


def is_machine_commit(subject: str) -> bool:
    return any(p.fullmatch(subject) for p in machine_subject_patterns())


def cited_paths(item_text: str, changed_paths: Iterable[str]) -> list[str]:
    """The changed paths whose literal path string occurs in the finding text."""
    return [path for path in changed_paths
            if path and re.search(rf"(?<![\w./-]){re.escape(path)}(?![\w/-])", item_text)]


def must_preserve_paths(plan_text: str) -> set[str]:
    """First backticked token of each `- ` bullet under `## Must preserve`.

    Stops at the next `## ` heading and skips the `### Intended removals` subsection.
    """
    paths: set[str] = set()
    inside = skipping = False
    for line in plan_text.splitlines():
        if not inside:
            inside = _MP_HEADING_RE.match(line) is not None
            continue
        if line.startswith("## "):
            break
        if line.startswith("### "):
            skipping = _INTENDED_HEADING_RE.match(line) is not None
            continue
        if skipping or not line.startswith("- "):
            continue
        token = _BACKTICK_RE.search(line)
        if token:
            paths.add(token.group(1))
    return paths


def classify(
    item_text: str,
    *,
    changed_paths: Iterable[str],
    subjects_by_path: Mapping[str, Sequence[str]],
    must_preserve: Iterable[str],
    removed_guard_paths: Iterable[str],
    amendments: Sequence = (),
) -> Optional[str]:
    """`keep`, `remove` or `hold` for a tagged finding, None for an untagged one.

    A finding that quotes a backed owner amendment is kept, so the fixer never reverts
    work the owner asked for. The check sits below `hold`: an amendment never licenses
    removing a `## Must preserve` guard.
    """
    if not is_unplanned_scope(item_text):
        return None
    paths = cited_paths(item_text, changed_paths)
    preserved, removed = set(must_preserve), set(removed_guard_paths)
    if any(path in removed and path in preserved for path in paths):
        return "hold"
    if amendment_covers(item_text, amendments):
        return "keep"
    if paths and all(_only_machine_commits(subjects_by_path.get(path, ())) for path in paths):
        return "remove"
    return "keep"


def _only_machine_commits(subjects: Sequence[str]) -> bool:
    return bool(subjects) and all(is_machine_commit(subject) for subject in subjects)


_MAX_INHERIT_DEPTH = 3


def commit_subjects_for(worktree: str, base_sha: str,
                        paths: Iterable[str]) -> dict[str, list[str]]:
    """Commit subjects on `base_sha..HEAD` per path. A failed lookup maps that path to [].

    A path added by a commit that also deleted another path gets the deleted path's earlier
    subjects too. A rename with a full rewrite reads as add plus delete, and `--follow` does
    not cross it (backlog#1789).
    """
    return {path: _subjects_with_inherited(worktree, base_sha, path) for path in paths}


def _subjects_with_inherited(worktree: str, base_sha: str, path: str) -> list[str]:
    own = _subjects_for(worktree, base_sha, path)
    inherited = _inherited_subjects(worktree, base_sha, path, "HEAD", _MAX_INHERIT_DEPTH, {path})
    if inherited is None:
        return []  # unresolved fold reads as keep
    return own + [s for s in inherited if s not in own]


def _inherited_subjects(worktree: str, base_sha: str, path: str, head: str, depth: int,
                        seen: set[str]) -> Optional[list[str]]:
    """Subjects of each path deleted by a `base_sha..head` commit that adds `path`, recursively.

    None when a git call fails or the chain needs more than `depth` hops.
    """
    adders = _git_out(worktree, "log", "--no-merges", "--no-renames", "--diff-filter=A",
                      "--format=%H", f"{base_sha}..{head}", "--", path)
    if adders is None:
        return None
    found: list[str] = []
    for sha in adders.split():
        deleted = _git_out(worktree, "diff-tree", "-r", "-z", "--no-commit-id", "--no-renames",
                           "--name-only", "--diff-filter=D", sha)
        if deleted is None:
            return None
        for old in [p for p in deleted.split("\0") if p]:
            if old in seen:
                continue
            if depth <= 0:
                return None
            seen.add(old)
            parent = f"{sha}^"
            subjects = _git_out(worktree, "log", "--follow", "--no-merges", "--format=%s",
                                f"{base_sha}..{parent}", "--", old)
            deeper = _inherited_subjects(worktree, base_sha, old, parent, depth - 1, seen)
            if subjects is None or deeper is None:
                return None
            found += subjects.splitlines() + deeper
    return found


def _subjects_for(worktree: str, base_sha: str, path: str) -> list[str]:
    out = _git_out(worktree, "log", "--follow", "--no-merges", "--format=%s",
                   f"{base_sha}..HEAD", "--", path)
    return out.splitlines() if out is not None else []


def _git_out(worktree: str, *args: str) -> Optional[str]:
    """Stdout of `git -C worktree <args>`, or None on any failure."""
    cmd = ["git", "-C", worktree, *args]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    except (OSError, ValueError, subprocess.SubprocessError):
        return None
    if proc.returncode != 0:
        return None
    return proc.stdout


def context_for(worktree: str, base_sha: str, plan_text: str, changed_paths: Iterable[str],
                removed_guard_rows: Iterable[Sequence[str]]) -> dict:
    """The keyword inputs `classify` takes. A removed-guard row's path is its second field."""
    changed = list(changed_paths)
    return {
        "changed_paths": changed,
        "subjects_by_path": commit_subjects_for(worktree, base_sha, changed),
        "must_preserve": must_preserve_paths(plan_text),
        "removed_guard_paths": {row[1] for row in removed_guard_rows},
    }


def partition(items: Iterable[dict], ctx: Optional[dict]) -> tuple[list, list, list]:
    """(work, kept, held). Only a hold-12 item can leave `work`.

    With a ctx, `classify` picks the branch, and a `remove` verdict sends the item to the
    fixer. With no ctx (detector failure, timeout, unreadable input), every tagged hold-12
    item is held: the classifier could not clear it, so the fixer must not delete its code.
    """
    work: list = []
    kept: list = []
    held: list = []
    for item in items:
        branch = None
        if item["hold"] == "12" and ctx is not None:
            branch = classify(item["text"], **ctx)
        elif item["hold"] == "12" and is_unplanned_scope(item["text"]):
            branch = "hold"   # no ctx: the classifier could not clear it
        {"keep": kept, "hold": held}.get(branch, work).append(item)
    return work, kept, held


_TAG_RE = re.compile(r"\[([^\[\]\s][^\[\]]*)\]")
_SEVERITY_TAGS = {"BLOCKING", "NOTE"}


def _same_change_key(item_text: str, changed_paths: Iterable[str]) -> Optional[tuple]:
    """(tags, cited paths) for a finding, or None when it cites no changed path.

    Severity is left out because a re-review may move a finding from BLOCKING to NOTE.
    """
    paths = cited_paths(item_text, changed_paths)
    if not paths:
        return None
    lines = item_text.splitlines()
    first = lines[0] if lines else ""
    tagged = _TAGGED_RE.match(first)
    tags = _TAG_RE.findall(first[:tagged.end()]) if tagged else []
    return (frozenset(t for t in tags if t not in _SEVERITY_TAGS), frozenset(paths))


def merge_kept(kept_all: list, new_items: Iterable[dict], changed_paths: Iterable[str]) -> None:
    """Add this round's kept items to `kept_all` in place.

    A new item with the same tags and cited paths as an item from an earlier round replaces
    it, so the newest wording is the one declared. That matters because `downgrade_applies`
    looks for the current round's titles in the PR body. Items from the same round never
    replace each other, and an item that cites no changed path is only skipped as an exact
    copy, so two distinct findings are never folded together by accident.
    """
    changed = tuple(changed_paths)
    earlier = len(kept_all)
    replaced: set[int] = set()
    for item in new_items:
        if item in kept_all:
            continue
        key = _same_change_key(item["text"], changed)
        match = None
        if key is not None:
            match = next((i for i in range(earlier) if i not in replaced
                          and _same_change_key(kept_all[i]["text"], changed) == key), None)
        if match is None:
            kept_all.append(item)
        else:
            kept_all[match] = item
            replaced.add(match)


def title_of(item_text: str) -> str:
    """The first line minus bullet, severity marker and tag, cut before `. Next action`."""
    lines = item_text.splitlines()
    first = lines[0] if lines else ""
    tagged = _TAGGED_RE.match(first)
    title = first[tagged.end():] if tagged else first
    return title.split(". Next action", 1)[0].strip()


_LEAD_TAGS_RE = re.compile(r"^(?:\[[^\[\]\s][^\[\]]*\]\s+)+")
_BULLET_RE = re.compile(r"^\s*(?:[-*]|\d+[.)])\s+")


def title_key(text: str) -> str:
    """The part of a finding title that stays put across re-reviews.

    The reviewer rewords the explanation after the first `: ` on every run, and it may
    add or drop a bracketed tag such as `[MERGE-RULE]`. The key drops leading tags and
    keeps the words before the first `: `, so the same finding matches its declared line
    and the owner's approved line. A title with no `: ` stops at its first sentence end
    (`. ` or a final `.`), because the reviewer also rewords the sentences after it
    (PRs 4202 and 4213). The key also stops at the first ` (` followed by a backtick.
    `render_kept_block` writes that path citation after each declared title, and a
    reviewer may put the path and prose there too, so the finding still matches the
    shorter declared and approved line (PR 4244). A ` (` with no backtick stays.
    `_listed` separately compares the path that this last cut drops.
    """
    return _key_sentence(text).split(" (`", 1)[0].strip()


def _key_sentence(text: str) -> str:
    """The title cut at the first `: ` and sentence end, path citation still attached."""
    title = title_of(text) if is_unplanned_scope(text) else _BULLET_RE.sub("", text.splitlines()[0] if text else "")
    title = _LEAD_TAGS_RE.sub("", title.strip())
    title = title.split(": ", 1)[0].split(". Next action", 1)[0]
    return title.split(". ", 1)[0].strip().rstrip(".").strip()


_LINE_SUFFIX = r"(?::\d+(?:-\d+)?)?"


def _key_citation(text: str) -> Optional[str]:
    """The first backticked token `title_key` cuts off, minus a line number, else None."""
    parts = _key_sentence(text).split(" (`", 1)
    if len(parts) < 2:
        return None
    return re.sub(_LINE_SUFFIX + "$", "", parts[1].split("`", 1)[0])


def _listed(text: str, span: str) -> bool:
    """True when the finding's title, or its stable key, is listed in `span`.

    When the key cut off a path citation, the listed line must name that path too.
    """
    title = title_of(text).strip()
    if len(title) >= MIN_TITLE_LEN and title in span:
        return True
    key = title_key(text)
    if len(key) < MIN_TITLE_LEN:
        return False
    path = _key_citation(text)
    cited = None if path is None else re.compile(f"`{re.escape(path)}{_LINE_SUFFIX}`")
    return any(_BULLET_RE.match(ln) and title_key(ln) == key
               and (cited is None or cited.search(ln) is not None)
               for ln in span.splitlines())


def render_kept_block(kept: Sequence[dict], ctx: Optional[dict]) -> str:
    """The PR-body declaration, or "" when nothing was kept."""
    if not kept:
        return ""
    changed = (ctx or {}).get("changed_paths", ())
    lines = [KEPT_BEGIN, "## Kept unplanned changes", _KEPT_VETO]
    for item in kept:
        paths = cited_paths(item["text"], changed)
        cited = ", ".join(f"`{path}`" for path in paths) or "no path cited"
        lines.append(f"- {title_of(item['text'])} ({cited})")
    lines.append(KEPT_END)
    return "\n".join(lines)


def upsert_kept_block(body: str, block: str) -> str:
    return removed_guards.upsert_marked_block(body, block, KEPT_BEGIN, KEPT_END)


def downgrade_applies(verdict_path: str, ctx: Optional[dict], body_text: str, payload: Optional[dict] = None,
                      approval: Optional[OwnerApproval] = None) -> bool:
    """True when a NOT READY verdict's blockers are all kept and owner-backed.

    Each blocker is either covered by a backed owner amendment in ctx["amendments"], or
    declared in the PR body's kept block and quoted from the owner.
    """
    from . import fidelity

    body = body_text or ""
    begin, end = body.find(KEPT_BEGIN), body.find(KEPT_END)
    amendments = (ctx or {}).get("amendments", ())
    declared = body[begin:end] if begin != -1 and end >= begin else ""
    if ctx is None or (not declared and not amendments):
        return False
    if fidelity.parse_verdict(verdict_path) != "NOT READY":
        return False
    texts = fidelity.blocking_finding_texts(verdict_path)
    if not texts or len(fidelity.verdict_items(verdict_path)["blocking"]) != len(texts):
        return False   # an unmarked bullet also blocks, and it can never be classified
    kept = [t for t in texts if is_unplanned_scope(t)]
    if len(kept) != len(texts):
        return False   # a bug in kept code is its own blocking finding and blocks
    return all(classify(t, **ctx) == "keep"
               and (amendment_covers(t, amendments)
                    or (bool(declared) and _listed(t, declared)
                        and (owner_evidence_ok(t, payload) or approval_covers(t, approval))))
               for t in kept)


# The reviewer copies the owner's words onto a kept-scope finding: owner: "<12+ chars>".
# Straight double quotes only; curly quotes, backticks, or `owner said` never match, so
# a malformed citation reads as no evidence and the verdict stays NOT READY.
OWNER_QUOTE_RE = re.compile(r'\bowner:\s*"([^"\n]{12,400})"')


def owner_quote(text: str) -> Optional[str]:
    m = OWNER_QUOTE_RE.search(text or "")
    return m.group(1) if m else None


def _norm_words(s: str) -> str:
    return " ".join(s.split()).casefold()


# bin/owner-approve's approval comment. Its final non-blank line is the marker; diff= is
# the PR diff's patch-id (hs_diff_fingerprint), or "unread" when it could not be computed.
APPROVAL_MARKER_RE = re.compile(
    r"^<!-- owner-approve mode=(?P<mode>approve|fix-first) head=(?P<head>[0-9a-f]{40})"
    r" diff=(?P<diff>[0-9a-f]{40}|unread) -->$")
APPROVAL_KEPT_BEGIN = "<!-- owner-approve:kept:begin -->"
APPROVAL_KEPT_END = "<!-- owner-approve:kept:end -->"
APPROVAL_WORDS_RE = re.compile(r'^Owner approved [^:\n]+: "(?P<w>[^"\n]*)"(?: \((?P<o>[^)\n]*)\))?')
STICKY_MARKER_TEXT = "<!-- pr-ready-verdict -->"
MIN_TITLE_LEN = 12


@dataclass(frozen=True)
class OwnerApproval:
    words: str
    kept_span: str
    created_at: str


def _authentic(comment: dict) -> bool:
    user = comment.get("user") or {}
    body = comment.get("body") or ""
    return (user.get("type") == "User"
            and comment.get("author_association") == "OWNER"
            and STICKY_MARKER_TEXT not in body
            and "<!-- hs-approved-diff" not in body)


def approval_from_comments(comments: list, current_fp: Optional[str]) -> Optional[OwnerApproval]:
    """The newest authentic bin/owner-approve approval bound to current_fp, else None."""
    if not current_fp or not re.fullmatch(r"[0-9a-f]{40}", current_fp):
        return None
    found = []
    for c in comments or []:
        if not isinstance(c, dict) or not _authentic(c):
            continue
        lines = [ln.strip() for ln in (c.get("body") or "").replace("\r", "").split("\n") if ln.strip()]
        m = APPROVAL_MARKER_RE.match(lines[-1]) if lines else None
        if m:
            found.append((c.get("created_at") or "", c, m))
    if not found:
        return None
    created, c, m = max(found, key=lambda x: x[0])
    if m.group("mode") != "approve":
        return None
    if m.group("diff") != current_fp:
        return None
    body = (c.get("body") or "").replace("\r", "")
    b, e = body.find(APPROVAL_KEPT_BEGIN), body.find(APPROVAL_KEPT_END)
    if b < 0 or e <= b:
        return None
    span = body[b + len(APPROVAL_KEPT_BEGIN):e].strip()
    if not span:
        return None
    w = APPROVAL_WORDS_RE.match(body.split("\n", 1)[0])
    words = (w.group("w") + (f" ({w.group('o')})" if w.group("o") else "")) if w else "owner approval"
    return OwnerApproval(words=words, kept_span=span, created_at=created)


def approval_covers(text: str, approval: Optional[OwnerApproval]) -> bool:
    """True when the finding's title is one the owner saw in the approval's kept span."""
    if approval is None:
        return False
    return _listed(text, approval.kept_span)


# A fix-first approval's scope, carried into the plan by `bin/owner-approve`. The plan
# entry alone is not trusted: `backed_amendments` matches it to its own approval comment.
AMENDMENTS_HEADING = "## Owner amendments"
_AMENDMENTS_HEADING_RE = re.compile(r"^## Owner amendments\s*$")
_AMENDMENT_MARKER_RE = re.compile(r"^<!-- owner-amendment comment=(\d+) head=([0-9a-f]{40}) -->$")
_FENCE_OPEN = "```text"
_FENCE_CLOSE = "```"
_WORDS_PREFIX = "Owner words: "
_CONDITION_PREFIX = "Condition: "


@dataclass(frozen=True)
class Amendment:
    comment_id: str
    head: str
    words: str
    condition: str
    date: str


def _one_line(s: str) -> str:
    return " ".join(s.replace("\r", "").split("\n")).strip()


# bin/owner-approve prints this sentence on the line after the Condition. A Condition
# that holds a newline runs on until it, a blank line or an HTML comment.
_CONDITION_END_PREFIX = "The label and the arm wait until the fix is pushed"


def _condition_text(body: str) -> str:
    """The first `Condition: ` line's text, with its continuation lines joined by spaces."""
    lines = body.split("\n")
    start = next((i for i, ln in enumerate(lines) if ln.startswith(_CONDITION_PREFIX)), None)
    if start is None:
        return ""
    parts = [lines[start][len(_CONDITION_PREFIX):]]
    for ln in lines[start + 1:]:
        if not ln.strip() or ln.startswith(_CONDITION_END_PREFIX) or ln.lstrip().startswith("<!--"):
            break
        parts.append(ln)
    return " ".join(parts).strip()


def comment_fields(comment: dict) -> Optional[Amendment]:
    """The amendment an authentic fix-first approval comment carries, else None."""
    if not isinstance(comment, dict):
        return None
    if not _authentic(comment):
        return None
    body = (comment.get("body") or "").replace("\r", "")
    lines = [ln.strip() for ln in body.split("\n") if ln.strip()]
    m = APPROVAL_MARKER_RE.fullmatch(lines[-1]) if lines else None
    if m is None:
        return None
    if m.group("mode") != "fix-first":
        return None
    w = APPROVAL_WORDS_RE.match(body.split("\n", 1)[0])
    if w is None:
        return None
    condition = _condition_text(body)
    if not condition:
        return None
    cid = comment.get("id")
    if not isinstance(cid, int) or isinstance(cid, bool):
        return None
    date = (comment.get("created_at") or "")[:10]
    return Amendment(comment_id=str(cid), head=m.group("head"), words=_one_line(w.group("w")),
                     condition=condition, date=date)


def render_amendment(a: Amendment) -> str:
    """One plan entry: heading, marker line and a text fence with the words and Condition."""
    return "\n".join([
        f"### {a.date} comment {a.comment_id}",
        f"<!-- owner-amendment comment={a.comment_id} head={a.head} -->",
        _FENCE_OPEN,
        f"{_WORDS_PREFIX}{_one_line(a.words)}",
        f"{_CONDITION_PREFIX}{_one_line(a.condition)}",
        _FENCE_CLOSE,
    ])


def _section_bounds(lines: Sequence[str]) -> Optional[tuple[int, int]]:
    """(heading index, end index) of the `## Owner amendments` section, else None."""
    start = next((i for i, ln in enumerate(lines) if _AMENDMENTS_HEADING_RE.match(ln)), None)
    if start is None:
        return None
    end = next((i for i in range(start + 1, len(lines)) if lines[i].startswith("## ")), len(lines))
    return start, end


def _entries_in(lines: Sequence[str]) -> list[Amendment]:
    """Entries in a section body. Anything not in the exact entry shape is skipped."""
    found: list[Amendment] = []
    date = ""
    i = 0
    while i < len(lines):
        line = lines[i]
        heading = re.match(r"^### (\d{4}-\d{2}-\d{2}) comment \d+\s*$", line)
        if heading:
            date = heading.group(1)
        m = _AMENDMENT_MARKER_RE.match(line)
        if (m and i + 4 < len(lines) and lines[i + 1] == _FENCE_OPEN
                and lines[i + 2].startswith(_WORDS_PREFIX)
                and lines[i + 3].startswith(_CONDITION_PREFIX)
                and lines[i + 4] == _FENCE_CLOSE
                and lines[i + 3][len(_CONDITION_PREFIX):].strip()):
            found.append(Amendment(comment_id=m.group(1), head=m.group(2),
                                   words=lines[i + 2][len(_WORDS_PREFIX):].strip(),
                                   condition=lines[i + 3][len(_CONDITION_PREFIX):].strip(),
                                   date=date))
            date = ""
            i += 5
            continue
        i += 1
    return found


def parse_amendments(plan_text: str) -> list[Amendment]:
    """Every entry under the exact `## Owner amendments` heading."""
    lines = (plan_text or "").replace("\r", "").split("\n")
    bounds = _section_bounds(lines)
    if bounds is None:
        return []
    start, end = bounds
    return _entries_in(lines[start + 1:end])


def _mismatch(e: Amendment, f: Optional[Amendment]) -> Optional[str]:
    """None when comment `f` backs entry `e`, else the reason it does not."""
    if f is None:
        return "no-comment"
    if f.head != e.head:
        return "head"
    if _norm_words(f.condition) != _norm_words(e.condition):
        return "condition"
    if _norm_words(f.words) != _norm_words(e.words):
        return "words"
    return None


def backed_amendments(entries: Iterable[Amendment],
                      comments: Iterable[dict]) -> tuple[list[Amendment], list[tuple[Amendment, str]]]:
    """(backed, [(unbacked, reason)]). Backed means an authentic fix-first comment with the
    same id, a byte-equal head and equal normalized Condition and words."""
    by_id: dict[str, Amendment] = {}
    for c in comments or []:
        f = comment_fields(c)
        if f is not None:
            by_id[f.comment_id] = f
    backed: list[Amendment] = []
    unbacked: list[tuple[Amendment, str]] = []
    for e in entries:
        f = by_id.get(e.comment_id)
        reason = _mismatch(e, f)
        if reason is None:
            backed.append(e)
        else:
            unbacked.append((e, reason))
    return backed, unbacked


def amendment_covers(text: str, amendments: Iterable[Amendment]) -> bool:
    """True when the finding's owner quote occurs in a backed amendment's Condition or words."""
    quote = owner_quote(text)
    if quote is None:
        return False
    needle = _norm_words(quote)
    return any(needle in _norm_words(a.condition) or needle in _norm_words(a.words)
               for a in amendments or ())


def strip_unbacked(plan_text: str, backed: Iterable[Amendment]) -> str:
    """The plan with its amendments section rebuilt from `backed` only, or removed when
    none is backed. Text outside the section is byte-identical."""
    text = plan_text or ""
    lines = text.split("\n")
    bounds = _section_bounds([ln.rstrip("\r") for ln in lines])
    if bounds is None:
        return text
    start, end = bounds
    keep = list(backed)
    if keep:
        body = [lines[start], ""]
        for a in keep:
            body += [render_amendment(a), ""]
        section = body
    else:
        section = []
    return "\n".join(lines[:start] + section + lines[end:])


def owner_evidence_ok(text: str, payload: Optional[dict]) -> bool:
    """True only when the finding's owner quote occurs in one verified owner turn."""
    quote = owner_quote(text)
    if quote is None or not isinstance(payload, dict):
        return False
    turns = payload.get("turns")
    if not isinstance(turns, list):
        return False
    needle = _norm_words(quote)
    return any(isinstance(t, dict) and isinstance(t.get("text"), str)
               and needle in _norm_words(t["text"]) for t in turns)


def owner_scope_word(verdict_path: str) -> str:
    """The posted word once every blocker qualified: notes keep WITH NOTES."""
    from . import fidelity
    return "READY WITH NOTES" if fidelity.verdict_items(verdict_path)["notes"] else "READY"


class _ReadFailed(Exception):
    """A git call or the removed-guards detector could not answer."""


def _git_output(worktree: str, *args: str) -> str:
    cmd = ["git", "-C", worktree, *args]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    except (OSError, ValueError, subprocess.SubprocessError) as e:
        raise _ReadFailed(f"git {args[0]}: {e}") from e
    if proc.returncode != 0:
        raise _ReadFailed(f"git {args[0]}: {proc.stderr.strip()}")
    return proc.stdout


def _cli_context(args: argparse.Namespace, plan_text: str) -> dict:
    span = f"{args.base}...HEAD"
    changed = _git_output(args.worktree, "diff", "--name-only", span).splitlines()
    diff_text = _git_output(args.worktree, "diff", span)
    ok, rows = removed_guards.run_detector(args.worktree, diff_text, args.plan)
    if not ok:
        raise _ReadFailed("bin/lib/deleted-guards failed")
    return context_for(args.worktree, args.base, plan_text, changed, rows)


def main(argv: Optional[Sequence[str]] = None) -> int:
    """Skill-engine entry point. 0 ok, 2 usage or unreadable file, 3 git or detector failure."""
    from . import fidelity

    parser = argparse.ArgumentParser(prog="harness.unplanned_scope")
    parser.add_argument("--verdict", required=True)
    parser.add_argument("--plan", required=True)
    parser.add_argument("--base", required=True)
    parser.add_argument("--worktree", required=True)
    parser.add_argument("--render-block", action="store_true")
    args = parser.parse_args(argv)

    try:
        with open(args.plan, encoding="utf-8") as fh:
            plan_text = fh.read()
        with open(args.verdict, encoding="utf-8"):
            pass
    except (OSError, ValueError) as e:
        print(f"unplanned_scope: cannot read input: {e}", file=sys.stderr)
        return 2
    try:
        ctx = _cli_context(args, plan_text)
    except _ReadFailed as e:
        print(f"unplanned_scope: {e}", file=sys.stderr)
        return 3

    texts = (fidelity.marked_finding_texts(args.verdict, "BLOCKING")
             + fidelity.marked_finding_texts(args.verdict, "NOTE"))
    tagged = [text for text in texts if is_unplanned_scope(text)]
    if args.render_block:
        kept = [{"hold": "12", "text": t} for t in tagged if classify(t, **ctx) == "keep"]
        block = render_kept_block(kept, ctx)
        if block:
            print(block)
        return 0
    for text in tagged:
        paths = ",".join(cited_paths(text, ctx["changed_paths"]))
        print(f"{classify(text, **ctx).upper()}\t{title_of(text)}\t{paths}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
