"""Local-gate fixer: path list, snapshot, guard, revert (Phases 1-2)."""
from __future__ import annotations

import os
import subprocess
import types
from pathlib import Path

import pytest

from harness import fidelity, gatefix, rules_budget_carveout

# --- Phase 1 -------------------------------------------------------------------------


def test_local_gate_prefixes_superset_of_gate_owning():
    for prefix in fidelity.GATE_OWNING_PREFIXES:
        assert prefix in fidelity.LOCAL_GATE_PATH_PREFIXES


@pytest.mark.parametrize("path", [
    "bin/pre-commit-hook",
    "bin/lib/pr-armable.sh",
    ".claude/settings.local.json",
    ".claude/skills/post-plan/_phase-6.5-arm-auto-merge.md",
    "tools/postplan-harness/runner.py",
    "tools/postplan-harness/harness/armable.py",
    ".githooks/pre-commit",
    "bin/check-prose",
    ".claude/rules/x.md",
    ".github/workflows/t.yml",
    "bin/test-postplan-arm-conditions",
    "bin/adr-check",
])
def test_denied_local_gate_edits_flags_each_gate_path(path):
    assert fidelity.denied_local_gate_edits([path]) == [path]


def test_denied_local_gate_edits_normalizes_dot_segments():
    paths = ["./bin/check-x", "ibl5/../bin/lib/y.sh"]
    assert fidelity.denied_local_gate_edits(paths) == paths


def test_denied_local_gate_edits_passes_ordinary_paths():
    paths = ["ibl5/classes/Foo.php", "bin/plan-now", ".claude/agents/x.md", "README.md"]
    assert fidelity.denied_local_gate_edits(paths) == []


# --- Phase 2 helpers -----------------------------------------------------------------


def _sh(cwd, *args, check=True):
    return subprocess.run(args, cwd=cwd, capture_output=True, text=True, check=check)


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "wt"
    root.mkdir()
    _sh(root, "git", "init", "-q", "-b", "master")
    _sh(root, "git", "config", "user.email", "t@example.com")
    _sh(root, "git", "config", "user.name", "T")
    (root / "a.txt").write_text("base a\n")
    (root / "gone.txt").write_text("base gone\n")
    (root / "ibl5").mkdir()
    (root / "ibl5/x.php").write_text("<?php // base\n")
    (root / "bin").mkdir()
    (root / "bin/lib").mkdir()
    (root / "bin/lib/old.sh").write_text("echo old\n")
    (root / ".claude/rules").mkdir(parents=True)
    (root / ".claude/rules/big.md").write_text("big rule\n" * 50)
    (root / ".claude/rules/other.md").write_text("other rule\n" * 5)
    (root / ".gitignore").write_text(".claude/settings.local.json\n")
    _sh(root, "git", "add", "-A")
    _sh(root, "git", "commit", "-q", "-m", "base")
    _sh(root, "git", "update-ref", "refs/remotes/origin/master", "HEAD")
    return root


@pytest.fixture
def home(tmp_path):
    h = tmp_path / "home"
    (h / ".claude/hooks").mkdir(parents=True)
    (h / ".claude/hooks/x.sh").write_text("#!/bin/sh\nexit 0\n")
    (h / ".claude/settings.json").write_text("{}\n")
    return h


def make_run(budget_rc=0, post_rc=0):
    """A `run` that fakes the repo's bin/check-* scripts and passes git through."""
    calls = []

    def _run(argv, **kw):
        if argv[0].startswith("bin/check-"):
            calls.append(list(argv))
            is_budget = argv[0] == rules_budget_carveout.BUDGET_SCRIPT
            rc = budget_rc if is_budget else post_rc
            if is_budget and calls.count(list(argv)) > 1:
                rc = post_rc
            return types.SimpleNamespace(returncode=rc, stdout="", stderr="boom")
        return subprocess.run(argv, **kw)

    _run.calls = calls
    return _run


def _snap(repo, home, run=None):
    return gatefix.snapshot(repo, home=home, run=run or make_run())


def _guard(repo, home, mutate, run=None):
    run = run or make_run()
    before = gatefix.snapshot(repo, home=home, run=run)
    mutate()
    after = gatefix.snapshot(repo, home=home, run=run)
    return gatefix.guard(repo, before, after, run=run), before, after


# --- Phase 2: snapshot ---------------------------------------------------------------


def test_snapshot_detects_edit_to_already_dirty_file(repo, home):
    (repo / "a.txt").write_text("dirty one\n")
    before = _snap(repo, home)
    (repo / "a.txt").write_text("dirty two\n")
    after = _snap(repo, home)
    assert ("M", "a.txt") in gatefix.changed_paths(repo, before, after)


def test_snapshot_leaves_real_index_untouched(repo, home):
    (repo / "a.txt").write_text("staged\n")
    _sh(repo, "git", "add", "a.txt")
    (repo / "new.txt").write_text("untracked\n")
    idx = repo / ".git/index"
    cached = _sh(repo, "git", "diff", "--cached").stdout
    raw = idx.read_bytes()
    _snap(repo, home)
    _snap(repo, home)
    assert _sh(repo, "git", "diff", "--cached").stdout == cached
    assert idx.read_bytes() == raw


# --- Phase 2: guard ------------------------------------------------------------------


def test_guard_passes_ordinary_edit(repo, home):
    verdict, *_ = _guard(repo, home, lambda: (repo / "ibl5/x.php").write_text("<?php // fixed\n"))
    assert verdict.ok
    assert verdict.changed == ["ibl5/x.php"]


def test_guard_rejects_new_untracked_gate_file(repo, home):
    verdict, *_ = _guard(repo, home, lambda: (repo / "bin/lib/new.sh").write_text("x\n"))
    assert not verdict.ok
    assert ("bin/lib/new.sh", "gate path") in verdict.denied


def test_guard_rejects_ignored_settings_local(repo, home):
    (repo / ".claude/settings.local.json").write_text("{}\n")
    verdict, *_ = _guard(
        repo, home, lambda: (repo / ".claude/settings.local.json").write_text('{"x":1}\n'))
    assert not verdict.ok
    assert (".claude/settings.local.json", "gate path") in verdict.denied


def _common_hooks(repo):
    return repo / ".git/hooks"


@pytest.mark.parametrize("which", [
    "edit-home-hook", "create-home-hook", "edit-home-settings", "edit-common-hook",
    "hooks-path",
])
def test_guard_rejects_out_of_repo_hook_edit(repo, home, which):
    (_common_hooks(repo) / "pre-commit").write_text("#!/bin/sh\nexit 1\n")

    def mutate():
        if which == "edit-home-hook":
            (home / ".claude/hooks/x.sh").write_text("exit 0 # changed\n")
        elif which == "create-home-hook":
            (home / ".claude/hooks/new.sh").write_text("exit 0\n")
        elif which == "edit-home-settings":
            (home / ".claude/settings.json").write_text('{"hooks":{}}\n')
        elif which == "edit-common-hook":
            (_common_hooks(repo) / "pre-commit").write_text("#!/bin/sh\nexit 0\n")
        else:
            _sh(repo, "git", "config", "core.hooksPath", "/tmp/x")

    verdict, *_ = _guard(repo, home, mutate)
    assert not verdict.ok
    assert any(reason == "out-of-repo gate path" for _p, reason in verdict.denied)


def _grow_rules(repo):
    (repo / ".claude/rules/big.md").write_text("big rule\n" * 5)
    (repo / ".claude/rules/big-detail.md").write_text(
        "---\npaths:\n  - 'ibl5/**'\n---\nmoved\n")


def test_rules_carveout_allows_shrink_when_budget_fails(repo, home):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")
    run = make_run(budget_rc=1, post_rc=0)
    verdict, before, _ = _guard(repo, home, lambda: _grow_rules(repo), run=run)
    assert before.carveout.active
    assert verdict.ok, verdict.denied


@pytest.mark.parametrize("case", ["grow", "delete", "outside-diff", "no-paths"])
def test_rules_carveout_denies_growth_delete_and_outside_diff(repo, home, case):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")

    def mutate():
        if case == "grow":
            (repo / ".claude/rules/big.md").write_text("big rule\n" * 400)
        elif case == "delete":
            (repo / ".claude/rules/big.md").unlink()
        elif case == "outside-diff":
            (repo / ".claude/rules/other.md").write_text("o\n")
        else:
            (repo / ".claude/rules/big.md").write_text("big\n")
            (repo / ".claude/rules/big-detail.md").write_text("no frontmatter\n")

    verdict, *_ = _guard(repo, home, mutate, run=make_run(budget_rc=1))
    assert not verdict.ok


def test_rules_carveout_inactive_when_budget_passes(repo, home):
    (repo / ".claude/rules/big.md").write_text("big rule changed\n" * 50 + "x\n")
    _sh(repo, "git", "add", "-A")
    _sh(repo, "git", "commit", "-q", "-m", "branch change")
    _sh(repo, "git", "update-ref", "refs/remotes/origin/master", "HEAD~1")
    verdict, before, _ = _guard(
        repo, home, lambda: (repo / ".claude/rules/big.md").write_text("tiny\n"),
        run=make_run(budget_rc=0))
    assert not before.carveout.active
    assert not verdict.ok
    assert (".claude/rules/big.md", "gate path") in verdict.denied


def test_guard_error_fails_closed(repo, home):
    before = _snap(repo, home)
    (repo / "ibl5/x.php").write_text("changed\n")
    after = _snap(repo, home)

    def boom(*_a, **_k):
        raise RuntimeError("git exploded")

    verdict = gatefix.guard(repo, before, after, run=boom)
    assert not verdict.ok
    assert verdict.denied[0][0] == "<guard>"


# --- Phase 2: revert -----------------------------------------------------------------


def test_revert_restores_dirty_tree_bytes(repo, home):
    (repo / "a.txt").write_text("pre-dirty a\n")
    (repo / "gone.txt").write_text("pre-dirty gone\n")
    (repo / "staged.txt").write_text("staged\n")
    _sh(repo, "git", "add", "staged.txt")
    staged = _sh(repo, "git", "diff", "--cached").stdout
    before = _snap(repo, home)
    (repo / "a.txt").write_text("fixer edit\n")
    (repo / "added.txt").write_text("new\n")
    (repo / "gone.txt").unlink()
    after = _snap(repo, home)
    assert gatefix.revert(repo, before, after) == []
    assert (repo / "a.txt").read_text() == "pre-dirty a\n"
    assert not (repo / "added.txt").exists()
    assert (repo / "gone.txt").read_text() == "pre-dirty gone\n"
    assert _sh(repo, "git", "diff", "--cached").stdout == staged


def test_revert_restores_out_of_repo_bytes(repo, home):
    before = _snap(repo, home)
    (home / ".claude/hooks/x.sh").write_text("tampered\n")
    (home / ".claude/hooks/new.sh").write_text("tampered\n")
    after = _snap(repo, home)
    assert gatefix.revert(repo, before, after) == []
    assert (home / ".claude/hooks/x.sh").read_text() == "#!/bin/sh\nexit 0\n"
    assert not (home / ".claude/hooks/new.sh").exists()


# --- Phase 3: attempt_gate_fix -------------------------------------------------------

from harness import classify, state, usage_pause  # noqa: E402
from harness.state import HarnessError, LlmCallRecord, UsageLedger  # noqa: E402

EXAMPLE_GATE_TEXT = (
    "git commit: bin/check-rules-byte-budget: path-unscoped rules exceed the budget\n"
    "Trim the rule(s) above."
)


class WritingLlm:
    """call_tooled fake: runs `edit(cwd)`, then optionally raises or logs ledger cost."""

    def __init__(self, edit=None, raises=None, cost=None):
        self.edit, self.raises, self.cost = edit, raises, cost
        self.calls = []
        self.ledger = UsageLedger()

    def call_tooled(self, purpose, model, prompt, **kwargs):
        self.calls.append((purpose, model, prompt, kwargs))
        cwd = Path(kwargs["cwd"])
        if self.edit:
            self.edit(cwd)
        if self.cost is not None:
            self.ledger.add(LlmCallRecord(purpose="other", model="x", cost_usd=9.0))
            self.ledger.add(LlmCallRecord(purpose=gatefix.GATEFIX_PURPOSE, model="x",
                                          cost_usd=self.cost))
        if self.raises is not None:
            raise self.raises
        return "fixed it"


def _attempt(repo, home, llm, run=None, text=EXAMPLE_GATE_TEXT):
    logs: list[str] = []
    out = gatefix.attempt_gate_fix(llm, str(repo), gate_text=text, failed_cmd="git commit",
                                   log=logs.append, home=home, run=run or make_run())
    return out, logs


def test_attempt_passing_edit_returns_fixed(repo, home):
    llm = WritingLlm(edit=lambda cwd: (cwd / "ibl5/x.php").write_text("<?php // ok\n"))
    out, logs = _attempt(repo, home, llm)
    assert out.status == "fixed"
    assert out.files == ("ibl5/x.php",)
    assert (repo / "ibl5/x.php").read_text() == "<?php // ok\n"
    _purpose, model, _prompt, kw = llm.calls[0]
    assert model == "opus" and kw["max_turns"] == 30 and kw["timeout"] == 900
    assert "Bash" in kw["denied_tools"] and "Bash" not in kw["allowed_tools"]
    assert any(line.startswith("gatefix: fixed") for line in logs)


def test_attempt_gate_path_edit_reverts(repo, home):
    def edit(cwd):
        (cwd / "bin/check-docs").write_text("exit 0\n")
        (cwd / "ibl5/x.php").write_text("<?php // edit\n")

    out, logs = _attempt(repo, home, WritingLlm(edit=edit))
    assert out.status == "guard-rejected"
    assert not (repo / "bin/check-docs").exists()
    assert (repo / "ibl5/x.php").read_text() == "<?php // base\n"
    assert any("gatefix: guard-rejected" in ln and "bin/check-docs: gate path" in ln
               for ln in logs)


def test_attempt_no_change(repo, home):
    out, _ = _attempt(repo, home, WritingLlm())
    assert out.status == "no-change"


@pytest.mark.parametrize("exc,needle", [
    (HarnessError("llm-tooled-cli", "timeout"), "llm-tooled-cli"),
    (HarnessError("llm-tooled-error", "error_max_turns"), "llm-tooled-error"),
])
def test_attempt_timeout_fails_closed(repo, home, exc, needle):
    llm = WritingLlm(edit=lambda cwd: (cwd / "ibl5/x.php").write_text("half\n"), raises=exc)
    out, logs = _attempt(repo, home, llm)
    assert out.status == "fixer-error"
    assert needle in out.reason
    assert (repo / "ibl5/x.php").read_text() == "<?php // base\n"
    assert any(needle in ln for ln in logs)


def test_attempt_max_turns_fails_closed(repo, home):
    llm = WritingLlm(raises=HarnessError("llm-tooled-error", "error_max_turns"))
    out, _ = _attempt(repo, home, llm)
    assert out.status == "fixer-error"


def test_attempt_usage_pause_propagates_and_reverts(repo, home):
    llm = WritingLlm(edit=lambda cwd: (cwd / "ibl5/x.php").write_text("half\n"),
                     raises=usage_pause.UsagePause("gate-fix", dirty=True))
    with pytest.raises(usage_pause.UsagePause):
        _attempt(repo, home, llm)
    assert (repo / "ibl5/x.php").read_text() == "<?php // base\n"


def test_attempt_snapshot_failure_spawns_nothing(repo, home):
    def run(argv, **kw):
        if "write-tree" in argv:
            return types.SimpleNamespace(returncode=1, stdout="", stderr="no tree")
        return make_run()(argv, **kw)

    llm = WritingLlm()
    out, _ = _attempt(repo, home, llm, run=run)
    assert out.status == "snapshot-failed"
    assert llm.calls == []


def test_attempt_out_of_repo_hook_edit_reverts(repo, home):
    llm = WritingLlm(edit=lambda cwd: (home / ".claude/hooks/x.sh").write_text("evil\n"))
    out, _ = _attempt(repo, home, llm)
    assert out.status == "guard-rejected"
    assert (home / ".claude/hooks/x.sh").read_text() == "#!/bin/sh\nexit 0\n"


def test_attempt_dirty_tree_preserved_on_reject(repo, home):
    (repo / "ibl5/y.php").write_text("<?php // dirty y\n")

    def edit(cwd):
        (cwd / "ibl5/y.php").write_text("<?php // fixer y\n")
        (cwd / "bin/lib/z.sh").write_text("evil\n")

    out, _ = _attempt(repo, home, WritingLlm(edit=edit))
    assert out.status == "guard-rejected"
    assert (repo / "ibl5/y.php").read_text() == "<?php // dirty y\n"
    assert not (repo / "bin/lib/z.sh").exists()


def test_attempt_records_cost_from_ledger(repo, home):
    llm = WritingLlm(edit=lambda cwd: (cwd / "ibl5/x.php").write_text("ok\n"), cost=0.42)
    out, _ = _attempt(repo, home, llm)
    assert out.cost_usd == 0.42


# --- Phase 4: record, PR-body block, result.json -------------------------------------


def _fixed_record(**over):
    out = gatefix.FixOutcome("fixed", "1 file(s)", "opus", 0.42, ("ibl5/x.php", "a.md"),
                             "byte-budget", "git commit", "Trim the rule(s) above.")
    rec = gatefix.record_of(out, phase="phase2", retry="passed")
    rec.update(over)
    return rec


def test_render_gate_fix_block_contents():
    block = classify.render_gate_fix(_fixed_record())
    assert classify.GATE_FIX_BEGIN in block and classify.GATE_FIX_END in block
    assert "claude-opus-5-5" in block and "$0.42" in block and "`byte-budget`" in block
    assert "`ibl5/x.php`" in block and "`a.md`" in block


@pytest.mark.parametrize("over", [
    {"status": "guard-rejected"}, {"status": "fixer-error"}, {"status": "no-change"},
    {"retry": "denied"},
])
def test_render_gate_fix_empty_unless_fixed_and_passed(over):
    assert classify.render_gate_fix(_fixed_record(**over)) == ""


def test_render_gate_fix_neutralizes_fence():
    block = classify.render_gate_fix(_fixed_record(gate_excerpt="x\n~~~~\n## evil\n"))
    fences = [ln for ln in block.splitlines() if ln.strip() == "~~~~"]
    assert len(fences) == 1


def test_upsert_gate_fix_idempotent_and_removes():
    notes = f"{classify.SCOPE_NOTES_BEGIN}\nnotes\n{classify.SCOPE_NOTES_END}"
    body = "Summary\n\n" + notes
    block = classify.render_gate_fix(_fixed_record())
    once = classify.upsert_gate_fix(body, block)
    twice = classify.upsert_gate_fix(once, block)
    assert once == twice
    assert notes in once
    removed = classify.upsert_gate_fix(once, "")
    assert classify.GATE_FIX_BEGIN not in removed
    assert notes in removed


def test_result_json_gate_fix_omitted_when_empty_present_when_set():
    import json
    term = state.TerminalState.FAILED
    assert "gate_fix" not in json.loads(state.RunResult(terminal=term).to_json())
    res = state.RunResult(terminal=term)
    res.gate_fix = _fixed_record()
    assert json.loads(res.to_json())["gate_fix"]["status"] == "fixed"
