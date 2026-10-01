"""Prose-fix pass: characterization of today's hold, then the pre-push auto-fix.

Every test runs on a real temp git repo with a verbatim copy of `bin/check-prose`
behind a stub `bin/run-meta-checks-local`, so the gate that clears the hold is the
production gate, not a mock.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit
from harness.armable import meta_checks_clearance

REAL_CHECK_PROSE = Path(__file__).resolve().parents[3] / "bin" / "check-prose"

STUB = r"""#!/usr/bin/env bash
BASE=master
while [ $# -gt 0 ]; do
  case "$1" in --base) BASE="$2"; shift 2 ;; *) shift ;; esac
done
DIR="$(cd "$(dirname "$0")" && pwd)"
failed=()
out="$("$DIR/check-prose" "--since=$BASE" 2>&1)"; rc=$?
if [ "$rc" -ne 0 ]; then
  printf 'FAIL  %s\n%s\n' check-prose-since "$out"; failed+=(check-prose-since)
else
  printf 'PASS  %s\n' check-prose-since
fi
if [ -n "${STUB_EXTRA_FAIL:-}" ]; then
  printf 'FAIL  %s\n' "$STUB_EXTRA_FAIL"; failed+=("$STUB_EXTRA_FAIL")
fi
for n in "${failed[@]}"; do printf 'META-CHECK-FAILED: %s\n' "$n"; done
[ "${#failed[@]}" -eq 0 ]
"""

TELL_LINE = "The cache warms fast — the pool stays small."
CLEAN_LINE = "The cache warms fast. The pool stays small."


class Env:
    def __init__(self, repo, git, flag, log):
        self.repo = repo
        self.git = git
        self.flag = flag
        self.log = log


def _git(repo, *args):
    return subprocess.run(["git", "-C", repo, *args], capture_output=True, text=True,
                          check=True).stdout


def make_prose_repo(tmp_path, monkeypatch, note_path="docs/note.md"):
    repo = tmp_path / "repo"
    (repo / "bin").mkdir(parents=True)
    (repo / "docs").mkdir()
    (repo / note_path).parent.mkdir(parents=True, exist_ok=True)
    r = str(repo)
    _git(r, "init", "-b", "master")
    _git(r, "config", "user.email", "t@t")
    _git(r, "config", "user.name", "t")
    _git(r, "config", "commit.gpgsign", "false")
    (repo / note_path).write_text("# Note\n\nThe pool starts small.\n")
    shutil.copy2(REAL_CHECK_PROSE, repo / "bin" / "check-prose")
    stub = repo / "bin" / "run-meta-checks-local"
    stub.write_text(STUB)
    stub.chmod(0o755)
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "base")
    branch = f"pfx-{tmp_path.name}"
    _git(r, "checkout", "-b", branch)
    with open(repo / note_path, "a") as fh:
        fh.write(TELL_LINE + "\n")
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "feat: add note")
    flag = f"/tmp/ibl5-meta-checks-prepush-{branch.replace('/', '-')}.failed"
    if os.path.exists(flag):
        os.unlink(flag)
    monkeypatch.setattr(runner, "_remediate_doc_staleness", lambda *a, **k: 0)
    proc = subprocess.run([str(stub), "--stage", "pre-push", "--base", "master"],
                          cwd=r, capture_output=True, text=True)
    assert proc.returncode == 1
    assert f"{note_path}:4:" in proc.stdout
    return Env(r, LiveGit(r), flag, [])


@pytest.fixture
def prose_repo(tmp_path, monkeypatch):
    env = make_prose_repo(tmp_path, monkeypatch)
    yield env
    if os.path.exists(env.flag):
        os.unlink(env.flag)


def test_char_sole_prose_failure_holds_today(prose_repo):
    e = prose_repo
    head = e.git.head()
    fo: list = []
    ok = runner.run_meta_checks_local(e.git, e.repo, "master", e.log.append,
                                      failures_out=fo)
    assert ok is False
    assert open(e.flag).read() == "check-prose-since\n"
    assert e.log[-1] == ("phase2: META-CHECKS FAILED (check-prose-since) — "
                         "pushing anyway, auto-merge will not arm")
    assert e.git.head() == head
    assert e.git.is_dirty() is False
    assert [f["name"] for f in fo] == ["check-prose-since"]
    assert "docs/note.md:4:" in fo[0]["output"]
    assert meta_checks_clearance(e.flag, 0) == "HELD"


def test_char_clean_branch_unlinks_stale_flag(prose_repo):
    e = prose_repo
    note = Path(e.repo) / "docs" / "note.md"
    note.write_text(note.read_text().replace(TELL_LINE, CLEAN_LINE))
    _git(e.repo, "add", "-A")
    _git(e.repo, "commit", "-m", "fix: clean prose")
    Path(e.flag).write_text("check-prose-since\n")
    assert runner.run_meta_checks_local(e.git, e.repo, "master", e.log.append) is True
    assert os.path.exists(e.flag) is False
    assert meta_checks_clearance(e.flag, 0) == "CLEARED"


# ---------------------------------------------------------------- Phase 2: helpers

from harness import prosefix  # noqa: E402

CAPTURED = """\
PASS  check-prose-self-test
FAIL  check-prose-since
.claude/review-shared/_plan-fidelity-review.md:51: [em-dash] ...**PR body vs. reality** — check the PR body's **hand...  FIX: End the sentence and start a new one. For a bullet label use `**Label.** text` or `Label: text`.
.claude/review-shared/_plan-fidelity-review.md:51: [comma-not] ...econciled by construction, not a claim — a drift there is...  FIX: Define by what it is. Keep only the true half; delete the ', not Y' tail.

check-prose: 2 Claude-slop tell(s) on lines added vs origin/master. Rewrite them per .claude/rules/prose-style.md. Verbatim quotes go in a code span or carry <!-- slop-ok --> on the line.
PASS  check-registry-trigger-rows
META-CHECK-FAILED: check-prose-since
META-CHECKS: FAILED stage=pre-push hard=6 failed=1 warn=0 skip=9 defer=0
"""


def test_parse_hits_real_captured_shape():
    assert prosefix.parse_hits(CAPTURED) == {
        ".claude/review-shared/_plan-fidelity-review.md": {51}}


def test_parse_hits_two_files():
    extra = ".claude/skills/plan/SKILL.md:234: [em-dash] ...x — y...  FIX: End the sentence.\n"
    hits = prosefix.parse_hits(CAPTURED + extra)
    assert set(hits) == {".claude/review-shared/_plan-fidelity-review.md",
                         ".claude/skills/plan/SKILL.md"}
    assert hits[".claude/skills/plan/SKILL.md"] == {234}


def test_parse_hits_no_hits_is_empty():
    out = ("PASS  a\nFAIL  check-prose-since\nbin/tool.sh:12: [em-dash] x\n"
           "check-prose: 0 Claude-slop tell(s)\nMETA-CHECK-FAILED: check-prose-since\n")
    assert prosefix.parse_hits(out) == {}


def test_gate_owned_flags_rules_path():
    assert prosefix.gate_owned({".claude/rules/prose-style.md": {3}, "docs/x.md": {1}}) == [
        ".claude/rules/prose-style.md"]
    assert prosefix.gate_owned({"docs/x.md": {1}}) == []


FLAGGED = {"docs/note.md": {4}}


def _note(env) -> Path:
    return Path(env.repo) / "docs" / "note.md"


def test_scope_in_place_rewrite_ok(prose_repo):
    n = _note(prose_repo)
    n.write_text(n.read_text().replace(TELL_LINE, CLEAN_LINE))
    assert prosefix.scope_violations(prose_repo.repo, FLAGGED) == []


def test_scope_sentence_split_ok(prose_repo):
    n = _note(prose_repo)
    n.write_text(n.read_text().replace(TELL_LINE, "The cache warms fast.\nThe pool stays small."))
    assert prosefix.scope_violations(prose_repo.repo, FLAGGED) == []


def test_scope_unflagged_line_rejected(prose_repo):
    n = _note(prose_repo)
    n.write_text(n.read_text().replace(TELL_LINE, CLEAN_LINE)
                 .replace("The pool starts small.", "The pool starts tiny."))
    assert "docs/note.md:3: edit outside flagged lines" in prosefix.scope_violations(
        prose_repo.repo, FLAGGED)


def test_scope_outside_file_rejected(prose_repo):
    (Path(prose_repo.repo) / "docs" / "other.md").write_text("x\n")
    assert "docs/other.md: file outside flagged set" in prosefix.scope_violations(
        prose_repo.repo, FLAGGED)


def test_scope_deleted_flagged_file_rejected(prose_repo):
    _note(prose_repo).unlink()
    v = prosefix.scope_violations(prose_repo.repo, FLAGGED)
    for n in (1, 2, 3):
        assert f"docs/note.md:{n}: edit outside flagged lines" in v


# ------------------------------------------------------------ Phase 3: attempt loop

import inspect  # noqa: E402

from harness.adapters.llm import ClaudeCli, TOOLED_MODELS  # noqa: E402
from harness.state import HarnessError  # noqa: E402


class FakeToolLlm:
    def __init__(self, actions):
        self.actions = actions
        self.calls: list = []

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools, denied_tools, **kw):
        self.calls.append((purpose, model, list(allowed_tools), list(denied_tools)))
        return self.actions[len(self.calls) - 1](cwd)


def _argv(env):
    return [os.path.join(env.repo, "bin", "run-meta-checks-local"),
            "--stage", "pre-push", "--base", "master"]


def _first_stdout(env):
    return subprocess.run(_argv(env), cwd=env.repo, capture_output=True, text=True).stdout


def _run_fix(env, llm, commit=None, first_stdout=None):
    return prosefix.attempt_prose_fix(
        git=env.git, llm=llm, repo=env.repo, argv=_argv(env),
        first_stdout=_first_stdout(env) if first_stdout is None else first_stdout,
        commit=commit or env.git.commit_all, log=env.log.append)


def _edit(old, new, rel="docs/note.md"):
    def act(cwd):
        p = Path(cwd) / rel
        p.write_text(p.read_text().replace(old, new))
    return act


def _noop(cwd):
    return None


def _fix_line4(cwd):
    _edit(TELL_LINE, CLEAN_LINE)(cwd)


def _commit_count(env) -> int:
    return int(_git(env.repo, "rev-list", "--count", "HEAD").strip())


def test_fix_first_attempt_clears(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    llm = FakeToolLlm([_fix_line4])
    assert _run_fix(e, llm) is True
    assert [c[1] for c in llm.calls] == ["sonnet"]
    assert llm.calls[0][0] == "prose-fix"
    assert llm.calls[0][2] == ["Read", "Grep", "Glob", "Edit"]
    assert "Bash" in llm.calls[0][3]
    assert _git(e.repo, "log", "-1", "--format=%s").strip() == "chore: rewrite flagged prose tells"
    assert _git(e.repo, "rev-parse", "HEAD^").strip() == head_before
    assert e.git.is_dirty() is False


def test_escalates_to_opus_after_noop(prose_repo):
    llm = FakeToolLlm([_noop, _fix_line4])
    assert _run_fix(prose_repo, llm) is True
    assert [c[1] for c in llm.calls] == ["sonnet", "opus"]


def test_both_attempts_fail_reverts(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    count = _commit_count(e)
    llm = FakeToolLlm([_edit(TELL_LINE, "The cache warms fast — the pool stays tiny."), _noop])
    assert _run_fix(e, llm) is False
    assert [c[1] for c in llm.calls] == ["sonnet", "opus"]
    assert e.git.head() == head_before
    assert e.git.is_dirty() is False
    assert _commit_count(e) == count


def test_scope_violation_reverts_and_counts(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    note = Path(e.repo) / "docs" / "note.md"
    original = note.read_text()

    def a1(cwd):
        _fix_line4(cwd)
        _edit("The pool starts small.", "The pool starts tiny.")(cwd)

    def a2(cwd):
        _fix_line4(cwd)
        (Path(cwd) / "docs" / "other.md").write_text("x\n")

    llm = FakeToolLlm([a1, a2])
    assert _run_fix(e, llm) is False
    assert len(llm.calls) == 2
    assert e.git.head() == head_before
    assert not (Path(e.repo) / "docs" / "other.md").exists()
    assert note.read_text() == original


def test_escape_hatch_rejected(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    llm = FakeToolLlm([_edit(TELL_LINE, TELL_LINE + " <!-- slop-ok -->"),
                       _edit("— the pool", "`— the pool`")])
    assert _run_fix(e, llm) is False
    assert e.git.head() == head_before


def test_gate_owned_path_makes_no_call(prose_repo):
    e = prose_repo
    llm = FakeToolLlm([])
    out = (".claude/rules/x.md:4: [em-dash] ...a — b...  FIX: End the sentence.\n"
           "META-CHECK-FAILED: check-prose-since\n")
    assert _run_fix(e, llm, first_stdout=out) is False
    assert llm.calls == []
    assert any("gate-owning path: .claude/rules/x.md" in l for l in e.log)


def test_dirty_tree_skips(prose_repo):
    e = prose_repo
    first = _first_stdout(e)
    scratch = Path(e.repo) / "scratch.txt"
    scratch.write_text("x\n")
    llm = FakeToolLlm([_fix_line4])
    assert _run_fix(e, llm, first_stdout=first) is False
    assert llm.calls == []
    assert scratch.exists()


def test_usage_limit_propagates(prose_repo):
    e = prose_repo
    head_before = e.git.head()

    def boom(cwd):
        _edit("The pool starts small.", "The pool starts tiny.")(cwd)
        raise HarnessError("llm-usage-limit", "cap")

    with pytest.raises(HarnessError) as ei:
        _run_fix(e, FakeToolLlm([boom]))
    assert ei.value.kind == "llm-usage-limit"
    assert e.git.head() == head_before
    assert e.git.is_dirty() is False


def test_other_model_error_holds(prose_repo):
    e = prose_repo

    def boom(cwd):
        raise HarnessError("llm-tooled-timeout", "t")

    llm = FakeToolLlm([boom, _fix_line4])
    assert _run_fix(e, llm) is False
    assert len(llm.calls) == 1
    assert e.git.is_dirty() is False


def test_rerun_other_failure_stops(prose_repo, monkeypatch):
    e = prose_repo
    head_before = e.git.head()
    first = _first_stdout(e)
    monkeypatch.setenv("STUB_EXTRA_FAIL", "check-docs-since")
    still_red = _edit(TELL_LINE, "The cache warms fast — the pool stays tiny.")
    llm = FakeToolLlm([still_red, still_red])
    assert _run_fix(e, llm, first_stdout=first) is False
    assert len(llm.calls) == 1
    assert e.git.head() == head_before
    assert any("failed=check-prose-since check-docs-since" in l for l in e.log)


def test_commit_denial_counts_failed(prose_repo):
    e = prose_repo
    state = {"n": 0}

    def commit(msg):
        state["n"] += 1
        if state["n"] == 1:
            raise HarnessError("local-gate", "denied")
        return e.git.commit_all(msg)

    llm = FakeToolLlm([_fix_line4, _fix_line4])
    assert _run_fix(e, llm, commit=commit) is True
    assert [c[1] for c in llm.calls] == ["sonnet", "opus"]


def test_failed_check_names_parses_markers():
    out = ("PASS  a\nMETA-CHECK-FAILED: check-prose-since\nMETA-CHECK-FAILED: adr-check\n"
           "META-CHECKS: FAILED stage=pre-push")
    assert prosefix.failed_check_names(out) == ["check-prose-since", "adr-check"]


def test_call_shape_binds_live_adapter():
    inspect.signature(ClaudeCli.call_tooled).bind(
        None, prosefix.PROSE_FIX_PURPOSE, "sonnet", "p", cwd="/tmp",
        allowed_tools=prosefix.PROSE_FIX_ALLOWED_TOOLS,
        denied_tools=prosefix.PROSE_FIX_DENIED_TOOLS)
    assert set(prosefix.PROSE_FIX_MODELS) <= set(TOOLED_MODELS)


# --------------------------------------------------- Phase 4: runner integration

import tempfile  # noqa: E402

from harness.adapters.gitad import ReplayGit  # noqa: E402
from harness.adapters.llm import FixtureLlm  # noqa: E402
from harness.state import UsageLedger  # noqa: E402

HELD_LOG = ("phase2: META-CHECKS FAILED (check-prose-since) — "
            "pushing anyway, auto-merge will not arm")
STILL_RED = "The cache warms fast — the pool stays tiny."


def _meta(env, llm, **kw):
    fo: list = []
    ok = runner.run_meta_checks_local(env.git, env.repo, "master", env.log.append,
                                      failures_out=fo, llm=llm, **kw)
    return ok, fo


def test_runner_prose_fix_clears_condition_16(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    Path(e.flag).write_text("check-prose-since\n")
    llm = FakeToolLlm([_fix_line4])
    ok, fo = _meta(e, llm)
    assert ok is True
    assert os.path.exists(e.flag) is False
    assert fo == []
    assert _git(e.repo, "rev-list", "--count", f"{head_before}..HEAD").strip() == "1"
    assert _git(e.repo, "log", "-1", "--format=%s").strip() == "chore: rewrite flagged prose tells"
    assert e.git.is_dirty() is False
    assert meta_checks_clearance(e.flag, 0) == "CLEARED"


def test_runner_prose_fix_failure_matches_today(prose_repo):
    e = prose_repo
    head_before = e.git.head()
    llm = FakeToolLlm([_edit(TELL_LINE, STILL_RED), _noop])
    ok, fo = _meta(e, llm)
    assert ok is False
    assert open(e.flag).read() == "check-prose-since\n"
    assert e.log[-1] == HELD_LOG
    assert e.git.head() == head_before
    assert e.git.is_dirty() is False
    assert [c[1] for c in llm.calls] == ["sonnet", "opus"]
    assert "docs/note.md:4:" in fo[0]["output"]
    assert "The cache warms fast" in fo[0]["output"]
    assert "stays tiny" not in fo[0]["output"]


def test_runner_scope_violation_holds(prose_repo):
    e = prose_repo
    head_before = e.git.head()

    def a1(cwd):
        _fix_line4(cwd)
        _edit("The pool starts small.", "The pool starts tiny.")(cwd)

    def a2(cwd):
        _fix_line4(cwd)
        (Path(cwd) / "docs" / "other.md").write_text("x\n")

    ok, _ = _meta(e, FakeToolLlm([a1, a2]))
    assert ok is False
    assert os.path.exists(e.flag)
    assert e.git.head() == head_before
    assert not (Path(e.repo) / "docs" / "other.md").exists()


def test_runner_gate_owned_path_no_call(tmp_path, monkeypatch):
    e = make_prose_repo(tmp_path, monkeypatch, note_path=".claude/rules/note.md")
    try:
        llm = FakeToolLlm([_fix_line4])
        ok, _ = _meta(e, llm)
        assert ok is False
        assert llm.calls == []
        assert os.path.exists(e.flag)
        assert any("gate-owning path: .claude/rules/note.md" in l for l in e.log)
    finally:
        if os.path.exists(e.flag):
            os.unlink(e.flag)


def test_runner_other_failure_no_call(prose_repo, monkeypatch):
    e = prose_repo
    monkeypatch.setenv("STUB_EXTRA_FAIL", "check-docs-since")
    llm = FakeToolLlm([_fix_line4])
    ok, _ = _meta(e, llm)
    assert ok is False
    assert llm.calls == []
    assert open(e.flag).read() == "check-prose-since check-docs-since\n"


def test_runner_dirty_tree_no_call(prose_repo):
    e = prose_repo
    scratch = Path(e.repo) / "scratch.txt"
    scratch.write_text("x\n")
    llm = FakeToolLlm([_fix_line4])
    ok, _ = _meta(e, llm)
    assert ok is False
    assert llm.calls == []
    assert scratch.exists()
    assert os.path.exists(e.flag)


def test_runner_llm_none_unchanged(prose_repo):
    e = prose_repo
    head = e.git.head()
    ok = runner.run_meta_checks_local(e.git, e.repo, "master", e.log.append)
    assert ok is False
    assert open(e.flag).read() == "check-prose-since\n"
    assert e.log[-1] == HELD_LOG
    assert e.git.head() == head


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_run_passes_llm_to_meta_checks(monkeypatch):
    recorded: dict = {}

    def fake(*a, **kw):
        recorded.update(kw)
        return True

    monkeypatch.setattr(runner, "run_meta_checks_local", fake)
    fx = {
        "slug": "prosefix-llm-passthrough",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 8889,
        "pr_meta": {"number": 8889, "title": "chore: passthrough test",
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": None, "phpstan": None, "go": None},
        "plan_content": "# Test plan\n\nNo matrix.\n",
    }
    canned = {
        "pr-copy": {"type": "chore", "title": "chore: passthrough test",
                    "commit_subject": "chore: passthrough test commit",
                    "summary_md": "## Summary\n- x\n"},
        "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
        "security-audit": [],
        "safety-verdict": {"holds": []},
        "manual-classify": [],
        "retrospective": {"save": False},
    }
    out = tempfile.mkdtemp(prefix="postplan-test-prosefix-")
    llm = FixtureLlm(UsageLedger(), canned)
    runner.run(fx, out, llm, mode="replay", headless=True)
    assert recorded.get("llm") is llm


def test_runner_usage_limit_propagates(prose_repo):
    e = prose_repo
    head_before = e.git.head()

    def boom(cwd):
        raise HarnessError("llm-usage-limit", "cap")

    with pytest.raises(HarnessError) as ei:
        _meta(e, FakeToolLlm([boom]))
    assert ei.value.kind == "llm-usage-limit"
    assert e.git.is_dirty() is False
    assert e.git.head() == head_before


def test_runner_model_error_holds(prose_repo):
    e = prose_repo

    def boom(cwd):
        raise HarnessError("llm-tooled-timeout", "t")

    llm = FakeToolLlm([boom, _fix_line4])
    ok, _ = _meta(e, llm)
    assert ok is False
    assert os.path.exists(e.flag)
    assert len(llm.calls) == 1
