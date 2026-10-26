"""Phase 5.0-pre conformance fixer: kill switch, classification, bounded round, post-checks.

No model is ever called. `FixtureLlm` records the spawn; `WritingLlm` performs the writes a
real fixer would (or raises), so the mechanical post-checks have a real tree to judge while
git stays replayed (`ReplayGit.extra_dirty` carries the written paths).
"""
import os
import subprocess
import sys
from pathlib import Path

import pytest

HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_ROOT)

from harness import conformance_fix, gatefix, planfile
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger

PROMISED = "ibl5/tests/Fixer/PromisedTest.php"

PLAN_TEXT = f"""# Synthetic plan

## Verification Matrix

| # | What to verify | Test type | When | Test |
|---|---|---|---|---|
| 1 | the promised behavior holds | PHPUnit | post-impl | `{PROMISED}` |

## Required Test Methods

- `testFoo()`

## Critical Files

- `ibl5/classes/Fixer/Thing.php` — production code
"""

ITEM_PATH = f"MISSING: {PROMISED} (matrix planned a test the diff never wrote)"
ITEM_METHOD = "MISSING-METHOD: testFoo (plan required a test method the diff never wrote)"

GOOD_PHP = """<?php
final class PromisedTest extends TestCase
{
    public function testFoo(): void
    {
        $this->assertSame(1, 1);
    }
}
"""


class WritingLlm(FixtureLlm):
    """FixtureLlm that also performs the writes a real fixer would, or raises."""

    def __init__(self, ledger, canned, writes=None, raise_kind=None, git=None, on_call=None):
        super().__init__(ledger, canned)
        self.writes = writes or {}
        self.raise_kind = raise_kind
        self.git = git
        self.on_call = on_call
        self.kwargs = []

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        out = super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)
        self.kwargs.append(dict(kw, prompt=prompt, cwd=cwd))
        if self.on_call:
            self.on_call()
        for rel, text in self.writes.items():
            p = Path(cwd) / rel
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(text)
            if self.git is not None and rel not in self.git.extra_dirty:
                self.git.extra_dirty.append(rel)
        if self.raise_kind:
            raise HarnessError(self.raise_kind, "boom")
        return out


class RealRestoreGit(ReplayGit):
    """ReplayGit whose restore_paths also removes the file, as LiveGit does for untracked paths."""

    def __init__(self, fixture, root):
        super().__init__(fixture)
        self.root = root

    def restore_paths(self, paths):
        super().restore_paths(paths)
        for p in paths:
            full = os.path.join(self.root, p)
            if os.path.exists(full):
                os.remove(full)


def _plan(extra_test_paths=0):
    text = PLAN_TEXT
    if extra_test_paths:
        rows = "".join(
            f"| {i + 2} | more | PHPUnit | post-impl | `ibl5/tests/Fixer/Extra{i}Test.php` |\n"
            for i in range(extra_test_paths))
        text = text.replace("\n## Required Test Methods", rows + "\n## Required Test Methods", 1)
    return planfile.locate_plan("demo", content_override=text)


def _fixture():
    return {"slug": "demo", "diff": "diff --git a/ibl5/classes/Fixer/Thing.php b/ibl5/classes/Fixer/Thing.php\n+<?php\n"}


def _setup(tmp_path, writes=None, raise_kind=None, git_cls=None, dirty=False, on_call=None):
    wt = tmp_path / "wt"
    wt.mkdir(exist_ok=True)
    fx = _fixture()
    if dirty:
        fx["worktree_diff"] = "diff --git a/ibl5/x.php b/ibl5/x.php\n+x\n"
    git = RealRestoreGit(fx, str(wt)) if git_cls is None else git_cls(fx)
    llm = WritingLlm(UsageLedger(), {"conformance-fix": "done"}, writes=writes,
                     raise_kind=raise_kind, git=git, on_call=on_call)
    return wt, git, llm


def _remediate(tmp_path, items, wt, git, llm, plan=None, env=None):
    return conformance_fix.remediate_missing(
        llm, git, plan or _plan(), str(wt), str(tmp_path / "out"), items,
        commit=lambda msg: git.commit_all(msg),
        push=lambda: (git.push() or ""),
        env=env if env is not None else {})


# --- kill switch -------------------------------------------------------------------

def test_enabled_when_unset():
    assert conformance_fix.enabled({}) is True


def test_enabled_when_empty():
    assert conformance_fix.enabled({"POSTPLAN_CONFORMANCE_FIX": ""}) is True


def test_disabled_only_by_exact_zero():
    assert conformance_fix.enabled({"POSTPLAN_CONFORMANCE_FIX": "0"}) is False
    assert conformance_fix.enabled({"POSTPLAN_CONFORMANCE_FIX": "off"}) is True
    assert conformance_fix.enabled({"POSTPLAN_CONFORMANCE_FIX": "false"}) is True


# --- classification ----------------------------------------------------------------

def test_classify_never_hands_over_missing_phase_or_unmet_contract():
    items = ["MISSING-PHASE: 2 — B (phase cites harness/b.py; none appeared in the diff)",
             "UNMET-CONTRACT: stop-condition tests-green (phase5 fail)"]
    cls = conformance_fix.classify(items, [PROMISED], [])
    assert cls.fixable == []
    assert cls.unfixable == {items[0]: "never-fixable", items[1]: "never-fixable"}


def test_classify_rejects_non_test_matrix_path_and_denied_prefixes():
    a = "MISSING: ibl5/foo.php (matrix planned a test the diff never wrote)"
    b = "MISSING-FILE: .claude/rules/x.md (plan Critical File never appeared in the diff)"
    c = "MISSING-FILE: ibl5/migrations/999_x.sql (plan Critical File never appeared in the diff)"
    cls = conformance_fix.classify([a, b, c], [], [])
    assert cls.fixable == []
    assert cls.unfixable[a] == "non-test-path"
    assert cls.unfixable[b] == "denied-path"
    assert cls.unfixable[c] == "denied-path"


def test_classify_method_items_target_plan_cited_test_paths():
    plan = _plan()
    assert conformance_fix.plan_test_paths(plan) == [PROMISED]
    cls = conformance_fix.classify([ITEM_METHOD], conformance_fix.plan_test_paths(plan),
                                   conformance_fix.plan_critical_paths(plan))
    assert [t.allowed_paths for t in cls.fixable] == [[PROMISED]]
    assert cls.fixable[0].kind == "method" and cls.fixable[0].method == "testFoo"
    none = conformance_fix.classify([ITEM_METHOD], [], [])
    assert none.fixable == [] and none.unfixable == {ITEM_METHOD: "no-target"}


# --- round gating ------------------------------------------------------------------

def test_round_skipped_when_fixable_count_exceeds_cap(tmp_path):
    items = [f"MISSING-FILE: ibl5/classes/F{i}.php (plan Critical File never appeared in the diff)"
             for i in range(conformance_fix.MAX_ITEMS + 1)]
    wt, git, llm = _setup(tmp_path)
    rec = _remediate(tmp_path, items, wt, git, llm)
    assert rec["reason"] == "cap-items"
    assert llm.tooled_argvs == []
    assert git.commit_messages == []


def test_round_skipped_on_dirty_worktree(tmp_path):
    wt, git, llm = _setup(tmp_path, dirty=True)
    assert git.is_dirty()
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "dirty-worktree"
    assert llm.tooled_argvs == []
    assert git.commit_messages == [] and git.pushes == 0


class _Boom:
    def __getattr__(self, name):
        raise AssertionError(f"disabled fixer touched {name}")


def test_disabled_fixer_makes_no_git_or_llm_calls(tmp_path):
    wt, git, llm = _setup(tmp_path)
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm, env={"POSTPLAN_CONFORMANCE_FIX": "0"})
    assert rec["enabled"] is False and rec["reason"] == "disabled"
    assert rec["items"] == [ITEM_PATH]
    assert llm.tooled_argvs == []
    assert git.commit_messages == [] and git.pushes == 0
    # Stronger: a git/llm pair that raises on any attribute access is never touched.
    rec2 = conformance_fix.remediate_missing(
        _Boom(), _Boom(), _plan(), str(wt), str(tmp_path / "out"), [ITEM_PATH],
        commit=lambda m: "", push=lambda: "", env={"POSTPLAN_CONFORMANCE_FIX": "0"})
    assert rec2["reason"] == "disabled"


# --- happy path --------------------------------------------------------------------

def test_fixer_spawns_once_with_gatefix_tool_tuples_and_commits(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: GOOD_PHP})
    rec = _remediate(tmp_path, [ITEM_PATH, ITEM_METHOD], wt, git, llm)
    assert len(llm.tooled_argvs) == 1 and len(llm.kwargs) == 1
    kw = llm.kwargs[0]
    assert kw["allowed_tools"] == gatefix.GATEFIX_ALLOWED
    assert kw["denied_tools"] == gatefix.GATEFIX_DENIED
    assert ITEM_PATH in kw["prompt"] and ITEM_METHOD in kw["prompt"]
    assert PROMISED in kw["prompt"]
    assert git.commit_messages == [conformance_fix.COMMIT_MSG]
    assert git.pushes == 1
    assert rec["reason"] == "committed"
    assert rec["written_paths"] == [PROMISED]
    assert rec["allowed_paths"] == [PROMISED]
    assert (wt / PROMISED).read_text() == GOOD_PHP


def test_no_edits_round_commits_nothing(tmp_path):
    wt, git, llm = _setup(tmp_path)
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert len(llm.tooled_argvs) == 1
    assert rec["reason"] == "no-edits"
    assert git.commit_messages == [] and git.pushes == 0


# --- post-check rejections (each restores the tree exactly) --------------------------

def test_out_of_scope_write_is_reverted_and_rejected(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: GOOD_PHP,
                                            "ibl5/classes/Foo.php": "<?php // stray\n"})
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "rejected" and rec["detail"] == "out-of-scope-write"
    assert git.restored == [["ibl5/classes/Foo.php"]]
    assert not (wt / PROMISED).exists()
    assert not (wt / "ibl5/classes/Foo.php").exists()
    assert git.commit_messages == []


def test_denied_path_write_is_reverted_and_rejected(tmp_path, monkeypatch):
    # classify() filters denied paths out of the allowed set, so the denied-path post-check
    # is unreachable through the public flow. Let classify see `bin/check-foo` as allowed,
    # then restore the real predicate once the fixer has "written".
    real_denied = conformance_fix._denied
    state = {"armed": False}
    monkeypatch.setattr(conformance_fix, "_denied",
                        lambda p: real_denied(p) if state["armed"] else False)
    item = "MISSING-FILE: bin/check-foo (plan Critical File never appeared in the diff)"
    wt, git, llm = _setup(tmp_path, writes={"bin/check-foo": "#!/bin/sh\nexit 0\n"},
                          on_call=lambda: state.update(armed=True))
    rec = _remediate(tmp_path, [item], wt, git, llm)
    assert rec["allowed_paths"] == ["bin/check-foo"]
    assert rec["reason"] == "rejected" and rec["detail"] == "denied-path"
    assert not (wt / "bin/check-foo").exists()
    assert git.commit_messages == []


def test_rejects_edit_that_removes_existing_test_lines(tmp_path):
    original = GOOD_PHP
    wt = tmp_path / "wt"
    (wt / "ibl5/tests/Fixer").mkdir(parents=True)
    (wt / PROMISED).write_text(original)
    before_bytes = (wt / PROMISED).read_bytes()
    edited = original.replace("        $this->assertSame(1, 1);\n",
                              "        $this->assertSame(2, 2);\n        $this->assertSame(3, 3);\n")
    wt, git, llm = _setup(tmp_path, writes={PROMISED: edited})
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "rejected" and rec["detail"] == "edited-existing-lines"
    assert (wt / PROMISED).read_bytes() == before_bytes
    assert git.commit_messages == []


def test_added_and_removed_counts_lines_that_look_like_diff_headers():
    before = b"a\n--$i;\nb\n"
    after = b"a\nb\n++$i;\n"
    added, removed = conformance_fix._added_and_removed(before, after)
    assert added == ["++$i;"]
    assert removed == 1


_VACUOUS = {
    "php": ("ibl5/tests/Fixer/PromisedTest.php",
            ["<?php\nfinal class PromisedTest extends TestCase\n{\n"
             "    public function testFoo(): void\n    {\n"
             "        $this->markTestSkipped('later');\n"
             "        $this->assertSame(1, 1);\n    }\n}\n",
             "<?php\nfinal class PromisedTest extends TestCase\n{\n"
             "    public function testFoo(): void\n    {\n        $x = 1;\n    }\n}\n"]),
    "py": ("tools/example/tests/test_promised.py",
           ["import pytest\n\ndef test_foo():\n    pytest.skip('later')\n    assert 1 == 1\n",
            "def test_foo():\n    x = 1\n"]),
    "ts": ("ibl5/tests/e2e/promised.spec.ts",
           ["import { test, expect } from '@playwright/test';\n"
            "test.skip('foo', async () => {\n  expect(1).toBe(1);\n});\n",
            "import { test } from '@playwright/test';\n"
            "test('foo', async () => {\n  const a = 1;\n});\n"]),
}


@pytest.mark.parametrize("lang", ["php", "py", "ts"])
def test_rejects_vacuous_test_with_skip_marker_or_no_assertion(tmp_path, lang):
    path, bodies = _VACUOUS[lang]
    item = f"MISSING: {path} (matrix planned a test the diff never wrote)"
    for n, body in enumerate(bodies):
        root = tmp_path / f"case{n}"
        root.mkdir()
        wt, git, llm = _setup(root, writes={path: body})
        rec = _remediate(root, [item], wt, git, llm)
        assert rec["reason"] == "rejected", (lang, n, rec)
        assert rec["detail"] == "vacuous-test", (lang, n, rec)
        assert not (wt / path).exists()
        assert git.commit_messages == []


def test_vacuous_checks_each_test_body():
    php = ("    public function testFoo(): void\n    {\n        $x = 1;\n    }\n"
           "    public function testBar(): void\n    {\n"
           "        $this->assertSame(1, 1);\n        $this->assertSame(2, 2);\n    }\n")
    assert conformance_fix._vacuous("ibl5/tests/A/FooTest.php", php)
    attr = ("    #[Test]\n    public function foo(): void\n    {\n"
            "        $this->assertSame(1, 1);\n    }\n")
    assert not conformance_fix._vacuous("ibl5/tests/A/FooTest.php", attr)
    py = "def test_a():\n    x = 1\ndef test_b():\n    assert 1\n    assert 2\n"
    assert conformance_fix._vacuous("tools/x/tests/test_a.py", py)


def test_rejects_test_path_with_no_test_declaration(tmp_path):
    helper_only = ("<?php\nfinal class PromisedTest extends TestCase\n{\n"
                   "    private function helper(): int\n    {\n        return 1;\n    }\n}\n")
    wt, git, llm = _setup(tmp_path, writes={PROMISED: helper_only})
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "rejected" and rec["detail"] == "vacuous-test"
    assert not (wt / PROMISED).exists()
    assert git.commit_messages == []
    assert conformance_fix._vacuous("tools/x/tests/test_a.py", "import os\n")


def test_rejects_missing_method_not_written(tmp_path):
    wrong = GOOD_PHP.replace("testFoo", "testBar")
    wt, git, llm = _setup(tmp_path, writes={PROMISED: wrong})
    rec = _remediate(tmp_path, [ITEM_METHOD], wt, git, llm)
    assert rec["reason"] == "rejected" and rec["detail"] == "method-not-written"
    assert not (wt / PROMISED).exists()
    assert git.commit_messages == []


def test_rejects_round_over_line_caps(tmp_path):
    # 151 added lines in one file.
    filler = "".join(f"        // filler {i}\n" for i in range(conformance_fix.MAX_ADDED_LINES_PER_FILE))
    big = GOOD_PHP.replace("    {\n        $this->assertSame", f"    {{\n{filler}        $this->assertSame", 1)
    assert big.count("\n") > conformance_fix.MAX_ADDED_LINES_PER_FILE
    wt, git, llm = _setup(tmp_path, writes={PROMISED: big})
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "rejected" and rec["detail"] == "cap-exceeded"
    assert not (wt / PROMISED).exists()
    assert git.commit_messages == []

    # 9 files (MAX_FILES + 1), each small and well-formed.
    root = tmp_path / "many"
    root.mkdir()
    n = conformance_fix.MAX_FILES + 1
    plan = _plan(extra_test_paths=n - 1)
    paths = [PROMISED] + [f"ibl5/tests/Fixer/Extra{i}Test.php" for i in range(n - 1)]
    assert conformance_fix.plan_test_paths(plan) == paths
    wt2, git2, llm2 = _setup(root, writes={p: GOOD_PHP for p in paths})
    rec2 = _remediate(root, [ITEM_METHOD], wt2, git2, llm2, plan=plan)
    assert rec2["reason"] == "rejected" and rec2["detail"] == "cap-exceeded"
    assert not any((wt2 / p).exists() for p in paths)
    assert git2.commit_messages == []


# --- crashes -----------------------------------------------------------------------

def test_crash_reverts_tree_and_records_crashed(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: "<?php // partial\n"},
                          raise_kind="subprocess-timeout")
    rec = _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert rec["reason"] == "crashed" and rec["detail"] == "subprocess-timeout"
    assert not (wt / PROMISED).exists()
    assert git.commit_messages == []


def test_usage_limit_crash_reverts_then_reraises(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: "<?php // partial\n"},
                          raise_kind="llm-usage-limit")
    with pytest.raises(HarnessError) as exc:
        _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert exc.value.kind == "llm-usage-limit"
    assert not (wt / PROMISED).exists()
    assert git.commit_messages == []


def test_rejected_commit_reverts_tree_then_reraises(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: GOOD_PHP})

    def _reject(_msg):
        raise HarnessError("local-gate", "pre-commit hook said no")

    with pytest.raises(HarnessError) as exc:
        conformance_fix.remediate_missing(
            llm, git, _plan(), str(wt), str(tmp_path / "out"), [ITEM_PATH],
            commit=_reject, push=lambda: "", env={})
    assert exc.value.kind == "local-gate"
    assert not (wt / PROMISED).exists()
    assert git.unstaged == [[PROMISED]]
    assert git.pushes == 0


def test_empty_commit_sha_reverts_tree(tmp_path):
    wt, git, llm = _setup(tmp_path, writes={PROMISED: GOOD_PHP})
    rec = conformance_fix.remediate_missing(
        llm, git, _plan(), str(wt), str(tmp_path / "out"), [ITEM_PATH],
        commit=lambda _msg: "", push=lambda: "", env={})
    assert rec["reason"] == "no-edits"
    assert not (wt / PROMISED).exists()
    assert git.unstaged == [[PROMISED]]
    assert git.pushes == 0


# --- post-commit gate-path check ----------------------------------------------------

class _GateHitGit(RealRestoreGit):
    def changed_files(self, base="origin/master"):
        return [".github/workflows/x.yml"] if base.endswith("^") else super().changed_files(base)


def test_post_commit_gate_path_hit_raises_gate_path_edit(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    git = _GateHitGit(_fixture(), str(wt))
    llm = WritingLlm(UsageLedger(), {"conformance-fix": "done"}, writes={PROMISED: GOOD_PHP}, git=git)
    with pytest.raises(HarnessError) as exc:
        _remediate(tmp_path, [ITEM_PATH], wt, git, llm)
    assert exc.value.kind == "gate-path-edit"
    assert git.pushes == 0
    assert git.commit_messages == [conformance_fix.COMMIT_MSG]


# --- hold note + CLI ------------------------------------------------------------------

def test_hold_note_shapes():
    assert conformance_fix.hold_note({}) == ""
    committed = {"reason": "committed", "written_paths": ["a.php"],
                 "sha": "abcdef0123456789", "still_missing": []}
    assert conformance_fix.hold_note(committed) == "wrote 1 file(s) in abcdef012345; still missing 0"
    assert conformance_fix.hold_note({"reason": "rejected", "detail": "vacuous-test"}) \
        == "rejected (vacuous-test)"
    assert conformance_fix.hold_note({"reason": "crashed", "detail": "subprocess-timeout"}) \
        == "crashed (subprocess-timeout)"
    assert conformance_fix.hold_note({"reason": "no-fixable-items"}) == "no-fixable-items"


def _cli(args, stdin_text=""):
    return subprocess.run([sys.executable, "-m", "harness.conformance_fix", *args],
                          input=stdin_text, capture_output=True, text=True, cwd=HARNESS_ROOT)


def test_classify_cli_prints_fixable_and_unfixable_lines(tmp_path):
    plan_path = tmp_path / "plan.md"
    plan_path.write_text(PLAN_TEXT)
    phase_item = "MISSING-PHASE: 2 — B (phase cites harness/b.py; none appeared in the diff)"
    proc = _cli(["classify", "--plan", str(plan_path)], f"{ITEM_METHOD}\n{phase_item}\n")
    assert proc.returncode == 0, proc.stderr
    lines = proc.stdout.splitlines()
    assert lines == [f"FIXABLE method {ITEM_METHOD}", f"UNFIXABLE never-fixable {phase_item}"]
    missing = _cli(["classify", "--plan", str(tmp_path / "absent.md")], ITEM_METHOD + "\n")
    assert missing.returncode == 1
    assert missing.stdout == ""
