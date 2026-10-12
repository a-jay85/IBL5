"""Cap pipeline-filed follow-up backlog issues at three per origin PR.

Every filer (`bin/backlog new`, the fidelity notes filer, the out-of-scope sweep) calls
`file_followup`. The first two follow-ups of a PR stay standalone issues, the third stays
an ordinary issue until a fourth arrives, and the fourth rewrites the third into a roll-up
checklist. Later items append to that checklist. A retry never files an item twice.

Stdlib only. It must not import `harness.adapters.ghad`, which imports this module.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass, field
from typing import Callable, Protocol

CAP = 3
REPO = "a-jay85/IBL5-backlog"
LIST_LIMIT = 3000
ROLLUP_MARKER = "<!-- followup-rollup -->"
_PR_URL_RE = re.compile(r"^(https://github\.com/a-jay85/IBL5/pull/(\d+))(?!\d)")
_ISSUE_NUM_RE = re.compile(r"/issues/(\d+)\s*$")
_CHECK_RE = re.compile(r"^- \[[ xX]\] (.+?)\s*$")


class FollowcapError(Exception):
    """A cap decision could not be made or executed; nothing (more) was filed."""


@dataclass
class Issue:
    number: int
    title: str
    body: str
    state: str = "OPEN"  # "OPEN" | "CLOSED", as gh --json state prints it
    labels: list[str] = field(default_factory=list)


@dataclass
class Decision:
    kind: str  # "create" | "convert" | "append" | "exists" | "create-rollup"
    target: int | None  # issue number edited / matched; None for create kinds
    title: str  # title to write (create, convert, create-rollup)
    body: str  # body to write (create, convert, append, create-rollup)
    reopen: bool = False  # target is CLOSED and now holds an open item


class Store(Protocol):
    def list_all(self) -> list[Issue]: ...
    def create(self, title: str, body: str, label: str) -> int: ...
    def edit(self, number: int, title: str | None, body: str, add_label: str) -> None: ...
    def reopen(self, number: int) -> None: ...


# --- pure helpers -----------------------------------------------------------------


def pr_url_of(body: str) -> tuple[str, int]:
    m = _PR_URL_RE.match(body.split("\n", 1)[0].strip())
    if not m:
        raise FollowcapError("body line 1 must be an origin PR URL")
    return m.group(1), int(m.group(2))


def issues_for_pr(issues: list[Issue], pr_url: str) -> list[Issue]:
    """Issues whose body line 1 names `pr_url`. Closed issues count toward the cap."""
    out: list[Issue] = []
    for i in issues:
        first = i.body.split("\n", 1)[0].strip()
        if not first.startswith(pr_url):
            continue
        rest = first[len(pr_url):]
        if rest[:1].isdigit():
            continue
        out.append(i)
    return sorted(out, key=lambda i: i.number)


def checklist_titles(body: str) -> list[str]:
    out: list[str] = []
    for line in body.split("\n"):
        m = _CHECK_RE.match(line)
        if m:
            out.append(m.group(1))
    return out


def render_item(title: str, body: str, done: bool = False) -> str:
    lines = body.split("\n")[1:]
    while lines and not lines[0].strip():
        lines.pop(0)
    mark = "x" if done else " "
    out = [f"- [{mark}] {title}"]
    out.extend(f"  {ln}" if ln.strip() else "" for ln in lines)
    return "\n".join(out).rstrip("\n") + "\n"


def rollup_title(pr_number: int) -> str:
    return f"Follow-ups from PR #{pr_number} (roll-up)"


def rollup_header(pr_url: str) -> str:
    return (
        f"{pr_url}\n\n{ROLLUP_MARKER}\n"
        f"Follow-ups from {pr_url} past the per-PR cap of {CAP}. "
        "Each checklist item below would otherwise be its own issue.\n\n"
    )


def decide(pr_issues: list[Issue], title: str, body: str) -> Decision:
    t = title.strip()
    url, n = pr_url_of(body)
    rollup = next((i for i in pr_issues if ROLLUP_MARKER in i.body), None)

    # 1. Retry dedup: a title already filed, or already folded into a roll-up checklist.
    for i in pr_issues:
        if i.title.strip() == t:
            return Decision("exists", i.number, "", "")
        if ROLLUP_MARKER in i.body and t in checklist_titles(i.body):
            return Decision("exists", i.number, "", "")

    # 2. A roll-up exists: append.
    if rollup is not None:
        return Decision(
            "append", rollup.number, "",
            rollup.body.rstrip("\n") + "\n" + render_item(t, body),
            reopen=rollup.state == "CLOSED",
        )

    # 3. Below cap: an ordinary issue.
    if len(pr_issues) < CAP:
        return Decision("create", None, t, body)

    # 4. Exactly at cap: the fourth arrival rewrites slot 3 into the roll-up.
    if len(pr_issues) == CAP:
        slot3 = pr_issues[2]
        closed = slot3.state == "CLOSED"
        new_body = (
            rollup_header(url)
            + render_item(slot3.title, slot3.body, done=closed)
            + render_item(t, body)
        )
        return Decision("convert", slot3.number, rollup_title(n), new_body, reopen=closed)

    # 5. Legacy PR already over the cap with no roll-up: one new roll-up.
    return Decision("create-rollup", None, rollup_title(n), rollup_header(url) + render_item(t, body))


# --- stores -----------------------------------------------------------------------


class MemoryStore:
    def __init__(self, issues: list[Issue] | None = None) -> None:
        self.issues: list[Issue] = list(issues or [])

    def list_all(self) -> list[Issue]:
        return [Issue(i.number, i.title, i.body, i.state, list(i.labels)) for i in self.issues]

    def create(self, title: str, body: str, label: str) -> int:
        num = max((i.number for i in self.issues), default=0) + 1
        self.issues.append(Issue(num, title, body, "OPEN", [label]))
        return num

    def edit(self, number: int, title: str | None, body: str, add_label: str) -> None:
        for i in self.issues:
            if i.number == number:
                if title:
                    i.title = title
                i.body = body
                if add_label not in i.labels:
                    i.labels.append(add_label)
                return
        raise FollowcapError(f"no issue #{number}")

    def reopen(self, number: int) -> None:
        for i in self.issues:
            if i.number == number:
                i.state = "OPEN"
                return
        raise FollowcapError(f"no issue #{number}")


class GhStore:
    def __init__(self, run: Callable[..., str]) -> None:
        self._run = run

    def list_all(self) -> list[Issue]:
        # Never --search: the index lags a fresh create, so the next call would undercount.
        out = self._run(
            "issue", "list", "--repo", REPO, "--state", "all", "--limit", str(LIST_LIMIT),
            "--json", "number,title,body,state,labels",
        )
        try:
            items = json.loads(out)
        except json.JSONDecodeError as exc:
            raise FollowcapError(f"issue list returned invalid JSON: {exc}") from exc
        if len(items) >= LIST_LIMIT:
            raise FollowcapError("issue list hit --limit; count unreliable, filing nothing")
        return [
            Issue(
                number=int(it["number"]),
                title=it.get("title") or "",
                body=it.get("body") or "",
                state=it.get("state") or "OPEN",
                labels=[lb["name"] for lb in it.get("labels", [])],
            )
            for it in items
        ]

    def create(self, title: str, body: str, label: str) -> int:
        out = self._run(
            "issue", "create", "--repo", REPO, "--label", label,
            "--title", title, "--body", body,
        )
        lines = out.strip().splitlines()
        m = _ISSUE_NUM_RE.search(lines[-1]) if lines else None
        if not m:
            raise FollowcapError(f"could not parse issue number from gh output: {out!r}")
        return int(m.group(1))

    def edit(self, number: int, title: str | None, body: str, add_label: str) -> None:
        self._run(
            "issue", "edit", str(number), "--repo", REPO,
            *(["--title", title] if title else []),
            "--body", body, "--add-label", add_label,
        )

    def reopen(self, number: int) -> None:
        self._run("issue", "reopen", str(number), "--repo", REPO)


# --- executor ---------------------------------------------------------------------


def plan_followup(store: Store, title: str, body: str) -> Decision:
    """Read and decide; write nothing."""
    url, _ = pr_url_of(body)
    issues = store.list_all()  # a failed count read files nothing (fail closed)
    return decide(issues_for_pr(issues, url), title, body)


def file_followup(store: Store, label: str, title: str, body: str) -> tuple[str, int | None]:
    d = plan_followup(store, title, body)
    num: int | None = d.target
    if d.kind in ("create", "create-rollup"):
        num = store.create(d.title, d.body, label)
    elif d.kind == "convert":
        assert d.target is not None
        store.edit(d.target, d.title, d.body, label)
    elif d.kind == "append":
        assert d.target is not None
        store.edit(d.target, None, d.body, label)
    if d.reopen and d.target is not None:
        store.reopen(d.target)
    return d.kind, num


# --- CLI --------------------------------------------------------------------------


def _gh_runner() -> Callable[..., str]:
    gh = os.environ.get("BACKLOG_GH") or "gh"

    def run(*args: str) -> str:
        try:
            proc = subprocess.run([gh, *args], capture_output=True, text=True, timeout=120)
        except subprocess.TimeoutExpired as exc:
            raise FollowcapError(f"gh {args[0]} {args[1]} timed out") from exc
        except OSError as exc:
            raise FollowcapError(f"cannot run {gh}: {exc}") from exc
        if proc.returncode != 0:
            raise FollowcapError(proc.stderr.strip()[:400] or f"gh exited {proc.returncode}")
        return proc.stdout

    return run


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="python3 -m harness.followcap")
    sub = parser.add_subparsers(dest="cmd", required=True)
    for name in ("file", "plan"):
        p = sub.add_parser(name)
        p.add_argument("label")
        p.add_argument("title")
        p.add_argument("body")
    args = parser.parse_args(argv)
    store = GhStore(_gh_runner())
    try:
        if args.cmd == "file":
            kind, num = file_followup(store, args.label, args.title, args.body)
            print(f"https://github.com/{REPO}/issues/{num}")
            print(f"followcap: {kind} #{num}", file=sys.stderr)
        else:
            d = plan_followup(store, args.title, args.body)
            target = f"#{d.target}" if d.target is not None else "-"
            print(f"would-{d.kind}\t{target}\t{args.title.strip()}")
    except FollowcapError as exc:
        print(f"backlog new: {exc}; nothing filed", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
