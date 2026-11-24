"""Trace-pinned evidence for the `trace-pinned` hold_trigger token.

An engine port PR that changes the golden snapshot may clear arming conditions (5) and (7)
without the owner when a Go test pins the ported routine to a raw JSB capture. The capture
is a per-call (input, output) dump taken by a hook on the original JSB binary
(`engine/docs/jsb-trace-capture.md`). The PR commits an evidence file (schema 2) at
`engine/trace-evidence/<branch>.json` naming each test, the capture it loads, and the
non-test engine `.go` files it ports (`ported_files`).

At arming time the runner calls `arm_input_value`. It returns exactly "ok" only when:

(a) every changed path is an engine `_test.go` file, a non-test engine `.go` file that some
    entry declares in `ported_files`, a golden snapshot file, or this branch's evidence
    file, and no raw capture or capture-loader file is added or modified; each declared
    file is a changed, existing, non-test engine `.go` file outside those two directories.
    Two inert paths also pass without a declaration: `engine/docs/jsb-trace-capture.md`
    when every change sits inside its "Rows blocked on a richer capture" section (a port
    that lifts a capture limit rewrites its bullet there), and
    `engine/internal/sim/drawsites.go` when every line it adds or removes is one
    `DrawSites` map entry (that table only labels draws in `cmd/jsbreplay` output, and Go
    coverage gives a map literal no block, so it can never meet (c));
(b) each named test starts with `TestTracePinned`, sits in a changed `_test.go` file, calls
    `jsbtrace.MustLoad(`, and its blob SHA matches the evidence;
    each capture already exists on the base commit with the recorded sha256, or, for an
    entry with `capture_host: jsb-native`, exists at the recorded jsb-native commit
    reachable from that repo's `main`, with the recorded sha256;
(c) each test passes under `go test -json -coverprofile`, executes at least one line the PR
    adds in every file its entry declares, and fails when every captured output is
    perturbed, which proves its expected values come from the capture.

Any other state returns a reason string, and armable keeps both holds (fail closed).

    python3 -m harness.trace_pinned record --branch B --test ./internal/sim:TestTracePinnedX \\
        --capture x.jsonl --ported engine/internal/sim/x.go[,engine/...] [--test ... ...]
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
CAPTURE_HOSTS = ("repo", "jsb-native")
HOSTED_DIR = "captures"  # capture dir inside the private jsb-native repo
JSB_NATIVE_MAIN = "refs/heads/main"
_COMMIT = re.compile(r"^[0-9a-f]{40}$")
LOADER_DIR = "engine/internal/jsbtrace"  # the capture loader every pinned test trusts
SCHEMA = 2
TEST_PREFIX = "TestTracePinned"
GOLDEN_FILES = ("engine/internal/sim/testdata/golden.json",
                "engine/internal/sim/testdata/golden_seeds.sha256")
GO_TIMEOUT = 300
CAPTURE_FORMAT = "jsb-trace/1"
CAPTURE_SOURCES = ("frida", "x32dbg")
_ADDRESS = re.compile(r"^0x[0-9a-f]+$")
_PACKAGE = re.compile(r"^\./[A-Za-z0-9_./-]+$")
GO_MOD = "engine/go.mod"
_HUNK = re.compile(r"^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@")
DRAW_SITES = "engine/internal/sim/drawsites.go"
# doc path -> the one heading whose section a trace-pinned port may edit
INERT_DOC_SECTIONS = {"engine/docs/jsb-trace-capture.md": "## Rows blocked on a richer capture"}
_OLD_NEW_HUNK = re.compile(r"^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@")
_DRAW_SITE_ENTRY = re.compile(r'^\s*"sim\.[A-Za-z0-9_.()*]+":\s*"(FUN_[0-9a-f]{8})?",\s*$')
_COVER_LINE = re.compile(r"^(.+\.go):(\d+)\.\d+,(\d+)\.\d+ \d+ (\d+)$")


def evidence_rel(branch) -> str:
    return f"{EVIDENCE_DIR}/{branch}.json"


def evidence_path(root, branch) -> Path:
    if not branch or "/" in branch or ".." in branch or any(ch.isspace() for ch in branch):
        raise ValueError(f"bad branch name for evidence path: {branch!r}")
    return Path(root) / evidence_rel(branch)


def _load(path):
    """The parsed evidence object, or None unless it is a JSON object with the current SCHEMA."""
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

def allowlist_verdict(changed_files, branch, ported=frozenset()) -> str:
    files = list(changed_files or ())
    if not files:
        return "empty-range"
    own_evidence = evidence_rel(branch)
    for path in files:
        if path.startswith(CAPTURE_DIR + "/"):
            return f"capture-in-diff: {path}"
        if path.startswith(LOADER_DIR + "/"):
            return f"loader-in-diff: {path}"
        if path in GOLDEN_FILES or path == own_evidence:
            continue
        if path in INERT_DOC_SECTIONS or (path == DRAW_SITES and path not in ported):
            continue  # the content check runs in evaluate_data once the base resolves
        if path.startswith("engine/") and path.endswith(".go"):
            if path.endswith("_test.go") or path in ported:
                continue
            return f"undeclared-engine-file: {path}"
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


def run_go_test(root, package, test, env=None, cover_profile=None, cover_pkgs=()) -> str:
    """The verdict of one `go test -json -run '^<test>$' <package>` run in `<root>/engine`."""
    if shutil.which("go") is None:
        return "go-unavailable"
    run_env = {k: v for k, v in os.environ.items() if k != "JSB_TRACE_CAPTURE_DIR"}
    run_env.update(env or {})
    cmd = ["go", "test", "-count=1", "-json", "-run", f"^{test}$"]
    if cover_profile:
        cmd += ["-covermode=set", f"-coverpkg={','.join(cover_pkgs)}", f"-coverprofile={cover_profile}"]
    cmd.append(package)
    try:
        proc = subprocess.run(
            cmd,
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


def _ported_list(entry):
    """The entry's declared ported_files, or None unless a non-empty list of unique strings."""
    ported = entry.get("ported_files")
    if not isinstance(ported, list) or not ported:
        return None
    if not all(isinstance(p, str) for p in ported) or len(set(ported)) != len(ported):
        return None
    return ported


def _bad_ported_path(path) -> bool:
    parts = path.split("/")
    return (not path.startswith("engine/") or not path.endswith(".go") or "\\" in path
            or any(part in ("", ".", "..") for part in parts))


def _check_ported(root, entry, changed) -> str:
    """Each declared file is a changed, existing, non-test engine .go file outside the
    capture and loader directories."""
    ported = _ported_list(entry)
    if ported is None:
        return "ported-malformed"
    for path in ported:
        if _bad_ported_path(path):
            return f"ported-bad-path: {path}"
        if path.endswith("_test.go"):
            return f"ported-test-file: {path}"
        if path.startswith(CAPTURE_DIR + "/") or path.startswith(LOADER_DIR + "/"):
            return f"ported-protected-dir: {path}"
        if not (Path(root) / path).is_file():
            return f"ported-missing: {path}"
        if path not in changed:
            return f"ported-not-in-diff: {path}"
    return "ok"


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


def jsb_native_dir(root) -> Path:
    """The jsb-native checkout beside the main IBL5 checkout. A worktree resolves to the main
    checkout's copy; a CI checkout resolves to a path that does not exist. No env override."""
    common = _git(["rev-parse", "--path-format=absolute", "--git-common-dir"], cwd=str(root))
    return Path(common.strip()).parent / "jsb-native"


def _hosted_ok(jsbn, args) -> bool:
    return subprocess.run(["git", "-C", str(jsbn), *args], capture_output=True).returncode == 0


def _hosted_bytes(entry, jsbn) -> bytes:
    """The capture bytes from the recorded jsb-native commit (never its working tree)."""
    return _git_bytes(["-C", str(jsbn), "show", f"{entry['capture_commit']}:{entry['capture']}"], jsbn)


def _check_hosted_capture(entry, jsbn) -> str:
    capture, commit = entry.get("capture"), entry.get("capture_commit")
    if not isinstance(capture, str) or str(Path(capture).parent) != HOSTED_DIR \
            or not capture.endswith(".jsonl") or ".." in capture:
        return "capture-bad-path"
    if not isinstance(commit, str) or not _COMMIT.match(commit):
        return "capture-commit-bad"
    jsbn = Path(jsbn)
    if not (jsbn / ".git").exists():
        return "capture-host-unavailable"
    if not _hosted_ok(jsbn, ["cat-file", "-e", f"{commit}^{{commit}}"]):
        return "capture-commit-bad"
    if not _hosted_ok(jsbn, ["merge-base", "--is-ancestor", commit, JSB_NATIVE_MAIN]):
        return "capture-commit-off-main"
    if not _hosted_ok(jsbn, ["cat-file", "-e", f"{commit}:{capture}"]):
        return "capture-not-at-commit"
    data = _hosted_bytes(entry, jsbn)
    if _sha256(data) != entry.get("capture_sha256"):
        return "capture-hash-mismatch"
    if _capture_lines(data) is None:
        return "capture-unreadable"
    return "ok"


def _check_capture(root, base, entry, jsbn_dir=None) -> str:
    host = entry.get("capture_host", "repo")
    if host == "jsb-native":
        return _check_hosted_capture(entry, jsbn_dir or jsb_native_dir(root))
    if host != "repo":
        return "capture-bad-host"
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


def _module_path(root) -> str:
    """The module line of engine/go.mod, or "" when it is missing."""
    try:
        text = (Path(root) / GO_MOD).read_text()
    except OSError:
        return ""
    found = re.search(r"^module\s+(\S+)\s*$", text, re.M)
    return found.group(1) if found else ""


def _cover_pkgs(module, ported):
    dirs = {str(Path(p).parent.relative_to("engine")) for p in ported}
    return sorted(module if d == "." else f"{module}/{d}" for d in dirs)


def parse_cover_profile(text, module):
    """{repo path: [(start line, end line, count)]} of a -coverprofile file, or None when
    the text is not one. Blocks of other modules are dropped."""
    lines = [ln for ln in (text or "").splitlines() if ln.strip()]
    if not lines or not lines[0].startswith("mode: ") or not module:
        return None
    prefix = module + "/"
    blocks = {}
    for line in lines[1:]:
        found = _COVER_LINE.match(line)
        if not found:
            return None  # malformed profile line
        name = found.group(1)
        if not name.startswith(prefix):
            continue
        rel = "engine/" + name[len(prefix):]
        blocks.setdefault(rel, []).append(
            (int(found.group(2)), int(found.group(3)), int(found.group(4))))
    return blocks


def added_lines(root, base, path):
    """Line numbers `path` adds in the work tree vs `base`; None on a git error."""
    try:
        _git(["cat-file", "-e", f"{base}:{path}"], cwd=str(root))
    except subprocess.CalledProcessError:
        text = (Path(root) / path).read_text()
        return set(range(1, len(text.splitlines()) + 1))
    try:
        diff = _git(["diff", "-U0", "--no-color", "--no-renames", base, "--", path], cwd=str(root))
    except (subprocess.CalledProcessError, OSError):
        return None
    lines = set()
    for line in diff.splitlines():
        hunk = _HUNK.match(line)
        if hunk:
            start = int(hunk.group(1))
            count = int(hunk.group(2)) if hunk.group(2) is not None else 1
            lines.update(range(start, start + count))
    return lines


_SAFE_DIFF_HEADERS = ("diff --git ", "index ", "--- ", "+++ ")


def _text_hunks(root, base, path):
    """[(old_start, old_count, new_start, new_count, [+/- lines])] of `path` vs `base`, or
    a reason string. Fails closed on a file missing on either side, a mode change, a binary
    diff, or any header line other than the plain text-diff ones."""
    if not (Path(root) / path).is_file():
        return f"inert-path-missing: {path}"
    try:
        _git(["cat-file", "-e", f"{base}:{path}"], cwd=str(root))
        diff = _git(["diff", "-U0", "--no-color", "--no-renames", "--no-ext-diff",
                     base, "--", path], cwd=str(root))
    except (subprocess.CalledProcessError, OSError):
        return f"inert-path-unreadable: {path}"
    hunks = []
    for line in diff.splitlines():
        found = _OLD_NEW_HUNK.match(line)
        if found:
            old_s, old_c, new_s, new_c = (int(g) if g is not None else 1 for g in found.groups())
            hunks.append((old_s, old_c, new_s, new_c, []))
        elif not hunks:
            if not line.startswith(_SAFE_DIFF_HEADERS):
                return f"inert-path-unsafe-diff: {path}"
        elif line.startswith(("+", "-")):
            hunks[-1][4].append(line)
    if diff.strip() and not hunks:
        return f"inert-path-unsafe-diff: {path}"
    return hunks


def draw_sites_verdict(root, base) -> str:
    """ok when every line drawsites.go adds or removes vs `base` is one DrawSites entry."""
    hunks = _text_hunks(root, base, DRAW_SITES)
    if isinstance(hunks, str):
        return hunks
    for *_, lines in hunks:
        if not all(_DRAW_SITE_ENTRY.match(line[1:]) for line in lines):
            return f"draw-sites-non-entry-change: {DRAW_SITES}"
    return "ok"


def _section_span(lines, heading):
    """(first, last) 1-based body lines of the one `heading` section, or None."""
    starts = [n for n, line in enumerate(lines, 1) if line.rstrip() == heading]
    if len(starts) != 1:
        return None
    start = starts[0]
    end = next((n - 1 for n, line in enumerate(lines, 1)
                if n > start and line.startswith("#")), len(lines))
    return start, end


def _inside(start, count, span) -> bool:
    """A hunk side (start, count) sits in the section body; count 0 inserts after `start`."""
    head, end = span
    if count == 0:
        return head <= start <= end
    return head < start and start + count - 1 <= end


def doc_section_verdict(root, base, path) -> str:
    """ok when every change to an inert doc sits inside its one allowed section."""
    heading = INERT_DOC_SECTIONS[path]
    hunks = _text_hunks(root, base, path)
    if isinstance(hunks, str):
        return hunks
    try:
        old = _git(["show", f"{base}:{path}"], cwd=str(root)).splitlines()
        new = (Path(root) / path).read_text().splitlines()
    except (subprocess.CalledProcessError, OSError, UnicodeDecodeError):
        return f"inert-path-unreadable: {path}"
    old_span, new_span = _section_span(old, heading), _section_span(new, heading)
    if old_span is None or new_span is None:
        return f"doc-section-missing: {path}"
    for old_s, old_c, new_s, new_c, lines in hunks:
        if not (_inside(old_s, old_c, old_span) and _inside(new_s, new_c, new_span)):
            return f"doc-change-outside-section: {path}"
        if any(line[1:].startswith("#") for line in lines):
            return f"doc-change-outside-section: {path}"
    return "ok"


def coverage_verdict(root, base, ported, blocks) -> str:
    """ok when the run executed at least one added line of every declared file."""
    for path in sorted(ported):
        added = added_lines(root, base, path)
        if added is None:
            return f"diff-unreadable: {path}"
        try:
            src = (Path(root) / path).read_text().splitlines()
        except OSError:
            return f"diff-unreadable: {path}"
        # blank and `//`-only lines never count: a Go cover block spans lines between statements
        added = {n for n in added if 0 < n <= len(src) and src[n - 1].strip()
                 and not src[n - 1].strip().startswith("//")}
        spans = blocks.get(path, ())
        if not any(c > 0 and s <= n <= e for n in added for s, e, c in spans):
            return f"unexercised-change: {path}"
    return "ok"


def _run_entry(root, base, entry, run_go, jsbn_dir=None) -> str:
    """Steps (c) and the perturbation run for one evidence entry."""
    if entry.get("capture_host") == "jsb-native":
        with tempfile.TemporaryDirectory(prefix="trace-pinned-hosted-") as stage_dir:
            stage = Path(stage_dir)
            name = Path(entry["capture"]).name
            dest = stage / CAPTURE_DIR / name
            dest.parent.mkdir(parents=True)
            dest.write_bytes(_hosted_bytes(entry, jsbn_dir or jsb_native_dir(root)))
            return _run_entry_at(root, base, entry, run_go, stage, f"{CAPTURE_DIR}/{name}",
                                 {"JSB_TRACE_CAPTURE_DIR": str(stage / CAPTURE_DIR)})
    return _run_entry_at(root, base, entry, run_go, root, entry["capture"], None)


def _run_entry_at(root, base, entry, run_go, capture_root, capture, cover_env) -> str:
    test, package = entry["test"], entry["package"]
    ported, module = entry["ported_files"], _module_path(root)
    with tempfile.TemporaryDirectory(prefix="trace-pinned-cover-") as cover_dir:
        profile = Path(cover_dir) / "cover.out"
        extra = {"env": cover_env} if cover_env else {}
        verdict = run_go(root, package, test, cover_profile=str(profile),
                         cover_pkgs=_cover_pkgs(module, ported), **extra)
        if verdict != "pass":
            return f"test-{verdict}"
        text = profile.read_text() if profile.is_file() else ""
    blocks = parse_cover_profile(text, module)
    if blocks is None:
        return "coverage-unreadable"
    covered = coverage_verdict(root, base, ported, blocks)
    if covered != "ok":
        return covered
    with tempfile.TemporaryDirectory(prefix="trace-pinned-") as tmp:
        if write_perturbed_dir(capture_root, capture, tmp) == 0:
            return "capture-out-unperturbable"
        verdict = run_go(root, package, test, env={"JSB_TRACE_CAPTURE_DIR": tmp})
    if verdict != "fail":
        if verdict == "pass":
            return f"circular: {test} passes with perturbed JSB outputs"
        return f"perturbation-{verdict}"
    return "ok"


def evaluate_data(root, branch, base, changed_files, data, run_go=None, jsbn_dir=None) -> str:
    """The verdict for an already-loaded evidence object."""
    run_go = run_go or run_go_test
    tests = data.get("tests") if isinstance(data, dict) else None
    if data.get("token") != TOKEN or data.get("branch") != branch \
            or not isinstance(tests, list) or not tests \
            or not all(isinstance(t, dict) for t in tests):
        return "evidence-malformed"
    changed = list(changed_files or ())
    ported = {p for t in tests for p in (_ported_list(t) or ())}
    allowed = allowlist_verdict(changed, branch, ported)
    if allowed != "ok":
        return allowed
    if not base:
        return "base-unresolved"
    if DRAW_SITES in changed and DRAW_SITES not in ported:
        note = draw_sites_verdict(root, base)
        if note != "ok":
            return note
    for doc in sorted(set(changed) & set(INERT_DOC_SECTIONS)):
        note = doc_section_verdict(root, base, doc)
        if note != "ok":
            return note
    for entry in tests:
        for step in (lambda: _check_ported(root, entry, changed),
                     lambda: _check_test_entry(root, entry, changed),
                     lambda: _check_capture(root, base, entry, jsbn_dir),
                     lambda: _run_entry(root, base, entry, run_go, jsbn_dir)):
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


def record(root, branch, test_specs, capture_names, run_go=None, ported_specs=None,
           capture_host="repo", jsbn_dir=None) -> int:
    """Write the evidence file after every check passes. 0 written, 1 refused, 2 usage."""
    test_specs, capture_names = list(test_specs or ()), list(capture_names or ())
    ported_specs = list(ported_specs or ())
    if not test_specs or len(test_specs) != len(capture_names):
        print("trace_pinned: record needs equal counts of --test and --capture", file=sys.stderr)
        return 2
    if len(ported_specs) != len(test_specs):
        print("trace_pinned: record needs one --ported per --test", file=sys.stderr)
        return 2
    tests = []
    for spec, name, ported in zip(test_specs, capture_names, ported_specs):
        package, sep, test = spec.rpartition(":")
        if not sep or not package or not test or "/" in name:
            print(f"trace_pinned: bad --test {spec!r} or --capture {name!r}", file=sys.stderr)
            return 2
        file = _find_test_file(root, package, test)
        entry = {"package": package, "test": test, "file": file,
                 "ported_files": ported.split(","),
                 "test_blob": _blob_sha(root, file) if file else ""}
        if capture_host == "jsb-native":
            capture = f"{HOSTED_DIR}/{name}"
            jsbn = Path(jsbn_dir or jsb_native_dir(root))
            commit = ""
            if (jsbn / ".git").exists():
                proc = subprocess.run(["git", "-C", str(jsbn), "log", "-1", "--format=%H",
                                       JSB_NATIVE_MAIN, "--", capture],
                                      capture_output=True, text=True)
                commit = proc.stdout.strip() if proc.returncode == 0 else ""
            entry.update({"capture": capture, "capture_host": "jsb-native",
                          "capture_commit": commit, "capture_sha256": ""})
            if commit and _hosted_ok(jsbn, ["cat-file", "-e", f"{commit}:{capture}"]):
                entry["capture_sha256"] = _sha256(_hosted_bytes(entry, jsbn))
        else:
            capture = f"{CAPTURE_DIR}/{name}"
            capture_file = Path(root) / capture
            entry.update({"capture": capture,
                          "capture_sha256": _sha256(capture_file.read_bytes())
                          if capture_file.is_file() else ""})
        tests.append(entry)
    data = {"schema": SCHEMA, "token": TOKEN, "branch": branch, "tests": tests}
    base = resolve_base(root, branch)
    changed = sorted(set(changed_files_since(root, base) if base else []) | {evidence_rel(branch)})
    verdict = evaluate_data(root, branch, base, changed, data, run_go, jsbn_dir)
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
    rec.add_argument("--capture-host", choices=CAPTURE_HOSTS, default="repo",
                     help="where every --capture lives: this repo or jsb-native captures/")
    rec.add_argument("--ported", action="append", default=[],
                     help="comma-separated engine .go files the paired --test ports; one per --test")
    chk = sub.add_parser("check", help="print the arming verdict for this branch's evidence")
    chk.add_argument("--branch", required=True)
    return parser


def main(argv) -> int:
    args = _parser().parse_args(argv)
    root = _git(["rev-parse", "--show-toplevel"]).strip()
    if args.command == "record":
        return record(root, args.branch, args.test, args.capture, ported_specs=args.ported,
                      capture_host=args.capture_host)
    base = resolve_base(root, args.branch)
    verdict = (evaluate_evidence(root, args.branch, base, changed_files_since(root, base))
               if base else "base-unresolved")
    print(verdict)
    return 0 if verdict == "ok" else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
