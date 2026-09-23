"""Phase 2 — the Phase 5.5 fidelity reviewer module."""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger

TREE = "a" * 40


def _plan(found=True, path=""):
    return types.SimpleNamespace(found=found, path=path)


# --- parse_verdict ------------------------------------------------------------

@pytest.mark.parametrize("body,expected", [
    ("blah\nREADY\n", "READY"),
    ("blah\nREADY WITH NOTES\n", "READY WITH NOTES"),
    ("blah\nNOT READY\n", "NOT READY"),
    ("READY  \n", "READY"),
    # verdict word only below the cut -> indeterminate, NOT a verdict
    ("notes\n## DIGEST\nREADY\n", None),
    # two above the cut -> the last one wins
    ("NOT READY\nrevised\nREADY\n", "READY"),
    # a verdict word that does not own its line is not a verdict
    ("the PR is READY to merge\n", None),
    ("", None),
    ("   \n", None),
    # above-the-cut word wins even when the digest repeats one
    ("READY WITH NOTES\n## DIGEST\nNOT READY\n", "READY WITH NOTES"),
])
def test_parse_verdict(tmp_path, body, expected):
    p = tmp_path / "v.md"
    p.write_text(body)
    assert fidelity.parse_verdict(str(p)) == expected


def test_parse_verdict_absent_file(tmp_path):
    assert fidelity.parse_verdict(str(tmp_path / "nope.md")) is None


def test_json_envelope_degrades_gracefully(tmp_path):
    """A JSON-shaped verdict file is indeterminate, never a false safety signal."""
    p = tmp_path / "v.md"
    p.write_text('{"SCORE": 85, "verdict": "READY", "notes": []}\n')
    assert fidelity.parse_verdict(str(p)) is None


# --- REVIEWED_TREE ------------------------------------------------------------

def test_record_reviewed_tree_appends_bare_hash(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("READY\n")
    fidelity.record_reviewed_tree(str(p), TREE)
    last = p.read_text().splitlines()[-1]
    assert fidelity.REVIEWED_TREE_RE.fullmatch(last)
    assert last == f"REVIEWED_TREE={TREE}"
    assert fidelity.read_reviewed_tree(str(p)) == TREE


def test_record_reviewed_tree_is_idempotent(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("READY\n")
    fidelity.record_reviewed_tree(str(p), TREE)
    fidelity.record_reviewed_tree(str(p), "b" * 40)
    assert p.read_text().count("REVIEWED_TREE=") == 1
    assert fidelity.read_reviewed_tree(str(p)) == TREE


def test_record_reviewed_tree_adds_missing_newline(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("READY")                       # no trailing newline
    fidelity.record_reviewed_tree(str(p), TREE)
    assert p.read_text() == f"READY\nREVIEWED_TREE={TREE}\n"


def test_record_reviewed_tree_never_creates_file(tmp_path):
    p = tmp_path / "absent.md"
    fidelity.record_reviewed_tree(str(p), TREE)
    assert not p.exists()


def test_record_reviewed_tree_leaves_empty_file_empty(tmp_path):
    p = tmp_path / "v.md"
    p.write_text("")
    fidelity.record_reviewed_tree(str(p), TREE)
    assert p.read_text() == ""


def test_read_reviewed_tree_rejects_trailing_prose(tmp_path):
    p = tmp_path / "v.md"
    p.write_text(f"READY\nREVIEWED_TREE={TREE} (reviewed)\n")
    assert fidelity.read_reviewed_tree(str(p)) is None


def test_read_reviewed_tree_returns_last_match(tmp_path):
    p = tmp_path / "v.md"
    p.write_text(f"REVIEWED_TREE={'c' * 40}\nREVIEWED_TREE={TREE}\n")
    assert fidelity.read_reviewed_tree(str(p)) == TREE


# --- procedure either-location lookup ----------------------------------------

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  case "$2" in
    *"$GIT_SHIM_OK_PATH") echo "PROCEDURE BODY"; exit 0 ;;
  esac
  echo "fatal: path does not exist" >&2
  exit 128
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


@pytest.mark.parametrize("ok_path", [
    ".claude/review-shared/_plan-fidelity-review.md",
])
def test_procedure_either_location(tmp_path, git_shim, ok_path):
    git_shim.setenv("GIT_SHIM_OK_PATH", ok_path)
    out = tmp_path / "out"
    out.mkdir()
    packet = fidelity.build_packet(str(out), "deadbeef", TREE, _plan(found=False),
                                   "diff", "body", 42, False, worktree=str(tmp_path))
    assert "PROCEDURE BODY" in open(os.path.join(packet, "procedure.md")).read()


def test_procedure_missing_from_both_locations(tmp_path, git_shim):
    git_shim.setenv("GIT_SHIM_OK_PATH", "no/such/path")
    out = tmp_path / "out"
    out.mkdir()
    with pytest.raises(HarnessError) as exc:
        fidelity.build_packet(str(out), "deadbeef", TREE, _plan(found=False),
                              "d", "b", 42, False, worktree=str(tmp_path))
    assert exc.value.kind == "fidelity-procedure-missing"


# --- plan-blind ---------------------------------------------------------------

def test_plan_blind_marker_never_synthesises_a_verdict(tmp_path, git_shim):
    git_shim.setenv("GIT_SHIM_OK_PATH", ".claude/review-shared/_plan-fidelity-review.md")
    out = tmp_path / "out"
    out.mkdir()
    packet = fidelity.build_packet(str(out), "deadbeef", TREE, _plan(found=False),
                                   "d", "b", 42, False, worktree=str(tmp_path))
    plan_md = open(os.path.join(packet, "plan.md")).read()
    assert "PLAN-BLIND RUN" in plan_md
    assert "not assessable" in plan_md
    assert "verdict word" in plan_md
    for name in os.listdir(packet):
        assert "NOT READY" not in open(os.path.join(packet, name)).read(), name


def test_packet_context_carries_the_reviewed_tree(tmp_path, git_shim):
    git_shim.setenv("GIT_SHIM_OK_PATH", ".claude/review-shared/_plan-fidelity-review.md")
    out = tmp_path / "out"
    out.mkdir()
    packet = fidelity.build_packet(str(out), "deadbeef", TREE, _plan(found=False),
                                   "d", "b", 42, True, worktree=str(tmp_path))
    ctx = open(os.path.join(packet, "context.md")).read()
    assert f"REVIEWED_TREE: {TREE}" in ctx
    assert "MASTER_SHA: deadbeef" in ctx
    assert "PHASE_4B_RAN: yes" in ctx


# --- review() -----------------------------------------------------------------

class _RaisingLlm(FixtureLlm):
    def call_tooled(self, purpose, model, prompt, **kw):
        raise HarnessError("llm-tooled-empty", purpose)


def test_review_degrades_to_indeterminate_without_raising(tmp_path):
    verdict = fidelity.verdict_path("degrade-test")
    if os.path.exists(verdict):
        os.unlink(verdict)
    got, kind = fidelity.review(_RaisingLlm(UsageLedger(), {}), str(tmp_path),
                                str(tmp_path), str(tmp_path), "degrade-test")
    assert got is None
    assert kind == "llm-tooled-empty"
    assert not os.path.exists(verdict)


def test_review_writes_the_file_and_parses_the_verdict(tmp_path):
    llm = FixtureLlm(UsageLedger(),
                     {"plan-fidelity-review": "6d checks\n\nREADY\n\n## DIGEST\nstuff\n"})
    got, kind = fidelity.review(llm, str(tmp_path), str(tmp_path), str(tmp_path),
                                "write-test", reviewed_tree=TREE)
    assert (got, kind) == ("READY", "")
    path = fidelity.verdict_path("write-test")
    assert fidelity.read_reviewed_tree(path) == TREE
    os.unlink(path)


def test_reviewer_tool_budget_is_narrowed(tmp_path):
    llm = FixtureLlm(UsageLedger(), {"plan-fidelity-review": "READY\n"})
    fidelity.review(llm, str(tmp_path), str(tmp_path), str(tmp_path), "budget-test")
    os.unlink(fidelity.verdict_path("budget-test"))
    purpose, argv = llm.tooled_argvs[0]
    assert purpose == "plan-fidelity-review"
    assert argv[argv.index("--tools") + 1] == "Read,Grep,Glob"
    denied = argv[argv.index("--disallowedTools") + 1]
    for t in ("Bash", "Write", "Edit"):
        assert t in denied
    assert argv[argv.index("--agent") + 1] == "pr-ready-phase6"
    assert "--model" not in argv
