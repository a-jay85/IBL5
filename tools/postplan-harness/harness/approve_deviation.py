"""Write an owner-approved deviation record onto a PR from a commit or diff.

`bin/approve-deviation` is a thin wrapper around `main`. The record format and
the trust model live in `owner_approvals.py` and ADR-0215; this module only
derives anchors from a unified diff, renders the comment, checks that the
caller really is the owner, and posts it idempotently.

Exit codes: 0 ok, 1 gh/git failure, 2 usage or nothing to approve,
3 caller is not the owner, 4 duplicate record id on the PR.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
from typing import Callable, Sequence

from . import owner_approvals as oa
from .state import HarnessError

_COMMENTS_JQ = ".[] | {id, body, login: .user.login, type: .user.type}"

RunGh = Callable[[list[str]], str]
RunGit = Callable[[list[str]], str]


def _default_run_gh(args: list[str]) -> str:
    return subprocess.run(["gh", *args], check=True, capture_output=True, text=True).stdout


def _default_run_git(args: list[str]) -> str:
    return subprocess.run(["git", *args], check=True, capture_output=True, text=True).stdout


def _strip_path(token: str) -> str:
    token = token.split("\t", 1)[0].strip()
    if token[:2] in ("a/", "b/"):
        token = token[2:]
    return token


def anchors_from_unified_diff(text: str) -> dict[str, tuple[list[str], list[str]]]:
    """Map path -> (keep, gone) anchor lines from a unified diff."""
    lines = text.splitlines()
    out: dict[str, tuple[list[str], list[str]]] = {}
    old_path: str | None = None
    cur: str | None = None
    i = 0
    while i < len(lines):
        line = lines[i]
        if line.startswith("--- ") and i + 1 < len(lines) and lines[i + 1].startswith("+++ "):
            old = line[4:].split("\t", 1)[0].strip()
            old_path = None if old == "/dev/null" else _strip_path(old)
            new = lines[i + 1][4:].split("\t", 1)[0].strip()
            cur = old_path if new == "/dev/null" else _strip_path(new)
            i += 2
            continue
        if line.startswith("diff --git "):
            cur = None
            old_path = None
        elif cur is not None and line.startswith("+"):
            _add(out, cur, 0, line[1:])
        elif cur is not None and line.startswith("-"):
            _add(out, cur, 1, line[1:])
        i += 1
    result: dict[str, tuple[list[str], list[str]]] = {}
    for path, (keep, gone) in out.items():
        if keep or gone:
            result[path] = (keep, gone)
    return result


def _add(out: dict, path: str, idx: int, raw: str) -> None:
    norm = oa.normalize_anchor(raw)
    if oa.is_trivial_anchor(norm):
        return
    bucket = out.setdefault(path, ([], []))[idx]
    if norm not in bucket:
        bucket.append(norm)


def record_id(files: dict[str, tuple[list[str], list[str]]]) -> str:
    parts = []
    for path, (keep, gone) in files.items():
        parts.extend(f"{path}\tkeep\t{ln}" for ln in keep)
        parts.extend(f"{path}\tgone\t{ln}" for ln in gone)
    digest = hashlib.sha256("\n".join(sorted(parts)).encode("utf-8")).hexdigest()
    return "oad-" + digest[:12]


def render_body(rid: str, summary: str, files: dict[str, tuple[list[str], list[str]]],
                source: str) -> str:
    record = {
        "schema": oa.RECORD_SCHEMA,
        "id": rid,
        "summary": summary,
        "files": [{"path": p, "keep": list(k), "gone": list(g)} for p, (k, g) in files.items()],
    }
    return (
        f"{oa.APPROVAL_MARKER}\n"
        f"Owner-approved deviation **{rid}**: {summary} (from {source})\n\n"
        f"```json\n{json.dumps(record, indent=2)}\n```\n"
    )


def _existing_owner_rows(run_gh: RunGh, pr: int) -> list[dict]:
    out = run_gh(["api", "--paginate", f"repos/{{owner}}/{{repo}}/issues/{pr}/comments",
                  "--jq", _COMMENTS_JQ])
    rows = []
    for line in out.splitlines():
        if not line.strip():
            continue
        row = json.loads(line)
        body = row.get("body") or ""
        if oa.APPROVAL_MARKER in body and oa.is_owner(row.get("login", ""), row.get("type", "")):
            rows.append(row)
    return rows


def _matching_rows(rows: list[dict], rid: str) -> list[dict]:
    hits = []
    for row in rows:
        try:
            rec = oa.parse_record(row["body"], int(row["id"]))
        except HarnessError:
            continue
        if rec.id == rid:
            hits.append(row)
    return hits


def _build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(
        prog="approve-deviation",
        description="Post an owner-approved deviation record on a PR from a commit or diff.")
    p.add_argument("--pr", type=int, required=True, help="PR number")
    src = p.add_mutually_exclusive_group(required=True)
    src.add_argument("--commit", metavar="SHA", help="derive anchors from this commit")
    src.add_argument("--diff", metavar="FILE", help="derive anchors from a unified diff ('-' = stdin)")
    p.add_argument("--summary", required=True, help="one-line reason for the deviation")
    p.add_argument("--paths", help="comma-separated path filter")
    p.add_argument("--dry-run", action="store_true", help="print the comment body, post nothing")
    return p


def _err(msg: str) -> None:
    print(msg, file=sys.stderr)


def main(argv: Sequence[str], run_gh: RunGh | None = None, run_git: RunGit | None = None) -> int:
    run_gh = run_gh or _default_run_gh
    run_git = run_git or _default_run_git
    try:
        args = _build_parser().parse_args(list(argv))
    except SystemExit as exc:
        return exc.code if isinstance(exc.code, int) else 2
    summary = " ".join(args.summary.split())
    if not summary:
        _err("--summary must not be empty")
        return 2
    try:
        if args.commit:
            diff_text = run_git(["show", "--format=", "--unified=0", "--no-color",
                                 "--no-ext-diff", args.commit])
            source = args.commit
        elif args.diff == "-":
            diff_text = sys.stdin.read()
            source = "diff"
        else:
            with open(args.diff, encoding="utf-8") as fh:
                diff_text = fh.read()
            source = "diff"
    except (OSError, subprocess.CalledProcessError) as exc:
        _err(f"cannot read the approved change: {exc}")
        return 1
    files = anchors_from_unified_diff(diff_text)
    if args.paths:
        wanted = {p.strip() for p in args.paths.split(",") if p.strip()}
        files = {p: v for p, v in files.items() if p in wanted}
    if not files:
        _err("no non-trivial anchor lines in the approved change")
        return 2
    rid = record_id(files)
    body = render_body(rid, summary, files, source)
    if args.dry_run:
        print(body, end="")
        return 0
    try:
        who = run_gh(["api", "user", "--jq", '.login + " " + .type']).strip()
        if who != f"{oa.OWNER_LOGIN} User":
            _err(f"refusing to post: authenticated as {who!r}, expected '{oa.OWNER_LOGIN} User'")
            return 3
        hits = _matching_rows(_existing_owner_rows(run_gh, args.pr), rid)
        if len(hits) > 1:
            _err("duplicate record id; delete one by hand")
            return 4
        if not hits:
            out = run_gh(["api", "--method", "POST",
                          f"repos/{{owner}}/{{repo}}/issues/{args.pr}/comments",
                          "-f", f"body={body}", "--jq", ".id"])
            print(f"posted {out.strip()}")
            return 0
        existing = hits[0]
        if (existing.get("body") or "").strip() == body.strip():
            print(f"unchanged {existing['id']}")
            return 0
        run_gh(["api", "--method", "PATCH",
                f"repos/{{owner}}/{{repo}}/issues/comments/{existing['id']}",
                "-f", f"body={body}"])
        print(f"updated {existing['id']}")
        return 0
    except (subprocess.CalledProcessError, json.JSONDecodeError) as exc:
        detail = getattr(exc, "stderr", None) or str(exc)
        _err(f"gh failed: {detail}")
        return 1
