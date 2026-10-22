"""Phase 5.5 remediation: narrowly scoped repo-file fixes under `.claude/rules/`.

Pure-function tests on `harness.fidelity_filefix`, git-backed verdict tests on a tmp
repo, and `fidelity.remediate` integration tests on the PR #2943 verdict-2 replay.
"""
import os
import subprocess
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, fidelity_filefix
from harness.adapters.llm import FixtureLlm
from harness.fidelity_filefix import (FileFix, eligible_paths, file_verdicts, named_paths,
                                      permit, prompt_text, snapshot)
from harness.state import HarnessError, UsageLedger

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures",
                       "pr2943-verdict2.md")

VISUAL = ".claude/rules/visual-review-prs.md"
OTHER = ".claude/rules/other.md"
THIRD = ".claude/rules/third.md"
FOURTH = ".claude/rules/fourth.md"
WORKFLOW = ".github/workflows/vr-pr-screens.yml"
PROCEDURE = ".claude/review-shared/_phase65-remediation.md"
PR_FILES = [VISUAL, OTHER, WORKFLOW, ".github/workflows/e2e-tests.yml",
            "bin/vr-pages-publish", ".gitleaks.toml",
            "ibl5/tests/ts-unit/vr-pr-screens-cli.test.ts"]
BLOCKING_VISUAL = f"- [BLOCKING] Restore the sentence in `{VISUAL}` step 3."
ACTIVE = FileFix(True, "active", frozenset({VISUAL}))


# --- repo helpers ------------------------------------------------------------

def _g(repo, *args):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True,
                          text=True, check=True).stdout.strip()


def rules_text(body, last_verified="2026-10-01", description="x", extra=""):
    return (f"---\ndescription: {description}\n{extra}last_verified: {last_verified}\n---\n"
            + "".join(f"{line}\n" for line in body))


BODY = [f"line {i}" for i in range(1, 6)]


def commit_change(repo, changes):
    """Write each path (None deletes it), stage everything, commit, return the sha."""
    for rel, text in changes.items():
        path = os.path.join(str(repo), rel)
        if text is None:
            os.unlink(path)
            continue
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, "w") as fh:
            fh.write(text)
    _g(repo, "add", "-A")
    _g(repo, "commit", "-q", "-m", "change")
    return _g(repo, "rev-parse", "HEAD")


@pytest.fixture
def repo(tmp_path):
    """Base = origin/master, then one PR commit that changes the three PR files."""
    root = tmp_path / "repo"
    root.mkdir()
    _g(root, "init", "-q")
    _g(root, "config", "user.name", "t")
    _g(root, "config", "user.email", "t@example.com")
    _g(root, "config", "commit.gpgsign", "false")
    base = commit_change(root, {VISUAL: rules_text(BODY),
                                OTHER: rules_text(["other 1", "other 2"]),
                                WORKFLOW: "name: vr\n",
                                PROCEDURE: "REMEDIATION PROCEDURE\n"})
    _g(root, "update-ref", "refs/remotes/origin/master", base)
    commit_change(root, {VISUAL: rules_text([b for b in BODY if b != "line 3"]),
                         OTHER: rules_text(["other 1", "other 2", "other 3"]),
                         WORKFLOW: "name: vr\non: push\n"})
    return SimpleNamespace(root=str(root), base=base)


def _verdicts(repo, hits, filefix=ACTIVE):
    return file_verdicts(hits, filefix, repo.root, _g(repo.root, "rev-parse", "HEAD"))


def _fix_visual(extra_lines=0):
    return rules_text(BODY + [f"extra {i}" for i in range(extra_lines)],
                      last_verified="2026-10-19")


# --- parse fixture -----------------------------------------------------------

def test_pr2943_fixture_parses_one_blocking_eight_notes():
    assert fidelity.parse_verdict(FIXTURE) == "NOT READY"
    items = fidelity.verdict_items(FIXTURE)
    assert len(items["blocking"]) == 1
    assert "visual-review-prs.md" in items["blocking"][0]
    assert len(items["notes"]) == 8


# --- named_paths / eligible_paths --------------------------------------------

def test_named_paths_full_path_in_pr_diff():
    assert named_paths(f"see `{VISUAL}` here", PR_FILES) == {VISUAL}


def test_named_paths_unique_basename_resolves():
    assert named_paths("see `visual-review-prs.md` here", PR_FILES) == {VISUAL}


def test_named_paths_ambiguous_basename_adds_nothing():
    assert named_paths("see `other.md` here", PR_FILES + ["docs/other.md"]) == set()


def test_named_paths_ignores_unbackticked_prose():
    assert named_paths(f"visual-review-prs.md and {VISUAL} in prose", PR_FILES) == set()


def test_eligible_paths_note_bullets_never_count():
    assert eligible_paths([f"- [NOTE] Touch `{OTHER}`"], PR_FILES) == frozenset()


def test_eligible_paths_unmarked_bullet_never_counts():
    assert eligible_paths([f"- Restore `{OTHER}`"], PR_FILES) == frozenset()


def test_eligible_paths_excludes_non_rules_gate_paths():
    assert eligible_paths(["- [BLOCKING] Fix the cancel age in `vr-pr-screens.yml`"],
                          PR_FILES) == frozenset()


def test_pr2943_replay_eligible_is_exactly_visual_review_prs():
    texts = fidelity.blocking_finding_texts(FIXTURE)
    assert eligible_paths(texts, PR_FILES) == frozenset({VISUAL})
    # The workflow is named too; the location rule, not the name rule, excludes it.
    assert WORKFLOW in named_paths(texts[0], PR_FILES)


# --- snapshot ----------------------------------------------------------------

def _stub_git(files=None, exc=None):
    def changed_files(base):
        if exc:
            raise exc
        return list(files)
    return SimpleNamespace(changed_files=changed_files)


def test_snapshot_inactive_without_worktree():
    got = snapshot([BLOCKING_VISUAL], _stub_git(PR_FILES), None)
    assert not got.active and got.reason == "no worktree"


def test_snapshot_inactive_when_no_blocking_finding():
    got = snapshot([f"- [NOTE] see `{VISUAL}`"], _stub_git(PR_FILES), "/wt")
    assert not got.active and got.reason == "no [BLOCKING] finding"


def test_snapshot_inactive_when_pr_diff_read_raises():
    got = snapshot([BLOCKING_VISUAL], _stub_git(exc=RuntimeError("git down")), "/wt")
    assert not got.active and got.reason == "pr diff read failed: git down"


def test_snapshot_active_collects_eligible():
    got = snapshot([BLOCKING_VISUAL], _stub_git(PR_FILES), "/wt")
    assert got == FileFix(True, "active", frozenset({VISUAL}))


# --- file_verdicts on a real repo --------------------------------------------

def test_verdict_allows_small_modification_with_last_verified_bump(repo):
    commit_change(repo.root, {VISUAL: _fix_visual()})
    assert _verdicts(repo, [VISUAL]) == []


def test_verdict_denies_unnamed_rules_file(repo):
    commit_change(repo.root, {OTHER: rules_text(["other 1", "other 9"])})
    denied = _verdicts(repo, [OTHER])
    assert [p for p, _ in denied] == [OTHER]
    assert "not named" in denied[0][1]


def test_verdict_denies_workflow_alongside(repo):
    commit_change(repo.root, {VISUAL: _fix_visual(), WORKFLOW: "name: vr\non: pull\n"})
    denied = _verdicts(repo, [VISUAL, WORKFLOW])
    assert [p for p, _ in denied] == [WORKFLOW]


def test_verdict_denies_added_rules_file(repo):
    new = ".claude/rules/new.md"
    commit_change(repo.root, {new: rules_text(["new 1"])})
    denied = _verdicts(repo, [new], FileFix(True, "active", frozenset({new})))
    assert [p for p, _ in denied] == [new]
    assert "only a modification" in denied[0][1]


def test_verdict_denies_deleted_rules_file(repo):
    commit_change(repo.root, {VISUAL: None})
    denied = _verdicts(repo, [VISUAL])
    assert [p for p, _ in denied] == [VISUAL]
    assert "only a modification" in denied[0][1]


def test_verdict_denies_over_line_cap(repo):
    commit_change(repo.root, {VISUAL: _fix_visual(extra_lines=21)})
    denied = _verdicts(repo, [VISUAL])
    assert [p for p, _ in denied] == [VISUAL]
    assert "cap 20" in denied[0][1]


def test_verdict_denies_over_deletion_cap(repo):
    big = rules_text([f"line {i}" for i in range(1, 13)])
    commit_change(repo.root, {VISUAL: big})
    commit_change(repo.root, {VISUAL: rules_text(["line 10", "line 11", "line 12"])})
    denied = _verdicts(repo, [VISUAL])
    assert [p for p, _ in denied] == [VISUAL]
    assert "deleted 9 lines" in denied[0][1]


def test_verdict_denies_frontmatter_change(repo):
    commit_change(repo.root, {VISUAL: rules_text(BODY, "2026-10-19", description="y")})
    denied = _verdicts(repo, [VISUAL])
    assert [p for p, _ in denied] == [VISUAL]
    assert "frontmatter changed" in denied[0][1]
    # A `paths:` line is residency, so adding one is a frontmatter change as well.
    commit_change(repo.root, {VISUAL: rules_text(BODY, "2026-10-19", extra="paths: x\n")})
    denied = _verdicts(repo, [VISUAL])
    assert "frontmatter changed" in denied[0][1]


def test_verdict_denies_total_line_cap(repo):
    paths = [VISUAL, OTHER, THIRD]
    commit_change(repo.root, {THIRD: rules_text(["third 1"])})
    grown = {}
    for p in paths:
        with open(os.path.join(repo.root, p)) as fh:
            grown[p] = fh.read() + "".join(f"added {i}\n" for i in range(15))
    commit_change(repo.root, grown)
    denied = _verdicts(repo, paths, FileFix(True, "active", frozenset(paths)))
    assert [p for p, _ in denied] == ["*"]
    assert "total 45 lines" in denied[0][1]


def test_verdict_denies_more_than_three_files(repo):
    paths = [VISUAL, OTHER, THIRD, FOURTH]
    commit_change(repo.root, {THIRD: rules_text(["third 1"]), FOURTH: rules_text(["f 1"])})
    commit_change(repo.root, {p: rules_text(["edited"]) for p in paths})
    denied = _verdicts(repo, paths, FileFix(True, "active", frozenset(paths)))
    assert ("*", "4 gate-owning files (cap 3)") in denied


# --- permit ------------------------------------------------------------------

def test_permit_inactive_refuses_and_skips_checks():
    def run(*a, **k):
        raise AssertionError("post-checks must not run for an inactive carve-out")
    lines = []
    ok = permit([VISUAL], FileFix(False, "no worktree", frozenset()), "/nope", "abc",
                lines.append, run=run)
    assert ok is False
    assert any("inactive (no worktree)" in ln for ln in lines)


def test_permit_refuses_when_post_check_fails(repo):
    commit_change(repo.root, {VISUAL: _fix_visual()})

    def run(argv, **kw):
        rc = 1 if argv[0] == "bin/check-prose" else 0
        return SimpleNamespace(returncode=rc, stdout="", stderr="tell\n" if rc else "")
    lines = []
    ok = permit([VISUAL], ACTIVE, repo.root, _g(repo.root, "rev-parse", "HEAD"),
                lines.append, run=run)
    assert ok is False
    assert any("post-check failed: bin/check-prose --since=origin/master exit 1: tell" in ln
               for ln in lines)


def test_permit_allows_and_logs_sorted_hits(repo):
    commit_change(repo.root, {VISUAL: _fix_visual(),
                              OTHER: rules_text(["other 1", "other 2", "other 4"])})
    ff = FileFix(True, "active", frozenset({VISUAL, OTHER}))
    run = lambda argv, **kw: SimpleNamespace(returncode=0, stdout="", stderr="")  # noqa: E731
    lines = []
    ok = permit([VISUAL, OTHER], ff, repo.root, _g(repo.root, "rev-parse", "HEAD"),
                lines.append, run=run)
    assert ok is True
    assert any(f"allowed: {sorted([VISUAL, OTHER])}" in ln for ln in lines)


# --- prompt_text -------------------------------------------------------------

def test_prompt_text_names_eligible_files_and_caps():
    text = prompt_text(frozenset({VISUAL, OTHER}))
    for needle in (VISUAL, OTHER, "20", "8", "40",
                   "bin/check-docs --since=origin/master --no-staleness", "file-fix"):
        assert needle in text


# --- remediate() integration -------------------------------------------------

class RepoGit:
    """Worktree-backed git object: every call shells out to the tmp repo."""

    def __init__(self, root):
        self.root = root
        self.pushes = 0

    def is_dirty(self):
        return bool(_g(self.root, "status", "--porcelain"))

    def changed_files(self, base="origin/master"):
        return _g(self.root, "diff", "--name-only", base, "HEAD").splitlines()

    def commit_all(self, message):
        _g(self.root, "add", "-A")
        if not _g(self.root, "diff", "--cached", "--name-only"):
            return ""
        _g(self.root, "commit", "-q", "-m", message)
        return _g(self.root, "rev-parse", "HEAD")

    def push(self):
        self.pushes += 1
        return _g(self.root, "rev-parse", "HEAD")

    def head_tree(self):
        return _g(self.root, "rev-parse", "HEAD^{tree}")

    def diff_vs_base(self, base="origin/master"):
        return _g(self.root, "diff", base, "HEAD")


class EditingLlm(FixtureLlm):
    """Fixer stand-in: records the prompt, then performs the edit the test wants."""

    def __init__(self, edit=None):
        super().__init__(UsageLedger(), {"fidelity-remediation": "edited"})
        self.edit = edit
        self.prompts = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.prompts.append(prompt)
        if self.edit:
            self.edit()
        return super().call_tooled(purpose, model, prompt, **kw)


def _packet(tmp_path):
    d = tmp_path / "packet"
    d.mkdir(exist_ok=True)
    (d / "diff.patch").write_text("diff\n")
    return str(d)


def _verdict(tmp_path, bullets):
    p = tmp_path / "verdict.md"
    p.write_text(f"NOT READY\n\n## FINDINGS\n{bullets}\n")
    return str(p)


def _run(repo, tmp_path, llm, verdict=FIXTURE):
    git, lines, outcome = RepoGit(repo.root), [], {}
    try:
        sha = fidelity.remediate(llm, git, str(tmp_path / "out"), repo.root,
                                 _packet(tmp_path), verdict, repo.base,
                                 log=lines.append, outcome=outcome)
        return SimpleNamespace(sha=sha, git=git, lines=lines, outcome=outcome, exc=None)
    except HarnessError as exc:
        return SimpleNamespace(sha=None, git=git, lines=lines, outcome=outcome, exc=exc)


def _has(lines, needle):
    return any(needle in ln for ln in lines)


@pytest.fixture
def checks_pass(monkeypatch):
    monkeypatch.setattr(fidelity_filefix, "post_check_failures", lambda *a, **k: [])


def _edit(repo, changes):
    def edit():
        for rel, text in changes.items():
            with open(os.path.join(repo.root, rel), "w") as fh:
                fh.write(text)
    return edit


def test_blocking_named_rules_file_is_committed(repo, tmp_path, checks_pass):
    llm = EditingLlm(_edit(repo, {VISUAL: _fix_visual()}))
    r = _run(repo, tmp_path, llm)
    assert r.exc is None
    assert r.sha == _g(repo.root, "rev-parse", "HEAD")
    assert r.git.pushes == 1
    assert r.outcome["reason"] == "committed"
    assert _has(r.lines, f"file-fix carve-out active for {VISUAL}")
    assert _has(r.lines, "file-fix commit allowed")


def test_no_file_named_stays_body_only(repo, tmp_path, checks_pass):
    verdict = _verdict(tmp_path, "- Scope prose says 3 files; numstat shows 5")
    r = _run(repo, tmp_path, EditingLlm(), verdict)
    assert r.exc is None and r.sha is None
    assert r.outcome["reason"] == "no-edits"
    assert r.git.pushes == 0
    assert _has(r.lines, "file-fix carve-out inactive (no [BLOCKING] finding)")


def test_blocking_without_path_stays_inactive(repo, tmp_path, checks_pass):
    verdict = _verdict(tmp_path, "- [BLOCKING] Scope prose says 3 files; numstat shows 5")
    llm = EditingLlm(_edit(repo, {VISUAL: _fix_visual()}))
    r = _run(repo, tmp_path, llm, verdict)
    assert r.exc is not None and r.exc.kind == "gate-path-edit"
    assert r.git.pushes == 0
    assert _has(r.lines, "file-fix carve-out inactive (no eligible rules file named)")


def test_file_outside_allowed_set_is_not_committed(repo, tmp_path, checks_pass):
    verdict = _verdict(tmp_path, "- [BLOCKING] Cancel age is wrong in `vr-pr-screens.yml`")
    head_before = _g(repo.root, "rev-parse", "HEAD")
    llm = EditingLlm(_edit(repo, {WORKFLOW: "name: vr\non: pull\n"}))
    r = _run(repo, tmp_path, llm, verdict)
    assert r.exc is not None and r.exc.kind == "gate-path-edit"
    assert r.git.pushes == 0
    assert _g(repo.root, "rev-parse", "HEAD") != head_before
    assert _g(repo.root, "rev-parse", "origin/master") == repo.base


def test_mixed_commit_with_unnamed_rules_file_is_discarded(repo, tmp_path, checks_pass):
    llm = EditingLlm(_edit(repo, {VISUAL: _fix_visual(),
                                  OTHER: rules_text(["other 1", "other 9"])}))
    r = _run(repo, tmp_path, llm)
    assert r.exc is not None and r.exc.kind == "gate-path-edit"
    assert r.git.pushes == 0
    assert _has(r.lines, f"file-fix carve-out refused {OTHER}")


def test_post_check_failure_discards_commit(repo, tmp_path, monkeypatch):
    monkeypatch.setattr(fidelity_filefix, "post_check_failures",
                        lambda *a, **k: ["bin/check-prose exit 1: tell"])
    llm = EditingLlm(_edit(repo, {VISUAL: _fix_visual()}))
    r = _run(repo, tmp_path, llm)
    assert r.exc is not None and r.exc.kind == "gate-path-edit"
    assert r.git.pushes == 0
    assert _has(r.lines, "post-check failed: bin/check-prose exit 1: tell")


def test_prompt_carries_filefix_paragraph_only_when_active(repo, tmp_path, checks_pass):
    active = EditingLlm()
    _run(repo, tmp_path, active)
    inactive = EditingLlm()
    verdict = _verdict(tmp_path, "- [BLOCKING] Scope prose says 3 files; numstat shows 5")
    _run(repo, tmp_path, inactive, verdict)
    assert "file-fix exception" in active.prompts[0]
    assert "file-fix exception" not in inactive.prompts[0]
    assert fidelity.GATE_EDIT_DENY_TEXT in active.prompts[0]
    assert fidelity.GATE_EDIT_DENY_TEXT in inactive.prompts[0]
