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

Evidence carries a `kind` (ADR-0248 `rewrite`, or `new-query` for a new read-only
query that serves an existing read path). Both kinds run the static read-only scan in
`harness.sql_readonly_scan`. A `new-query` file also names a committed
`ibl5/tests/DatabaseIntegration/**Test.php::method` equivalence test.

    python3 -m harness.sql_equivalence record --branch B --kind rewrite|new-query \\
        --before F --after F --source P [--source P ...] --bind-test T [--bind-test T ...] \\
        [--equivalence-test T ...]
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

from harness import sql_readonly_scan

TOKEN = "sql-equivalent"
EVIDENCE_DIR = "ibl5/tests/sql-equivalence"
SCHEMA = 2
KINDS = ("rewrite", "new-query")
EQUIVALENCE_TEST_PATH = re.compile(r"^ibl5/tests/DatabaseIntegration/.+Test\.php::\w+$")
HUNK_HEADER = re.compile(r"^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@")
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


def record(root, branch, before, after, sources, bind_tests, kind="rewrite",
           equivalence_tests=()) -> int:
    """Write the evidence file. 0 on pass, 1 on mismatch or zero cases, 2 on refusal."""
    sources = list(sources or ())
    bind_tests = list(bind_tests or ())
    equivalence_tests = list(equivalence_tests or ())
    if not sources or not bind_tests:
        print("sql_equivalence: record needs at least one --source and one --bind-test",
              file=sys.stderr)
        return 2
    if kind not in KINDS:
        print(f"sql_equivalence: unknown --kind {kind!r}", file=sys.stderr)
        return 2
    if kind == "new-query" and not equivalence_tests:
        print("sql_equivalence: --kind new-query needs at least one --equivalence-test",
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
        "kind": kind,
        "equivalence_tests": equivalence_tests,
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
    """The parsed evidence object, or None when it is not a current-schema JSON object."""
    try:
        data = json.loads(path.read_text())
    except ValueError:
        return None
    if not isinstance(data, dict) or data.get("schema") != SCHEMA:
        return None
    return data


_GUARD_WORDS = re.compile(
    r"\bthrow\b|\bin_array\b|\bif\b|\breturn\b|\bpreg_\w+|\bintval\b|\bctype_\w+|\bis_numeric\b"
    r"|\bfilter_var\b|\bescape\w*|\bquote\w*|\bhtmlspecialchars\b|\(\s*(?:int|float|bool)\s*\)|\babs\s*\(",
    re.IGNORECASE)


def parse_added_lines(diff_text, total_lines=None) -> frozenset[int]:
    """Head line numbers a `git diff --unified=0` adds or deletes beside, from its `@@ -a,b +c,d @@` headers.

    A hunk that deletes a guard-looking line (throw, in_array, a cast, an escape), whether
    it is a pure deletion or a replacement, can strand the guard far from the statement it
    protected. When `total_lines` is given, that deletion marks every head line so each SQL
    statement in the file is judged.
    """
    added: set[int] = set()
    for line in diff_text.splitlines():
        m = HUNK_HEADER.match(line)
        if m:
            start = int(m.group(1))
            count = 1 if m.group(2) is None else int(m.group(2))
            added.update(range(start, start + count))
            if count == 0:
                # a pure deletion adds no line; mark the head lines on both sides of the
                # gap so a statement that lost its guard (a sanitizer) still gets judged
                added.update(ln for ln in (start, start + 1) if ln >= 1)
        elif line.startswith("-") and not line.startswith("---"):
            # any deleted guard-looking line counts, also when a replacement line is added
            # beside it (`-1 +1`): the unchanged SQL line it protected must be re-judged
            if total_lines and _GUARD_WORDS.search(line[1:]):
                added.update(range(1, total_lines + 1))
    return frozenset(added)


def _base_text(root, base, path):
    """The file at `base`, or None when it is new on the branch. Other git failures raise."""
    proc = subprocess.run(["git", "show", f"{base}:{path}"], cwd=str(root),
                          capture_output=True, text=True)
    if proc.returncode == 0:
        return proc.stdout
    if "does not exist" in proc.stderr or "exists on disk, but not in" in proc.stderr:
        return None
    raise subprocess.CalledProcessError(proc.returncode, proc.args, proc.stdout, proc.stderr)


def _load_source_pairs(root, base, paths) -> dict:
    """Base text, head text and added lines per path, diffed against `base`."""
    pairs = {}
    for path in paths:
        head = (Path(root) / path).read_text()
        diff = _git(["diff", "--no-color", "--unified=0", base, "--", path], cwd=root)
        base_text = _base_text(root, base, path)
        added = parse_added_lines(diff, len(head.splitlines()))
        if base_text is None and not added:
            # an untracked new file has no diff; every line of it is new
            added = frozenset(range(1, len(head.splitlines()) + 1))
        pairs[path] = sql_readonly_scan.SourcePair(base_text, head, added)
    return pairs


def resolve_base(root, base="origin/master") -> str:
    """The merge-base `_changed_files` diffs from (`<base>...HEAD`)."""
    return _git(["merge-base", base, "HEAD"], cwd=root).strip()


def evaluate_evidence(root, branch, changed_files, base=None) -> str:
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
        kind = data.get("kind")
        if kind not in ("rewrite", "new-query"):
            return "malformed: kind"
        if kind == "new-query":
            note = _equivalence_tests_note(root, data.get("equivalence_tests"), changed)
            if note:
                return note
        php = [p for p in sorted(changed) if p.endswith(".php") and not p.startswith("ibl5/tests/")]
        scanned = [p for p in php if (Path(root) / p).is_file()]
        merge_base = resolve_base(root) if base is None else base
        pairs = _load_source_pairs(root, merge_base, scanned)
        for gone in php:
            if gone not in pairs:
                # a deleted file adds no line, so it scans as an empty head
                pairs[gone] = sql_readonly_scan.SourcePair(_base_text(root, merge_base, gone), "", frozenset())
        scan_note = sql_readonly_scan.scan_sources(kind, sorted(changed), pairs)
        if scan_note != "ok":
            return scan_note
        return "ok"
    except Exception:
        return "error"


def _equivalence_tests_note(root, equivalence_tests, changed):
    """None when every new-query equivalence test is a DatabaseIntegration test in the diff."""
    if not equivalence_tests or not isinstance(equivalence_tests, list):
        return "no equivalence test"
    for eq_path in equivalence_tests:
        if not isinstance(eq_path, str):
            return "equivalence test path"
        if not EQUIVALENCE_TEST_PATH.match(eq_path):
            return f"equivalence test path: {eq_path}"
        test_path, _, method = eq_path.partition("::")
        if _is_bad_path(test_path):
            return "bad path"
        if test_path not in changed:
            return f"equivalence test not in diff: {eq_path}"
        if not _has_method(root, test_path, method):
            return f"equivalence test missing: {eq_path}"
    return None


def arm_input_value(plan_hold_triggers, root, branch, changed_files, base=None) -> str:
    """The `ArmInputs.sql_equivalence_evidence` value. Reads nothing without the token."""
    if TOKEN not in set(plan_hold_triggers or ()):
        return "not-applicable"
    return evaluate_evidence(root, branch, changed_files, base)


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
    rec.add_argument("--kind", choices=("rewrite", "new-query"), required=True)
    rec.add_argument("--before", required=True)
    rec.add_argument("--after", required=True)
    rec.add_argument("--source", action="append", default=[])
    rec.add_argument("--bind-test", action="append", default=[], dest="bind_test")
    rec.add_argument("--equivalence-test", action="append", default=[], dest="equivalence_test")
    chk = sub.add_parser("check", help="print the arming verdict for this branch's evidence")
    chk.add_argument("--branch", required=True)
    return parser


def main(argv) -> int:
    parser = _parser()
    args = parser.parse_args(argv)
    root = _git(["rev-parse", "--show-toplevel"]).strip()
    if args.command == "record":
        rc = record(root, args.branch, args.before, args.after, args.source, args.bind_test,
                    args.kind, args.equivalence_test)
        if rc != 2:
            print(f"sql_equivalence: wrote {evidence_path(root, args.branch)} "
                  f"({'pass' if rc == 0 else 'fail'})")
        return rc
    verdict = evaluate_evidence(root, args.branch, _changed_files(root))
    print(verdict)
    return 0 if verdict == "ok" else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
