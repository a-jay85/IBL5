"""Trace-pinned evidence for the `trace-pinned` hold_trigger token.

An engine port PR that changes the golden snapshot may clear arming conditions (5) and (7)
without the owner when a Go test pins the ported routine to a raw JSB capture. The capture
is a per-call (input, output) dump taken by a hook on the original JSB binary
(`engine/docs/jsb-trace-capture.md`). The PR commits an evidence file at
`engine/trace-evidence/<branch>.json` naming each test and the capture it loads.

At arming time the runner calls `arm_input_value`. It returns exactly "ok" only when:

(a) every changed path is an engine `.go` file, a golden snapshot file, or this branch's
    evidence file, and no raw capture is added or modified;
(b) each named test starts with `TestTracePinned`, sits in a changed `_test.go` file, calls
    `jsbtrace.MustLoad(`, and its blob SHA matches the evidence;
    each capture already exists on the base commit with the recorded sha256;
(c) each test passes under `go test -json`, and fails when every captured output is
    perturbed, which proves its expected values come from the capture.

Any other state returns a reason string, and armable keeps both holds (fail closed).

    python3 -m harness.trace_pinned record --branch B --test ./internal/sim:TestTracePinnedX \\
        --capture x.jsonl [--test ... --capture ...]
    python3 -m harness.trace_pinned check --branch B
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

from .sql_equivalence import _blob_sha, _git

TOKEN = "trace-pinned"
EVIDENCE_DIR = "engine/trace-evidence"
CAPTURE_DIR = "engine/testdata/jsb-captures"
SCHEMA = 1
TEST_PREFIX = "TestTracePinned"
GOLDEN_FILES = ("engine/internal/sim/testdata/golden.json",
                "engine/internal/sim/testdata/golden_seeds.sha256")
GO_TIMEOUT = 300
CAPTURE_FORMAT = "jsb-trace/1"
CAPTURE_SOURCES = ("frida", "x32dbg")
_ADDRESS = re.compile(r"^0x[0-9a-f]+$")
_PACKAGE = re.compile(r"^\./[A-Za-z0-9_./-]+$")


def evidence_rel(branch) -> str:
    return f"{EVIDENCE_DIR}/{branch}.json"


def evidence_path(root, branch) -> Path:
    if not branch or "/" in branch or ".." in branch or any(ch.isspace() for ch in branch):
        raise ValueError(f"bad branch name for evidence path: {branch!r}")
    return Path(root) / evidence_rel(branch)


def _load(path):
    """The parsed evidence object, or None when it is not a schema-1 JSON object."""
    try:
        data = json.loads(Path(path).read_text())
    except (OSError, ValueError):
        return None
    if not isinstance(data, dict) or data.get("schema") != SCHEMA:
        return None
    return data


def _sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def _git_bytes(args, cwd) -> bytes:
    proc = subprocess.run(["git", *args], cwd=str(cwd), check=True, capture_output=True)
    return proc.stdout


# --- (a) path allowlist -----------------------------------------------------------------------

def allowlist_verdict(changed_files, branch) -> str:
    files = list(changed_files or ())
    if not files:
        return "empty-range"
    own_evidence = evidence_rel(branch)
    for path in files:
        if path.startswith(CAPTURE_DIR + "/"):
            return f"capture-in-diff: {path}"
        if path in GOLDEN_FILES or path == own_evidence:
            continue
        if path.startswith("engine/") and path.endswith(".go"):
            continue
        return f"non-engine-path: {path}"
    return "ok"


# --- capture parsing and perturbation --------------------------------------------------------

def _capture_lines(data: bytes):
    """(header, records) of a raw capture, or None when it does not parse per jsb-trace/1."""
    try:
        lines = [ln for ln in data.decode("utf-8").splitlines() if ln.strip()]
        if len(lines) < 2:
            return None
        header = json.loads(lines[0])
        records = [json.loads(ln) for ln in lines[1:]]
    except (UnicodeDecodeError, ValueError):
        return None
    if not isinstance(header, dict) or header.get("format") != CAPTURE_FORMAT:
        return None
    if header.get("source") not in CAPTURE_SOURCES:
        return None
    for key in ("jsb_build", "function"):
        if not isinstance(header.get(key), str) or not header[key]:
            return None
    if not isinstance(header.get("address"), str) or not _ADDRESS.match(header["address"]):
        return None
    for rec in records:
        if not isinstance(rec, dict) or not isinstance(rec.get("in"), dict) \
                or not isinstance(rec.get("out"), dict):
            return None
    return header, records


def perturb(value):
    """(value with every leaf shifted, number of leaves shifted). Numbers get +1, bools
    flip, strings get a suffix; lists and objects recurse; null stays."""
    if isinstance(value, bool):
        return (not value), 1
    if isinstance(value, (int, float)):
        return value + 1, 1
    if isinstance(value, str):
        return value + "~perturbed", 1
    if isinstance(value, list):
        out, n = [], 0
        for item in value:
            new, k = perturb(item)
            out.append(new)
            n += k
        return out, n
    if isinstance(value, dict):
        out, n = {}, 0
        for key, item in value.items():
            new, k = perturb(item)
            out[key] = new
            n += k
        return out, n
    return value, 0


def write_perturbed_dir(root, capture, dest) -> int:
    """Copy the capture dir to `dest` with `capture`'s outputs perturbed; the leaf count."""
    shutil.copytree(Path(root) / CAPTURE_DIR, dest, dirs_exist_ok=True)
    parsed = _capture_lines((Path(root) / capture).read_bytes())
    if parsed is None:
        return 0
    header, records = parsed
    total = 0
    lines = [json.dumps(header)]
    for rec in records:
        new_out, n = perturb(rec["out"])
        total += n
        lines.append(json.dumps({**rec, "out": new_out}))
    (Path(dest) / Path(capture).name).write_text("\n".join(lines) + "\n")
    return total


# --- (c) go test -json -----------------------------------------------------------------------

def parse_go_test_json(stdout, test) -> str:
    """"pass", "skip", "fail" or "absent" for `test` in a `go test -json` stream."""
    passed = False
    for line in (stdout or "").splitlines():
        try:
            ev = json.loads(line)
        except ValueError:
            continue
        if not isinstance(ev, dict) or ev.get("Test") != test:
            continue
        if ev.get("Action") in ("skip", "fail"):
            return ev["Action"]
        if ev.get("Action") == "pass":
            passed = True
    if passed:
        return "pass"
    return "absent"


def run_go_test(root, package, test, env=None) -> str:
    """The verdict of one `go test -json -run '^<test>$' <package>` run in `<root>/engine`."""
    if shutil.which("go") is None:
        return "go-unavailable"
    run_env = {k: v for k, v in os.environ.items() if k != "JSB_TRACE_CAPTURE_DIR"}
    run_env.update(env or {})
    try:
        proc = subprocess.run(
            ["go", "test", "-count=1", "-json", "-run", f"^{test}$", package],
            cwd=str(Path(root) / "engine"), env=run_env, capture_output=True, text=True,
            timeout=GO_TIMEOUT)
    except subprocess.TimeoutExpired:
        return "timeout"
    except OSError:
        return "go-error"
    result = parse_go_test_json(proc.stdout, test)
    if result == "pass" and proc.returncode != 0:
        return f"exit-{proc.returncode}"
    return result


# --- the checker -----------------------------------------------------------------------------

def resolve_base(root, branch) -> str:
    """merge-base of HEAD and origin/<iblBase> (origin/master when unset); "" on failure."""
    try:
        declared = _git(["config", f"branch.{branch}.iblBase"], cwd=str(root)).strip()
    except (subprocess.CalledProcessError, OSError):
        declared = ""
    ref = f"origin/{declared}" if declared else "origin/master"
    try:
        return _git(["merge-base", "HEAD", ref], cwd=str(root)).strip()
    except (subprocess.CalledProcessError, OSError):
        return ""


def _check_test_entry(root, entry, changed) -> str:
    """Steps (b) and capture provenance for one evidence entry, before any Go run."""
    test, file, package = entry.get("test"), entry.get("file"), entry.get("package")
    if not isinstance(test, str) or not test.startswith(TEST_PREFIX) \
            or not re.fullmatch(r"\w+", test):
        return "not-trace-pinned-name"
    if not isinstance(file, str) or not file.endswith("_test.go") or file not in changed:
        return "test-not-in-diff"
    if not isinstance(package, str) or not _PACKAGE.match(package) or ".." in package:
        return "evidence-malformed"
    test_file = Path(root) / file
    source = test_file.read_text() if test_file.is_file() else ""
    if not re.search(rf"^func {test}\(t \*testing\.T\)", source, re.M) \
            or "jsbtrace.MustLoad(" not in source:
        return "test-shape"
    if _blob_sha(root, file) != entry.get("test_blob"):
        return "stale-test-blob"
    return "ok"


def _check_capture(root, base, entry) -> str:
    capture = entry.get("capture")
    if not isinstance(capture, str) or str(Path(capture).parent) != CAPTURE_DIR \
            or not capture.endswith(".jsonl") or ".." in capture:
        return "capture-bad-path"
    try:
        _git(["cat-file", "-e", f"{base}:{capture}"], cwd=str(root))
    except subprocess.CalledProcessError:
        return "capture-not-on-base"
    base_bytes = _git_bytes(["show", f"{base}:{capture}"], root)
    work = Path(root) / capture
    work_sha = _sha256(work.read_bytes()) if work.is_file() else ""
    want = entry.get("capture_sha256")
    if _sha256(base_bytes) != want or work_sha != want:
        return "capture-hash-mismatch"
    if _capture_lines(base_bytes) is None:
        return "capture-unreadable"
    return "ok"


def _run_entry(root, entry, run_go) -> str:
    """Steps (c) and the perturbation run for one evidence entry."""
    test, package, capture = entry["test"], entry["package"], entry["capture"]
    verdict = run_go(root, package, test)
    if verdict != "pass":
        return f"test-{verdict}"
    with tempfile.TemporaryDirectory(prefix="trace-pinned-") as tmp:
        if write_perturbed_dir(root, capture, tmp) == 0:
            return "capture-out-unperturbable"
        verdict = run_go(root, package, test, env={"JSB_TRACE_CAPTURE_DIR": tmp})
    if verdict != "fail":
        if verdict == "pass":
            return f"circular: {test} passes with perturbed JSB outputs"
        return f"perturbation-{verdict}"
    return "ok"


def evaluate_data(root, branch, base, changed_files, data, run_go=None) -> str:
    """The verdict for an already-loaded evidence object."""
    run_go = run_go or run_go_test
    tests = data.get("tests") if isinstance(data, dict) else None
    if data.get("token") != TOKEN or data.get("branch") != branch \
            or not isinstance(tests, list) or not tests \
            or not all(isinstance(t, dict) for t in tests):
        return "evidence-malformed"
    changed = list(changed_files or ())
    allowed = allowlist_verdict(changed, branch)
    if allowed != "ok":
        return allowed
    if not base:
        return "base-unresolved"
    for entry in tests:
        for step in (lambda: _check_test_entry(root, entry, changed),
                     lambda: _check_capture(root, base, entry),
                     lambda: _run_entry(root, entry, run_go)):
            note = step()
            if note != "ok":
                return note
    return "ok"


def evaluate_evidence(root, branch, base, changed_files, run_go=None) -> str:
    """"ok" when the committed evidence passes every check; else a reason string."""
    try:
        path = evidence_path(root, branch)
    except ValueError:
        return "evidence-malformed"
    if not path.is_file():
        return "evidence-missing"
    data = _load(path)
    if data is None:
        return "evidence-malformed"
    return evaluate_data(root, branch, base, changed_files, data, run_go)


def arm_input_value(plan_hold_triggers, root, branch, changed_files) -> str:
    """The `ArmInputs.trace_pinned_evidence` value. Reads nothing without the token."""
    if TOKEN not in set(plan_hold_triggers or ()):
        return "not-applicable"
    try:
        base = resolve_base(root, branch)
        return evaluate_evidence(root, branch, base, changed_files)
    except Exception as exc:  # noqa: BLE001 — fail closed, never raise into arming
        return f"error: {type(exc).__name__}"


# --- CLI -------------------------------------------------------------------------------------

def changed_files_since(root, base) -> list[str]:
    """Files changed vs `base` (both sides of a rename), plus untracked ones."""
    files = set(_git(["diff", "--name-only", "--no-renames", base], cwd=str(root)).splitlines())
    for line in _git(["status", "--porcelain", "--untracked-files=all"], cwd=str(root)).splitlines():
        if line.startswith("??"):
            files.add(line[3:].strip('"'))
    return sorted(f for f in files if f)


def _find_test_file(root, package, test) -> str:
    pkg_dir = Path(root) / "engine" / package
    pattern = re.compile(rf"^func {re.escape(test)}\(t \*testing\.T\)", re.M)
    for path in sorted(pkg_dir.glob("*_test.go")):
        if pattern.search(path.read_text()):
            return str(path.relative_to(root))
    return ""


def record(root, branch, test_specs, capture_names, run_go=None) -> int:
    """Write the evidence file after every check passes. 0 written, 1 refused, 2 usage."""
    test_specs, capture_names = list(test_specs or ()), list(capture_names or ())
    if not test_specs or len(test_specs) != len(capture_names):
        print("trace_pinned: record needs equal counts of --test and --capture", file=sys.stderr)
        return 2
    tests = []
    for spec, name in zip(test_specs, capture_names):
        package, sep, test = spec.rpartition(":")
        if not sep or not package or not test or "/" in name:
            print(f"trace_pinned: bad --test {spec!r} or --capture {name!r}", file=sys.stderr)
            return 2
        capture = f"{CAPTURE_DIR}/{name}"
        file = _find_test_file(root, package, test)
        capture_file = Path(root) / capture
        tests.append({
            "package": package, "test": test, "file": file,
            "test_blob": _blob_sha(root, file) if file else "",
            "capture": capture,
            "capture_sha256": _sha256(capture_file.read_bytes()) if capture_file.is_file() else "",
        })
    data = {"schema": SCHEMA, "token": TOKEN, "branch": branch, "tests": tests}
    base = resolve_base(root, branch)
    changed = sorted(set(changed_files_since(root, base) if base else []) | {evidence_rel(branch)})
    verdict = evaluate_data(root, branch, base, changed, data, run_go)
    if verdict != "ok":
        print(f"trace_pinned: refusing to write evidence: {verdict}", file=sys.stderr)
        return 1
    out = evidence_path(root, branch)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(data, indent=2, sort_keys=True) + "\n")
    print(f"trace_pinned: wrote {out}")
    return 0


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="python3 -m harness.trace_pinned")
    sub = parser.add_subparsers(dest="command", required=True)
    rec = sub.add_parser("record", help="run the checks and write the evidence file")
    rec.add_argument("--branch", required=True)
    rec.add_argument("--test", action="append", default=[])
    rec.add_argument("--capture", action="append", default=[])
    chk = sub.add_parser("check", help="print the arming verdict for this branch's evidence")
    chk.add_argument("--branch", required=True)
    return parser


def main(argv) -> int:
    args = _parser().parse_args(argv)
    root = _git(["rev-parse", "--show-toplevel"]).strip()
    if args.command == "record":
        return record(root, args.branch, args.test, args.capture)
    base = resolve_base(root, args.branch)
    verdict = (evaluate_evidence(root, args.branch, base, changed_files_since(root, base))
               if base else "base-unresolved")
    print(verdict)
    return 0 if verdict == "ok" else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
