"""Owner amendments: carry a fix-first approval's scope into the plan.

`bin/owner-approve` is the only writer, through `append`. Every parse and match lives in
`unplanned_scope`; this module holds file I/O and argument parsing only.

Subcommands:
  append --plan PATH --comments FILE   add one entry per new fix-first approval comment
  verify --plan PATH --comments FILE   print `backed <id>` or `unbacked <id> (<reason>)`
  card --comments FILE --head SHA [--plan PATH] [--pr N]
                                       the pr-triage card's Owner approvals field

FILE holds REST issue comments: a JSON array, NDJSON, or concatenated arrays (the shape
`gh api --paginate` prints). Exit: 0 ok; 2 usage; 3 plan missing or unreadable;
4 plan write failed (plan unchanged); 5 comments unparseable.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import tempfile
from typing import Optional, Sequence

from . import unplanned_scope as us

EXIT_USAGE = 2
EXIT_PLAN = 3
EXIT_WRITE = 4
EXIT_COMMENTS = 5


class _CommentsError(Exception):
    """The comments file could not be read or parsed."""


def load_comments(path: str) -> list[dict]:
    """Every comment object in `path`. Raises _CommentsError on any read or parse error."""
    try:
        with open(path, encoding="utf-8") as fh:
            raw = fh.read()
    except (OSError, ValueError) as e:
        raise _CommentsError(f"cannot read {path}: {e}") from e
    decoder = json.JSONDecoder()
    out: list[dict] = []
    i, n = 0, len(raw)
    while True:
        while i < n and raw[i].isspace():
            i += 1
        if i >= n:
            break
        try:
            value, i = decoder.raw_decode(raw, i)
        except ValueError as e:
            raise _CommentsError(f"{path}: not JSON: {e}") from e
        items = value if isinstance(value, list) else [value]
        for item in items:
            if not isinstance(item, dict):
                raise _CommentsError(f"{path}: a comment is not a JSON object")
            out.append(item)
    return out


def _oldest_first(comments: Sequence[dict]) -> list[dict]:
    return sorted(comments, key=lambda c: str(c.get("created_at") or ""))


def _read_plan(plan: str) -> Optional[tuple[str, str]]:
    """(realpath, text), or None when the plan is missing or unreadable."""
    real = os.path.realpath(plan)
    if not os.path.isfile(real):
        return None
    try:
        with open(real, encoding="utf-8", newline="") as fh:
            return real, fh.read()
    except (OSError, ValueError):
        return None


def insert_entries(plan_text: str, entries: Sequence[us.Amendment]) -> str:
    """The plan with `entries` added at the end of `## Owner amendments`, or in a new
    section at EOF. Text outside the section is unchanged."""
    blocks: list[str] = []
    for a in entries:
        blocks += [us.render_amendment(a), ""]
    lines = plan_text.split("\n")
    bounds = us._section_bounds([ln.rstrip("\r") for ln in lines])
    if bounds is None:
        head = plan_text.rstrip("\n")
        return head + "\n\n" + us.AMENDMENTS_HEADING + "\n\n" + "\n".join(blocks)
    start, end = bounds
    section = lines[start:end]
    while len(section) > 1 and not section[-1].strip():
        section.pop()
    tail = lines[end:]
    if not tail:
        return "\n".join(lines[:start] + section + [""] + blocks)
    return "\n".join(lines[:start] + section + [""] + blocks + tail)


def _write_atomic(real: str, text: str) -> None:
    """Write `text` to a temp file beside `real`, then replace `real` with it."""
    mode = os.stat(real).st_mode & 0o7777
    fd, tmp = tempfile.mkstemp(dir=os.path.dirname(real), prefix=".owner-amendments.")
    try:
        with os.fdopen(fd, "w", encoding="utf-8", newline="") as fh:
            fh.write(text)
        os.chmod(tmp, mode)
        os.replace(tmp, real)
    except BaseException:
        try:
            os.remove(tmp)
        except OSError:
            pass
        raise


def cmd_append(args: argparse.Namespace) -> int:
    read = _read_plan(args.plan)
    if read is None:
        print(f"amendments: plan missing or unreadable: {args.plan}; nothing written", file=sys.stderr)
        return EXIT_PLAN
    real, text = read
    try:
        comments = load_comments(args.comments)
    except _CommentsError as e:
        print(f"amendments: {e}", file=sys.stderr)
        return EXIT_COMMENTS
    present = {e.comment_id for e in us.parse_amendments(text)}
    already = len(present)
    new_entries: list[us.Amendment] = []
    for c in _oldest_first(comments):
        a = us.comment_fields(c)
        if a is None:
            continue
        if a.comment_id in present:
            continue
        new_entries.append(a)
        present.add(a.comment_id)
    if not new_entries:
        print(f"amendments: appended 0, present {already}")
        return 0
    try:
        _write_atomic(real, insert_entries(text, new_entries))
    except OSError as e:
        print(f"amendments: writing {real} failed, plan unchanged: {e}", file=sys.stderr)
        return EXIT_WRITE
    print(f"amendments: appended {len(new_entries)}, present {already}")
    return 0


def cmd_verify(args: argparse.Namespace) -> int:
    read = _read_plan(args.plan)
    if read is None:
        print(f"amendments: plan missing or unreadable: {args.plan}", file=sys.stderr)
        return EXIT_PLAN
    try:
        comments = load_comments(args.comments)
    except _CommentsError as e:
        print(f"amendments: {e}", file=sys.stderr)
        return EXIT_COMMENTS
    backed, unbacked = us.backed_amendments(us.parse_amendments(read[1]), comments)
    for a in backed:
        print(f"backed {a.comment_id}")
    for a, reason in unbacked:
        print(f"unbacked {a.comment_id} ({reason})")
    return 0


def _marker(comment: dict):
    body = (comment.get("body") or "").replace("\r", "")
    lines = [ln.strip() for ln in body.split("\n") if ln.strip()]
    return us.APPROVAL_MARKER_RE.fullmatch(lines[-1]) if lines else None


def _kept_bullets(body: str) -> list[str]:
    b, e = body.find(us.APPROVAL_KEPT_BEGIN), body.find(us.APPROVAL_KEPT_END)
    if b < 0 or e <= b:
        return []
    span = body[b + len(us.APPROVAL_KEPT_BEGIN):e]
    return [ln.strip()[2:].strip() for ln in span.split("\n") if ln.strip().startswith("- ")]


def card_lines(comments: Sequence[dict], head: str, plan_text: Optional[str], pr: str) -> list[str]:
    """The Owner approvals field, oldest approval first."""
    recorded = {e.comment_id for e in us.parse_amendments(plan_text)} if plan_text is not None else None
    rows: list[str] = []
    n = 0
    for c in _oldest_first(comments):
        if not us._authentic(c):
            continue
        m = _marker(c)
        if m is None:
            continue
        n += 1
        body = (c.get("body") or "").replace("\r", "")
        moved = "PR head moved past it" if m.group("head") != head else "PR head unchanged"
        rows.append(f"  {n}. {c.get('created_at') or 'unknown time'} {m.group('mode')} "
                    f"head {m.group('head')[:7]} ({moved})")
        w = us.APPROVAL_WORDS_RE.match(body.split("\n", 1)[0])
        if w:
            opt = f" ({w.group('o')})" if w.group("o") else ""
            rows.append(f'     words: "{w.group("w")}"{opt}')
        if m.group("mode") == "fix-first":
            rows.append(f'     condition: "{us._condition_text(body)}"')
        for bullet in _kept_bullets(body):
            rows.append(f"     kept: {bullet}")
        if m.group("mode") == "fix-first":
            fields = us.comment_fields(c)
            if recorded is None:
                status = "no plan file"
            elif fields is not None and fields.comment_id in recorded:
                status = "recorded"
            else:
                status = f"MISSING (run bin/owner-approve {pr} --record-amendments)"
            rows.append(f"     amendment: {status}")
    if not rows:
        return ["Owner approvals: none"]
    return ["Owner approvals:"] + rows


def cmd_card(args: argparse.Namespace) -> int:
    try:
        comments = load_comments(args.comments)
    except _CommentsError as e:
        print(f"amendments: {e}", file=sys.stderr)
        return EXIT_COMMENTS
    plan_text = None
    if args.plan:
        read = _read_plan(args.plan)
        plan_text = read[1] if read is not None else None
    for line in card_lines(comments, args.head, plan_text, args.pr or "N"):
        print(line)
    return 0


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(prog="harness.amendments")
    sub = parser.add_subparsers(dest="cmd", required=True)
    for name in ("append", "verify"):
        p = sub.add_parser(name)
        p.add_argument("--plan", required=True)
        p.add_argument("--comments", required=True)
    p = sub.add_parser("card")
    p.add_argument("--comments", required=True)
    p.add_argument("--head", required=True)
    p.add_argument("--plan")
    p.add_argument("--pr")
    try:
        args = parser.parse_args(argv)
    except SystemExit as e:
        return e.code if isinstance(e.code, int) else EXIT_USAGE
    return {"append": cmd_append, "verify": cmd_verify, "card": cmd_card}[args.cmd](args)


if __name__ == "__main__":
    sys.exit(main())
