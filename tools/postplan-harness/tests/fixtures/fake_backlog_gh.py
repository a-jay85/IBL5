#!/usr/bin/env python3
"""Stateful fake `gh` for the IBL5-backlog repo.

Usage: fake_backlog_gh.py --store <path.json> <gh args...>

Store JSON: {"issues": [{"number", "title", "body", "state", "labels": [..]}], "fail_list": false}.
A missing store reads as empty. Every call appends its argv as one JSON line to <store>.log.
Supports `issue list|create|edit|reopen` and exits 1 on anything else.
"""
from __future__ import annotations

import json
import os
import sys


def _flag(args: list[str], name: str) -> str | None:
    for i, a in enumerate(args):
        if a == name and i + 1 < len(args):
            return args[i + 1]
    return None


def main(argv: list[str]) -> int:
    if len(argv) < 3 or argv[0] != "--store":
        print("fake gh: usage: --store <path> <gh args>", file=sys.stderr)
        return 1
    store_path, args = argv[1], argv[2:]
    with open(store_path + ".log", "a", encoding="utf-8") as log:
        log.write(json.dumps(args) + "\n")

    if os.path.exists(store_path):
        with open(store_path, encoding="utf-8") as fh:
            store = json.load(fh)
    else:
        store = {"issues": [], "fail_list": False}
    issues = store.setdefault("issues", [])

    def save() -> None:
        with open(store_path, "w", encoding="utf-8") as fh:
            json.dump(store, fh)

    def find(num: str) -> dict | None:
        return next((i for i in issues if str(i["number"]) == num), None)

    if args[:2] == ["issue", "list"]:
        if store.get("fail_list"):
            print("HTTP 502", file=sys.stderr)
            return 1
        rows = issues
        if _flag(args, "--state") == "open":
            rows = [i for i in issues if i.get("state", "OPEN") == "OPEN"]
        print(json.dumps([
            {**i, "labels": [{"name": n} for n in i.get("labels", [])]} for i in rows
        ]))
        return 0

    if args[:2] == ["issue", "create"]:
        num = max((i["number"] for i in issues), default=0) + 1
        label = _flag(args, "--label")
        issues.append({
            "number": num,
            "title": _flag(args, "--title") or "",
            "body": _flag(args, "--body") or "",
            "state": "OPEN",
            "labels": [label] if label else [],
        })
        save()
        print(f"https://github.com/a-jay85/IBL5-backlog/issues/{num}")
        return 0

    if args[:2] == ["issue", "edit"] and len(args) > 2:
        issue = find(args[2])
        if issue is None:
            print(f"fake gh: no issue #{args[2]}", file=sys.stderr)
            return 1
        title = _flag(args, "--title")
        if title is not None:
            issue["title"] = title
        body = _flag(args, "--body")
        if body is not None:
            issue["body"] = body
        label = _flag(args, "--add-label")
        if label and label not in issue.setdefault("labels", []):
            issue["labels"].append(label)
        save()
        return 0

    if args[:2] == ["issue", "reopen"] and len(args) > 2:
        issue = find(args[2])
        if issue is None:
            print(f"fake gh: no issue #{args[2]}", file=sys.stderr)
            return 1
        issue["state"] = "OPEN"
        save()
        return 0

    print(f"fake gh: unsupported {args}", file=sys.stderr)
    return 1


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
