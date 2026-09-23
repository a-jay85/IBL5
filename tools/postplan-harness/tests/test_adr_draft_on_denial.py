"""Tests for one-shot ADR auto-draft on pre-push-adr-hook denial.

Covers:
  - adr_draft.draft(): happy path, validation rejections, scope guard, gate failures,
    numbering collision handling, LLM error propagation
  - runner._push_with_adr_draft(): one-draft-per-run guard, stale-base passthrough,
    second-denial verdict, draft failure chaining, gate-after-commit path
  - runner._run_fidelity() and runner.run() call sites
  - RunResult JSON serialisation of adr_* fields
  - _GATE_REMEDY["adr"] content
  - End-to-end: real git hook, draft, commit, re-push
"""
from __future__ import annotations

import json
import os
import pathlib
import shutil
import stat
import subprocess
import sys
import tempfile
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import adr_draft, fidelity
from harness.adapters import gitad
from harness.adapters.gitad import (
    LiveGit,
    ReplayGit,
    _GATE_CLASSES,
    _STALE_BASE_MARKER,
    classify_local_gate_denial,
    is_stale_base,
)
from harness.adapters.ghad import RecordingGh
from harness.adapters.llm import MODEL_MAP, FixtureLlm, UsageLedger
from harness.state import HarnessError, RunResult, TerminalState

# ---------------------------------------------------------------------------
# Hook text fixtures (exact text bin/pre-push-adr-hook emits, as LiveGit wraps it)
# ---------------------------------------------------------------------------

_STALE_BASE_TEXT = (
    "git push: pre-push-adr-hook: branch does not contain origin/master.\n"
    "Fetch and merge before pushing:\n"
    "  git fetch origin master && git merge origin/master"
)

_ADR_TEXT = (
    "git push: pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR.\n"
    "Resolve with ONE of:\n"
    '  1. Add an ADR under ibl5/docs/decisions/ (run: bin/next-adr "kebab-title").'
)

# ---------------------------------------------------------------------------
# FakeGit (copied from test_behind_lease_retry.py pattern)
# ---------------------------------------------------------------------------


class FakeGit:
    def __init__(self, push_errors=None, head_shas=None, proof_ok=True):
        self.push_errors = list(push_errors or [])
        self.head_shas = list(head_shas or ["a" * 40])
        self.proof_ok = proof_ok
        self.pushes = 0
        self.rebases = 0
        self.fetches = 0
        self.proofs = 0

    def branch(self): return "wt-slug"
    def head(self): return self.head_shas[min(self.rebases, len(self.head_shas) - 1)]

    def push(self):
        err = self.push_errors[self.pushes] if self.pushes < len(self.push_errors) else None
        self.pushes += 1
        if err:
            raise err

    def capture_lostwork_pre(self, key): return True
    def fetch_base(self, base="origin/master"): self.fetches += 1
    def rebase_onto(self, base="origin/master"): self.rebases += 1

    def prove_lostwork(self, key):
        self.proofs += 1
        return (True, "TREE-EQUIVALENT") if self.proof_ok else (False, "TREE DIVERGED")


def _noop_log(msg): pass


# ---------------------------------------------------------------------------
# git shim for subprocess calls inside fidelity.build_packet / fidelity.remediate
# ---------------------------------------------------------------------------

_GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


def _install_git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    g = bindir / "git"
    g.write_text(_GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")


# ---------------------------------------------------------------------------
# Real-repo fixture
# ---------------------------------------------------------------------------

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_TEMPLATE_SRC = os.path.join(
    _REPO_ROOT, "ibl5", "docs", "decisions", "0000-template.md"
)


@pytest.fixture
def repo(tmp_path):
    """Minimal git repo: master with template + stubs, bare origin, wt-slug branch."""
    wt = tmp_path / "wt"
    wt.mkdir()

    subprocess.run(["git", "init", str(wt)], check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "symbolic-ref", "HEAD",
                    "refs/heads/master"], check=True)
    subprocess.run(["git", "-C", str(wt), "config", "user.email",
                    "test@example.com"], check=True)
    subprocess.run(["git", "-C", str(wt), "config", "user.name", "Test User"],
                   check=True)

    adr_dir = wt / "ibl5" / "docs" / "decisions"
    adr_dir.mkdir(parents=True)
    shutil.copy(_TEMPLATE_SRC, str(adr_dir / "0000-template.md"))
    for name in ("0131-a.md", "0132-b.md", "0133-c.md"):
        (adr_dir / name).write_text(f"# {name}\nstub content\n")
    (wt / "bin").mkdir()
    (wt / "bin" / ".keep").write_text("")

    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(["git", "-C", str(wt), "commit", "-m", "init"],
                   check=True, capture_output=True)

    origin = tmp_path / "origin.git"
    subprocess.run(["git", "init", "--bare", str(origin)],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "remote", "add", "origin", str(origin)],
                   check=True)
    subprocess.run(["git", "-C", str(wt), "push", "origin", "master"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "checkout", "-b", "wt-slug"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "update-ref",
                    "refs/remotes/origin/master", "master"], check=True)

    git = LiveGit(str(wt), push_remote="origin")
    return {"wt": wt, "origin": origin, "git": git, "tmp_path": tmp_path}


# ---------------------------------------------------------------------------
# fake_scripts: monkeypatch adr_draft._run_script
# ---------------------------------------------------------------------------

def fake_scripts(monkeypatch, overrides=None):
    """Replace adr_draft._run_script with a per-basename dispatcher.

    Defaults:
      adr-check  -- returns surfaces on call 0, (0, "") on call 1+
      next-adr   -- copies 0000-template.md to 0134-<slug>.md, returns abs path
      check-numbering, check-docs, check-prose -- (0, "")

    overrides: dict{basename -> callable(worktree, rel, *args, stdin="")}
    """
    adr_check_calls = []

    def _adr_check(worktree, rel, *args, stdin=""):
        idx = len(adr_check_calls)
        adr_check_calls.append(args)
        if idx == 0:
            return (
                1,
                "Decision-trigger surfaces detected:\n"
                "  - [new-tool-script] bin/x - new bin/ helper\n"
                "FAIL: ADR required",
            )
        return (0, "")

    def _next_adr(worktree, rel, *args, stdin=""):
        slug = args[0] if args else "wt-slug"
        decisions = os.path.join(worktree, "ibl5", "docs", "decisions")
        os.makedirs(decisions, exist_ok=True)
        template = os.path.join(decisions, "0000-template.md")
        dest = os.path.join(decisions, f"0134-{slug}.md")
        shutil.copy(template, dest)
        return (0, dest)

    handlers = {
        "adr-check": _adr_check,
        "next-adr": _next_adr,
        "check-numbering": lambda wt, rel, *a, stdin="": (0, ""),
        "check-docs": lambda wt, rel, *a, stdin="": (0, ""),
        "check-prose": lambda wt, rel, *a, stdin="": (0, ""),
    }
    if overrides:
        handlers.update(overrides)

    def _dispatch(worktree, rel, *args, stdin=""):
        name = os.path.basename(rel)
        h = handlers.get(name)
        if h:
            return h(worktree, rel, *args, stdin=stdin)
        return (0, "")

    monkeypatch.setattr(adr_draft, "_run_script", _dispatch)
    return {"adr_check_calls": adr_check_calls}


# ---------------------------------------------------------------------------
# WritingLlm: records call_tooled and writes content_fn(rel) to the target
# ---------------------------------------------------------------------------

class WritingLlm:
    """Fake LLM that writes ADR content supplied by content_fn(rel) -> str."""

    def __init__(self, content_fn):
        self.content_fn = content_fn
        self.calls = []   # list of (purpose, model, prompt, kwargs)

    def call_tooled(self, purpose, model, prompt, **kwargs):
        cwd = kwargs.get("cwd", "")
        # The prompt's last "Reply with ..." line names the target rel.
        rel = None
        for line in reversed(prompt.splitlines()):
            if "ADR-WRITTEN:" in line:
                rel = line.split("ADR-WRITTEN:", 1)[1].strip()
                break
        self.calls.append((purpose, model, prompt, kwargs))
        if rel and cwd:
            content = self.content_fn(rel)
            target = os.path.join(cwd, rel)
            os.makedirs(os.path.dirname(target), exist_ok=True)
            with open(target, "w") as fh:
                fh.write(content)
        return f"ADR-WRITTEN: {rel}"


def valid_adr(number):
    """Return a fully valid ADR string for the given four-digit number."""
    return (
        "---\n"
        f"description: Automated test ADR {number}\n"
        "last_verified: 2026-09-20\n"
        "---\n"
        f"{adr_draft.ADR_ATTRIBUTION}\n"
        f"# ADR-{number}: Automated Decision for Test Coverage\n\n"
        "**Status:** Accepted\n"
        "**Date:** 2026-09-20\n"
        "**Deciders:** post-plan harness (auto-draft)\n\n"
        "## Context\n\n"
        "The decision-trigger surface was flagged by bin/adr-check during the "
        "post-plan harness push phase. The implementation introduces a new helper "
        "script in the bin/ directory that requires architectural documentation. "
        "Without an ADR the pre-push gate blocks the branch.\n\n"
        "## Decision\n\n"
        "We accept the new helper script and record the decision here. "
        "The gate enforcement is via bin/pre-push-adr-hook, which checks that "
        "every push touching a decision-trigger surface includes a numbered ADR.\n\n"
        "## Alternatives Considered\n\n"
        "- **Skip the ADR** -- do not write an ADR. Rejected because: "
        "bin/pre-push-adr-hook enforces presence; skipping is not possible.\n"
        "- **Manual draft before push** -- write by hand before every push. "
        "Rejected because: the harness auto-draft removes the manual step.\n\n"
        "## Consequences\n\n"
        "- Positive: the architectural decision is documented for future readers.\n"
        "- Negative: marginal overhead of maintaining an additional file.\n\n"
        "## References\n\n"
        "- `ibl5/docs/decisions/0000-template.md`\n"
    )


# ---------------------------------------------------------------------------
# Helpers shared by _run_fidelity tests
# ---------------------------------------------------------------------------

def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


class _Res:
    def __init__(self):
        self.fidelity = {}
        self.adr_drafted = False
        self.adr_path = None
        self.adr_draft_model = None


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


# ---------------------------------------------------------------------------
# StaleBaseGit for _run_fidelity tests (copied from test_stale_base_push.py)
# ---------------------------------------------------------------------------

class _StaleBaseGit(ReplayGit):
    def __init__(self, fixture, rebase_ok=True):
        super().__init__(fixture)
        self._push_call_count = 0
        self._rebase_count = 0
        self._commit_count = 0
        self._current_sha = fixture.get("initial_sha", "initial-" + "0" * 34)
        self.fetches = 0
        self.rebase_ok = rebase_ok

    def commit_all(self, message):
        self._commit_count += 1
        self._current_sha = f"commit-sha-{self._commit_count}"
        return self._current_sha

    def head(self):
        return self._current_sha

    def capture_lostwork_pre(self, key):
        return True

    def fetch_base(self, base="origin/master"):
        self.fetches += 1

    def rebase_onto(self, base="origin/master"):
        if not self.rebase_ok:
            raise HarnessError("rebase-conflict", "CONFLICT (content): x")
        self._rebase_count += 1
        if self._current_sha:
            self._current_sha = f"rebased-{self._current_sha}"

    def prove_lostwork(self, key):
        return (True, "TREE-EQUIVALENT")

    def push(self):
        idx = self._push_call_count
        self._push_call_count += 1
        if idx == 0:
            raise HarnessError("local-gate", _STALE_BASE_TEXT)


# ---------------------------------------------------------------------------
# Inline fixture for replay-mode runner.run test
# ---------------------------------------------------------------------------

_INLINE_CANNED = {
    "pr-copy": {
        "type": "chore",
        "title": "chore: adr-wrapper test",
        "commit_subject": "chore: adr-wrapper commit",
        "summary_md": "## Summary\n- x\n",
    },
    "body-check": {"corrected_body": "## Summary\n- x\n", "findings": []},
    "review-agent-a": [],
    "review-agent-b": [],
    "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _inline_fixture(**over):
    fx = {
        "slug": "synthetic-adr-wrapper",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9997,
        "pr_meta": {
            "number": 9997,
            "title": "chore: adr-wrapper test",
            "body": "## Manual Testing\n\nNo manual testing needed\n",
            "headRefOid": "deadbeef",
        },
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Plan\n\nSynthetic.\n",
    }
    fx.update(over)
    return fx


# ===========================================================================
# Tests: adr_draft.draft()
# ===========================================================================

def test_draft_happy_path_writes_validates_and_commits(repo, tmp_path, monkeypatch):
    """Happy path: draft() writes, validates, gates pass, commits, returns result."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    fake_scripts(monkeypatch)

    logged = []
    result = adr_draft.draft(
        WritingLlm(lambda rel: valid_adr("0134")),
        git, str(wt), out_dir, logged.append,
        phase="phase2", today="2026-09-20",
    )

    assert result.path == "ibl5/docs/decisions/0134-wt-slug.md"
    assert result.model == MODEL_MAP["opus"]

    expected_subject = adr_draft.ADR_COMMIT_MSG.format(number="0134", slug="wt-slug")
    actual_subject = subprocess.run(
        ["git", "-C", str(wt), "log", "-1", "--format=%s"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert actual_subject == expected_subject

    # Tree is clean after commit
    status = subprocess.run(
        ["git", "-C", str(wt), "status", "--porcelain"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert status == ""

    # The committed file starts with frontmatter and has attribution as first body line
    content = (wt / "ibl5" / "docs" / "decisions" / "0134-wt-slug.md").read_text()
    assert content.startswith("---\n")
    body_start = content.find("\n---", 4) + 4  # skip opening block
    body = content[body_start:]
    first_body_line = next(ln for ln in body.splitlines() if ln.strip())
    assert first_body_line.strip() == adr_draft.ADR_ATTRIBUTION


def test_draft_prompt_carries_surfaces_diff_plan_head_and_examples(
        repo, tmp_path, monkeypatch):
    """The LLM prompt embeds surfaces, a diff line, plan head, example basenames,
    the attribution, and the target path."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    # Commit something on wt-slug so the diff is non-empty
    (wt / "bin" / "x").write_text("#!/bin/bash\necho hello\n")
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(["git", "-C", str(wt), "commit", "-m", "add bin/x"],
                   check=True, capture_output=True)

    # Plan file: >4000 chars so truncation is exercised
    plan_head = "P" * 4000
    plan_sentinel = "UNIQUEPLANSENTINEL"
    plan_path = str(tmp_path / "plan.md")
    with open(plan_path, "w") as fh:
        fh.write(plan_head + plan_sentinel)

    fake_scripts(monkeypatch)
    llm = WritingLlm(lambda rel: valid_adr("0134"))

    adr_draft.draft(
        llm, git, str(wt), out_dir, _noop_log,
        phase="phase2", plan_path=plan_path, today="2026-09-20",
    )

    assert llm.calls, "call_tooled was never called"
    _, _, prompt, _ = llm.calls[0]

    # Surfaces block
    assert "Decision-trigger surfaces detected:" in prompt
    assert "bin/x" in prompt

    # Diff contains the new file
    assert "bin/x" in prompt

    # Plan head (first 4000 chars) is included; sentinel (char 4001+) is not
    assert "P" * 100 in prompt
    assert plan_sentinel not in prompt

    # Example ADR basenames appear in the examples block
    for basename in ("0131-a.md", "0132-b.md", "0133-c.md"):
        assert basename in prompt

    # Attribution line is in the prompt
    assert adr_draft.ADR_ATTRIBUTION in prompt

    # Target path appears in the prompt
    assert "ibl5/docs/decisions/0134-wt-slug.md" in prompt


def test_draft_uses_opus_with_bash_and_edit_denied(repo, tmp_path, monkeypatch):
    """call_tooled receives model='opus', correct allowed/denied tools, max_turns."""
    wt = repo["wt"]
    git = repo["git"]
    fake_scripts(monkeypatch)
    llm = WritingLlm(lambda rel: valid_adr("0134"))

    adr_draft.draft(
        llm, git, str(wt), str(tmp_path / "out"), _noop_log,
        phase="phase2", today="2026-09-20",
    )

    assert llm.calls, "call_tooled was never called"
    purpose, model, prompt, kwargs = llm.calls[0]
    assert model == "opus"
    assert kwargs["allowed_tools"] == adr_draft.ADR_DRAFT_ALLOWED_TOOLS
    assert "Bash" in kwargs["denied_tools"]
    assert "Edit" in kwargs["denied_tools"]
    assert kwargs["max_turns"] == adr_draft.ADR_DRAFT_MAX_TURNS


def test_draft_rejects_missing_attribution_and_reverts_tree(
        repo, tmp_path, monkeypatch):
    """Draft without attribution raises adr-draft-invalid; file gone, tree clean."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    fake_scripts(monkeypatch)

    def _no_attribution(rel):
        # Valid frontmatter but body has no attribution line
        return (
            "---\n"
            "description: missing attribution\n"
            "last_verified: 2026-09-20\n"
            "---\n"
            "# ADR-0134: Something\n\n"
            "## Context\n\nContext text.\n\n"
            "## Decision\n\nDecision text.\n\n"
            "## Alternatives Considered\n\n"
            "- **Alt 1** -- x. Rejected because: y.\n"
            "- **Alt 2** -- a. Rejected because: b.\n\n"
            "## Consequences\n\n- Positive: good.\n\n"
            "## References\n\n- none\n" * 10  # pad to >600 bytes
        )

    head_before = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(_no_attribution),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-invalid"

    # Draft target is absent
    assert not os.path.exists(str(wt / "ibl5" / "docs" / "decisions" / "0134-wt-slug.md"))

    # Rejected copy is saved in out_dir
    assert os.path.exists(os.path.join(out_dir, adr_draft.REJECTED_DRAFT_NAME))

    # Tree is clean
    status = subprocess.run(
        ["git", "-C", str(wt), "status", "--porcelain"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert status == ""

    # No new commit
    head_after = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert head_before == head_after


def test_draft_rejects_no_adr_marker(repo, tmp_path, monkeypatch):
    """Draft containing 'no-adr' raises adr-draft-invalid."""
    wt = repo["wt"]
    git = repo["git"]
    fake_scripts(monkeypatch)

    def _with_no_adr(rel):
        body = valid_adr("0134")
        return body + "<!-- no-adr: bypass -->\n"

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(_with_no_adr),
            git, str(wt), str(tmp_path / "out"), _noop_log,
            phase="phase2", today="2026-09-20",
        )
    assert exc_info.value.kind == "adr-draft-invalid"
    assert "no-adr" in (exc_info.value.detail or "")


def test_draft_rejects_template_leftovers(repo, tmp_path, monkeypatch):
    """Draft keeping '<Title>' raises adr-draft-invalid."""
    wt = repo["wt"]
    git = repo["git"]
    fake_scripts(monkeypatch)

    def _with_leftover(rel):
        body = valid_adr("0134")
        return body.replace("Automated Decision for Test Coverage", "<Title>")

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(_with_leftover),
            git, str(wt), str(tmp_path / "out"), _noop_log,
            phase="phase2", today="2026-09-20",
        )
    assert exc_info.value.kind == "adr-draft-invalid"
    assert "<Title>" in (exc_info.value.detail or "")


def test_draft_rejects_stray_edits_outside_decisions_dir(repo, tmp_path, monkeypatch):
    """Drafter writing outside decisions dir raises adr-draft-scope; strays cleaned."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    fake_scripts(monkeypatch)

    def _stray_content(rel):
        # Side effects: write a stray file and append to an existing ADR stub
        (wt / "bin" / "stray.sh").write_text("#!/bin/bash\nstray\n")
        stub = wt / "ibl5" / "docs" / "decisions" / "0133-c.md"
        with open(str(stub), "a") as fh:
            fh.write("stray content appended by LLM\n")
        return valid_adr("0134")

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(_stray_content),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )
    assert exc_info.value.kind == "adr-draft-scope"

    # Stray file is gone
    assert not os.path.exists(str(wt / "bin" / "stray.sh"))

    # 0133-c.md is restored
    stub_content = (wt / "ibl5" / "docs" / "decisions" / "0133-c.md").read_text()
    assert "stray content appended" not in stub_content

    # Tree is clean
    status = subprocess.run(
        ["git", "-C", str(wt), "status", "--porcelain"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert status == ""

    # No new commit
    head = subprocess.run(
        ["git", "-C", str(wt), "log", "--oneline"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert head.count("\n") == 0  # only the init commit


def test_draft_check_prose_failure_is_terminal_and_not_committed(
        repo, tmp_path, monkeypatch):
    """check-prose failure raises adr-draft-gate; HEAD unchanged; rejected copy saved."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    fake_scripts(monkeypatch, overrides={
        "check-prose": lambda wt, rel, *a, stdin="": (1, "em-dash tell"),
    })

    head_before = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-gate"
    assert "check-prose" in (exc_info.value.detail or "")

    head_after = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert head_before == head_after

    assert os.path.exists(os.path.join(out_dir, adr_draft.REJECTED_DRAFT_NAME))


def test_draft_check_docs_failure_is_terminal(repo, tmp_path, monkeypatch):
    """check-docs failure raises adr-draft-gate; rejected copy saved."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    fake_scripts(monkeypatch, overrides={
        "check-docs": lambda wt, rel, *a, stdin="": (1, "stale doc: foo.md"),
    })

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-gate"
    assert "check-docs" in (exc_info.value.detail or "")
    assert os.path.exists(os.path.join(out_dir, adr_draft.REJECTED_DRAFT_NAME))


def test_draft_adr_check_still_failing_after_commit_keeps_commit(
        repo, tmp_path, monkeypatch):
    """adr-check still failing after commit: raises adr-draft-gate; HEAD moved."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    # adr-check always fails (both pre- and post-commit calls)
    fake_scripts(monkeypatch, overrides={
        "adr-check": lambda wt, rel, *a, stdin="": (
            1,
            "Decision-trigger surfaces detected:\n"
            "  - [new-tool-script] bin/x\nFAIL: still failing",
        ),
    })

    head_before = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-gate"
    detail = exc_info.value.detail or ""
    # Detail format: "{rel}|{sha}|adr-check still fails: ..."
    assert detail.startswith("ibl5/docs/decisions/0134-wt-slug.md|")
    parts = detail.split("|", 2)
    assert len(parts[1]) == 40, "expected sha in middle field"

    # HEAD moved by one commit (the ADR commit stays local)
    head_after = subprocess.run(
        ["git", "-C", str(wt), "rev-parse", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert head_before != head_after


def test_draft_llm_error_discards_and_reraises_kind(repo, tmp_path, monkeypatch):
    """LLM error propagates unchanged; target absent; rejected copy saved."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    fake_scripts(monkeypatch)

    class _ErrorLlm:
        calls = []

        def call_tooled(self, purpose, model, prompt, **kwargs):
            raise HarnessError("llm-tooled-degraded", "x")

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            _ErrorLlm(), git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "llm-tooled-degraded"
    assert not os.path.exists(
        str(wt / "ibl5" / "docs" / "decisions" / "0134-wt-slug.md")
    )
    assert os.path.exists(os.path.join(out_dir, adr_draft.REJECTED_DRAFT_NAME))


# ---------------------------------------------------------------------------
# Collision tests
# ---------------------------------------------------------------------------

def test_collision_renumbers_before_draft(repo, tmp_path, monkeypatch):
    """Numbering collision on 0134 causes rename to 0135; prompt names ADR-0135."""
    wt = repo["wt"]
    origin = repo["origin"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    # Add 0134-other.md to master (and push so origin/master has it)
    subprocess.run(["git", "-C", str(wt), "checkout", "master"],
                   check=True, capture_output=True)
    (wt / "ibl5" / "docs" / "decisions" / "0134-other.md").write_text(
        "# 0134-other\nstub\n"
    )
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(["git", "-C", str(wt), "commit", "-m", "add 0134-other"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "push", "origin", "master"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "checkout", "wt-slug"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "update-ref",
                    "refs/remotes/origin/master", "master"], check=True)

    numbering_calls = []

    def _check_numbering_collision(wt_path, rel, *args, stdin=""):
        call_idx = len(numbering_calls)
        numbering_calls.append(args)
        if call_idx == 0:
            return (
                1,
                "\nNumbering collision:\n"
                "  0134: ibl5/docs/decisions/0134-wt-slug.md, "
                "ibl5/docs/decisions/0134-other.md (already on base)\n",
            )
        return (0, "")

    logged = []
    fake_scripts(monkeypatch, overrides={"check-numbering": _check_numbering_collision})

    # content_fn extracts the number from the target rel (may be 0135)
    def _content(rel):
        num = os.path.basename(rel)[:4]
        return valid_adr(num)

    result = adr_draft.draft(
        WritingLlm(_content), git, str(wt), out_dir, logged.append,
        phase="phase2", today="2026-09-20",
    )

    assert result.path == "ibl5/docs/decisions/0135-wt-slug.md"
    assert any("renumbered to" in line for line in logged)


def test_collision_on_foreign_number_is_terminal(repo, tmp_path, monkeypatch):
    """Collision on a number that is not the draft's own raises adr-draft-gate."""
    wt = repo["wt"]
    git = repo["git"]

    def _foreign_collision(wt_path, rel, *args, stdin=""):
        return (
            1,
            "\nNumbering collision:\n"
            "  0132: ibl5/docs/decisions/0132-b.md, "
            "ibl5/docs/decisions/0132-dup.md\n",
        )

    fake_scripts(monkeypatch, overrides={"check-numbering": _foreign_collision})

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, str(wt), str(tmp_path / "out"), _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-gate"

    head_count = subprocess.run(
        ["git", "-C", str(wt), "rev-list", "--count", "HEAD"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    assert head_count == "1"  # only the init commit; draft not committed


def test_collision_surviving_one_rename_is_terminal(repo, tmp_path, monkeypatch):
    """Collision persisting after rename raises adr-draft-gate."""
    wt = repo["wt"]
    git = repo["git"]

    def _always_collides(wt_path, rel, *args, stdin=""):
        return (
            1,
            "\nNumbering collision:\n"
            "  0134: ibl5/docs/decisions/0134-wt-slug.md, "
            "ibl5/docs/decisions/0134-other.md\n",
        )

    fake_scripts(monkeypatch, overrides={"check-numbering": _always_collides})

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, str(wt), str(tmp_path / "out"), _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-gate"


# ===========================================================================
# Tests: runner._push_with_adr_draft() wrapper
# ===========================================================================

def test_push_wrapper_drafts_once_and_repushes(tmp_path, monkeypatch):
    """One ADR denial: wrapper drafts, records result, re-pushes successfully."""
    git = FakeGit(push_errors=[HarnessError("local-gate", _ADR_TEXT), None])
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: adr_draft.AdrDraftResult(
            "ibl5/docs/decisions/0134-x.md", "0134", "claude-opus-5-5", "b" * 40
        ),
    )
    res = RunResult(terminal=TerminalState.FAILED)
    logged = []

    sha = runner._push_with_adr_draft(
        git, logged.append, "phase2",
        llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
    )

    assert sha  # returns a sha
    assert git.pushes == 2
    assert res.adr_drafted is True
    assert res.adr_path == "ibl5/docs/decisions/0134-x.md"
    assert res.adr_draft_model is not None
    assert any("re-pushing once" in line for line in logged)


def test_push_wrapper_never_drafts_on_stale_base(tmp_path, monkeypatch):
    """Three stale-base push errors exhaust the cap; draft never called."""
    stale = HarnessError("local-gate", _STALE_BASE_TEXT)
    git = FakeGit(push_errors=[stale, stale, stale])
    draft_calls = []
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: draft_calls.append(1),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_adr_draft(
            git, _noop_log, "phase5.5",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    assert exc_info.value.kind == "push-retry-cap"
    assert not draft_calls


def test_push_wrapper_second_denial_exits_3_and_names_path(tmp_path, monkeypatch):
    """Second ADR denial re-raises local-gate; verdict names the drafted path."""
    adr = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr, adr])
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: adr_draft.AdrDraftResult(
            "ibl5/docs/decisions/0134-x.md", "0134", "claude-opus-5-5", "b" * 40
        ),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_adr_draft(
            git, _noop_log, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    exc = exc_info.value
    # Simulate what runner.run() does when it catches the exception
    res.error_kind = exc.kind
    res.error = f"{exc.kind}: {exc.detail}"

    assert runner.exit_code_for(res) == 3

    line = runner.verdict_line(res, 3)
    assert "0134-x.md" in line
    assert "committed locally" in line
    assert "Write the ADR for" not in line


def test_push_wrapper_draft_failure_keeps_local_gate_kind(tmp_path, monkeypatch):
    """Draft LLM failure: wrapper re-raises original local-gate; adr_drafted stays False."""
    adr = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr])
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: (_ for _ in ()).throw(HarnessError("llm-tooled-degraded", "x")),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_adr_draft(
            git, _noop_log, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    assert exc_info.value.kind == "local-gate"
    assert _ADR_TEXT in (exc_info.value.detail or "")
    assert res.adr_drafted is False

    res.error_kind = exc_info.value.kind
    res.error = f"{exc_info.value.kind}: {exc_info.value.detail}"
    assert runner.exit_code_for(res) == 3


def test_push_wrapper_gate_failure_after_commit_records_path(tmp_path, monkeypatch):
    """Draft raises adr-draft-gate with a pipe-delimited detail: wrapper records path."""
    adr = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr])
    sha = "c" * 40
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: (_ for _ in ()).throw(
            HarnessError(
                "adr-draft-gate",
                f"ibl5/docs/decisions/0134-x.md|{sha}|adr-check still fails",
            )
        ),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_adr_draft(
            git, _noop_log, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    assert exc_info.value.kind == "local-gate"
    assert res.adr_drafted is True
    assert res.adr_path == "ibl5/docs/decisions/0134-x.md"


def test_push_wrapper_one_draft_per_run(tmp_path, monkeypatch):
    """res.adr_drafted=True before call: wrapper refuses a second draft."""
    adr = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr])
    draft_calls = []
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: draft_calls.append(1),
    )
    res = RunResult(terminal=TerminalState.FAILED)
    res.adr_drafted = True
    res.adr_path = "ibl5/docs/decisions/0133-prev.md"
    logged = []

    with pytest.raises(HarnessError):
        runner._push_with_adr_draft(
            git, logged.append, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    assert not draft_calls
    assert any("after this run's one draft" in line for line in logged)


def test_push_wrapper_skips_without_worktree(tmp_path, monkeypatch):
    """worktree=None: wrapper re-raises the denial; draft never called."""
    adr = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr])
    draft_calls = []
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: draft_calls.append(1),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError):
        runner._push_with_adr_draft(
            git, _noop_log, "phase2",
            llm=None, worktree=None, out_dir=str(tmp_path), res=res,
        )

    assert not draft_calls


def test_push_wrapper_passes_non_adr_denials_through(tmp_path, monkeypatch):
    """Byte-budget denial is not classified as 'adr'; raises unchanged; draft never called."""
    byte_budget_text = (
        "Trim the rule(s) above to stay under the byte budget.\n"
        ".claude/rules byte budget exceeded"
    )
    git = FakeGit(push_errors=[HarnessError("local-gate", byte_budget_text)])
    draft_calls = []
    monkeypatch.setattr(
        runner.adr_draft, "draft",
        lambda *a, **k: draft_calls.append(1),
    )
    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_adr_draft(
            git, _noop_log, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    assert exc_info.value.kind == "local-gate"
    assert not draft_calls


# ===========================================================================
# Tests: push call sites
# ===========================================================================

_PR_PHASE55 = 9920
_TREE_PHASE55 = "a" * 40


def test_phase55_push_site_calls_adr_wrapper(tmp_path, monkeypatch):
    """_run_fidelity passes 'phase5.5' and res= to _push_with_adr_draft."""
    _install_git_shim(tmp_path, monkeypatch)

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nREADY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _StaleBaseGit({
        "slug": "demo",
        "worktree_diff": "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": [_TREE_PHASE55],
    })
    gh = RecordingGh(str(tmp_path))
    res = _Res()

    push_calls = []

    def _recorder(g, log, phase, *, llm, worktree, out_dir, res, pr=None):
        push_calls.append({"phase": phase, "res": res})
        return "recorded" + "0" * 33

    monkeypatch.setattr(runner, "_push_with_adr_draft", _recorder)

    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff --git a/x b/x\n", "body", _PR_PHASE55, "dead" * 10,
            _TREE_PHASE55, False, _noop_log, res,
        )
    finally:
        _cleanup(_PR_PHASE55, f"{_PR_PHASE55}-2")

    assert push_calls, "_push_with_adr_draft was never called from _run_fidelity"
    assert push_calls[0]["phase"] == "phase5.5"
    assert push_calls[0]["res"] is res


def test_phase2_push_site_calls_adr_wrapper(monkeypatch, stub_ambient_git_show):
    """runner.run() replay mode passes 'phase2' and res= to _push_with_adr_draft."""
    push_calls = []

    def _recorder(g, log, phase, *, llm, worktree, out_dir, res, pr=None):
        push_calls.append({"phase": phase, "res": res})
        return ""

    monkeypatch.setattr(runner, "_push_with_adr_draft", _recorder)

    out = tempfile.mkdtemp(prefix="postplan-test-adr-phase2-")
    llm = FixtureLlm(UsageLedger(), _INLINE_CANNED)
    fx = _inline_fixture()
    res = runner.run(fx, out, llm, mode="replay", headless=True)

    assert push_calls, "_push_with_adr_draft was never called from runner.run phase2"
    assert push_calls[0]["phase"] == "phase2"
    assert push_calls[0]["res"] is res


# ===========================================================================
# Tests: RunResult serialisation, remedy text, exit code
# ===========================================================================

def test_result_json_carries_adr_fields():
    """RunResult.to_json() includes adr_drafted=False, adr_path=None, adr_draft_model=None."""
    blob = json.loads(RunResult(terminal=TerminalState.FAILED).to_json())
    assert blob["adr_drafted"] is False
    assert blob["adr_path"] is None
    assert blob["adr_draft_model"] is None


def test_gate_remedy_adr_names_one_draft_attempt():
    """_GATE_REMEDY['adr'] references the one-draft-attempt bound."""
    assert "one ADR draft attempt" in runner._GATE_REMEDY["adr"]


# ===========================================================================
# End-to-end: real git hook, real draft, real push
# ===========================================================================

_HOOK_SCRIPT = """\
#!/usr/bin/env bash
# Exit 0 if the diff vs origin/master already contains a numbered ADR.
if git diff --name-only origin/master...HEAD | grep -q '^ibl5/docs/decisions/[0-9]'; then
    exit 0
fi
echo "git push: pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR." >&2
echo "Resolve with ONE of:" >&2
echo '  1. Add an ADR under ibl5/docs/decisions/ (run: bin/next-adr "kebab-title").' >&2
exit 1
"""


def test_end_to_end_real_git_denying_hook_drafts_and_pushes(
        repo, tmp_path, monkeypatch):
    """Full round-trip: real pre-push hook denies, wrapper drafts, commits, re-pushes."""
    wt = repo["wt"]
    origin = repo["origin"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    os.makedirs(out_dir, exist_ok=True)

    # Install the pre-push hook
    hooks_dir = wt / ".git" / "hooks"
    hook_path = hooks_dir / "pre-push"
    hook_path.write_text(_HOOK_SCRIPT)
    hook_path.chmod(hook_path.stat().st_mode | stat.S_IEXEC)

    # Commit a new bin/ script on wt-slug to trigger the hook
    (wt / "bin" / "x").write_text("#!/bin/bash\necho decision-trigger\n")
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(["git", "-C", str(wt), "commit", "-m", "add bin/x"],
                   check=True, capture_output=True)

    fake_scripts(monkeypatch)
    res = RunResult(terminal=TerminalState.FAILED)
    logged = []

    sha = runner._push_with_adr_draft(
        git, logged.append, "phase2",
        llm=WritingLlm(lambda rel: valid_adr(os.path.basename(rel)[:4])),
        worktree=str(wt),
        out_dir=out_dir,
        res=res,
    )

    assert sha, "expected a SHA from the successful re-push"

    # Origin's wt-slug branch has the ADR commit as its tip
    tip_subject = subprocess.run(
        ["git", "-C", str(origin), "log", "-1", "--format=%s", "wt-slug"],
        capture_output=True, text=True, check=True,
    ).stdout.strip()
    expected = adr_draft.ADR_COMMIT_MSG.format(number="0134", slug="wt-slug")
    assert tip_subject == expected, f"tip={tip_subject!r} expected={expected!r}"

    assert res.adr_drafted is True

    # The pushed tree contains the ADR file
    tree_files = subprocess.run(
        ["git", "-C", str(origin), "ls-tree", "--name-only", "wt-slug",
         "ibl5/docs/decisions/"],
        capture_output=True, text=True, check=True,
    ).stdout
    assert "0134-wt-slug.md" in tree_files


# ===========================================================================
# Tests: runner._commit_with_adr_draft() (commit-site ADR gate)
# ===========================================================================

# ---------------------------------------------------------------------------
# Helpers for commit-site tests
# ---------------------------------------------------------------------------


def _make_commit_site_adr_check(call2_rc=0):
    """Return (handler, calls_list) for the 3-call commit-site adr-check sequence.

    | call | caller                 | returns                                    |
    |------|------------------------|--------------------------------------------|
    |  0   | commit_gate()          | (1, body with no SURFACES_MARKER)          |
    |  1   | probe inside draft()   | (1, body WITH SURFACES_MARKER)             |
    |  2   | post-draft re-validate | (call2_rc, "")                             |
    """
    calls = []

    def _handler(worktree, rel, *args, stdin=""):
        idx = len(calls)
        calls.append((args, stdin))
        if idx == 0:
            return (1, "pre-commit-adr-gate: decision trigger detected")
        if idx == 1:
            return (
                1,
                "Decision-trigger surfaces detected:\n"
                "  - [new-tool-script] bin/x - new bin/ helper\n"
                "FAIL: ADR required",
            )
        return (call2_rc, "")

    return _handler, calls


def _build_mini_repo(root, name):
    """Build a minimal git repo at root/name with the same shape as the repo fixture."""
    wt = root / name
    wt.mkdir()
    subprocess.run(["git", "init", str(wt)], check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "symbolic-ref", "HEAD",
                    "refs/heads/master"], check=True)
    subprocess.run(["git", "-C", str(wt), "config", "user.email",
                    "test@example.com"], check=True)
    subprocess.run(["git", "-C", str(wt), "config", "user.name", "Test"], check=True)
    adr_dir = wt / "ibl5" / "docs" / "decisions"
    adr_dir.mkdir(parents=True)
    shutil.copy(_TEMPLATE_SRC, str(adr_dir / "0000-template.md"))
    for n in ("0131-a.md", "0132-b.md", "0133-c.md"):
        (adr_dir / n).write_text(f"# {n}\nstub\n")
    (wt / "bin").mkdir()
    (wt / "bin" / ".keep").write_text("")
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(["git", "-C", str(wt), "commit", "-m", "init"],
                   check=True, capture_output=True)
    origin = root / f"{name}.git"
    subprocess.run(["git", "init", "--bare", str(origin)],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "remote", "add", "origin", str(origin)],
                   check=True)
    subprocess.run(["git", "-C", str(wt), "push", "origin", "master"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "checkout", "-b", "wt-slug"],
                   check=True, capture_output=True)
    subprocess.run(["git", "-C", str(wt), "update-ref",
                    "refs/remotes/origin/master", "master"], check=True)
    return wt, LiveGit(str(wt), push_remote="origin")


# ---------------------------------------------------------------------------
# Commit-site golden path
# ---------------------------------------------------------------------------


def test_commit_site_draft_lands_in_phase2_commit(repo, tmp_path, monkeypatch):
    """Commit-site gate denies, draft stages the ADR (commit=False); exactly one
    commit_all call is made for the whole phase, with the PR subject (not
    ADR_COMMIT_MSG), and the ADR path is already in the index at that moment."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    handler, _ = _make_commit_site_adr_check()
    fake_scripts(monkeypatch, overrides={"adr-check": handler})

    # Intercept commit_all to record staged files and message at call time
    commit_calls = []
    staged_at_commit = []
    _real_commit_all = git.commit_all

    def _tracking_commit_all(message):
        result = subprocess.run(
            ["git", "-C", str(wt), "diff", "--cached", "--name-only"],
            capture_output=True, text=True,
        )
        staged_at_commit.append(result.stdout.strip().splitlines())
        commit_calls.append(message)
        return _real_commit_all(message)

    git.commit_all = _tracking_commit_all

    res = RunResult(terminal=TerminalState.FAILED)

    runner._commit_with_adr_draft(
        git, _noop_log, "phase2",
        llm=WritingLlm(lambda rel: valid_adr("0134")),
        worktree=str(wt), out_dir=out_dir, res=res,
    )

    # _commit_with_adr_draft must not commit (draft runs with commit=False)
    assert commit_calls == [], \
        "ADR must be staged, not committed, inside _commit_with_adr_draft"

    # The ADR is already in the index before the Phase 2 commit
    staged_before = subprocess.run(
        ["git", "-C", str(wt), "diff", "--cached", "--name-only"],
        capture_output=True, text=True,
    ).stdout.strip().splitlines()
    assert res.adr_path in staged_before

    # Simulate the single Phase 2 commit (_commit_with_gate_remediation would make)
    pr_subject = "chore: implement new decision-trigger tool"
    git.commit_all(pr_subject)

    # Exactly one call, with the PR subject, not the ADR_COMMIT_MSG
    assert len(commit_calls) == 1
    assert commit_calls[0] == pr_subject
    assert "docs: add ADR" not in commit_calls[0]

    # ADR path was staged at the moment of that call
    assert res.adr_path in staged_at_commit[0]

    # RunResult fields
    assert res.adr_drafted is True
    assert res.adr_path is not None
    assert res.adr_draft_model is not None


# ---------------------------------------------------------------------------
# Commit-site draft failure
# ---------------------------------------------------------------------------


def test_commit_site_draft_failure_reraises_original_denial(tmp_path, monkeypatch):
    """adr_draft.draft raising adr-draft-invalid is wrapped in the synthesised
    local-gate denial; classify_local_gate_denial reads 'adr'; exit code = 3."""
    monkeypatch.setattr(
        adr_draft, "commit_gate",
        lambda wt, base="origin/master": (1, "adr gate denied"),
    )
    monkeypatch.setattr(
        adr_draft, "draft",
        lambda *a, **k: (_ for _ in ()).throw(
            HarnessError("adr-draft-invalid",
                         "first body line is not the harness attribution")
        ),
    )

    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._commit_with_adr_draft(
            FakeGit(), _noop_log, "phase2",
            llm=None, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
        )

    exc = exc_info.value
    assert exc.kind == "local-gate"
    assert "pre-commit-adr-gate:" in (exc.detail or "")
    assert classify_local_gate_denial(exc.detail or "") == "adr"

    res.error_kind = exc.kind
    res.error = f"{exc.kind}: {exc.detail}"
    assert runner.exit_code_for(res) == 3


# ---------------------------------------------------------------------------
# Commit-site revalidation failure: sha is empty in stage-only mode
# ---------------------------------------------------------------------------


def test_commit_site_revalidation_failure_records_path_with_empty_sha(
        repo, tmp_path, monkeypatch):
    """When post-draft adr-check still fails the inner adr-draft-gate detail has
    {rel}|| (sha='' because commit=False); res.adr_path recorded; outer = local-gate;
    exit code = 3."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")
    rel = "ibl5/docs/decisions/0134-wt-slug.md"

    handler, _ = _make_commit_site_adr_check(call2_rc=1)
    fake_scripts(monkeypatch, overrides={"adr-check": handler})

    res = RunResult(terminal=TerminalState.FAILED)

    with pytest.raises(HarnessError) as exc_info:
        runner._commit_with_adr_draft(
            git, _noop_log, "phase2",
            llm=WritingLlm(lambda r: valid_adr("0134")),
            worktree=str(wt), out_dir=out_dir, res=res,
        )

    exc = exc_info.value
    assert exc.kind == "local-gate"
    assert "pre-commit-adr-gate:" in (exc.detail or "")

    # Inner exception: sha is "" because commit=False, separator pair is adjacent
    inner = exc.__cause__
    assert inner is not None and inner.kind == "adr-draft-gate"
    assert (inner.detail or "").startswith(f"{rel}||adr-check still fails: ")

    # Path recorded despite no commit
    assert res.adr_path == rel

    res.error_kind = exc.kind
    res.error = f"{exc.kind}: {exc.detail}"
    assert runner.exit_code_for(res) == 3


# ---------------------------------------------------------------------------
# One-draft-per-run guard across commit and push sites
# ---------------------------------------------------------------------------


def test_one_opus_call_per_run_across_commit_and_push_sites(
        repo, tmp_path, monkeypatch):
    """Commit-site draft consumes the one-draft token; the phase-5.5 push-site guard
    short-circuits without a second LLM call; total call_tooled count = 1."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    handler, _ = _make_commit_site_adr_check()
    fake_scripts(monkeypatch, overrides={"adr-check": handler})

    llm = WritingLlm(lambda rel: valid_adr("0134"))
    res = RunResult(terminal=TerminalState.FAILED)

    # Commit site: one LLM call, res.adr_drafted set to True
    runner._commit_with_adr_draft(
        git, _noop_log, "phase2",
        llm=llm, worktree=str(wt), out_dir=out_dir, res=res,
    )
    assert res.adr_drafted is True
    assert len(llm.calls) == 1

    # Push site: guard fires before attempting a second draft
    adr_denial = HarnessError("local-gate", _ADR_TEXT)
    push_git = FakeGit(push_errors=[adr_denial])
    logged = []

    with pytest.raises(HarnessError):
        runner._push_with_adr_draft(
            push_git, logged.append, "phase5.5",
            llm=llm, worktree=str(wt), out_dir=out_dir, res=res,
        )

    assert len(llm.calls) == 1, "second LLM call must not happen"
    assert any("after this run's one draft" in line for line in logged)


# ---------------------------------------------------------------------------
# Commit gate pass: drafter never entered
# ---------------------------------------------------------------------------


def test_commit_gate_pass_skips_drafter(tmp_path, monkeypatch):
    """commit_gate returning rc=0 means no ADR needed; drafter and LLM never called."""
    monkeypatch.setattr(
        adr_draft, "commit_gate",
        lambda wt, base="origin/master": (0, ""),
    )
    draft_calls = []
    monkeypatch.setattr(
        adr_draft, "draft",
        lambda *a, **k: draft_calls.append(1),
    )
    llm = WritingLlm(lambda rel: valid_adr("0134"))
    res = RunResult(terminal=TerminalState.FAILED)

    runner._commit_with_adr_draft(
        FakeGit(), _noop_log, "phase2",
        llm=llm, worktree="/fake/wt", out_dir=str(tmp_path), res=res,
    )

    assert not llm.calls, "LLM must not be called when commit gate passes"
    assert not draft_calls, "draft must not be called when commit gate passes"
    assert res.adr_drafted is False


# ---------------------------------------------------------------------------
# No-op when worktree is None (replay shape)
# ---------------------------------------------------------------------------


def test_commit_site_noop_without_worktree(tmp_path, monkeypatch):
    """worktree=None (replay shape): returns immediately without touching adr-check."""
    run_script_calls = []

    def _sentinel(*a, **k):
        run_script_calls.append(a)
        return (0, "")

    monkeypatch.setattr(adr_draft, "_run_script", _sentinel)
    res = RunResult(terminal=TerminalState.FAILED)

    runner._commit_with_adr_draft(
        FakeGit(), _noop_log, "phase2",
        llm=None, worktree=None, out_dir=str(tmp_path), res=res,
    )

    assert not run_script_calls, "adr-check must not run when worktree is None"
    assert res.adr_drafted is False


# ---------------------------------------------------------------------------
# commit_gate stdin and args
# ---------------------------------------------------------------------------


def test_commit_gate_pipes_branch_commit_messages(repo, monkeypatch):
    """commit_gate passes git log --format=%B as stdin; empty base..HEAD passes ''."""
    wt = repo["wt"]

    recorded = []

    def _recording(wt_path, rel, *args, stdin=""):
        recorded.append({"rel": rel, "args": args, "stdin": stdin})
        return (1, "FAIL")

    monkeypatch.setattr(adr_draft, "_run_script", _recording)

    # Part 1: empty range (wt-slug at origin/master, no new commits) -> stdin = ""
    adr_draft.commit_gate(str(wt))

    assert len(recorded) == 1, "gate must evaluate even on an empty range"
    assert recorded[0]["stdin"] == ""

    # Part 2: add a commit so the range is non-empty
    recorded.clear()
    (wt / "bin" / "z").write_text("#!/bin/bash\necho decision\n")
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    subprocess.run(
        ["git", "-C", str(wt), "commit", "-m", "add bin/z: decision trigger"],
        check=True, capture_output=True,
    )

    adr_draft.commit_gate(str(wt))

    assert len(recorded) == 1
    call = recorded[0]
    assert (call["rel"],) + call["args"] == (
        "bin/adr-check", "--commit", "--bypass-from-stdin", "--base=origin/master",
    )
    assert "add bin/z: decision trigger" in call["stdin"]


# ---------------------------------------------------------------------------
# check-docs gate skipped at commit site, present at push site
# ---------------------------------------------------------------------------


def test_commit_site_skips_check_docs_since_gate(tmp_path, monkeypatch):
    """draft(check_mode='commit') omits check-docs; draft(check_mode='pr') includes it."""

    def _run_and_collect(wt_str, git, out_dir_str, mode):
        gate_calls = []
        adr_check_idx = [0]

        def _dispatch(wt_path, rel, *args, stdin=""):
            name = os.path.basename(rel)
            gate_calls.append(name)
            if name == "adr-check":
                idx = adr_check_idx[0]
                adr_check_idx[0] += 1
                if idx == 0:
                    return (
                        1,
                        "Decision-trigger surfaces detected:\n"
                        "  - [new-tool-script] bin/x\nFAIL: ADR required",
                    )
                return (0, "")
            if name == "next-adr":
                slug = args[0] if args else "wt-slug"
                decisions = os.path.join(wt_path, "ibl5", "docs", "decisions")
                dest = os.path.join(decisions, f"0134-{slug}.md")
                shutil.copy(os.path.join(decisions, "0000-template.md"), dest)
                return (0, dest)
            return (0, "")

        monkeypatch.setattr(adr_draft, "_run_script", _dispatch)
        adr_draft.draft(
            WritingLlm(lambda rel: valid_adr("0134")),
            git, wt_str, out_dir_str, _noop_log,
            phase="phase2", today="2026-09-20", check_mode=mode,
        )
        return gate_calls

    wt_c, git_c = _build_mini_repo(tmp_path, "commit-mode")
    commit_gates = _run_and_collect(
        str(wt_c), git_c, str(tmp_path / "out-commit"), "commit",
    )
    assert "check-numbering" in commit_gates
    assert "check-prose" in commit_gates
    assert "check-docs" not in commit_gates, \
        "check-docs must be skipped in commit mode"

    wt_p, git_p = _build_mini_repo(tmp_path, "pr-mode")
    pr_gates = _run_and_collect(
        str(wt_p), git_p, str(tmp_path / "out-pr"), "pr",
    )
    assert "check-numbering" in pr_gates
    assert "check-prose" in pr_gates
    assert "check-docs" in pr_gates, \
        "check-docs must be called in pr mode"


# ---------------------------------------------------------------------------
# Stray check: baseline filtering
# ---------------------------------------------------------------------------


def test_stray_check_ignores_the_runs_own_staged_work(repo, tmp_path, monkeypatch):
    """Paths staged before the drafter runs (same porcelain code after) are not strays;
    draft() succeeds and _discard is never called."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    # Stage several run files to populate the pre-draft baseline
    (wt / "bin" / "run-tool.sh").write_text("#!/bin/bash\necho run\n")
    (wt / "bin" / "run-config.json").write_text('{"v": 1}')
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)
    # Leave uncommitted: baseline status "A " for each

    discard_calls = []
    _orig_discard = adr_draft._discard

    def _tracking_discard(*a, **k):
        discard_calls.append(k.get("strays", ()))
        _orig_discard(*a, **k)

    monkeypatch.setattr(adr_draft, "_discard", _tracking_discard)
    fake_scripts(monkeypatch)

    result = adr_draft.draft(
        WritingLlm(lambda rel: valid_adr("0134")),
        git, str(wt), out_dir, _noop_log,
        phase="phase2", today="2026-09-20",
    )

    assert result.path.endswith("0134-wt-slug.md")
    assert not discard_calls, \
        "_discard must not be called when all extra paths are in the baseline"


def test_stray_check_reverts_only_paths_absent_from_baseline(repo, tmp_path, monkeypatch):
    """_discard receives only paths absent from the pre-draft baseline; no baseline path
    is included, preventing a work-destroying revert of the run's own staged output."""
    wt = repo["wt"]
    git = repo["git"]
    out_dir = str(tmp_path / "out")

    # Stage one baseline file before the draft runs
    (wt / "bin" / "harness-output.log").write_text("run log\n")
    subprocess.run(["git", "-C", str(wt), "add", "-A"], check=True)

    discard_calls = []
    _orig_discard = adr_draft._discard

    def _tracking_discard(wt_path, rel, out_dir_path, log, phase, reason, strays=()):
        discard_calls.append(list(strays))
        _orig_discard(wt_path, rel, out_dir_path, log, phase, reason, strays=strays)

    monkeypatch.setattr(adr_draft, "_discard", _tracking_discard)

    def _evil_content(rel):
        # Side-effect: write a source file outside the decisions directory
        os.makedirs(os.path.join(str(wt), "src"), exist_ok=True)
        with open(os.path.join(str(wt), "src", "evil.py"), "w") as fh:
            fh.write("# injected by drafter\n")
        return valid_adr("0134")

    fake_scripts(monkeypatch)

    with pytest.raises(HarnessError) as exc_info:
        adr_draft.draft(
            WritingLlm(_evil_content),
            git, str(wt), out_dir, _noop_log,
            phase="phase2", today="2026-09-20",
        )

    assert exc_info.value.kind == "adr-draft-scope"
    assert discard_calls, "_discard must be called"
    strays_received = discard_calls[0]
    # git reports an untracked new directory as "src/" (directory-level), not the
    # individual file — either form proves the stray was detected.
    assert any(s.startswith("src") for s in strays_received), \
        f"expected src/evil.py stray but got {strays_received}"
    assert "bin/harness-output.log" not in strays_received


# ---------------------------------------------------------------------------
# classify_local_gate_denial: pre-commit marker
# ---------------------------------------------------------------------------


def test_classify_local_gate_denial_reads_pre_commit_marker():
    """Both pre-commit-adr-gate: and pre-push-adr-hook: classify as 'adr'."""
    assert (
        classify_local_gate_denial("phase2: pre-commit-adr-gate: bin/x added") == "adr"
    )
    assert (
        classify_local_gate_denial(
            "git push: pre-push-adr-hook: a decision-trigger surface"
        ) == "adr"
    )


def test_stage_all_precedes_the_commit_site_draft():
    """_commit_with_adr_draft reads the INDEX via `adr-check --commit`, so _run()
    must stage before calling it. If the stage_all() call ever moves below the
    draft call, the gate sees an empty index, returns 0, and the ADR is never
    drafted -- the exact case deliverable 3 exists for."""
    src = (pathlib.Path(runner.__file__)).read_text()
    stage = src.index("            git.stage_all()")
    draft = src.index("        _commit_with_adr_draft(git, log, \"phase2\"")
    assert stage < draft, "git.stage_all() must run before _commit_with_adr_draft"
