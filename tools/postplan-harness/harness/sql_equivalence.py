"""SQL-equivalence evidence for the `sql-equivalent` hold_trigger token.

A pure SQL rewrite plan runs the old and new query over the same input cases on the
local DB, dumping one `HASH <case-id> <sha256 of the ordered rows>` line per case. The
recorder compares the two dumps and writes a committed evidence file at
`ibl5/tests/sql-equivalence/<branch>.json`. The seat sees that file in the diff.

At arming time the runner calls `arm_input_value`. It re-checks the evidence file
against the reviewed tree and returns exactly "ok" only when every check passes. Any
other value keeps condition (7) with the owner (armable.plan_triggers_clearable).

The evidence binds to the git blob SHA of each rewritten source file. A tree hash
would be circular, since writing the file changes the tree, and post-plan's commit
changes the commit SHA. A blob SHA survives that commit and goes stale on any edit.

    python3 -m harness.sql_equivalence record --branch B --before F --after F \\
        --source P [--source P ...] --bind-test T [--bind-test T ...]
    python3 -m harness.sql_equivalence check --branch B
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path

TOKEN = "sql-equivalent"
EVIDENCE_DIR = "ibl5/tests/sql-equivalence"
SCHEMA = 1
HASH_LINE = re.compile(r"^HASH[ \t]")


def read_hash_lines(path) -> list[str]:
    """The case lines of a dump, in order. Only lines starting `HASH<space|tab>` count."""
    text = Path(path).read_text()
    return [line for line in text.splitlines() if HASH_LINE.match(line)]


def digest(lines) -> str:
    return hashlib.sha256("\n".join(lines).encode()).hexdigest()


def evidence_path(root, branch) -> Path:
    if not branch or "/" in branch or ".." in branch or any(ch.isspace() for ch in branch):
        raise ValueError(f"bad branch name for evidence path: {branch!r}")
    return Path(root) / EVIDENCE_DIR / f"{branch}.json"


def _blob_sha(root, rel_path) -> str:
    proc = subprocess.run(["git", "hash-object", rel_path], cwd=str(root),
                          check=True, capture_output=True, text=True)
    return proc.stdout.strip()


def record(root, branch, before, after, sources, bind_tests) -> int:
    """Write the evidence file. 0 on pass, 1 on mismatch or zero cases, 2 on refusal."""
    sources = list(sources or ())
    bind_tests = list(bind_tests or ())
    if not sources or not bind_tests:
        print("sql_equivalence: record needs at least one --source and one --bind-test",
              file=sys.stderr)
        return 2
    before_lines = read_hash_lines(before)
    after_lines = read_hash_lines(after)
    ok = bool(before_lines) and before_lines == after_lines
    data = {
        "schema": SCHEMA,
        "status": "pass" if ok else "fail",
        "cases": len(before_lines),
        "before_digest": digest(before_lines),
        "after_digest": digest(after_lines),
        "sources": {src: _blob_sha(root, src) for src in sources},
        "bind_tests": bind_tests,
    }
    out = evidence_path(root, branch)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n")
    return 0 if ok else 1


def _is_bad_path(path) -> bool:
    return (not isinstance(path, str) or not path or path.startswith("/")
            or ".." in Path(path).parts)


def _has_method(root, rel_path, method) -> bool:
    """True when the file exists and declares `function <method>(`."""
    if not re.fullmatch(r"\w+", method or ""):
        return False
    file = Path(root) / rel_path
    if not file.is_file():
        return False
    return re.search(rf"\bfunction\s+{method}\s*\(", file.read_text()) is not None


def _load(path):
    """The parsed evidence object, or None when it is not a schema-1 JSON object."""
    try:
        data = json.loads(path.read_text())
    except ValueError:
        return None
    if not isinstance(data, dict) or data.get("schema") != SCHEMA:
        return None
    return data


def evaluate_evidence(root, branch, changed_files) -> str:
    """"ok" when the evidence is present, passing, in the diff and fresh; else a note."""
    try:
        changed = set(changed_files or ())
        path = evidence_path(root, branch)
        if not path.is_file():
            return "absent"
        data = _load(path)
        if data is None:
            return "malformed"
        if f"{EVIDENCE_DIR}/{branch}.json" not in changed:
            return "not in diff"
        if data.get("status") != "pass":
            return "failed"
        cases = data.get("cases")
        if not isinstance(cases, int) or isinstance(cases, bool) or cases <= 0:
            return "no cases"
        if data.get("before_digest") != data.get("after_digest"):
            return "digest mismatch"
        sources = data.get("sources")
        if not sources or not isinstance(sources, dict):
            return "no sources"
        bind_tests = data.get("bind_tests")
        if not bind_tests or not isinstance(bind_tests, list):
            return "no bind test"
        bind_paths = [t.split("::", 1)[0] if isinstance(t, str) else t for t in bind_tests]
        if any(_is_bad_path(p) for p in [*sources, *bind_paths]):
            return "bad path"
        for path, blob in sources.items():
            if path not in changed:
                return f"source not in diff: {path}"
            if not (Path(root) / path).is_file() or _blob_sha(root, path) != blob:
                return f"stale: {path}"
        for changed_path in sorted(changed):
            if (changed_path.startswith("ibl5/classes/") and changed_path.endswith(".php")
                    and changed_path not in sources):
                return f"changed source not covered: {changed_path}"
        for entry in bind_tests:
            test_path, _, method = entry.partition("::")
            if not test_path.startswith("ibl5/tests/") or not _has_method(root, test_path, method):
                return f"bind test missing: {entry}"
        return "ok"
    except Exception:
        return "error"


def arm_input_value(plan_hold_triggers, root, branch, changed_files) -> str:
    """The `ArmInputs.sql_equivalence_evidence` value. Reads nothing without the token."""
    if TOKEN not in set(plan_hold_triggers or ()):
        return "not-applicable"
    return evaluate_evidence(root, branch, changed_files)


def _git(args, cwd=None) -> str:
    proc = subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True, text=True)
    return proc.stdout


def _changed_files(root) -> list[str]:
    """Files this branch changes vs origin/master, plus uncommitted ones."""
    files = set(_git(["diff", "--name-only", "origin/master...HEAD"], cwd=root).splitlines())
    for line in _git(["status", "--porcelain", "--untracked-files=all"], cwd=root).splitlines():
        entry = line[3:]
        files.add(entry.split(" -> ", 1)[-1].strip('"'))
    return sorted(f for f in files if f)


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="python3 -m harness.sql_equivalence")
    sub = parser.add_subparsers(dest="command", required=True)
    rec = sub.add_parser("record", help="compare two HASH dumps and write the evidence file")
    rec.add_argument("--branch", required=True)
    rec.add_argument("--before", required=True)
    rec.add_argument("--after", required=True)
    rec.add_argument("--source", action="append", default=[])
    rec.add_argument("--bind-test", action="append", default=[], dest="bind_test")
    chk = sub.add_parser("check", help="print the arming verdict for this branch's evidence")
    chk.add_argument("--branch", required=True)
    return parser


def main(argv) -> int:
    parser = _parser()
    args = parser.parse_args(argv)
    root = _git(["rev-parse", "--show-toplevel"]).strip()
    if args.command == "record":
        rc = record(root, args.branch, args.before, args.after, args.source, args.bind_test)
        if rc != 2:
            print(f"sql_equivalence: wrote {evidence_path(root, args.branch)} "
                  f"({'pass' if rc == 0 else 'fail'})")
        return rc
    verdict = evaluate_evidence(root, args.branch, _changed_files(root))
    print(verdict)
    return 0 if verdict == "ok" else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
