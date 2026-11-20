"""Condition (16) meta-check fixer: selector, gate-output parser and bounded fix pass.

The parser fixtures are pasted verbatim from the `meta_check_failures[].output`
fields of two real run records, so a format drift in `bin/run-meta-checks-local`
shows up here first. The fix-pass tests run on a real temp git repo behind a fake
pre-push gate script, so the re-run that decides `cleared` is a real subprocess.
"""
from __future__ import annotations

import os
import subprocess
import sys
import textwrap

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import metafix

# From tools/postplan-harness/out/live-owner-request-second-reviewer-20261115-113003-27226/
# result.json, `meta_check_failures[0].output`.
OWNER_REQUEST_OUTPUT = (
    'PASS  check-docs-self-test\n'
    'FAIL  check-docs-no-staleness\n'
    'FAIL ibl5/docs/decisions/README.md\n'
    '     - duplicate `last_verified` key (3 occurrences)\n'
    '\n'
    '1 failure(s) across 468 docs plus ADR transitions.\n'
    'FAIL  check-docs-since\n'
    'On-touch doc check FAILED:\n'
    '     - ibl5/docs/decisions/README.md: last_verified moved backward (2026-11-14 -> 2026-11-12); a re-verify bumps it to today, never earlier\n'
    '     - ibl5/docs/decisions/README.md: duplicate `last_verified` key (3 occurrences)\n'
    '\n'
    '2 issue(s) in changed docs.\n'
    'PASS  check-prose-self-test\n'
    'PASS  check-prose-since\n'
    'PASS  check-registry-trigger-rows\n'
    'PASS  check-model-pins\n'
    'PASS  test-check-model-pins (cached)\n'
    'PASS  test-check-engine-freeze (cached)\n'
    'PASS  check-engine-freeze\n'
    'PASS  test-check-pipefail-grep-q (cached)\n'
    'PASS  check-pipefail-grep-q\n'
    'PASS  test-check-shell-portability\n'
    'PASS  check-shell-portability\n'
    'PASS  test-check-reviewer-eval\n'
    'PASS  reviewer-eval-validate\n'
    'PASS  test-text-hold-recheck\n'
    'SKIP  check-pr-manual-testing (no --body-file)\n'
    'SKIP  check-post-merge-recipe (no --body-file)\n'
    'SKIP  check-reviewer-eval (no --body-file)\n'
    'FAIL  check-numbering\n'
    '\n'
    'Numbering collision:\n'
    '  0240: duplicated row in ibl5/docs/decisions/README.md\n'
    '\n'
    "ADR or migration: run 'bin/next-adr <slug>' or 'bin/next-migration' from\n"
    "your worktree to get a free number, then 'git mv' the file.\n"
    'Do not renumber any existing file.\n'
    'SKIP  check-e2e-hygiene (filter e2ehygiene=false)\n'
    'SKIP  check-e2e-fa-offers-owner (filter e2ehygiene=false)\n'
    'SKIP  check-e2e-mutator-isolation (filter e2ehygiene=false)\n'
    'SKIP  check-hot-files (filter hotfiles=false)\n'
    'SKIP  check-orphan-css (filter orphancss=false)\n'
    'DEFER adr-check (deferred to post-pr for PR bypass check)\n'
    'SKIP  refactor-flag (filter refactor=false)\n'
    'META-CHECK-FAILED: check-docs-no-staleness\n'
    'META-CHECK-FAILED: check-docs-since\n'
    'META-CHECK-FAILED: check-numbering\n'
    'META-CHECK-DEFERRED: adr-check\n'
    'CACHE hits=3 misses=2 on=1 dir=/Users/ajaynicolas/.cache/ibl5-meta-checks\n'
    'META-CHECKS: FAILED stage=pre-push hard=18 failed=3 warn=0 skip=9 defer=1'
)

# From tools/postplan-harness/out/live-vr-gallery-record-rendered-views-20261115-013659-47786/
# result.json, `meta_check_failures[0].output`.
VR_GALLERY_OUTPUT = (
    'PASS  check-docs-self-test\n'
    'FAIL  check-docs-no-staleness\n'
    'FAIL dead references in source comments\n'
    '     - bin/vr-build-gallery:27 dead reference in comment: `ibl5/vr-rendered`\n'
    '         > //     [--rendered-dir=ibl5/vr-rendered]\n'
    '\n'
    '1 failure(s) across 466 docs plus ADR transitions.\n'
    'PASS  check-docs-since\n'
    'PASS  check-prose-self-test\n'
    'PASS  check-prose-since\n'
    'PASS  check-registry-trigger-rows\n'
    'PASS  check-model-pins\n'
    'PASS  test-check-model-pins (cached)\n'
    'PASS  test-check-engine-freeze (cached)\n'
    'PASS  check-engine-freeze\n'
    'PASS  test-check-pipefail-grep-q (cached)\n'
    'PASS  check-pipefail-grep-q\n'
    'PASS  test-check-shell-portability\n'
    'PASS  check-shell-portability\n'
    'PASS  test-check-reviewer-eval (cached)\n'
    'PASS  reviewer-eval-validate (cached)\n'
    'PASS  test-text-hold-recheck\n'
    'SKIP  check-pr-manual-testing (no --body-file)\n'
    'SKIP  check-post-merge-recipe (no --body-file)\n'
    'SKIP  check-reviewer-eval (no --body-file)\n'
    'SKIP  check-numbering (filter numbering=false)\n'
    'PASS  check-e2e-hygiene\n'
    'PASS  check-e2e-fa-offers-owner\n'
    'PASS  check-e2e-mutator-isolation\n'
    'SKIP  check-hot-files (filter hotfiles=false)\n'
    'PASS  check-orphan-css\n'
    'DEFER adr-check (deferred to post-pr for PR bypass check)\n'
    'DEFER refactor-flag (deferred to post-pr for PR bypass check)\n'
    'META-CHECK-FAILED: check-docs-no-staleness\n'
    'META-CHECK-DEFERRED: adr-check\n'
    'META-CHECK-DEFERRED: refactor-flag\n'
    'CACHE hits=5 misses=0 on=1 dir=/Users/ajaynicolas/.cache/ibl5-meta-checks\n'
    'META-CHECKS: FAILED stage=pre-push hard=20 failed=1 warn=0 skip=5 defer=2'
)

README = "ibl5/docs/decisions/README.md"
DUP_KEY = "duplicate `last_verified` key (3 occurrences)"
MOVED_BACK = ("last_verified moved backward (2026-11-14 -> 2026-11-12); a re-verify "
              "bumps it to today, never earlier")


def _block(output: str, step: str) -> str:
    """One `FAIL  <step>` section of the real output, up to the next step line."""
    lines = output.splitlines()
    start = lines.index(f"FAIL  {step}")
    end = start + 1
    while end < len(lines) and not metafix._STEP_RE.match(lines[end]):
        end += 1
    return "\n".join(lines[start:end]) + f"\nMETA-CHECK-FAILED: {step}\n"


# --- Phase 1: selector and parser -------------------------------------------------

def test_fixable_requires_every_name_in_safe_set():
    assert metafix.fixable([]) is False
    assert metafix.fixable(None) is False
    assert metafix.fixable(["unknown"]) is False
    assert metafix.fixable(["check-docs-since", "check-numbering"]) is False
    assert metafix.fixable(["check-prose-since", "check-docs-no-staleness"]) is True


def test_parse_doc_failures_reads_real_outputs():
    no_staleness = _block(OWNER_REQUEST_OUTPUT, "check-docs-no-staleness")
    since = _block(OWNER_REQUEST_OUTPUT, "check-docs-since")
    assert metafix.parse_doc_failures(no_staleness) == ({README: [DUP_KEY]}, [])
    assert metafix.parse_doc_failures(since) == ({README: [MOVED_BACK, DUP_KEY]}, [])
    targets, refusals = metafix.parse_doc_failures(OWNER_REQUEST_OUTPUT)
    assert refusals == []
    assert targets == {README: [DUP_KEY, MOVED_BACK, DUP_KEY]}


def test_parse_doc_failures_refuses_source_comment_section():
    targets, refusals = metafix.parse_doc_failures(VR_GALLERY_OUTPUT)
    assert targets == {}
    assert refusals and refusals[0].startswith("non-doc section:")
    assert "dead references in source comments" in refusals[0]


def test_parse_doc_failures_refuses_md_line_ref_near_miss():
    output = ("FAIL  check-docs-since\n"
              "     - docs/guide.md:12 dead reference in comment: x\n"
              "META-CHECK-FAILED: check-docs-since\n")
    targets, refusals = metafix.parse_doc_failures(output)
    assert targets == {}
    assert len(refusals) == 1
    assert refusals[0].startswith("non-doc target:")


def test_parse_doc_failures_ignores_other_check_sections():
    output = ("FAIL  check-prose-since\n"
              "     - docs/x.md: y\n"
              "FAIL  check-docs-since\n"
              "     - docs/guide.md: z\n"
              "META-CHECK-FAILED: check-prose-since\n"
              "META-CHECK-FAILED: check-docs-since\n")
    assert metafix.parse_doc_failures(output) == ({"docs/guide.md": ["z"]}, [])


# --- Fixture: a temp repo and a fake pre-push gate ---------------------------------

OLD_DATE = "2026-11-01"
NEW_DATE = "2026-11-19"

GATE = textwrap.dedent('''\
    import sys
    mode, base = sys.argv[1], sys.argv[2]
    print("META-BASE: " + base)
    if mode == "pins":
        print("FAIL  check-model-pins")
        print("META-CHECK-FAILED: check-model-pins")
        sys.exit(1)
    text = open("docs/guide.md", encoding="utf-8").read()
    failed = []
    if "last_verified: %s" in text:
        print("FAIL  check-docs-since")
        print("On-touch doc check FAILED:")
        print("     - docs/guide.md: body changed but last_verified not bumped (still %s)")
        failed.append("check-docs-since")
    if mode == "mixed":
        print("FAIL  check-numbering")
        failed.append("check-numbering")
    for name in failed:
        print("META-CHECK-FAILED: " + name)
    sys.exit(1 if failed else 0)
''') % (OLD_DATE, OLD_DATE)


def _git(repo, *args):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True, text=True,
                          check=True).stdout


def _doc(body: str, date: str = OLD_DATE) -> str:
    return f"---\nlast_verified: {date}\n---\n# Doc\n\n{body}\n"


class Env:
    def __init__(self, repo, base, gate):
        self.repo = repo
        self.base = base
        self.gate = gate

    def argv(self, mode="docs"):
        return [sys.executable, "-I", self.gate, mode, self.base]


@pytest.fixture
def env(tmp_path):
    repo = tmp_path / "repo"
    (repo / "docs").mkdir(parents=True)
    (repo / ".claude" / "rules").mkdir(parents=True)
    r = str(repo)
    _git(r, "init", "-b", "master")
    _git(r, "config", "user.email", "t@t")
    _git(r, "config", "user.name", "t")
    _git(r, "config", "commit.gpgsign", "false")
    (repo / "docs" / "guide.md").write_text(_doc("Base guide."))
    (repo / "docs" / "other.md").write_text(_doc("Base other."))
    (repo / "docs" / "untouched.md").write_text(_doc("Base untouched."))
    (repo / ".claude" / "rules" / "x.md").write_text("# Rule\n\nBase rule.\n")
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "base")
    base = _git(r, "rev-parse", "HEAD").strip()
    _git(r, "checkout", "-b", "feature")
    (repo / "docs" / "guide.md").write_text(_doc("Branch guide."))
    (repo / "docs" / "other.md").write_text(_doc("Branch other."))
    (repo / ".claude" / "rules" / "x.md").write_text("# Rule\n\nBranch rule.\n")
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "branch")
    gate = tmp_path / "gate.py"
    gate.write_text(GATE)
    return Env(r, base, str(gate))


class FakeGit:
    def __init__(self, repo):
        self.repo = repo

    def is_dirty(self):
        return bool(_git(self.repo, "status", "--porcelain").strip())

    def head(self):
        return _git(self.repo, "rev-parse", "HEAD").strip()


class FakeLlm:
    def __init__(self, repo, edits):
        self.repo = repo
        self.edits = list(edits)
        self.calls = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls.append((purpose, model))
        if self.edits:
            self.edits.pop(0)(self.repo)
        return ""


def _commit(repo):
    def commit(msg):
        _git(repo, "add", "-A")
        _git(repo, "commit", "-m", msg)
    return commit


def _write(repo, rel, text):
    with open(os.path.join(repo, rel), "w", encoding="utf-8") as fh:
        fh.write(text)


def _bump_guide(repo):
    _write(repo, "docs/guide.md", _doc("Branch guide.", NEW_DATE))


def _attempt(env, llm, mode="docs"):
    return metafix.attempt_meta_fix(git=FakeGit(env.repo), llm=llm, repo=env.repo,
                                    argv=env.argv(mode), commit=_commit(env.repo),
                                    log=lambda m: None, timeout=120, today=NEW_DATE)


def _commits(env):
    return int(_git(env.repo, "rev-list", "--count", "HEAD").strip())


def test_plan_targets_refuses_gate_owned_doc(env):
    output = (f"META-BASE: {env.base}\nFAIL  check-docs-since\n"
              "     - .claude/rules/x.md: body changed but last_verified not bumped\n"
              "META-CHECK-FAILED: check-docs-since\n")
    assert metafix.plan_targets(output, env.repo) == ({}, {}, "gate-owned: .claude/rules/x.md")


def test_plan_targets_refuses_doc_outside_pr_diff(env):
    output = (f"META-BASE: {env.base}\nFAIL  check-docs-since\n"
              "     - docs/untouched.md: body changed but last_verified not bumped\n"
              "META-CHECK-FAILED: check-docs-since\n")
    prose, docs, refusal = metafix.plan_targets(output, env.repo)
    assert (prose, docs) == ({}, {})
    assert refusal.startswith("outside-pr-diff:")
    assert "docs/untouched.md" in refusal


# --- Phase 2: the bounded fix pass --------------------------------------------------

def test_attempt_clears_doc_check_and_commits(env):
    before = _commits(env)
    llm = FakeLlm(env.repo, [_bump_guide])
    out = _attempt(env, llm)
    assert out["verdict"] == "cleared"
    assert out["checks"] == ["check-docs-since"]
    assert llm.calls == [("meta-fix", "sonnet")]
    assert _commits(env) == before + 1
    assert _git(env.repo, "log", "-1", "--format=%s").strip() == metafix.META_FIX_COMMIT_MSG


def test_attempt_gate_path_edit_fails(env):
    head = FakeGit(env.repo).head()

    def edit(repo):
        _bump_guide(repo)
        _write(repo, ".claude/rules/x.md", "# Rule\n\nEdited rule.\n")

    out = _attempt(env, FakeLlm(env.repo, [edit]))
    assert (out["verdict"], out["reason"]) == ("failed", "gate-path")
    assert FakeGit(env.repo).head() == head


def test_attempt_unfixable_names_no_model_call(env):
    head = FakeGit(env.repo).head()
    llm = FakeLlm(env.repo, [_bump_guide])
    out = _attempt(env, llm, mode="pins")
    assert out["verdict"] == "refused"
    assert out["reason"].startswith("unfixable:")
    assert "check-model-pins" in out["reason"]
    assert llm.calls == []
    assert FakeGit(env.repo).head() == head
    assert not FakeGit(env.repo).is_dirty()


def test_attempt_mixed_list_no_model_call(env):
    head = FakeGit(env.repo).head()
    llm = FakeLlm(env.repo, [_bump_guide])
    out = _attempt(env, llm, mode="mixed")
    assert out["verdict"] == "refused"
    assert out["reason"] == "unfixable: check-docs-since check-numbering"
    assert llm.calls == []
    assert FakeGit(env.repo).head() == head


def test_attempt_still_failing_after_two_attempts(env):
    def body(text):
        return lambda repo: _write(repo, "docs/guide.md", _doc(text))

    llm = FakeLlm(env.repo, [body("Edit one."), body("Edit two.")])
    out = _attempt(env, llm)
    assert llm.calls == [("meta-fix", "sonnet"), ("meta-fix", "opus")]
    assert (out["verdict"], out["reason"]) == ("failed", "still-failing")


def test_attempt_scope_violation_fails(env):
    def edit(repo):
        _bump_guide(repo)
        _write(repo, "docs/other.md", _doc("Stray edit."))

    out = _attempt(env, FakeLlm(env.repo, [edit]))
    assert (out["verdict"], out["reason"]) == ("failed", "scope")


def test_attempt_missing_gate_script_refuses(env):
    llm = FakeLlm(env.repo, [_bump_guide])
    out = metafix.attempt_meta_fix(git=FakeGit(env.repo), llm=llm, repo=env.repo,
                                   argv=[os.path.join(env.repo, "bin", "no-such-gate")],
                                   commit=_commit(env.repo), log=lambda m: None,
                                   timeout=120, today=NEW_DATE)
    assert out["verdict"] == "refused"
    assert out["reason"].startswith("gate-error:")
    assert llm.calls == []
