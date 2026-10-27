"""Phase 5.5 delta re-review: a moved tree after a prior full review.

Phase 1 pins today's behaviour (a moved diff always runs the full reviewer) before any
production edit. Later sections extend the file with the delta module's unit tests and the
multi-invocation suite.
"""
import hashlib
import os
import pathlib
import shutil
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.ghad import RecordingGh
import runner

TREE = "a" * 40
TREE2 = "2" * 40
DIFF_ID = "e" * 40
PLAN_HASH = "b" * 64
VERSION = "v" * 64

DIFF = """\
diff --git a/a.py b/a.py
index aaaaaaa..bbbbbbb 100644
--- a/a.py
+++ b/a.py
@@ -1,3 +1,4 @@
 # module a
+# first change
 import re
 import os
diff --git a/b.py b/b.py
index ccccccc..ddddddd 100644
--- a/b.py
+++ b/b.py
@@ -1,3 +1,4 @@
 # module b
+# second change
 import sys
 import json
"""

DIFF_MOVED = DIFF + """\
diff --git a/a.py b/a.py
index bbbbbbb..eeeeeee 100644
--- a/a.py
+++ b/a.py
@@ -10,3 +10,4 @@
 def helper():
+    return 1
     pass
"""

NOT_READY_TEXT = ("## 6d checks\n\n## FINDINGS\n- [BLOCKING] a.py: phase 2 helper missing\n\n"
                  "NOT READY\n")
READY_TEXT = "6d checks\n\nREADY\n"


def _sticky(verdict="READY", diff_id=DIFF_ID, plan_hash=PLAN_HASH, tree=TREE, **fid_extra):
    """Build a sticky body through the real composer, so the parser and the writer
    can never drift apart in this test file."""
    fid = {"verdict_1": verdict, "reviewed_tree": tree, "error_kind": None, **fid_extra}
    return fidelity.compose_sticky(
        "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
        fidelity.terminal_line(verdict, None, None, None, None, 0),
        diff_id=diff_id, plan_hash=plan_hash)


@pytest.fixture(autouse=True)
def _cleanup_verdict_99():
    """Real-review tests (pr=99) write the verdict file; remove it afterwards."""
    yield
    try:
        os.unlink(fidelity.verdict_path(99))
    except FileNotFoundError:
        pass


@pytest.fixture
def git_shim(tmp_path, monkeypatch):
    """Shims git for _run_fidelity tests. Passes `git patch-id` through to the real binary."""
    real_git = shutil.which("git")
    shim_bin = tmp_path / "shimbin"
    shim_bin.mkdir()
    shim = shim_bin / "git"
    shim.write_text(f"""\
#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
if [ "$1" = "patch-id" ]; then
  exec /usr/bin/env -u PATH_SHIM REAL_GIT="$REAL_GIT" "{real_git}" "$@"
fi
exit 0
""")
    shim.chmod(shim.stat().st_mode | stat.S_IEXEC | stat.S_IXGRP | stat.S_IXOTH)
    monkeypatch.setenv("PATH", str(shim_bin) + ":" + os.environ.get("PATH", ""))
    monkeypatch.setenv("REAL_GIT", real_git or "git")
    yield str(shim_bin)


def _git(head_trees=None, diff=DIFF):
    """Minimal ReplayGit-like object for _run_fidelity."""
    trees = (head_trees or [TREE, TREE])[:]

    class _G:
        def is_dirty(self):
            return True

        def head(self):
            return "c" * 40

        def head_tree(self):
            return trees[0] if trees else TREE

        def diff_vs_base(self):
            return diff

        def fetch_base(self):
            return "deadbeef" * 5

        def rebase_onto(self, sha):
            if trees:
                trees.pop(0)
            return "dead" * 10

        def push(self, branch):
            pass

    return _G()


def _drive(tmp_path, prior_sticky, plan_body=b"# plan\n", canned=None, diff=DIFF,
           tree=TREE, verdict_text=READY_TEXT, purposes_out=None,
           state_dir=None, slug="", head_tree=None):
    """Run _run_fidelity in live mode against RecordingGh.

    `state_dir` / `slug` are forwarded only when set, so the same driver runs against the
    unchanged signature (Phase 1) and the state-dir kwargs (Phase 4 on).
    `head_tree` is the tree `git.head_tree()` reports at record time (default `tree`).
    """
    tmp_path = pathlib.Path(tmp_path)
    tmp_path.mkdir(parents=True, exist_ok=True)

    def _tmp_verdict_path(pr_number):
        return str(tmp_path / f"verdict-{pr_number}.md")

    if verdict_text is not None:
        pathlib.Path(_tmp_verdict_path(99)).write_text(verdict_text)
    plan_file = tmp_path / "plan.md"
    plan_file.write_bytes(plan_body)
    plan_obj = types.SimpleNamespace(found=True, path=str(plan_file), auto_merge_false=False)
    gh = RecordingGh(str(tmp_path), {"prior_sticky_body": prior_sticky})
    logs = []

    from harness.state import UsageLedger
    from harness.adapters.llm import FixtureLlm
    llm = FixtureLlm(UsageLedger(), canned or {})

    spawn_count = [0]
    orig_review = runner.fidelity.review
    orig_verdict_path = runner.fidelity.verdict_path

    def counting_review(*a, **kw):
        spawn_count[0] += 1
        return orig_review(*a, **kw)

    from harness.state import TerminalState
    res = runner.RunResult(terminal=TerminalState.FAILED)
    kw = {}
    if state_dir is not None:
        kw["state_dir"] = state_dir
    if slug:
        kw["slug"] = slug
    end_tree = head_tree or tree
    try:
        runner.fidelity.review = counting_review
        runner.fidelity.verdict_path = _tmp_verdict_path
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _git(head_trees=[end_tree, end_tree], diff=diff),
            gh, plan_obj, diff, "body", 99, "dead" * 10, tree, True, logs.append, res,
            cache_version=VERSION, **kw)
    finally:
        runner.fidelity.review = orig_review
        runner.fidelity.verdict_path = orig_verdict_path
    if purposes_out is not None:
        purposes_out.extend(c.purpose for c in llm.ledger.calls)
    return res, spawn_count[0], logs


# --- Phase 1: characterization pins (pass before and after the change) ----------

@pytest.mark.usefixtures("git_shim")
def test_pin_moved_tree_after_prior_full_runs_full_reviewer(tmp_path):
    p1 = []
    res, _, _ = _drive(tmp_path, prior_sticky=None, diff=DIFF,
                       canned={"plan-fidelity-review": NOT_READY_TEXT}, purposes_out=p1)
    assert p1 == ["plan-fidelity-review"]
    assert res.fidelity["verdict_1"] == "NOT READY"

    plan_hash = hashlib.sha256(b"# plan\n").hexdigest()
    sticky = _sticky(verdict="NOT READY", diff_id=fidelity.diff_patch_id(DIFF),
                     plan_hash=plan_hash)
    p2 = []
    _, spawns, logs = _drive(tmp_path, prior_sticky=sticky, diff=DIFF_MOVED, tree=TREE2,
                             canned={"plan-fidelity-review": READY_TEXT}, purposes_out=p2)
    assert p2 == ["plan-fidelity-review"]
    assert spawns == 1
    assert any(l.startswith("phase5.5 fidelity: review: full (carry-forward declined: ")
               for l in logs)
    assert not any("review: delta" in l for l in logs)


@pytest.mark.usefixtures("git_shim")
def test_pin_no_state_dir_never_writes_a_delta_record(tmp_path):
    _drive(tmp_path, prior_sticky=None, diff=DIFF,
           canned={"plan-fidelity-review": NOT_READY_TEXT})
    plan_hash = hashlib.sha256(b"# plan\n").hexdigest()
    sticky = _sticky(verdict="NOT READY", diff_id=fidelity.diff_patch_id(DIFF),
                     plan_hash=plan_hash)
    _drive(tmp_path, prior_sticky=sticky, diff=DIFF_MOVED, tree=TREE2,
           canned={"plan-fidelity-review": READY_TEXT})
    assert not [n for n in os.listdir(tmp_path) if n.endswith(".fidelity-delta.json")]
