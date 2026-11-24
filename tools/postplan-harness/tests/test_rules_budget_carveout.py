"""Rules byte-budget carve-out for the Phase 7 ci-fix fixer.

Phase 1 pins today's gate-edit deny at every `denied_gate_edits` call site and in the
ci-fix prompt. Phase 2 adds unit tests for `harness.rules_budget_carveout`; later phases
add the prompt wiring and runner wiring.
"""
from __future__ import annotations

import os
import pathlib
import subprocess
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.fidelity as fidelity
import runner
from harness import cifix
from harness import rules_budget_carveout as rbc
from harness.rules_budget_carveout import (POST_CHECKS, Carveout, file_verdicts,
                                           frontmatter_paths, permit,
                                           post_check_failures, snapshot)
from test_cifix_ship import CiFixGh, CiFixGit, ScriptedLlm, _has, _run


class RulesEditGit(CiFixGit):
    """The fixer's commit touches a rules file."""

    def changed_files(self, ref): return [".claude/rules/work-triage.md"]


class TestCharacterizationTodayDeny:
    def test_ci_fix_rules_edit_is_discarded_today(self, monkeypatch, tmp_path):
        git, gh, llm = RulesEditGit(), CiFixGh(), ScriptedLlm()
        r = _run(monkeypatch, tmp_path, git, gh, llm, failed=["Static guards"],
                 commit=("b" * 40,))
        assert _has(r.lines, "gate-path edit detected in fix commit")
        assert git.pushes == 0
        assert _has(r.lines, "outcome=error:gate-path-edit")

    def test_prompt_carries_deny_text_and_no_carveout_by_default(self):
        prompt = cifix.ci_fix_prompt(4242, 1, ["Static guards"],
                                     {"Static guards": "/tmp/x.log"},
                                     "/tmp/diff.patch", [])
        assert fidelity.GATE_EDIT_DENY_TEXT in prompt
        assert "byte budget" not in prompt.lower()

    def test_existing_prompt_deny_text_assertion_still_present(self):
        text = pathlib.Path(__file__).with_name("test_cifix.py").read_text()
        assert "def test_prompt_carries_gate_edit_deny_text" in text  # test_cifix.py::test_prompt_carries_gate_edit_deny_text

    def test_non_cifix_call_sites_have_no_carveout_hook(self):
        base = pathlib.Path(fidelity.__file__)
        for name in ("fidelity.py", "thread_ingestion.py", "prosefix.py"):
            text = base.with_name(name).read_text()
            assert "denied_gate_edits(" in text, name
            if name == "fidelity.py":
                # The Phase 5.5 fixer imports the module for annotate_work_list only
                # (budget-trim fix hint). It must not use the ci-fix carve-out hooks.
                for hook in ("snapshot(", "permit(", "carveout_prompt_text", "file_verdicts("):
                    assert f"rules_budget_carveout.{hook}" not in text, hook
                continue
            assert "rules_budget_carveout" not in text, name
        paths = [".claude/rules/x.md", "bin/check-prose", ".github/workflows/t.yml",
                 "tools/postplan-harness/harness/armable.py", "ibl5/x.php"]
        assert fidelity.denied_gate_edits(paths) == paths[:4]


# --- Phase 2: module unit tests ----------------------------------------------------

BIG = ".claude/rules/big.md"
OTHER = ".claude/rules/other.md"
ACTIVE = Carveout(True, "active", frozenset({BIG}))
DETAIL_TEXT = '---\npaths:\n  - "ibl5/**"\n---\n# Detail\nmoved\n'
DETAIL = ".claude/rules/big-detail.md"
SMALL = "b" * 99 + "\n"


def _g(repo, *args):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True,
                          text=True, check=True).stdout.strip()


def _commit(repo, msg, writes=None, removes=(), moves=()):
    for rel, text in (writes or {}).items():
        path = pathlib.Path(repo) / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text)
    for src, dst in moves:
        _g(repo, "mv", src, dst)
    for rel in removes:
        _g(repo, "rm", "-q", rel)
    _g(repo, "add", "-A")
    _g(repo, "commit", "-q", "-m", msg)
    return _g(repo, "rev-parse", "HEAD")


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "repo"
    root.mkdir()
    _g(root, "init", "-q")
    _g(root, "config", "user.name", "t")
    _g(root, "config", "user.email", "t@example.com")
    _g(root, "config", "commit.gpgsign", "false")
    _commit(root, "base", {BIG: "b" * 299 + "\n", OTHER: "o" * 299 + "\n",
                           "ibl5/x.php": "<?php\n"})
    _g(root, "update-ref", "refs/remotes/origin/master", "HEAD")
    _commit(root, "pr", {BIG: "b" * 399 + "\n"})
    return str(root)


def _run_rc(rc):
    return lambda argv, **kw: SimpleNamespace(
        returncode=rc, stdout="", stderr="FAIL  .claude/rules/big.md ...")


def _git_with(files):
    return SimpleNamespace(changed_files=lambda base: list(files))


class TestFrontmatterPaths:
    def test_paths_scalar(self):
        assert frontmatter_paths('---\npaths: "ibl5/**"\n---\nbody\n') == ["ibl5/**"]
        assert frontmatter_paths("---\npaths: ibl5/**\n---\n") == ["ibl5/**"]

    def test_paths_list(self):
        text = '---\nname: x\npaths:\n  - "a/**"\n  - \'b/*.md\'\nother: 1\n---\n'
        assert frontmatter_paths(text) == ["a/**", "b/*.md"]

    def test_paths_absent_or_empty(self):
        assert frontmatter_paths("---\nname: x\n---\nbody\n") == []
        assert frontmatter_paths("---\npaths:\nname: x\n---\n") == []
        assert frontmatter_paths("# no frontmatter\npaths: x\n") == []
        assert frontmatter_paths("") == []

    def test_paths_key_outside_frontmatter_ignored(self):
        assert frontmatter_paths("---\nname: x\n---\npaths: ibl5/**\n") == []
        assert frontmatter_paths("---\nname: x\n---\npaths:\n  - a\n") == []


class TestSnapshot:
    def test_inactive_without_worktree(self):
        c = snapshot(["Static guards"], _git_with([BIG]), None, run=_run_rc(1))
        assert not c.active and c.reason == "no worktree"

    def test_inactive_when_other_checks_fail(self):
        for names in (["Static guards", "pytest (stdlib harness)"], []):
            c = snapshot(names, _git_with([BIG]), "/wt", run=_run_rc(1))
            assert not c.active
            assert c.reason.startswith("failing checks are not exactly Static guards")

    def test_inactive_when_budget_passes_locally(self):
        c = snapshot(["Static guards"], _git_with([BIG]), "/wt", run=_run_rc(0))
        assert not c.active and c.reason == "local byte budget passes"

    def test_inactive_when_budget_run_raises(self):
        def boom(argv, **kw):
            raise OSError("no such script")
        c = snapshot(["Static guards"], _git_with([BIG]), "/wt", run=boom)
        assert not c.active and c.reason == "byte budget run failed: no such script"

    def test_inactive_when_no_rules_file_in_pr_diff(self):
        c = snapshot(["Static guards"], _git_with(["ibl5/x.php", "bin/check-prose"]),
                     "/wt", run=_run_rc(1))
        assert not c.active and c.reason == "no rules file in PR diff"

    def test_inactive_when_pr_diff_read_raises(self):
        def boom(base):
            raise RuntimeError("git down")
        c = snapshot(["Static guards"], SimpleNamespace(changed_files=boom), "/wt",
                     run=_run_rc(1))
        assert not c.active and c.reason == "pr diff read failed: git down"

    def test_active_collects_in_diff_rules_files(self):
        seen = []

        def run(argv, **kw):
            seen.append((argv, kw["cwd"]))
            return SimpleNamespace(returncode=1, stdout="", stderr="")
        c = snapshot(["Static guards"],
                     _git_with([BIG, "ibl5/x.php", ".claude/rules/x.txt"]), "/wt", run=run)
        assert c.active and c.reason == "active"
        assert c.in_diff == frozenset({".claude/rules/big.md"})
        assert seen == [([rbc.BUDGET_SCRIPT], "/wt")]


class TestFileVerdicts:
    def _verdicts(self, repo, hits, carveout=ACTIVE):
        return file_verdicts(hits, carveout, repo, _g(repo, "rev-parse", "HEAD"))

    def test_allows_shrunk_in_diff_file(self, repo):
        _commit(repo, "fix", {BIG: SMALL})
        assert self._verdicts(repo, [BIG]) == []

    def test_allows_shrink_plus_new_detail_with_paths(self, repo):
        _commit(repo, "fix", {BIG: SMALL, DETAIL: DETAIL_TEXT})
        assert self._verdicts(repo, [BIG, DETAIL]) == []

    def test_denies_rules_file_not_in_pr_diff(self, repo):
        _commit(repo, "fix", {OTHER: "o" * 99 + "\n"})
        assert self._verdicts(repo, [OTHER]) == [(OTHER, "not in the PR diff vs origin/master")]

    def test_denies_grown_file(self, repo):
        _commit(repo, "fix", {BIG: "b" * 499 + "\n"})
        assert self._verdicts(repo, [BIG]) == [(BIG, "did not shrink (400 -> 500 bytes)")]

    def test_denies_equal_size_file(self, repo):
        _commit(repo, "fix", {BIG: "c" * 399 + "\n"})
        assert self._verdicts(repo, [BIG]) == [(BIG, "did not shrink (400 -> 400 bytes)")]

    def test_denies_new_detail_without_paths(self, repo):
        _commit(repo, "fix", {BIG: SMALL, DETAIL: "# Detail\nno frontmatter\n"})
        assert self._verdicts(repo, [BIG, DETAIL]) == [(DETAIL, "new companion has no paths: list")]

    def test_denies_new_non_detail_file(self, repo):
        new = ".claude/rules/newrule.md"
        _commit(repo, "fix", {BIG: SMALL, new: DETAIL_TEXT})
        assert self._verdicts(repo, [BIG, new]) == [
            (new, "a new rules file must be a *-detail.md companion")]

    def test_denies_deleted_file(self, repo):
        _commit(repo, "fix", removes=[BIG])
        assert self._verdicts(repo, [BIG]) == [(BIG, "deleting a rules file is never allowed")]

    def test_denies_rename_as_delete_plus_add(self, repo):
        _commit(repo, "fix", moves=[(BIG, DETAIL)])
        # Content is unchanged, so git would report R100. With --no-renames the old path is
        # a delete and the new path is an add without paths:, each judged alone.
        assert self._verdicts(repo, [BIG, DETAIL]) == [
            (BIG, "deleting a rules file is never allowed"),
            (DETAIL, "new companion has no paths: list")]

    def test_denies_non_rules_gate_paths_alongside(self, repo):
        outside = ["bin/check-rules-byte-budget", ".github/workflows/tests.yml",
                   "tools/postplan-harness/harness/armable.py"]
        _commit(repo, "fix", {BIG: SMALL, **{p: "x\n" for p in outside}})
        reason = "outside the carve-out (only .claude/rules/*.md may change)"
        assert self._verdicts(repo, [BIG, *outside]) == [(p, reason) for p in outside]


def _recording_run(rcs=None):
    rcs = rcs or {}
    calls = []

    def run(argv, **kw):
        calls.append((list(argv), kw.get("cwd")))
        rc = rcs.get(tuple(argv), 0)
        return SimpleNamespace(returncode=rc, stdout="out line\n\n",
                               stderr="" if rc == 0 else "first\nbroken thing\n\n")
    return run, calls


class TestPostChecks:
    def test_runs_three_checks_in_worktree(self):
        run, calls = _recording_run()
        assert post_check_failures("/wt", run=run) == []
        assert [c[0] for c in calls] == [list(a) for a in POST_CHECKS]
        assert all(c[1] == "/wt" for c in calls)

    def test_reports_failing_check(self):
        run, calls = _recording_run({POST_CHECKS[1]: 1})
        failures = post_check_failures("/wt", run=run)
        assert failures == ["bin/check-prose --since=origin/master exit 1: broken thing"]
        assert len(calls) == 3 and calls[2][0][0] == "bin/check-docs"


class TestPermit:
    def _shrink(self, repo):
        return _commit(repo, "fix", {BIG: SMALL})

    def test_inactive_refuses_and_skips_checks(self, repo):
        run, calls = _recording_run()
        log = []
        sha = self._shrink(repo)
        inactive = Carveout(False, "no worktree", frozenset())
        assert permit([BIG], inactive, repo, sha, log.append, run=run) is False
        assert log == ["phase7 ci-fix: rules-budget carve-out inactive (no worktree)"]
        assert calls == []

    def test_denied_file_refuses_before_post_checks(self, repo):
        run, calls = _recording_run()
        log = []
        sha = _commit(repo, "fix", {BIG: SMALL, "bin/check-prose": "x\n"})
        assert permit([BIG, "bin/check-prose"], ACTIVE, repo, sha, log.append, run=run) is False
        assert calls == []
        assert len(log) == 1
        assert log[0].startswith(
            "phase7 ci-fix: rules-budget carve-out refused bin/check-prose: outside")

    def test_post_check_failure_refuses(self, repo):
        run, calls = _recording_run({POST_CHECKS[2]: 2})
        log = []
        sha = self._shrink(repo)
        assert permit([BIG], ACTIVE, repo, sha, log.append, run=run) is False
        assert len(calls) == 3
        assert len(log) == 1
        assert "carve-out post-check failed: bin/check-docs" in log[0]

    def test_allows_and_logs(self, repo):
        run, _ = _recording_run()
        log = []
        sha = self._shrink(repo)
        assert permit([BIG], ACTIVE, repo, sha, log.append, run=run) is True
        assert log == [f"phase7 ci-fix: rules-budget carve-out allowed: ['{BIG}']"]

    def test_git_error_refuses(self, repo):
        run, calls = _recording_run()
        log = []
        assert permit([BIG], ACTIVE, repo, "0" * 40, log.append, run=run) is False
        assert len(log) == 1 and "carve-out error" in log[0]
        assert calls == []


class TestRealScripts:
    """Default `run`: the real bin/check-* scripts, no stubs."""

    ROOT = str(pathlib.Path(__file__).resolve().parents[3])

    def test_real_budget_script_runs_in_repo(self):
        c = snapshot(["Static guards"], _git_with([BIG]), self.ROOT)
        assert not c.active and c.reason == "local byte budget passes"

    def test_post_checks_pass_on_clean_worktree(self, tmp_path):
        # Run the real gates in a private clone whose origin/master is pinned to HEAD, so
        # every CI checkout (shallow PR merge or master push) sees an empty, valid diff.
        # Stripping GITHUB_ACTIONS keeps check-rules-byte-budget from fetching the live
        # remote master, which moved past HEAD mid-run and broke the merge base before.
        clone = tmp_path / "clone"
        git = ["git", "-c", "advice.detachedHead=false"]
        head = subprocess.run(["git", "rev-parse", "HEAD"], cwd=self.ROOT,
                              capture_output=True, text=True, check=True).stdout.strip()
        subprocess.run([*git, "clone", "-q", "--shared", "--no-checkout", self.ROOT, str(clone)],
                       check=True, capture_output=True)
        subprocess.run([*git, "checkout", "-q", "--detach", head], cwd=clone,
                       check=True, capture_output=True)
        subprocess.run(["git", "update-ref", "refs/remotes/origin/master", head], cwd=clone,
                       check=True, capture_output=True)
        env = {k: v for k, v in os.environ.items()
               if k not in ("GITHUB_ACTIONS", "GITHUB_BASE_REF")}

        def run(argv, **kw):
            return subprocess.run(argv, env=env, **kw)

        assert post_check_failures(str(clone), run=run) == []


class TestPromptWiring:
    def _prompt(self, **kw):
        return cifix.ci_fix_prompt(4242, 1, ["Static guards"],
                                   {"Static guards": "/tmp/x.log"}, "/tmp/diff.patch",
                                   [], **kw)

    def test_prompt_lists_allowed_files_after_deny_text(self):
        prompt = self._prompt(rules_carveout=(BIG,))
        assert fidelity.GATE_EDIT_DENY_TEXT in prompt
        assert BIG in prompt
        assert "bin/check-rules-byte-budget" in prompt
        assert "-detail.md" in prompt
        assert prompt.index(fidelity.GATE_EDIT_DENY_TEXT) < prompt.index("ONE exception")

    def test_prompt_omits_carveout_when_empty(self):
        assert "ONE exception" not in self._prompt(rules_carveout=())


class RulesGit(CiFixGit):
    def changed_files(self, ref): return [BIG]


class TestRunnerWiring:
    def _permit(self, monkeypatch, verdict, calls):
        def fake_permit(gate_hits, carveout, worktree, sha, log, **kw):
            calls.append((list(gate_hits), sha))
            return verdict
        monkeypatch.setattr(runner.rules_budget_carveout, "permit", fake_permit)

    def test_loop_pushes_when_permit_allows(self, monkeypatch, tmp_path):
        calls = []
        monkeypatch.setattr(runner.rules_budget_carveout, "snapshot",
                            lambda names, git, worktree: ACTIVE)
        self._permit(monkeypatch, True, calls)
        git = RulesGit()
        r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(),
                 failed=["Static guards"])
        assert git.pushes == 1
        assert calls == [([BIG], "b" * 40)]
        assert not _has(r.lines, "gate-path edit detected")

    def test_loop_discards_when_permit_refuses(self, monkeypatch, tmp_path):
        calls = []
        monkeypatch.setattr(runner.rules_budget_carveout, "snapshot",
                            lambda names, git, worktree: ACTIVE)
        self._permit(monkeypatch, False, calls)
        git = RulesGit()
        r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(),
                 failed=["Static guards"])
        assert git.pushes == 0
        assert _has(r.lines, "gate-path edit detected in fix commit")
        assert _has(r.lines, "outcome=error:gate-path-edit")

    def test_permit_not_called_without_gate_hits(self, monkeypatch, tmp_path):
        calls = []
        self._permit(monkeypatch, True, calls)
        git = CiFixGit()
        _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(),
             failed=["Static guards"])
        assert calls == []
        assert git.pushes == 1

    def test_snapshot_sees_triaged_names_before_fixer(self, monkeypatch, tmp_path):
        events, seen = [], []

        def fake_snapshot(names, git, worktree):
            events.append("snapshot")
            seen.append(list(names))
            return Carveout(False, "no worktree", frozenset())

        class OrderLlm(ScriptedLlm):
            def call_tooled(self, purpose, model, prompt, **kw):
                events.append("llm")
                return super().call_tooled(purpose, model, prompt, **kw)

        monkeypatch.setattr(runner.rules_budget_carveout, "snapshot", fake_snapshot)
        _run(monkeypatch, tmp_path, CiFixGit(), CiFixGh(), OrderLlm(),
             failed=["Static guards", "Tests and Analysis", "human-signoff"])
        assert seen == [["Static guards"]]
        assert events.index("snapshot") < events.index("llm")

    def test_prompt_kwarg_tracks_snapshot(self, monkeypatch, tmp_path):
        kwargs = []

        def fake_prompt(*a, **kw):
            kwargs.append(kw.get("rules_carveout"))
            return "p"

        monkeypatch.setattr(runner.cifix, "ci_fix_prompt", fake_prompt)
        for carveout, expected in ((ACTIVE, (BIG,)),
                                   (Carveout(False, "no worktree", frozenset({BIG})), ())):
            monkeypatch.setattr(runner.rules_budget_carveout, "snapshot",
                                lambda names, git, worktree, c=carveout: c)
            _run(monkeypatch, tmp_path, CiFixGit(), CiFixGh(), ScriptedLlm(),
                 failed=["Static guards"])
            assert kwargs[-1] == expected

    def test_real_snapshot_inactive_without_worktree_logs_reason(self, monkeypatch,
                                                                 tmp_path):
        git = RulesGit()
        r = _run(monkeypatch, tmp_path, git, CiFixGh(), ScriptedLlm(),
                 failed=["Static guards"])
        assert _has(r.lines, "rules-budget carve-out inactive (no worktree)")
        assert git.pushes == 0


# --- budget-forced trim hint (annotate_work_list) ------------------------------------

X_RULES = ".claude/rules/x.md"
X_LINES = 10                                   # 10 lines x 100 B = 1000 B at base
FINDING = (f"- [BLOCKING] [unplanned-scope] The PR deleted prose from `{X_RULES}` "
           "that the plan never named. Put the phrases back.")


def _caps_stub(root, cap, *, exit_code=0):
    """Cap oracle: prints one --print-caps row for x.md from the HEAD size and `cap`."""
    size = os.path.getsize(os.path.join(root, X_RULES))
    row = f"{X_RULES}\tlazy\t{cap}\t{size}\t{cap - size}"
    stub = pathlib.Path(root) / "bin" / "check-rules-byte-budget"
    stub.parent.mkdir(exist_ok=True)
    stub.write_text("#!/bin/sh\n"
                    f'if [ "{exit_code}" != 0 ]; then exit {exit_code}; fi\n'
                    f"printf '{row}\\n'\n")
    stub.chmod(0o755)


@pytest.fixture
def trim_repo(tmp_path):
    """Base holds 1000 B in x.md; the PR commit deletes 3 lines (300 B), leaving 700 B."""
    root = tmp_path / "trim"
    root.mkdir()
    _g(root, "init", "-q")
    _g(root, "config", "user.name", "t")
    _g(root, "config", "user.email", "t@example.com")
    _g(root, "config", "commit.gpgsign", "false")
    lines = [f"{i}" * 99 + "\n" for i in range(X_LINES)]
    base = _commit(root, "base", {X_RULES: "".join(lines)})
    _commit(root, "pr", {X_RULES: "".join(lines[:7])})
    return SimpleNamespace(root=str(root), base=base)


def _items(*texts):
    return [{"hold": "12", "text": t} for t in texts]


def test_annotate_work_list_pins_hint_wording(trim_repo):
    _caps_stub(trim_repo.root, 700)                      # headroom 0, 300 B cut: forced
    out = rbc.annotate_work_list(_items(FINDING), trim_repo.root, trim_repo.base)
    hint = rbc.BUDGET_TRIM_HINT.format(path=X_RULES)
    assert out[0]["text"].endswith(hint)
    assert out[0]["text"] == f"{FINDING}\n{hint}"
    assert "Shrink your own added text or move it to a -detail companion." in out[0]["text"]
    assert out[0]["hold"] == "12"


def test_annotate_work_list_keeps_blocking_tag(trim_repo):
    _caps_stub(trim_repo.root, 700)
    other = "- [NOTE] a different finding"
    out = rbc.annotate_work_list(_items(FINDING, other), trim_repo.root, trim_repo.base)
    assert len(out) == 2
    assert out[0]["text"].startswith("- [BLOCKING] [unplanned-scope]")
    assert "[NOTE]" not in out[0]["text"]
    assert out[1]["text"] == other


def test_budget_forced_paths_skips_trim_that_fits(trim_repo):
    _caps_stub(trim_repo.root, 2000)                     # 1300 B headroom >= 300 B cut
    assert rbc.budget_forced_paths(trim_repo.root, trim_repo.base, [X_RULES]) == frozenset()
    items = _items(FINDING)
    out = rbc.annotate_work_list(items, trim_repo.root, trim_repo.base)
    assert out == items
    assert out[0]["text"] == FINDING


def test_annotate_work_list_skips_withheld_and_non_rules(trim_repo):
    _caps_stub(trim_repo.root, 700)
    non_rules = "- [BLOCKING] [unplanned-scope] The PR touched `bin/foo` without a plan row."
    no_tag = f"- [BLOCKING] The PR deleted prose from `{X_RULES}`."
    items = _items(FINDING, non_rules, no_tag)
    out = rbc.annotate_work_list(items, trim_repo.root, trim_repo.base, skip=(FINDING,))
    assert [i["text"] for i in out] == [FINDING, non_rules, no_tag]


def test_budget_forced_paths_script_failure_returns_empty(trim_repo):
    _caps_stub(trim_repo.root, 700, exit_code=2)
    assert rbc.budget_forced_paths(trim_repo.root, trim_repo.base, [X_RULES]) == frozenset()
    items = _items(FINDING)
    assert rbc.annotate_work_list(items, trim_repo.root, trim_repo.base) == items


def test_carveout_does_not_import_fidelity():
    import ast
    tree = ast.parse(pathlib.Path(rbc.__file__).read_text())
    names = set()
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            names.update(a.name for a in node.names)
        elif isinstance(node, ast.ImportFrom):
            names.add(node.module or "")
            names.update(a.name for a in node.names)
    flat = {part for n in names for part in n.replace(".", " ").split()}
    assert "fidelity" not in flat and "unplanned_scope" not in flat


# --- second cap source: bin/test-architect-contract-split ----------------------------

X_CONTRACT = ".claude/skills/plan/_architect-contract.md"
CONTRACT_FINDING = (f"- [BLOCKING] [unplanned-scope] The PR deleted prose from "
                    f"`{X_CONTRACT}` that the plan never named. Put the phrases back.")


def _split_caps_stub(root, cap, exit_code=0):
    """Cap oracle for the split script: one --print-caps row for X_CONTRACT."""
    size = os.path.getsize(os.path.join(root, X_CONTRACT))
    row = f"{X_CONTRACT}\tfile\t{cap}\t{size}\t{cap - size}"
    stub = pathlib.Path(root) / "bin" / "test-architect-contract-split"
    stub.parent.mkdir(exist_ok=True)
    stub.write_text("#!/bin/sh\n"
                    f'if [ "{exit_code}" != 0 ]; then exit {exit_code}; fi\n'
                    f"printf '{row}\\n'\n")
    stub.chmod(0o755)


@pytest.fixture
def contract_trim_repo(tmp_path):
    """Like trim_repo, and the PR commit also cuts 300 B from X_CONTRACT (1000 -> 700 B)."""
    root = tmp_path / "ctrim"
    root.mkdir()
    _g(root, "init", "-q")
    _g(root, "config", "user.name", "t")
    _g(root, "config", "user.email", "t@example.com")
    _g(root, "config", "commit.gpgsign", "false")
    lines = [f"{i}" * 99 + "\n" for i in range(X_LINES)]
    base = _commit(root, "base", {X_RULES: "".join(lines), X_CONTRACT: "".join(lines)})
    _commit(root, "pr", {X_RULES: "".join(lines[:7]), X_CONTRACT: "".join(lines[:7])})
    return SimpleNamespace(root=str(root), base=base)


def test_budget_forced_paths_split_caps_forces_contract(contract_trim_repo):
    _split_caps_stub(contract_trim_repo.root, 700)            # headroom 0, 300 B cut
    got = rbc.budget_forced_paths(contract_trim_repo.root, contract_trim_repo.base,
                                  [X_CONTRACT])
    assert got == frozenset({X_CONTRACT})


def test_budget_forced_paths_split_failure_empties_all(contract_trim_repo):
    _caps_stub(contract_trim_repo.root, 700)                  # rules file alone is forced
    _split_caps_stub(contract_trim_repo.root, 700, exit_code=2)
    got = rbc.budget_forced_paths(contract_trim_repo.root, contract_trim_repo.base,
                                  [X_RULES, X_CONTRACT])
    assert got == frozenset()


def test_budget_forced_paths_rules_only_skips_split_script(trim_repo):
    _caps_stub(trim_repo.root, 700)                           # no split stub exists
    assert not os.path.exists(os.path.join(trim_repo.root, rbc.SPLIT_CAPS_SCRIPT))
    got = rbc.budget_forced_paths(trim_repo.root, trim_repo.base, [X_RULES])
    assert got == frozenset({X_RULES})


def test_budget_forced_paths_contract_trim_that_fits(contract_trim_repo):
    _split_caps_stub(contract_trim_repo.root, 2000)           # 1300 B headroom >= 300 B
    got = rbc.budget_forced_paths(contract_trim_repo.root, contract_trim_repo.base,
                                  [X_CONTRACT])
    assert got == frozenset()


def test_annotate_work_list_hints_contract_with_split_script(contract_trim_repo):
    _split_caps_stub(contract_trim_repo.root, 700)
    out = rbc.annotate_work_list(_items(CONTRACT_FINDING), contract_trim_repo.root,
                                 contract_trim_repo.base)
    assert out[0]["text"].startswith(CONTRACT_FINDING + "\n")
    assert (f"bin/test-architect-contract-split fails on {X_CONTRACT}"
            in out[0]["text"])
    assert "bin/check-rules-byte-budget" not in out[0]["text"]
    assert out[0]["text"].endswith("Keep the trim in place.")


def test_annotate_work_list_ignores_mdx_near_miss(contract_trim_repo):
    _split_caps_stub(contract_trim_repo.root, 700)
    near_miss = ".claude/skills/plan/_architect-contract.mdx"
    text = ("- [BLOCKING] [unplanned-scope] The PR deleted prose from "
            f"`{near_miss}` that the plan never named.")
    items = _items(text)
    out = rbc.annotate_work_list(items, contract_trim_repo.root, contract_trim_repo.base)
    assert out == items
    assert out[0]["text"] == text


def test_annotate_work_list_no_hint_for_uncapped_path(contract_trim_repo):
    _split_caps_stub(contract_trim_repo.root, 700)
    uncapped = ".claude/review-shared/_plan-fidelity-review.md"
    text = ("- [BLOCKING] [unplanned-scope] The PR deleted prose from "
            f"`{uncapped}` that the plan never named.")
    items = _items(text)
    out = rbc.annotate_work_list(items, contract_trim_repo.root, contract_trim_repo.base)
    assert out == items


def test_forced_paths_since_reads_changed_files(contract_trim_repo):
    _caps_stub(contract_trim_repo.root, 700)
    _split_caps_stub(contract_trim_repo.root, 700)
    got = rbc.forced_paths_since(contract_trim_repo.root, contract_trim_repo.base)
    assert got == frozenset({X_RULES, X_CONTRACT})


def test_forced_paths_since_bad_base_returns_empty(contract_trim_repo):
    _caps_stub(contract_trim_repo.root, 700)
    _split_caps_stub(contract_trim_repo.root, 700)
    assert rbc.forced_paths_since(contract_trim_repo.root, "deadbeef") == frozenset()
