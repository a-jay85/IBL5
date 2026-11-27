"""The trace-pinned evidence checker.

Each test builds a throwaway git repo whose base commit holds a raw JSB capture and whose
head commit adds an engine change with a `TestTracePinned*` test. A fake `run_go` stands in
for the Go toolchain, so the suite needs no Go install. The `go test -json` parser reads
streams captured once from a real run (tests/fixtures/trace_pinned/)."""
import json
import os
import re
import subprocess
from pathlib import Path

import pytest

from harness import replay_progress
from harness import trace_pinned as tp

REPO = Path(__file__).resolve().parents[3]
FIXTURES = Path(__file__).resolve().parent / "fixtures" / "trace_pinned"
BRANCH = "port-foul-roll"
TEST = "TestTracePinnedFoulRoll"
PACKAGE = "./internal/sim"
CAPTURE_NAME = "foul-roll.jsonl"
CAPTURE = f"engine/testdata/jsb-captures/{CAPTURE_NAME}"
TEST_FILE = "engine/internal/sim/foul_roll_trace_test.go"
ENGINE_FILE = "engine/internal/sim/foul.go"
GOLDEN = "engine/internal/sim/testdata/golden.json"
MODULE = "github.com/a-jay85/IBL5/engine"
EVIDENCE = f"engine/trace-evidence/{BRANCH}.json"
CHANGED = [ENGINE_FILE, TEST_FILE, GOLDEN, EVIDENCE]

HEADER = {"format": "jsb-trace/1", "source": "frida", "jsb_build": "jsb-5.60",
          "function": "foul_roll", "address": "0x4a1f20"}
RECORDS = [{"in": {"roll": 17, "fouls": 3}, "out": {"foul": True, "site": "shooting"}},
           {"in": {"roll": 82, "fouls": 1}, "out": {"foul": False, "site": "none"}}]
TEST_SOURCE = f"""package sim

import (
\t"testing"

\t"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
)

func {TEST}(t *testing.T) {{
\tc := jsbtrace.MustLoad(t, "{CAPTURE_NAME}")
\t_ = c
}}
"""
GIT_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@example.com",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@example.com"}


def _capture_text(header=None, records=None) -> str:
    lines = [json.dumps(header or HEADER)] + [json.dumps(r) for r in (records or RECORDS)]
    return "\n".join(lines) + "\n"


def _git(root, *args) -> str:
    return subprocess.run(["git", *args], cwd=root, check=True, capture_output=True,
                          text=True, env=GIT_ENV).stdout.strip()


def _write(root, rel, body):
    path = Path(root) / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(body.encode())  # no newline translation: `\r` reaches the file as is


def _commit(root, message) -> str:
    _git(root, "add", "-A")
    _git(root, "commit", "-q", "-m", message)
    return _git(root, "rev-parse", "HEAD")


def _make_repo(tmp_path, capture_text=None, base_files=None):
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q")
    _write(root, "engine/go.mod", "module github.com/a-jay85/IBL5/engine\n\ngo 1.22\n")
    _write(root, CAPTURE, capture_text or _capture_text())
    for rel, body in (base_files or {}).items():
        _write(root, rel, body)
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 0 }\n")
    base = _commit(root, "base")
    _git(root, "update-ref", "refs/remotes/origin/master", base)
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 1 }\n")
    _write(root, TEST_FILE, TEST_SOURCE)
    _write(root, GOLDEN, "{}\n")
    _commit(root, "head")
    return root, base


def _profile(covered, zero=(), span=(1, 10000)):
    rows = ["mode: set"]
    for path, count in [*((p, 1) for p in covered), *((p, 0) for p in zero)]:
        rows.append(f"{MODULE}/{path[len('engine/'):]}:{span[0]}.1,{span[1]}.2 1 {count}")
    return "\n".join(rows) + "\n"


def _fake_go(normal="pass", perturbed="fail", calls=None, capture_name=CAPTURE_NAME,
             covered=(ENGINE_FILE,), zero=(), span=(1, 10000), write_profile=True, pkgs=None):
    def run_go(root, package, test, env=None, cover_profile=None, cover_pkgs=()):
        if calls is not None:
            calls.append(env)
        if env:
            moved = Path(env["JSB_TRACE_CAPTURE_DIR"]) / capture_name
            assert moved.read_text() != (Path(root) / tp.CAPTURE_DIR / capture_name).read_text()
            return perturbed
        if pkgs is not None and cover_profile:
            pkgs.append(list(cover_pkgs))
        if cover_profile and write_profile:
            Path(cover_profile).write_text(_profile(covered, zero, span))
        return normal
    return run_go


def _entry(root, **overrides):
    entry = {"package": PACKAGE, "test": TEST, "file": TEST_FILE,
             "test_blob": _git(root, "hash-object", TEST_FILE), "capture": CAPTURE,
             "capture_sha256": tp._sha256((Path(root) / CAPTURE).read_bytes()),
             "ported_files": [ENGINE_FILE]}
    entry.update(overrides)
    return entry


def _write_evidence(root, **overrides):
    data = {"schema": tp.SCHEMA, "token": "trace-pinned", "branch": BRANCH,
            "tests": [_entry(root, **overrides)]}
    _write(root, EVIDENCE, json.dumps(data))


def _verdict(root, base, changed=CHANGED, run_go=None):
    return tp.evaluate_evidence(root, BRANCH, base, changed, run_go=run_go or _fake_go())


@pytest.fixture
def repo(tmp_path):
    root, base = _make_repo(tmp_path)
    _write_evidence(root)
    return root, base


def _stream(name) -> str:
    return (FIXTURES / f"go-test-{name}.jsonl").read_text()


# ---- happy path ----

def test_full_evidence_is_ok(repo):
    root, base = repo
    calls = []
    assert _verdict(root, base, run_go=_fake_go(calls=calls)) == "ok"
    assert calls[0] is None and "JSB_TRACE_CAPTURE_DIR" in calls[1]


def test_arm_input_value_ok_on_full_evidence(repo, monkeypatch):
    root, _ = repo
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(("trace-pinned",), root, BRANCH, CHANGED) == "ok"


def test_arm_input_value_not_applicable_without_token(repo, monkeypatch):
    root, _ = repo
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(("golden-new",), root, BRANCH, CHANGED) == "not-applicable"
    assert tp.arm_input_value((), root, BRANCH, CHANGED) == "not-applicable"


def test_arm_input_value_never_raises(repo, monkeypatch):
    root, _ = repo

    def boom(*a, **k):
        raise RuntimeError("x")
    monkeypatch.setattr(tp, "resolve_base", boom)
    assert tp.arm_input_value(("trace-pinned",), root, BRANCH, CHANGED) == "error: RuntimeError"


# ---- evidence file ----

def test_missing_evidence_holds(tmp_path):
    root, base = _make_repo(tmp_path)
    assert _verdict(root, base) == "evidence-missing"


def test_malformed_evidence_holds(repo):
    root, base = repo
    _write(root, EVIDENCE, json.dumps({"schema": tp.SCHEMA, "token": "trace-pinned", "branch": "other",
                                       "tests": [_entry(root)]}))
    assert _verdict(root, base) == "evidence-malformed"
    _write(root, EVIDENCE, "{not json")
    assert _verdict(root, base) == "evidence-malformed"


# ---- (a) path allowlist ----

def test_allowlist_rejects_non_engine_file(repo):
    root, base = repo
    path = "ibl5/classes/Trade/TradeService.php"
    assert _verdict(root, base, changed=[*CHANGED, path]) == f"non-engine-path: {path}"


@pytest.mark.parametrize("path", ["engine/go.mod", "engine/go.sum", "engine/Makefile",
                                  "engine/docs/j27-foul-volume-port.md",
                                  "engine/docs/j27-live-outcome-map-port.md", "engine/README.md"])
def test_allowlist_rejects_engine_non_go_files(repo, path):
    root, base = repo
    assert _verdict(root, base, changed=[*CHANGED, path]) == f"non-engine-path: {path}"


CAPTURE_DOC = "engine/docs/jsb-trace-capture.md"
CAPTURE_DOC_BASE = """# JSB per-call trace capture

## Using a capture

Check the header before you land it.

## Rows blocked on a richer capture

A capture can be too thin to pin a port.

- **pl-0027.** The capture carries no record values.

## Later section

Tail text.
"""


def _doc_repo(tmp_path, head_body):
    root, base = _make_repo(tmp_path, base_files={CAPTURE_DOC: CAPTURE_DOC_BASE})
    _write(root, CAPTURE_DOC, head_body)
    _write_evidence(root)
    return root, base


@pytest.mark.parametrize("head", [
    CAPTURE_DOC_BASE.replace("- **pl-0027.** The capture carries no record values.\n",
                             "No row is blocked on a thin capture today.\n"),
    CAPTURE_DOC_BASE.replace("- **pl-0027.** The capture carries no record values.\n", ""),
    CAPTURE_DOC_BASE.replace("thin to pin a port.\n", "thin to pin a port.\n\n- **pl-0031.** x\n"),
], ids=["replace-bullet", "drop-bullet", "add-bullet"])
def test_capture_doc_edit_inside_blocked_rows_section_is_ok(tmp_path, head):
    root, base = _doc_repo(tmp_path, head)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) == "ok"


@pytest.mark.parametrize("head", [
    CAPTURE_DOC_BASE.replace("Check the header before you land it.", "Land it."),
    CAPTURE_DOC_BASE.replace("Tail text.", "Other text."),
    CAPTURE_DOC_BASE.replace("## Rows blocked on a richer capture", "## Rows blocked"),
    CAPTURE_DOC_BASE.replace("A capture can be", "## Using a capture\n\nA capture can be"),
    CAPTURE_DOC_BASE + "\n## Rows blocked on a richer capture\n",
], ids=["edit-above", "edit-below", "rename-heading", "new-heading", "second-heading"])
def test_capture_doc_edit_outside_section_holds(tmp_path, head):
    root, base = _doc_repo(tmp_path, head)
    verdict = _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC])
    assert verdict in (f"doc-change-outside-section: {CAPTURE_DOC}",
                       f"doc-section-missing: {CAPTURE_DOC}")


def test_capture_doc_mode_change_holds(tmp_path):
    root, base = _doc_repo(tmp_path, CAPTURE_DOC_BASE)
    os.chmod(root / CAPTURE_DOC, 0o755)
    _git(root, "config", "core.fileMode", "true")
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"inert-path-unsafe-diff: {CAPTURE_DOC}"


_FUN = "FUN" + "_"  # keeps the address-shaped tokens out of this file's text
DRAW_SITES_BASE = """package sim

var DrawSites = map[string]string{
\t"sim.rollMake":   "",
\t"sim.possession": "@@A@@",
}
""".replace("@@A@@", _FUN + "004e3860")


def _draw_sites_repo(tmp_path, head_body):
    root, base = _make_repo(tmp_path, base_files={tp.DRAW_SITES: DRAW_SITES_BASE})
    _write(root, tp.DRAW_SITES, head_body)
    _write_evidence(root)
    return root, base


def test_draw_sites_entry_only_change_is_ok(tmp_path):
    """gofmt realigns neighbours when a long key lands; those rewritten entries pass too."""
    head = DRAW_SITES_BASE.replace('\t"sim.rollMake":   "",\n',
                                   '\t"sim.dayStartScaledPass": "",\n'
                                   '\t"sim.rollMake":            "",\n'
                                   '\t"sim.(*gameState).foo":    "' + _FUN + '004e4e90",\n')
    root, base = _draw_sites_repo(tmp_path, head)
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) == "ok"


@pytest.mark.parametrize("bad_line", [
    '\tfoo := 1\n',
    '\t"sim.x": "' + _FUN + '4e4e90",\n',
    '\t"sim.x": "not-an-address",\n',
    '\t"other.x": "",\n',
    '// a comment\n',
    'func init() { DrawSites["sim.x"] = "" }\n',
])
def test_draw_sites_non_entry_change_holds(tmp_path, bad_line):
    root, base = _draw_sites_repo(tmp_path, DRAW_SITES_BASE + bad_line)
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"draw-sites-non-entry-change: {tp.DRAW_SITES}"


def test_draw_sites_removed_non_entry_line_holds(tmp_path):
    root, base = _draw_sites_repo(tmp_path, DRAW_SITES_BASE.replace("package sim\n", ""))
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"draw-sites-non-entry-change: {tp.DRAW_SITES}"


def test_draw_sites_new_file_holds(repo):
    root, base = repo
    _write(root, tp.DRAW_SITES, DRAW_SITES_BASE)
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"inert-path-unreadable: {tp.DRAW_SITES}"


def test_draw_sites_deleted_holds(tmp_path):
    root, base = _make_repo(tmp_path, base_files={tp.DRAW_SITES: DRAW_SITES_BASE})
    (root / tp.DRAW_SITES).unlink()
    _write_evidence(root)
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"inert-path-missing: {tp.DRAW_SITES}"


def test_draw_sites_binary_content_holds(tmp_path):
    root, base = _draw_sites_repo(tmp_path, DRAW_SITES_BASE + "\x00func evil() {}\n")
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"inert-path-unsafe-diff: {tp.DRAW_SITES}"


def test_capture_doc_binary_content_holds(tmp_path):
    head = CAPTURE_DOC_BASE.replace("Tail text.", "Tail text.\n") \
        .replace("A capture can be too thin", "A capture can be too\x00 thin")
    root, base = _doc_repo(tmp_path, head)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"inert-path-unsafe-diff: {CAPTURE_DOC}"


@pytest.mark.parametrize("base_extra,head_extra", [
    ("", '\t"sim.zzz": "",\r}; func evil() {}; var _ = map[string]string{\n'),
    ('\t"sim.zzz": "",\rfunc keep() {}\n', ""),
], ids=["added", "removed"])
def test_draw_sites_code_after_lone_cr_holds(tmp_path, base_extra, head_extra):
    root, base = _make_repo(tmp_path, base_files={tp.DRAW_SITES: DRAW_SITES_BASE + base_extra})
    _write(root, tp.DRAW_SITES, DRAW_SITES_BASE + head_extra)
    _write_evidence(root)
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"draw-sites-non-entry-change: {tp.DRAW_SITES}"


def test_draw_sites_crlf_entry_change_is_ok(tmp_path):
    root, base = _draw_sites_repo(tmp_path, DRAW_SITES_BASE + '\t"sim.zzz": "",\r\n')
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) == "ok"


def test_draw_sites_undecodable_content_holds(tmp_path):
    root, base = _draw_sites_repo(tmp_path, DRAW_SITES_BASE)
    (root / tp.DRAW_SITES).write_bytes(DRAW_SITES_BASE.encode() + b"\xff\n")
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"inert-path-unreadable: {tp.DRAW_SITES}"
    assert tp.added_lines(root, base, tp.DRAW_SITES) is None


def test_capture_doc_heading_after_lone_cr_holds(tmp_path):
    head = CAPTURE_DOC_BASE.replace("- **pl-0027.** The capture carries no record values.\n",
                                    "- x\r## Sneaky\n")
    root, base = _doc_repo(tmp_path, head)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"doc-change-outside-section: {CAPTURE_DOC}"


@pytest.mark.parametrize("added", [
    " ## Sneaky\n", "   ## Sneaky\n", "===\n", "---\n", "-\n", "   --- \n", "---\r\n",
    "\nSneaky\n---\n",
])
def test_capture_doc_indented_or_setext_heading_holds(tmp_path, added):
    head = CAPTURE_DOC_BASE.replace("thin to pin a port.\n", "thin to pin a port.\n" + added)
    root, base = _doc_repo(tmp_path, head)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"doc-change-outside-section: {CAPTURE_DOC}"


@pytest.mark.parametrize("before,after", [
    ("port.\n\n---\n", "port.\n\nSneaky\n---\n"),
    ("port.\n\n---\n", "port.\n---\n"),
], ids=["text-added-above", "blank-removed-above"])
def test_capture_doc_text_joined_to_existing_underline_holds(tmp_path, before, after):
    old = CAPTURE_DOC_BASE.replace("port.\n", before)
    root, base = _make_repo(tmp_path, base_files={CAPTURE_DOC: old})
    _write(root, CAPTURE_DOC, old.replace(before, after))
    _write_evidence(root)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"doc-change-outside-section: {CAPTURE_DOC}"


def test_capture_doc_lone_cr_does_not_shift_the_section(tmp_path):
    """Five `\r` above the section would slide its span onto a later line if they split."""
    before = CAPTURE_DOC_BASE.replace("Check the header", "Check\r\r\r\r\r the header")
    root, base = _make_repo(tmp_path, base_files={CAPTURE_DOC: before})
    _write(root, CAPTURE_DOC, before.replace("Tail text.", "Other text."))
    _write_evidence(root)
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE_DOC]) \
        == f"doc-change-outside-section: {CAPTURE_DOC}"


def test_declared_draw_sites_takes_the_coverage_rule(tmp_path):
    """Declaring drawsites.go skips the entry check, and a map literal never meets (c)."""
    head = DRAW_SITES_BASE.replace('\t"sim.rollMake":   "",\n',
                                   '\t"sim.rollMake":   "",\n\t"sim.zzz":        "",\n')
    root, base = _draw_sites_repo(tmp_path, head)
    _write_evidence(root, ported_files=[ENGINE_FILE, tp.DRAW_SITES])
    assert _verdict(root, base, changed=[*CHANGED, tp.DRAW_SITES]) \
        == f"unexercised-change: {tp.DRAW_SITES}"


def test_allowlist_rejects_capture_in_diff(repo):
    root, base = repo
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE]) == f"capture-in-diff: {CAPTURE}"
    readme = "engine/testdata/jsb-captures/README.md"
    assert tp.allowlist_verdict([readme], BRANCH) == f"capture-in-diff: {readme}"


def test_allowlist_requires_declared_engine_go():
    cmd = "engine/cmd/x/main.go"
    files = [ENGINE_FILE, TEST_FILE, cmd, *tp.GOLDEN_FILES, EVIDENCE]
    assert tp.allowlist_verdict(files, BRANCH, {ENGINE_FILE, cmd}) == "ok"
    assert tp.allowlist_verdict(files, BRANCH, {ENGINE_FILE}) == f"undeclared-engine-file: {cmd}"
    assert tp.allowlist_verdict(files, BRANCH) == f"undeclared-engine-file: {ENGINE_FILE}"
    assert tp.allowlist_verdict(["engine/trace-evidence/other.json"], BRANCH) \
        == "non-engine-path: engine/trace-evidence/other.json"


def test_allowlist_exempts_test_files_without_declaration():
    files = [TEST_FILE, "engine/cmd/x/main_test.go", GOLDEN, EVIDENCE]
    assert tp.allowlist_verdict(files, BRANCH) == "ok"


def test_loader_in_diff_wins_over_ported_reasons(repo):
    root, base = repo
    loader = "engine/internal/jsbtrace/jsbtrace.go"
    _write_evidence(root, ported_files=[ENGINE_FILE, loader])
    assert _verdict(root, base, changed=[*CHANGED, loader]) == f"loader-in-diff: {loader}"


def test_allowlist_rejects_capture_loader_change():
    loader = "engine/internal/jsbtrace/jsbtrace.go"
    assert tp.allowlist_verdict([ENGINE_FILE, loader], BRANCH, {ENGINE_FILE}) == f"loader-in-diff: {loader}"


def test_empty_range_holds(repo):
    root, base = repo
    assert _verdict(root, base, changed=[]) == "empty-range"


UNRELATED = "engine/internal/backup/plr.go"


def test_unrelated_engine_edit_cannot_borrow_pinned_capture(repo):
    """backlog#1930: an unrelated engine edit rides a pinned test of a different routine."""
    root, base = repo
    _write(root, UNRELATED, "package backup\n\nfunc assemble() int { return 2 }\n")
    verdict = _verdict(root, base, changed=[*CHANGED, UNRELATED])
    assert verdict == f"undeclared-engine-file: {UNRELATED}"


# ---- (b) the test ----

def test_test_name_without_prefix_holds(repo):
    root, base = repo
    _write_evidence(root, test="TestFoulRoll")
    assert _verdict(root, base) == "not-trace-pinned-name"


def test_test_file_not_in_diff_holds(repo):
    root, base = repo
    changed = [f for f in CHANGED if f != TEST_FILE]
    assert _verdict(root, base, changed=changed) == "test-not-in-diff"


def test_test_without_mustload_holds(repo):
    root, base = repo
    _write(root, TEST_FILE, TEST_SOURCE.replace("jsbtrace.MustLoad(", "jsbtrace.Load("))
    _write_evidence(root)
    assert _verdict(root, base) == "test-shape"


def test_stale_test_blob_holds(repo):
    root, base = repo
    _write(root, TEST_FILE, TEST_SOURCE + "\n// edited after record\n")
    assert _verdict(root, base) == "stale-test-blob"


# ---- capture provenance ----

def test_capture_missing_on_base_holds(repo):
    root, base = repo
    other = "engine/testdata/jsb-captures/new-routine.jsonl"
    _write(root, other, _capture_text())
    _write_evidence(root, capture=other, capture_sha256=tp._sha256((Path(root) / other).read_bytes()))
    assert _verdict(root, base) == "capture-not-on-base"


def test_capture_hash_mismatch_holds(repo):
    root, base = repo
    _write_evidence(root, capture_sha256="0" * 64)
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_worktree_capture_edit_holds_as_hash_mismatch(repo):
    root, base = repo
    _write(root, CAPTURE, _capture_text(records=RECORDS[:1]))
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_unreadable_capture_holds(tmp_path):
    root, base = _make_repo(tmp_path, capture_text=_capture_text(header={**HEADER, "format": "jsb-trace/2"}))
    _write_evidence(root)
    assert _verdict(root, base) == "capture-unreadable"


def test_base_unresolved_holds(repo):
    root, _ = repo
    assert _verdict(root, "") == "base-unresolved"


# ---- (c) go test -json ----

def test_parse_go_json_pass():
    assert tp.parse_go_test_json(_stream("pass"), TEST) == "pass"


def test_parse_go_json_skip_counts_as_not_run():
    assert tp.parse_go_test_json(_stream("skip"), TEST) == "skip"


def test_parse_go_json_fail():
    assert tp.parse_go_test_json(_stream("fail"), TEST) == "fail"


def test_parse_go_json_absent(repo):
    assert tp.parse_go_test_json(_stream("pass"), "TestTracePinnedOther") == "absent"
    assert tp.parse_go_test_json("", TEST) == "absent"
    root, base = repo
    absent = _fake_go(normal=tp.parse_go_test_json("", TEST), perturbed="fail")
    assert _verdict(root, base, run_go=absent) == "test-absent"


def _stream_go(normal, perturbed="fail"):
    return _fake_go(normal=tp.parse_go_test_json(_stream(normal), TEST), perturbed=perturbed)


def test_skipped_trace_test_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_stream_go("skip")) == "test-skip"


def test_failed_trace_test_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_stream_go("fail")) == "test-fail"


def test_go_unavailable_holds(repo, monkeypatch):
    root, base = repo
    real_run = subprocess.run

    def fake_run(args, *a, **k):
        if args and args[0] == "go":
            return subprocess.CompletedProcess(args, 0, stdout=_stream("pass"), stderr="")
        return real_run(args, *a, **k)
    monkeypatch.setattr(tp.shutil, "which", lambda name: None)
    monkeypatch.setattr(tp.subprocess, "run", fake_run)
    assert tp.evaluate_evidence(root, BRANCH, base, CHANGED) == "test-go-unavailable"


# ---- perturbation run ----

def test_perturbed_run_still_passing_holds_as_circular(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(perturbed="pass")) \
        == f"circular: {TEST} passes with perturbed JSB outputs"


def test_perturbed_run_other_verdict_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(perturbed="skip")) == "perturbation-skip"


def test_perturb_shifts_every_leaf():
    value, n = tp.perturb({"a": 1, "b": [True, "x", 2.5], "c": None, "d": {"e": False}})
    assert value == {"a": 2, "b": [False, "x~perturbed", 3.5], "c": None, "d": {"e": True}}
    assert n == 5


def test_perturb_without_leaf_holds(tmp_path):
    records = [{"in": {"roll": 1}, "out": {}}, {"in": {"roll": 2}, "out": {"x": None}}]
    root, base = _make_repo(tmp_path, capture_text=_capture_text(records=records))
    _write_evidence(root)
    assert _verdict(root, base) == "capture-out-unperturbable"


# ---- base resolution ----

def test_stacked_base_resolves_declared_base(tmp_path):
    root, master_base = _make_repo(tmp_path)
    _git(root, "reset", "-q", "--hard", master_base)
    stacked = "engine/testdata/jsb-captures/stacked.jsonl"
    _write(root, stacked, _capture_text())
    _write(root, "ibl5/classes/Parent/Thing.php", "<?php\n")
    parent = _commit(root, "parent PR")
    _git(root, "update-ref", "refs/remotes/origin/parent-branch", parent)
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 2 }\n")
    _write(root, TEST_FILE, TEST_SOURCE.replace(CAPTURE_NAME, "stacked.jsonl"))
    _commit(root, "child")
    _git(root, "config", f"branch.{BRANCH}.iblBase", "parent-branch")
    _write_evidence(root, capture=stacked, capture_sha256=tp._sha256((Path(root) / stacked).read_bytes()))

    base = tp.resolve_base(root, BRANCH)
    assert base == parent
    changed = tp.changed_files_since(root, base)
    assert "ibl5/classes/Parent/Thing.php" not in changed
    assert tp.evaluate_evidence(root, BRANCH, base, changed,
                                run_go=_fake_go(capture_name="stacked.jsonl")) == "ok"


def test_resolve_base_defaults_to_origin_master(repo):
    root, base = repo
    assert tp.resolve_base(root, BRANCH) == base


# ---- CLI and evidence path ----

def test_cli_rejects_unknown_flag(repo, monkeypatch):
    root, _ = repo
    monkeypatch.chdir(root)
    for argv in (["record", "--bogus"], ["check"], ["check", "--branch", BRANCH, "--bogus"]):
        with pytest.raises(SystemExit) as exc:
            tp.main(argv)
        assert exc.value.code == 2, argv


def test_evidence_path_not_gitignored(tmp_path, monkeypatch):
    root, _ = _make_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME],
                     ported_specs=[ENGINE_FILE]) == 0
    written = root / "engine" / "trace-evidence" / f"{BRANCH}.json"
    assert written.is_file()
    assert tp.evidence_path(root, BRANCH) == written
    data = json.loads(written.read_text())
    assert data["tests"][0]["file"] == TEST_FILE
    ignored = subprocess.run(["git", "check-ignore", "-q", f"engine/trace-evidence/{BRANCH}.json"],
                             cwd=REPO)
    assert ignored.returncode == 1


def test_record_refuses_on_failing_check(tmp_path, monkeypatch):
    root, _ = _make_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go(perturbed="pass"))
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME],
                     ported_specs=[ENGINE_FILE]) == 1
    assert not (root / EVIDENCE).exists()


def test_record_requires_equal_test_and_capture_counts(tmp_path):
    root, _ = _make_repo(tmp_path)
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], []) == 2


@pytest.mark.parametrize("value", [[], ENGINE_FILE, [ENGINE_FILE, 7], [ENGINE_FILE, ENGINE_FILE]],
                         ids=["empty-list", "string-not-list", "non-string-item", "duplicate-item"])
def test_ported_malformed_field_types_hold(repo, value):
    root, base = repo
    _write_evidence(root, ported_files=value)
    assert _verdict(root, base, changed=[TEST_FILE, GOLDEN, EVIDENCE]) == "ported-malformed"


@pytest.mark.parametrize("path,reason", [
    ("engine/../ibl5/x.go", "ported-bad-path"), ("/engine/internal/sim/x.go", "ported-bad-path"),
    ("engine/go.mod", "ported-bad-path"), (TEST_FILE, "ported-test-file"),
    ("engine/internal/jsbtrace/jsbtrace.go", "ported-protected-dir"),
    ("engine/testdata/jsb-captures/x.go", "ported-protected-dir")])
def test_ported_bad_paths_hold(repo, path, reason):
    root, base = repo
    _write_evidence(root, ported_files=[path])
    assert _verdict(root, base, changed=[TEST_FILE, GOLDEN, EVIDENCE]) == f"{reason}: {path}"


def test_ported_missing_or_unchanged_file_holds(repo):
    root, base = repo
    gone = "engine/internal/sim/gone.go"
    _write_evidence(root, ported_files=[ENGINE_FILE, gone])
    assert _verdict(root, base, changed=[*CHANGED, gone]) == f"ported-missing: {gone}"
    _write_evidence(root, ported_files=[ENGINE_FILE])
    assert _verdict(root, base, changed=[TEST_FILE, GOLDEN, EVIDENCE]) \
        == f"ported-not-in-diff: {ENGINE_FILE}"


def test_schema_1_evidence_holds_as_malformed(repo):
    root, base = repo
    _write(root, EVIDENCE, json.dumps({"schema": 1, "token": "trace-pinned", "branch": BRANCH,
                                       "tests": [_entry(root)]}))
    assert _verdict(root, base) == "evidence-malformed"


def test_record_writes_ported_files(tmp_path):
    root, _ = _make_repo(tmp_path)
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME], run_go=_fake_go(),
                     ported_specs=[ENGINE_FILE]) == 0
    data = json.loads((root / EVIDENCE).read_text())
    assert data["schema"] == tp.SCHEMA == 2
    assert data["tests"][0]["ported_files"] == [ENGINE_FILE]


def test_cli_record_ported_count_mismatch_exits_2(tmp_path, monkeypatch, capsys):
    root, _ = _make_repo(tmp_path)
    monkeypatch.chdir(root)
    argv = ["record", "--branch", BRANCH, "--test", f"{PACKAGE}:{TEST}", "--capture", CAPTURE_NAME]
    assert tp.main(argv) == 2
    assert "one --ported per --test" in capsys.readouterr().err
    assert not (root / EVIDENCE).exists()


def test_record_refuses_empty_ported_item(tmp_path, capsys):
    root, _ = _make_repo(tmp_path)
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME], run_go=_fake_go(),
                     ported_specs=[f"{ENGINE_FILE},"]) == 1
    assert "ported-bad-path: " in capsys.readouterr().err
    assert not (root / EVIDENCE).exists()


def test_declared_unrelated_edit_unexercised_holds(repo):
    root, base = repo
    _write(root, UNRELATED, "package backup\n\nfunc assemble() int { return 2 }\n")
    _write_evidence(root, ported_files=[ENGINE_FILE, UNRELATED])
    assert _verdict(root, base, changed=[*CHANGED, UNRELATED]) == f"unexercised-change: {UNRELATED}"


def test_change_outside_covered_block_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(span=(50, 60))) == f"unexercised-change: {ENGINE_FILE}"


def test_zero_count_block_holds(repo):
    root, base = repo
    go = _fake_go(covered=(), zero=(ENGINE_FILE,))
    assert _verdict(root, base, run_go=go) == f"unexercised-change: {ENGINE_FILE}"


def test_comment_inside_covered_span_holds(repo):
    root, base = repo
    code = tp._git(["show", f"{base}:{ENGINE_FILE}"], cwd=str(root))
    _write(root, ENGINE_FILE, code.replace("func", "// note\n\nfunc", 1))
    assert tp.added_lines(root, base, ENGINE_FILE) == {3, 4}
    assert _verdict(root, base) == f"unexercised-change: {ENGINE_FILE}"


def _insert_verdict(tmp_path, added, above="", below=""):
    """coverage_verdict for `added` inserted between `above` and `below`, all of it inside
    one executed block."""
    root, _ = _make_repo(tmp_path)
    head = f"package sim\n\nfunc foulRoll() int {{\n\ta := 1\n{above}"
    tail = f"{below}\treturn a\n}}\n"
    _write(root, ENGINE_FILE, head + tail)
    base = _commit(root, "wider base")
    _write(root, ENGINE_FILE, head + added + tail)
    start = head.count("\n") + 1
    assert tp.added_lines(root, base, ENGINE_FILE) == set(range(start, start + added.count("\n")))
    return tp.coverage_verdict(root, base, {ENGINE_FILE}, {ENGINE_FILE: [(1, 10000, 1)]})


@pytest.mark.parametrize("added,above,below", [
    ("\t/* note */\n", "", ""),
    ("\t/* one */ /* two */ // three\n", "", ""),
    ("\t/* start\n\t   interior\n\t * starred\n\n\t end */\n", "", ""),
    ("\t   a = 2\n", "\t/*\n", "\t*/\n"),
    ("\t/*/\n\t   a = 2\n\t*/\n", "", ""),
    # a quote inside a comment opens no string
    ("\t/* it's \" ` */\n\t// it's \" ` /*\n\t/* two */\n", "", ""),
    # comment markers inside a literal neither open nor hide a comment
    ("\t   a = 2\n", '\t_ = "//" /* open\n', "\t*/\n"),
    ("\t// see */ below\n", '\t_ = "/*"\n', ""),
    ("\t// see */ below\n", '\t_ = "\\"/*"\n', ""),
    ("\t   a = 2\n", "\t_, _ = '\"', \"x\" /* open\n", "\t*/\n"),
    ("\t   a = 2\n", "\t_ = `\n//` /* open\n", "\t*/\n"),
    # a lone `\r` ends no line for git or Go: the rest of the comment is not a line of code
    ("\t// note\n", "\t// x\ry\n", ""),
    ("\t// note\r\n", "", ""),
    # a lone `\r` forges no hunk header: line 4 (`a := 1`) was not added
    ("\t// x\r@@ -1 +4 @@\n", "", ""),
])
def test_block_comment_inside_covered_span_holds(tmp_path, added, above, below):
    assert _insert_verdict(tmp_path, added, above, below) == f"unexercised-change: {ENGINE_FILE}"


@pytest.mark.parametrize("added,above,below", [
    ("\ta++ /* x */\n", "", ""),
    ("\t/* x */ a++\n", "", ""),
    ("\t   end */ a++\n", "\t/* start\n", ""),
    ("\ta++ /* open\n\t*/\n", "", ""),
    ('\t_ = "/*"\n', "", ""),
    ("\t   raw text\n", "\t_ = `/*\n", "`\n"),
    ("\ta++\r\n", "", ""),
])
def test_code_beside_block_comment_counts(tmp_path, added, above, below):
    assert _insert_verdict(tmp_path, added, above, below) == "ok"


def test_missing_cover_profile_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(write_profile=False)) == "coverage-unreadable"


def test_added_lines_reads_zero_context_hunks(repo):
    root, base = repo
    assert tp.added_lines(root, base, ENGINE_FILE) == {3}   # git prints "@@ -3 +3 @@"
    _write(root, ENGINE_FILE, "package sim\n\n")            # pure deletion of line 3
    assert tp.added_lines(root, base, ENGINE_FILE) == set()
    assert _verdict(root, base) == f"unexercised-change: {ENGINE_FILE}"


def test_added_lines_new_file_counts_every_line(repo):
    root, base = repo
    fresh = "engine/internal/sim/fresh.go"
    _write(root, fresh, "package sim\n\nfunc fresh() int {\n\treturn 4\n}\n")
    assert tp.added_lines(root, base, fresh) == {1, 2, 3, 4, 5}


def test_parse_cover_profile_reads_real_profile():
    text = (FIXTURES / "cover-real-set.out").read_text()
    blocks = tp.parse_cover_profile(text, MODULE)
    assert blocks
    assert all(k.startswith("engine/internal/") and k.endswith(".go") for k in blocks)
    assert sum(len(v) for v in blocks.values()) == len(text.strip().splitlines()) - 1
    assert any(c > 0 for v in blocks.values() for _, _, c in v)


def test_parse_cover_profile_rejects_near_miss_lines():
    line = f"{MODULE}/internal/sim/foul.go:3.1,4.2 1 1"
    assert tp.parse_cover_profile(f"mode: set\n{line[:-2]}\n", MODULE) is None   # count missing
    assert tp.parse_cover_profile(f"{line}\n", MODULE) is None                   # no mode header
    other = "example.com/other/x.go:1.1,2.2 1 1"
    assert tp.parse_cover_profile(f"mode: set\n{other}\n{line}\n", MODULE) \
        == {"engine/internal/sim/foul.go": [(3, 4, 1)]}


def test_run_go_test_passes_cover_flags(monkeypatch, tmp_path):
    seen = []
    monkeypatch.setattr(tp.shutil, "which", lambda name: "/usr/local/bin/go")
    monkeypatch.setattr(tp.subprocess, "run", lambda argv, **kw: seen.append(argv) or
                        subprocess.CompletedProcess(argv, 0, stdout="", stderr=""))
    tp.run_go_test(tmp_path, PACKAGE, TEST, cover_profile="/tmp/c.out",
                   cover_pkgs=[f"{MODULE}/internal/sim"])
    tp.run_go_test(tmp_path, PACKAGE, TEST)
    assert seen[0][-1] == PACKAGE and "-covermode=set" in seen[0]
    assert f"-coverpkg={MODULE}/internal/sim" in seen[0] and "-coverprofile=/tmp/c.out" in seen[0]
    assert not any(a.startswith("-cover") for a in seen[1])


def test_cover_pkgs_follow_declared_files(repo):
    root, base = repo
    pkgs = []
    assert _verdict(root, base, run_go=_fake_go(pkgs=pkgs)) == "ok"
    assert pkgs == [[f"{MODULE}/internal/sim"]]
    assert tp._cover_pkgs(MODULE, ["engine/internal/sim/a.go", "engine/internal/sim/b.go",
                                   "engine/main.go"]) == [MODULE, f"{MODULE}/internal/sim"]


# ---- in-repo captures (ADR-0286) ----


def test_in_repo_capture_on_base_passes(repo):
    root, base = repo
    assert _verdict(root, base) == "ok"


def test_in_repo_capture_sha_mismatch_held(repo):
    root, base = repo
    _write_evidence(root, capture_sha256="0" * 64)
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_capture_only_in_working_tree_held(repo):
    root, base = repo
    new = f"{tp.CAPTURE_DIR}/new-capture.jsonl"
    _write(root, new, _capture_text())
    _write_evidence(root, capture=new, capture_sha256=tp._sha256((root / new).read_bytes()))
    assert _verdict(root, base) == "capture-not-on-base"


def test_capture_missing_file_held(repo):
    root, base = repo
    _write_evidence(root, capture=f"{tp.CAPTURE_DIR}/absent.jsonl")
    assert _verdict(root, base) == "capture-not-on-base"


def test_capture_deleted_from_working_tree_held(repo):
    root, base = repo
    (root / CAPTURE).unlink()
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_capture_over_size_cap_held(repo, monkeypatch):
    root, base = repo
    monkeypatch.setattr(tp, "MAX_CAPTURE_BYTES", len(_capture_text().encode()) - 1)
    assert _verdict(root, base) == "capture-too-large"


def test_capture_at_size_cap_passes(repo, monkeypatch):
    root, base = repo
    monkeypatch.setattr(tp, "MAX_CAPTURE_BYTES", len(_capture_text().encode()))
    assert _verdict(root, base) == "ok"


def test_jsb_native_host_entry_held_bad_host(repo):
    root, base = repo
    _write_evidence(root, capture="captures/x.jsonl", capture_host="jsb-native",
                    capture_commit="a" * 40)
    assert _verdict(root, base) == "capture-bad-host"


def test_record_rejects_capture_host_flag(tmp_path, monkeypatch, capsys):
    root, _ = _make_repo(tmp_path)
    monkeypatch.chdir(root)
    with pytest.raises(SystemExit) as exc:
        tp.main(["record", "--branch", BRANCH, "--test", f"{PACKAGE}:{TEST}",
                 "--capture", CAPTURE_NAME, "--ported", ENGINE_FILE, "--capture-host", "repo"])
    assert exc.value.code == 2
    assert "unrecognized arguments" in capsys.readouterr().err


def test_check_passes_without_jsb_native_sibling(repo):
    root, base = repo
    assert not tp.jsb_native_dir(root).exists()
    assert _verdict(root, base) == "ok"


def test_every_committed_capture_under_size_cap():
    captures = sorted((REPO / tp.CAPTURE_DIR).glob("*.jsonl"))
    assert captures
    for capture in captures:
        assert capture.stat().st_size <= tp.MAX_CAPTURE_BYTES, capture.name


def test_committed_evidence_entries_pin_in_repo_captures():
    evidence_files = sorted((REPO / tp.EVIDENCE_DIR).glob("*.json"))
    assert evidence_files
    for evidence in evidence_files:
        for entry in json.loads(evidence.read_text())["tests"]:
            where = f"{evidence.name}:{entry['test']}"
            assert entry.get("capture_host", "repo") == "repo", where
            assert "capture_commit" not in entry, where
            assert str(Path(entry["capture"]).parent) == tp.CAPTURE_DIR, where
            assert (REPO / entry["capture"]).is_file(), where
            # Hash only pinned `-<7hex>` copies. A later recapture overwrites a plain
            # `pl-NNNN.jsonl`, so hashing it here would turn that capture PR red (thread
            # 4697870450). `_check_capture` hashes every entry on its own port branch.
            if re.search(r"-[0-9a-f]{7}\.jsonl$", entry["capture"]):
                assert tp._sha256((REPO / entry["capture"]).read_bytes()) == entry["capture_sha256"], where


def test_jsb_native_dir_is_common_dir_parent(tmp_path):
    main = tmp_path / "main"
    main.mkdir()
    _git(main, "init", "-q", "-b", "master")
    _write(main, "README", "x\n")
    _commit(main, "init")
    _git(main, "worktree", "add", "-q", str(tmp_path / "wt"), "-b", "wt")
    want = (main / "jsb-native").resolve()
    assert tp.jsb_native_dir(tmp_path / "wt").resolve() == want
    assert tp.jsb_native_dir(main).resolve() == want


def test_repo_entry_without_host_unchanged(repo):
    root, base = repo
    _write_evidence(root, capture=f"{tp.CAPTURE_DIR}/absent.jsonl")
    assert _verdict(root, base) == "capture-not-on-base"


# ---- jsb-port-* branches also need replay progress over base ----

PORT_BRANCH = "jsb-port-pl-0027-r1"
PORT_EVIDENCE = f"engine/trace-evidence/{PORT_BRANCH}.json"
PORT_CHANGED = [ENGINE_FILE, TEST_FILE, GOLDEN, PORT_EVIDENCE]
TRIGGERS = ("trace-pinned",)


def _port_repo(tmp_path):
    root, base = _make_repo(tmp_path)
    data = {"schema": tp.SCHEMA, "token": "trace-pinned", "branch": PORT_BRANCH,
            "tests": [_entry(root)]}
    _write(root, PORT_EVIDENCE, json.dumps(data))
    return root, base


def _first(day=1, game=0, gi=0, matched=0):
    return {"day": day, "game": game, "global_index": gi, "matched_draws": matched,
            "total_draws": 900, "kind": "draw", "target": "0xFEEDF00D"}


def _fake_replay_child(base_first, head_first):
    def fake(args, timeout):
        if "probe" in args:
            return {"status": "ok", "recording": "rec-a", "baseline_sha256": "b1"}
        label = args[args.index("--label") + 1]
        return {"status": "ok", "first": base_first if label == "base" else head_first}
    return fake


def _progress_seam(tmp_path, base_first, head_first):
    loop_dir = tmp_path / "fake-jsbn" / "tools" / "parity_loop"
    loop_dir.mkdir(parents=True)
    (loop_dir / "diverge.py").write_text("")

    def seam(root, branch, base, jsbn_dir):
        return replay_progress.verdict(root, branch, base, tmp_path / "fake-jsbn",
                                       run_child=_fake_replay_child(base_first, head_first),
                                       cache_dir=tmp_path / "cache")
    return seam


def _raising_seam(*args):
    raise AssertionError("progress check must not run")


def test_arm_port_branch_holds_when_replay_unchanged(tmp_path, monkeypatch):
    from harness.armable import trace_pinned_lifts
    from harness.classify import classify
    from tests.test_armable import inputs
    root, _ = _port_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    seam = _progress_seam(tmp_path, _first(), _first())
    value = tp.arm_input_value(TRIGGERS, root, PORT_BRANCH, PORT_CHANGED, progress=seam)
    assert value == "replay-no-progress:pl-0027:day1:game0:draw0:matched0"
    inp = inputs(classification=classify([ENGINE_FILE, GOLDEN], ""), plan_hold_triggers=TRIGGERS,
                 trace_pinned_evidence=value)
    assert trace_pinned_lifts(inp) is False


def test_arm_port_branch_clears_when_replay_advances(tmp_path, monkeypatch):
    root, _ = _port_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    seam = _progress_seam(tmp_path, _first(), _first(gi=12))
    assert tp.arm_input_value(TRIGGERS, root, PORT_BRANCH, PORT_CHANGED, progress=seam) == "ok"


def test_arm_port_branch_holds_when_replay_cannot_run(tmp_path, monkeypatch):
    root, _ = _port_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(TRIGGERS, root, PORT_BRANCH, PORT_CHANGED) \
        == "replay-unavailable:no-jsb-native"


def test_arm_port_branch_skips_replay_when_pin_fails(tmp_path, monkeypatch):
    root, _ = _make_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    value = tp.arm_input_value(TRIGGERS, root, PORT_BRANCH, PORT_CHANGED, progress=_raising_seam)
    assert value == "evidence-missing"


def test_arm_non_port_branch_never_runs_replay(repo, monkeypatch):
    root, _ = repo
    assert not BRANCH.startswith(tp.PORT_PREFIX)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(TRIGGERS, root, BRANCH, CHANGED, progress=_raising_seam) == "ok"


def test_arm_progress_exception_fails_closed(tmp_path, monkeypatch):
    root, _ = _port_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())

    def boom(*args):
        raise RuntimeError("x")
    assert tp.arm_input_value(TRIGGERS, root, PORT_BRANCH, PORT_CHANGED, progress=boom) \
        == "error: RuntimeError"
