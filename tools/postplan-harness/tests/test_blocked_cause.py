"""Blocked post-plan runs name the failing command and its error.

Covers the carried command/output fields (HarnessError, RunResult), the bounded
redacted tail helper, the verdict arms, and the blocked-ship block.
"""
import json
import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError, RunResult, TerminalState


@pytest.fixture()
def repo():
    d = tempfile.mkdtemp(prefix="postplan-blocked-cause-")
    env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}

    def sh(*a):
        subprocess.run(["git", "-C", d, *a], check=True, capture_output=True, env=env)
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    sh("config", "user.email", "t@t")
    sh("config", "user.name", "t")
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    sh("add", "-A")
    sh("commit", "-m", "base")
    return d


def _install_reject_hook(repo, body):
    """Write an executable pre-commit hook into the THROWAWAY fixture repo only."""
    hooks = os.path.join(repo, ".git", "hooks")
    os.makedirs(hooks, exist_ok=True)
    hook = os.path.join(hooks, "pre-commit")
    assert hook.startswith(tempfile.gettempdir()), f"refusing to write a hook at {hook}"
    with open(hook, "w") as fh:
        fh.write(body)
    os.chmod(hook, 0o755)
    return hook


_NOISY_HOOK = ("#!/bin/sh\n"
               "i=0\n"
               "while [ $i -lt 60 ]; do echo \"filler line $i\"; i=$((i+1)); done\n"
               "echo 'GUIDANCE: run bin/check-x'\n"
               "exit 1\n")


def test_harness_error_keeps_cmd_and_trims_output():
    e = HarnessError("local-gate", "d", cmd="git commit", output="x" * 9000 + "LAST")
    assert e.cmd == "git commit"
    assert len(e.output) == 4000
    assert e.output.endswith("LAST")
    plain = HarnessError("git", "d")
    assert plain.cmd == ""
    assert plain.output == ""


def test_commit_all_records_command_and_tail_detail_unchanged(repo):
    _install_reject_hook(repo, _NOISY_HOOK)
    g = LiveGit(repo)
    with open(os.path.join(repo, "b.txt"), "w") as fh:
        fh.write("blocked\n")
    with pytest.raises(HarnessError) as ei:
        g.commit_all("chore: should be rejected")
    e = ei.value
    assert e.kind == "local-gate"
    assert e.cmd == "git commit"
    assert "GUIDANCE: run bin/check-x" in e.output
    assert len(e.detail) <= 800
    assert "GUIDANCE: run bin/check-x" not in e.detail, "detail stays the old head slice"


def test_result_json_omits_unset_error_cmd():
    d = json.loads(RunResult(terminal=TerminalState.FAILED).to_json())
    assert "error_cmd" not in d
    assert "error_output_tail" not in d
    d2 = json.loads(RunResult(terminal=TerminalState.FAILED, error_cmd="git commit").to_json())
    assert d2["error_cmd"] == "git commit"
    assert "error_output_tail" not in d2


def test_result_json_redacts_credentials():
    secret = "ghp_" + _ALNUM36
    res = RunResult(terminal=TerminalState.FAILED)
    runner._record_failure_context(res, HarnessError(
        "local-gate", "push failed",
        cmd=f"git push https://x:{secret}@github.com/a/b.git",
        output=f"remote: https://x:{secret}@github.com/a/b.git",
    ))
    d = json.loads(res.to_json())
    assert secret not in d.get("error_cmd", "")
    assert secret not in d.get("error_output_tail", "")


def test_finish_redacts_error_in_result_json(tmp_path):
    secret = "ghp_" + _ALNUM36
    res = RunResult(terminal=TerminalState.FAILED,
                    error=f"push-failed: remote: https://x:{secret}@github.com/a/b.git")
    runner._finish(res, str(tmp_path))
    blob = json.loads((tmp_path / "result.json").read_text())
    assert secret not in blob["error"]
    assert blob["error"].startswith("push-failed: remote: https://")


def test_finish_redacts_audit_lines(tmp_path):
    secret = "ghp_" + _ALNUM36
    res = RunResult(terminal=TerminalState.FAILED, audit=[
        f"[12:00:00] FAILED: push-failed: https://x:{secret}@github.com/a/b.git",
        "[12:00:01] phase2: ok",
    ])
    runner._finish(res, str(tmp_path))
    result_text = (tmp_path / "result.json").read_text()
    audit_text = (tmp_path / "audit.log").read_text()
    assert secret not in result_text
    assert secret not in audit_text
    assert "[12:00:01] phase2: ok" in json.loads(result_text)["audit"]
    assert "[12:00:01] phase2: ok" in audit_text
    assert secret in res.audit[0]


def test_finish_keeps_in_memory_error_raw(tmp_path):
    secret = "ghp_" + _ALNUM36
    res = RunResult(terminal=TerminalState.FAILED,
                    error=f"push-failed: remote: https://x:{secret}@github.com/a/b.git")
    out = runner._finish(res, str(tmp_path))
    assert out is res
    assert secret in res.error


def test_finish_clean_error_is_byte_identical(tmp_path):
    res = RunResult(terminal=TerminalState.FAILED,
                    error="rebase-conflict: CONFLICT (content) in foo.py")
    runner._finish(res, str(tmp_path))
    assert (tmp_path / "result.json").read_text() == res.to_json()

    # _finish must guard on res.error: _redact(None) raises TypeError.
    with pytest.raises(TypeError):
        runner._redact(None)
    none_dir = tmp_path / "none"
    none_dir.mkdir()
    res_none = RunResult(terminal=TerminalState.FAILED, error=None)
    runner._finish(res_none, str(none_dir))
    text = (none_dir / "result.json").read_text()
    assert text == res_none.to_json()
    assert json.loads(text)["error"] is None


# --- tail / redaction helpers -------------------------------------------------

def test_error_tail_keeps_last_three_lines_of_10kb():
    lines = [f"line {i:03d} " + "x" * 40 for i in range(200)]
    result = runner._error_tail("\n".join(lines))
    assert result == lines[-3:]
    assert len(" ".join(result)) <= 300
    assert all("\n" not in el for el in result)


def test_error_tail_cuts_single_huge_line_to_its_end():
    result = runner._error_tail("y" * 10000 + "END-MARKER")
    assert len(result) == 1
    assert len(result[0]) <= 300
    assert result[0].endswith("END-MARKER")
    assert result[0].startswith("…")


@pytest.mark.parametrize("text", [None, "", " \n\t\n "])
def test_error_tail_empty_inputs(text):
    assert runner._error_tail(text) == []


def test_error_tail_matches_flat_for_short_errors():
    from test_verdict_line import _RC3_CASES
    gate_ids = {"gate-adr", "gate-adr-drafted", "gate-unknown",
                "gate-stale-base", "gate-byte-budget", "gate-doc-staleness"}
    rows = [c for c in _RC3_CASES if c[0] in gate_ids]
    assert len(rows) == 6
    for _id, _kind, err, _extra, _expected in rows:
        assert " ".join(runner._error_tail(err)) == " ".join(err.split()), _id


_ALNUM36 = "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8"


@pytest.mark.parametrize("text,secret,survivor", [
    (f"https://x-access-token:ghs_{_ALNUM36}@github.com/a/b.git", f"ghs_{_ALNUM36}",
     "github.com/a/b.git"),
    (f"push failed with ghp_{_ALNUM36}", f"ghp_{_ALNUM36}", None),
    ("token github_pat_" + "a1B2c3D4e5" * 4, "a1B2c3D4e5" * 4, None),
    ("Authorization: Bearer abc.def-ghi", "abc.def-ghi", None),
])
def test_redact_strips_credentials(text, secret, survivor):
    assert secret not in " ".join(runner._error_tail(text))
    assert secret not in runner._cmd_text(text)
    if survivor:
        assert survivor in " ".join(runner._error_tail(text))


def _stage_res(kind, error):
    r = RunResult(terminal=TerminalState.FAILED)
    r.error_kind = kind
    r.error = error
    return r


def test_stage_of_prefers_phase_then_kind():
    diverged = ("remote-head-diverged: phase7: remote head X diverged from Y "
                "with different content")
    assert runner._stage_of(_stage_res("remote-head-diverged", diverged)) == "phase7"
    assert runner._stage_of(_stage_res("local-gate", "local-gate: phase5.5: x")) == "phase5.5"
    assert runner._stage_of(_stage_res("git", "boom")) == "git"
    assert runner._stage_of(_stage_res(None, None)) == "unrecorded"


# --- verdict_line ---------------------------------------------------------------

def _failed(kind, error, **kw):
    r = RunResult(terminal=TerminalState.FAILED)
    r.error_kind = kind
    r.error = error
    for k, v in kw.items():
        setattr(r, k, v)
    return r


def test_diverged_verdict_names_stage_and_evidence():
    res = _failed("remote-head-diverged",
                  "remote-head-diverged: phase7: remote head abc1234 diverged from "
                  "def5678 with different content", pr_number=42)
    line = runner.verdict_line(res, 3, "")
    assert "at phase7" in line
    assert "abc1234" in line and "def5678" in line
    assert "PR #42" in line
    assert "cause unknown" not in line
    assert "\n" not in line
    assert line.startswith("RESULT: post-plan BLOCKED")


def test_local_gate_verdict_names_command_and_tail():
    tail = "\n".join([f"filler {i:03d}" for i in range(60)] + ["GUIDANCE: run bin/check-x"])
    res = _failed("local-gate", "local-gate: git commit: " + "f" * 800,
                  error_output_tail=tail, error_cmd="git commit")
    line = runner.verdict_line(res, 3, "")
    assert "Command: git commit." in line
    assert "GUIDANCE: run bin/check-x" in line
    assert "[class=" in line
    assert "filler 000" not in line
    assert "\n" not in line


_FAIL_LINE = ("FAIL  /wt/IBL5/.claude/rules/x.md  16150 bytes  lazy (path-scoped) tier  "
              "cap 16000  over by 150  -- compress this companion; do not raise the cap")

_BOILERPLATE = (
    "\n"
    "One or more checks failed:\n"
    "  1.   A per-file cap was exceeded. Resident (path-unscoped) rules load into EVERY\n"
    "       session and every sub-agent — trim, or move rationale into a path-scoped\n"
    "       companion. Lazy (path-scoped) rules have a looser regrowth cap — compress\n"
    "       the companion. Never raise a cap to make the gate pass.\n"
    "  2.   Aggregate resident byte budget exceeded (lazy files never count toward it).\n"
    "       Override only deliberately, and only to diagnose:\n"
    "       RULES_RESIDENT_BUDGET=<n> RULES_LAZY_BUDGET=<n> RULES_TOTAL_BUDGET=<n>\n"
    "  3.   A paths: glob matches no tracked file (rule can never load). Fix or remove it.\n"
    "  4.   A path-scoped rule is no longer named by its always-on referrer (payload\n"
    "       unreachable). Restore the stub pointer in the always-on file.\n"
)

_BYTE_BUDGET_FULL = (
    "On-touch doc check passed (3 files)\n"
    "On-touch doc check passed: README.md\n"
    "On-touch doc check passed: ibl5/docs/schema/current-schema.sql\n"
    + _FAIL_LINE + "\n"
    + _BOILERPLATE
    + "Trim the rule(s) above (or move detail into a path-scoped *-detail.md\n"
    "companion) before committing.\n"
)


def _truncated_result(full, **kw):
    """Mirror gitad.commit_all: error = head slice of 800, error_output_tail = whole text."""
    return _failed("local-gate", "local-gate: " + full[:800],
                   error_output_tail=full, error_cmd="git commit", **kw)


def _assert_truncation_shape():
    from harness.adapters.gitad import classify_local_gate_denial as cls
    head = "local-gate: " + _BYTE_BUDGET_FULL[:800]
    assert len(_BYTE_BUDGET_FULL) > 800 and "Trim the rule(s) above" not in head
    assert _FAIL_LINE in head                      # blocked-ship path extraction reads the head
    assert cls(head) == "unknown" and cls(_BYTE_BUDGET_FULL) == "byte-budget"


def _blocked_ship_text(res, tmp_path):
    runner.write_blocked_ship(str(tmp_path), res, 3, "/wt")
    return (tmp_path / runner.BLOCKED_SHIP_FILE).read_text()


def test_truncated_byte_budget_denial_verdict_names_class_and_remedy():
    _assert_truncation_shape()
    res = _truncated_result(_BYTE_BUDGET_FULL)
    line = runner.verdict_line(res, 3, "")
    assert "[class=byte-budget]" in line
    assert runner._GATE_REMEDY["byte-budget"] in line
    assert "[class=unknown]" not in line
    assert runner._GATE_REMEDY["unknown"] not in line
    assert "\n" not in line


def test_truncated_byte_budget_denial_blocked_ship_names_file_and_steps(tmp_path):
    _assert_truncation_shape()
    text = _blocked_ship_text(_truncated_result(_BYTE_BUDGET_FULL), tmp_path)
    assert "/wt/IBL5/.claude/rules/x.md" in text
    assert "A rule file under .claude/rules is over its size limit." in text
    assert "bin/check-rules-byte-budget" in text
    assert "A pre-commit or pre-push check refused the commit." not in text


@pytest.mark.parametrize("tail", [
    pytest.param("gofmt: engine/x.go needs formatting\n", id="gofmt"),
    pytest.param("Fix the above doc issues before committing.\n", id="docs-hint"),
    pytest.param(_BOILERPLATE, id="boilerplate-only"),
])
def test_truncated_tail_without_byte_budget_marker_stays_unknown(tail, tmp_path):
    res = _truncated_result("x" * 900 + "\n" + tail)
    line = runner.verdict_line(res, 3, "")
    assert "[class=unknown]" in line
    assert "[class=byte-budget]" not in line
    text = _blocked_ship_text(res, tmp_path)
    assert "A pre-commit or pre-push check refused the commit." in text
    assert "over its size limit" not in text


def test_truncated_doc_staleness_tail_is_not_upgraded(tmp_path):
    res = _truncated_result("x" * 900 + "\nBump last_verified in docs/a.md\n")
    line = runner.verdict_line(res, 3, "")
    assert "[class=unknown]" in line
    assert "[class=doc-staleness]" not in line
    assert runner._GATE_REMEDY["doc-staleness"] not in line
    text = _blocked_ship_text(res, tmp_path)
    assert "A doc changed but its last_verified date was not updated." not in text


def test_head_class_wins_over_byte_budget_tail():
    res = _failed("local-gate", "local-gate: pre-commit-adr-gate: needs ADR ...",
                  error_output_tail=_BYTE_BUDGET_FULL, error_cmd="git commit")
    line = runner.verdict_line(res, 3, "")
    assert "[class=adr]" in line
    assert "[class=byte-budget]" not in line


def test_fallback_names_stage_when_no_command():
    line = runner.verdict_line(_failed(None, "phase3: something odd"), 3, "")
    assert "cause unknown (stage: phase3)" in line
    assert "something odd" in line


def test_fallback_with_command_omits_cause_unknown():
    line = runner.verdict_line(_failed("mystery", "boom", error_cmd="bin/foo --x"), 3, "")
    assert "Command: bin/foo --x." in line
    assert "Error: boom" in line
    assert "at mystery" in line
    assert "cause unknown" not in line


def test_verdict_redacts_token_in_tail():
    secret = "ghs_" + _ALNUM36
    res = _failed("local-gate", "local-gate: git push",
                  error_output_tail=f"https://x-access-token:{secret}@github.com/a/b.git",
                  error_cmd="git push")
    line = runner.verdict_line(res, 3, "")
    assert secret not in line
    assert "github.com/a/b.git" in line


# --- human_block ----------------------------------------------------------------

_FILLER_TAIL = "\n".join([f"filler {i:03d}" for i in range(60)] + ["GUIDANCE: run bin/check-x"])


def _block(res, wt="/w", log="/l"):
    return runner.human_block(res, 3, wt, log)


def test_unknown_block_shows_stage_command_and_tail():
    res = _failed("mystery", "phase3: odd", error_cmd="bin/foo --x",
                  error_output_tail=_FILLER_TAIL)
    block = _block(res, log="/tmp/run.log")
    assert "Stopped during: phase3" in block
    assert "Command: bin/foo --x" in block
    assert "Last error lines:" in block
    assert "> GUIDANCE: run bin/check-x" in block
    assert "filler 000" not in block
    assert block.splitlines()[-1] == "Log: /tmp/run.log"


def test_gate_unknown_block_shows_command_and_tail():
    res = _failed("local-gate", "One or more checks failed:", error_cmd="git commit")
    block = _block(res)
    assert "Command: git commit" in block
    assert "> One or more checks failed:" in block


def test_diverged_block_names_stage_and_evidence():
    res = _failed("remote-head-diverged",
                  "remote-head-diverged: phase7: remote head abc1234 diverged from "
                  "def5678 with different content")
    block = _block(res)
    assert "Stopped during: phase7" in block
    assert "Evidence:" in block
    assert "abc1234" in block
    assert "git fetch origin" in block


def test_block_10kb_error_under_budget_keeps_fix_and_log():
    big = "\n".join(f"line {i:03d} " + "x" * 40 for i in range(200))
    res = _failed(None, big, error_cmd="c" * 10000, error_output_tail=big, slug="z" * 150)
    log = "l" * 500
    block = _block(res, wt="w" * 500, log=log)
    assert len(block) <= runner._BLOCK_BUDGET
    assert "\nFix:\n  1. cd " in block
    assert f"Log: {log}" in block.splitlines()
    assert "postplan-fix" in block.splitlines()[-1]
    assert sum(1 for ln in block.splitlines() if ln.startswith("> ")) <= 3


def test_block_drops_detail_before_fix_under_pressure(monkeypatch):
    res = _failed("mystery", "phase3: odd", error_cmd="bin/foo --x",
                  error_output_tail=_FILLER_TAIL)
    full = _block(res, log="/tmp/run.log")
    monkeypatch.setattr(runner, "_BLOCK_BUDGET", len(full) - 1)
    squeezed = _block(res, log="/tmp/run.log")
    assert "Command:" not in squeezed
    assert "Last error lines:" not in squeezed
    assert "Fix:" in squeezed
    assert squeezed.splitlines()[-1] == "Log: /tmp/run.log"
    assert len(squeezed) <= len(full) - 1


def test_block_redacts_token():
    secret = "ghs_" + _ALNUM36
    res = _failed(None, "boom",
                  error_output_tail=f"https://x-access-token:{secret}@github.com/a/b.git")
    assert secret not in _block(res)
