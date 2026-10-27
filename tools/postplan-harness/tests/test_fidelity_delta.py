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


# --- Phase 2: record schema, pure helpers, eligibility ---------------------------

import copy  # noqa: E402
import json  # noqa: E402

from harness import fidelity_delta, reviewcache  # noqa: E402
from harness.adapters.llm import FixtureLlm  # noqa: E402
from harness.state import HarnessError, UsageLedger  # noqa: E402

ANCHOR_DIFF_ID = "1" * 40
FINDINGS = "- [BLOCKING] a.py: phase 2 helper missing\n"
PLAN_TEXT = ("---\ntitle: p\n---\nPreamble.\n\n"
             "## Phase 1 — W\nw body\n\n"
             "## Phase 2 — X\nx body touches a.py and b.py\n\n"
             "## Phase 3 — Y\ny body\n\n"
             "## Critical Files\n- a.py\n")
DELTA_READY_TEXT = ("## 6c\nDELTA-ANCHOR: live-prior\nREVIEW-COVERAGE: NONE\n\n## 6d\n"
                    "- [BLOCKING] a.py: phase 2 helper missing — RESOLVED by hunk 2\n\nREADY\n")


def _fid(seed):
    return hashlib.sha1(seed.encode()).hexdigest()


def _file_diff(path, n_added):
    body = "".join(f"+added {i}\n" for i in range(n_added))
    return (f"diff --git a/{path} b/{path}\nindex 1111111..2222222 100644\n"
            f"--- a/{path}\n+++ b/{path}\n@@ -1,1 +1,{n_added + 1} @@\n context\n" + body)


def _world_diff(sizes):
    return "".join(_file_diff(p, n) for p, n in sizes.items())


def _record(anchor_ids, **over):
    kw = dict(slug="s", pr_number=99, run_id="live-prior", version=VERSION,
              plan_hash=PLAN_HASH, verdict="NOT READY", diff_id=ANCHOR_DIFF_ID,
              per_file_ids=anchor_ids, reviewed_tree=TREE, findings=FINDINGS, full_lines=2)
    kw.update(over)
    return fidelity_delta.build_anchor_record(**kw)


def _elig_kwargs(changed=None, unchanged=None, plan_text=None):
    """A valid baseline eligibility call: `changed` paths moved since the anchor."""
    changed = {"a.py": 10} if changed is None else changed
    unchanged = {"b.py": 30} if unchanged is None else unchanged
    anchor_ids = {**{p: _fid("old" + p) for p in changed},
                  **{p: _fid(p) for p in unchanged}}
    current_ids = {**{p: _fid("new" + p) for p in changed},
                   **{p: _fid(p) for p in unchanged}}
    if plan_text is None:
        plan_text = ("## Phase 2 — X\n" + " ".join([*changed, *unchanged])
                     + "\n\n## Phase 3 — Y\nnothing\n")
    return dict(
        live=True, state_dir="/unused", record=_record(anchor_ids), pr_number=99,
        version=VERSION, plan_hash=PLAN_HASH, diff_id=DIFF_ID,
        current_per_file_ids=current_ids,
        sticky_body=_sticky(verdict="NOT READY", diff_id=ANCHOR_DIFF_ID, plan_hash=PLAN_HASH),
        plan_text=plan_text, current_diff=_world_diff({**changed, **unchanged}))


def _kw(**over):
    kw = _elig_kwargs()
    kw.update(over)
    return kw


def _kw_rec(edit):
    kw = _elig_kwargs()
    rec = copy.deepcopy(kw["record"])
    edit(rec)
    kw["record"] = rec
    return kw


def _kw_two_deltas():
    kw = _elig_kwargs()
    rec = fidelity_delta.append_delta(kw["record"], run_id="r2", verdict="READY",
                                      diff_id="2" * 40, delta_paths=["a.py"], reviewed_tree=TREE)
    rec = fidelity_delta.append_delta(rec, run_id="r3", verdict="READY",
                                      diff_id="3" * 40, delta_paths=["a.py"], reviewed_tree=TREE)
    kw["record"] = rec
    kw["sticky_body"] = _sticky(verdict="READY", diff_id="3" * 40, plan_hash=PLAN_HASH)
    return kw


def _kw_no_delta():
    kw = _elig_kwargs()
    kw["current_per_file_ids"] = dict(kw["record"]["anchor"]["per_file_ids"])
    return kw


def _kw_new_file():
    kw = _elig_kwargs()
    kw["current_per_file_ids"]["c.py"] = _fid("c")
    return kw


def _kw_removed_file():
    kw = _elig_kwargs()
    del kw["current_per_file_ids"]["b.py"]
    return kw


def test_record_round_trip(tmp_path):
    state = str(tmp_path / "state")
    rec = _record({"a.py": _fid("a"), "b.py": _fid("b")})
    assert rec is not None
    assert fidelity_delta.save_record(state, "s", rec) is True
    assert fidelity_delta.load_record(state, "s") == rec
    before = copy.deepcopy(rec)
    rec2 = fidelity_delta.append_delta(rec, run_id="r2", verdict="READY", diff_id="2" * 40,
                                       delta_paths=["a.py"], reviewed_tree=TREE2)
    assert rec == before                      # append_delta never mutates its input
    assert rec2["anchor"] == rec["anchor"]
    assert len(rec2["deltas"]) == 1
    assert fidelity_delta.save_record(state, "s", rec2) is True
    assert fidelity_delta.load_record(state, "s") == rec2
    fidelity_delta.clear_record(state, "s")
    assert fidelity_delta.load_record(state, "s") is None
    fidelity_delta.clear_record(state, "s")   # idempotent


def _doc_edit(edit):
    def build():
        doc = _record({"a.py": _fid("a")})
        edit(doc)
        return json.dumps(doc).encode()
    return build


_GARBLED = {
    "non-json": lambda: b"not json{",
    "empty-list": lambda: b"[]",
    "schema-only": lambda: json.dumps({"schema_version": 2}).encode(),
    "schema-version-2-otherwise-valid": _doc_edit(lambda d: d.update(schema_version=2)),
    "missing-anchor": _doc_edit(lambda d: d.pop("anchor")),
    "empty-per-file-ids": _doc_edit(lambda d: d["anchor"].update(per_file_ids={})),
    "non-hex-diff-id": _doc_edit(lambda d: d["anchor"].update(diff_id="zz" * 20)),
    "findings-too-big": _doc_edit(
        lambda d: d["anchor"].update(findings="x" * (fidelity_delta.MAX_FINDINGS_BYTES + 1))),
    "deltas-string": _doc_edit(lambda d: d.update(deltas="oops")),
    "full-lines-string": _doc_edit(lambda d: d["anchor"].update(full_lines="12")),
}


@pytest.mark.parametrize("name", sorted(_GARBLED))
def test_load_record_rejects_garbled_shapes(tmp_path, name):
    state = str(tmp_path / "state")
    os.makedirs(state)
    with open(fidelity_delta.record_path(state, "s"), "wb") as fh:
        fh.write(_GARBLED[name]())
    assert fidelity_delta.load_record(state, "s") is None


def test_load_record_accepts_a_valid_doc_and_missing_file(tmp_path):
    state = str(tmp_path / "state")
    assert fidelity_delta.load_record(state, "s") is None      # no file
    assert fidelity_delta.save_record(state, "s", _record({"a.py": _fid("a")}))
    assert fidelity_delta.load_record(state, "s") is not None  # control for the garbled set


def test_findings_at_the_byte_cap_load(tmp_path):
    state = str(tmp_path / "state")
    doc = _record({"a.py": _fid("a")}, findings="x" * fidelity_delta.MAX_FINDINGS_BYTES)
    assert doc is not None
    assert _record({"a.py": _fid("a")},
                   findings="x" * (fidelity_delta.MAX_FINDINGS_BYTES + 1)) is None
    assert fidelity_delta.save_record(state, "s", doc)
    assert fidelity_delta.load_record(state, "s") is not None


def test_save_record_atomic_and_fail_soft(tmp_path):
    state = str(tmp_path / "state")
    rec = _record({"a.py": _fid("a")})
    assert fidelity_delta.save_record(state, "s", rec) is True
    assert not [n for n in os.listdir(state) if n.endswith(".tmp")]
    blocker = tmp_path / "plainfile"
    blocker.write_text("x")
    assert fidelity_delta.save_record(str(blocker / "sub"), "s", rec) is False
    assert fidelity_delta.save_record(str(blocker), "s", rec) is False
    assert blocker.read_text() == "x"


@pytest.mark.parametrize("reason,build", [
    ("not-live", lambda: _kw(live=False)),
    ("no-plan-hash", lambda: _kw(plan_hash="")),
    ("no-diff-id", lambda: _kw(diff_id="")),
    ("no-state-dir", lambda: _kw(state_dir="")),
    ("no-record", lambda: _kw(record=None)),
    ("pr-mismatch", lambda: _kw(pr_number=100)),
    ("version-changed", lambda: _kw(version="w" * 64)),
    ("plan-changed", lambda: _kw(plan_hash="c" * 64)),
    ("anchor-verdict-unusable", lambda: _kw_rec(lambda r: r["anchor"].update(verdict="MAYBE"))),
    ("anchor-ids-missing", lambda: _kw_rec(lambda r: r["anchor"].update(per_file_ids={}))),
    ("sticky-missing", lambda: _kw(sticky_body=None)),
    ("sticky-mismatch", lambda: _kw(
        sticky_body=_sticky(verdict="NOT READY", diff_id="3" * 40, plan_hash=PLAN_HASH))),
    ("sticky-plan-mismatch", lambda: _kw(
        sticky_body=_sticky(verdict="NOT READY", diff_id=ANCHOR_DIFF_ID, plan_hash="c" * 64))),
    ("delta-cap", _kw_two_deltas),
    ("current-ids-missing", lambda: _kw(current_per_file_ids={})),
    ("no-delta", _kw_no_delta),
    ("scope-changed", _kw_new_file),
    ("gate-surface", lambda: _elig_kwargs(changed={".github/workflows/x.yml": 5})),
    ("unplanned-path", lambda: _elig_kwargs(
        changed={"a.py": 10, "z.py": 5}, plan_text="## Phase 2 — X\na.py b.py\n")),
    ("too-many-files", lambda: _elig_kwargs(
        changed={f"f{i}.py": 5 for i in range(9)}, unchanged={"b.py": 100})),
    ("too-large", lambda: _elig_kwargs(changed={"a.py": 201}, unchanged={"b.py": 300})),
    ("delta-ratio", lambda: _elig_kwargs(changed={"a.py": 10}, unchanged={"b.py": 5})),
    ("no-plan-sections", lambda: _elig_kwargs(
        plan_text="a.py b.py named only in the preamble\n## Phase 2 — X\nnothing\n")),
], ids=lambda v: v if isinstance(v, str) else "")
def test_eligibility_decline_order(reason, build):
    ctx, got = fidelity_delta.eligibility(**build())
    assert (ctx, got) == (None, reason)


def test_eligibility_baseline_is_eligible():
    ctx, reason = fidelity_delta.eligibility(**_elig_kwargs())
    assert reason is None
    assert ctx["delta_paths"] == ["a.py"]
    assert (ctx["delta_lines"], ctx["full_lines"], ctx["deltas_so_far"]) == (10, 40, 0)
    assert ctx["sections"] == ["Phase 2 — X"]
    assert "b/a.py" in ctx["delta_diff"] and "b/b.py" not in ctx["delta_diff"]
    assert set(fidelity_delta.DECLINE_REASONS[:23]) >= {
        "not-live", "no-plan-sections"}


@pytest.mark.parametrize("changed,unchanged,expect", [
    ({f"f{i}.py": 25 for i in range(8)}, {"b.py": 200}, None),            # 200 lines, 8 files, ratio 0.5
    ({"a.py": 201}, {"b.py": 201}, "too-large"),
    ({f"f{i}.py": 1 for i in range(8)}, {"b.py": 100}, None),             # 8 files ok
    ({f"f{i}.py": 1 for i in range(9)}, {"b.py": 100}, "too-many-files"),
    ({"a.py": 50}, {"b.py": 50}, None),                                   # ratio exactly 0.5
    ({"a.py": 51}, {"b.py": 49}, "delta-ratio"),                          # ratio 0.51
])
def test_eligibility_threshold_boundaries(changed, unchanged, expect):
    ctx, reason = fidelity_delta.eligibility(**_elig_kwargs(changed=changed, unchanged=unchanged))
    assert reason == expect
    assert (ctx is None) == (expect is not None)


def test_eligibility_plan_critical_criteria():
    # New file and removed file both change the review scope.
    assert fidelity_delta.eligibility(**_kw_new_file()) == (None, "scope-changed")
    assert fidelity_delta.eligibility(**_kw_removed_file()) == (None, "scope-changed")
    # A path the plan never names is unplanned.
    kw = _elig_kwargs(changed={"a.py": 10, "z.py": 5},
                      plan_text="## Phase 2 — X\na.py b.py\n")
    assert fidelity_delta.eligibility(**kw) == (None, "unplanned-path")
    # Lockfiles and the phpstan baseline are exempt from the unplanned-path arm.
    kw = _elig_kwargs(
        changed={"a.py": 10, "ibl5/composer.lock": 3, "ibl5/bun.lock": 3,
                 "ibl5/phpstan-baseline.neon": 3},
        plan_text="## Phase 2 — X\na.py b.py\n")
    ctx, reason = fidelity_delta.eligibility(**kw)
    assert reason is None
    assert "ibl5/composer.lock" in ctx["delta_paths"]
    # Gate-surface paths always decline, even when the plan names them.
    assert fidelity.LOCAL_GATE_PATH_PREFIXES
    for path in (".github/workflows/x.yml", ".claude/rules/x.md",
                 "tools/postplan-harness/runner.py", "bin/lib/x.sh"):
        assert fidelity_delta.eligibility(
            **_elig_kwargs(changed={path: 5})) == (None, "gate-surface"), path


def test_sticky_compares_to_last_reviewed_diff():
    kw = _elig_kwargs()
    rec = fidelity_delta.append_delta(kw["record"], run_id="r2", verdict="READY",
                                      diff_id="2" * 40, delta_paths=["a.py"], reviewed_tree=TREE)
    kw["record"] = rec
    # The sticky still names the anchor's diff, but the last reviewed diff is the delta's.
    kw["sticky_body"] = _sticky(verdict="NOT READY", diff_id=ANCHOR_DIFF_ID,
                                plan_hash=PLAN_HASH)
    assert fidelity_delta.eligibility(**kw) == (None, "sticky-mismatch")
    kw["sticky_body"] = _sticky(verdict="READY", diff_id="2" * 40, plan_hash=PLAN_HASH)
    ctx, reason = fidelity_delta.eligibility(**kw)
    assert reason is None and ctx["deltas_so_far"] == 1


def test_delta_cap_and_sticky_last_diff():
    kw = _kw_two_deltas()
    assert fidelity_delta.eligibility(**kw) == (None, "delta-cap")
    kw["sticky_body"] = _sticky(verdict="READY", diff_id="2" * 40, plan_hash=PLAN_HASH)
    assert fidelity_delta.eligibility(**kw) == (None, "sticky-mismatch")
    kw["sticky_body"] = _sticky(verdict="NOT READY", diff_id=ANCHOR_DIFF_ID,
                                plan_hash=PLAN_HASH)
    assert fidelity_delta.eligibility(**kw) == (None, "sticky-mismatch")


def test_sections_touching_always_includes_critical_files():
    plan = ("preamble\n## Phase 1 — W\nnothing\n## Phase 2 — X\ntouches a.py\n"
            "## Critical Files\n- other.py\n")
    assert fidelity_delta.sections_touching(plan, ["a.py"]) == [
        "Phase 2 — X", "Critical Files"]
    # A Critical Files section that itself names the path is not duplicated.
    plan2 = plan.replace("other.py", "a.py")
    assert fidelity_delta.sections_touching(plan2, ["a.py"]) == [
        "Phase 2 — X", "Critical Files"]
    # Basename match; no Critical Files and no match gives nothing.
    assert fidelity_delta.sections_touching(plan, ["dir/a.py"]) == [
        "Phase 2 — X", "Critical Files"]
    assert fidelity_delta.sections_touching("## P\nnothing here\n", ["a.py"]) == []


def test_unplanned_paths_exempts_generated_files():
    plan = "the plan names a.py and sub/c.py only"
    paths = ["a.py", "ibl5/bun.lock", "ibl5/composer.lock", "ibl5/phpstan-baseline.neon",
             "z.py", "other/a.py", "sub/c.py"]
    assert fidelity_delta.unplanned_paths(plan, paths) == ["z.py"]
    assert fidelity_delta.unplanned_paths("", ["ibl5/bun.lock"]) == []


def test_changed_lines_ignores_headers():
    diff = ("diff --git a/x b/x\nindex 1..2 100644\n--- a/x\n+++ b/x\n@@ -1,2 +1,2 @@\n"
            " ctx\n+added\n-removed\n+another\n")
    assert fidelity_delta.changed_lines(diff) == 3
    assert fidelity_delta.changed_lines("") == 0
    assert fidelity_delta.changed_lines(None) == 0


# --- Phase 3: excerpt, packet, attempt --------------------------------------------

def test_plan_excerpt_sections():
    plan = ("---\ntitle: t\n---\nIntro text.\n\n"
            "## Phase 1 — W\nw body\n\n## Phase 2 — X\nx body\n\n"
            "## Phase 3 — Y\ny body\n\n## Critical Files\n- a.py\n")
    out = fidelity_delta.plan_excerpt(plan, ["Phase 2 — X"])
    assert out.startswith("---\ntitle: t\n---\nIntro text.")
    assert "## Phase 2 — X\nx body" in out
    assert "y body" not in out and "w body" not in out
    head, omitted = out.split("## Omitted sections (delta review)", 1)
    assert "- Phase 3 — Y" in omitted and "- Phase 1 — W" in omitted
    assert "- Critical Files" in omitted
    assert "y body" not in head
    # With the Critical Files section selected (what sections_touching always adds):
    titles = fidelity_delta.sections_touching(plan, ["a.py"])
    assert titles == ["Critical Files"]
    out2 = fidelity_delta.plan_excerpt(plan, titles)
    head2, omitted2 = out2.split("## Omitted sections (delta review)", 1)
    assert "## Critical Files\n- a.py" in head2
    assert "- Critical Files" not in omitted2
    assert fidelity_delta.plan_excerpt(plan, [t for t, _ in fidelity_delta.plan_sections(plan)
                                              if t]).endswith("(none)\n")


@pytest.mark.usefixtures("git_shim")
def test_build_delta_packet_contents(tmp_path):
    anchor = _record({"a.py": _fid("a"), "b.py": _fid("b")},
                     findings="- [BLOCKING] a.py: x\n\nno trailing newline")["anchor"]
    ctx = {"anchor": anchor, "delta_paths": ["a.py"],
           "delta_diff": reviewcache.subset_diff(DIFF, {"a.py"}),
           "delta_lines": 1, "full_lines": 2, "sections": ["Phase 2 — X"],
           "deltas_so_far": 0}
    packet = fidelity_delta.build_delta_packet(
        str(tmp_path / "out"), "dead" * 10, TREE, PLAN_TEXT, ctx, "pr body", 99, False,
        worktree=str(tmp_path), review_findings="")
    assert os.path.basename(packet) == fidelity_delta.PACKET_NAME
    read = lambda n: pathlib.Path(packet, n).read_text()  # noqa: E731
    patch = read("diff.patch")
    assert "b/a.py" in patch and "b/b.py" not in patch
    plan = read("plan.md")
    assert "x body touches" in plan
    assert "y body" not in plan and "w body" not in plan
    assert "## Omitted sections (delta review)" in plan
    assert read("anchor-findings.md") == anchor["findings"]
    ctx_md = read("context.md")
    assert "REVIEW_KIND: delta" in ctx_md
    assert "DELTA_ANCHOR_RUN: live-prior" in ctx_md
    assert "DELTA_LINES: 1 of 2" in ctx_md
    assert "PLAN_SECTIONS: Phase 2 — X" in ctx_md
    assert read("delta-paths.txt") == "a.py\n"
    assert read("pr-body.md") == "pr body"


def _run_attempt(tmp_path, monkeypatch, *, text=None, llm=None):
    """attempt() against a real on-disk anchor record; DIFF is the anchor, DIFF_MOVED moves a.py."""
    tmp_path = pathlib.Path(tmp_path)
    plan_bytes = PLAN_TEXT.encode()
    plan_file = tmp_path / "plan.md"
    plan_file.write_bytes(plan_bytes)
    plan_hash = hashlib.sha256(plan_bytes).hexdigest()
    state = str(tmp_path / "state")
    anchor_diff_id = fidelity.diff_patch_id(DIFF)
    rec = _record(reviewcache.per_file_patch_ids(DIFF), plan_hash=plan_hash,
                  diff_id=anchor_diff_id)
    assert rec is not None
    assert fidelity_delta.save_record(state, "s", rec)
    vpath = str(tmp_path / "verdict-99.md")
    monkeypatch.setattr(fidelity, "verdict_path", lambda pr: vpath)
    gh = RecordingGh(str(tmp_path), {"prior_sticky_body": _sticky(
        verdict="NOT READY", diff_id=anchor_diff_id, plan_hash=plan_hash)})
    if llm is None:
        llm = FixtureLlm(UsageLedger(), {fidelity_delta.PURPOSE: text})
    plan_obj = types.SimpleNamespace(found=True, path=str(plan_file), auto_merge_false=False)
    logs = []
    out = fidelity_delta.attempt(
        llm, str(tmp_path / "out"), str(tmp_path), gh, plan_obj, DIFF_MOVED, "body", 99,
        "dead" * 10, TREE2, True, logs.append, state_dir=state, slug="s",
        plan_hash=plan_hash, diff_id=fidelity.diff_patch_id(DIFF_MOVED),
        cache_version=VERSION, phase4b_ran=False, review_findings="")
    return out, logs, llm, vpath


_NO_ECHO = DELTA_READY_TEXT.replace("DELTA-ANCHOR: live-prior\n", "")


@pytest.mark.usefixtures("git_shim")
@pytest.mark.parametrize("text", [
    _NO_ECHO,
    DELTA_READY_TEXT.replace("live-prior", "some-other-run"),
    DELTA_READY_TEXT.replace("\nREADY\n", "\nPROBABLY FINE\n"),
], ids=["no-echo", "wrong-echo", "unknown-verdict-word"])
def test_attempt_rejects_candidate_without_anchor_echo(tmp_path, monkeypatch, text):
    out, logs, _, vpath = _run_attempt(tmp_path, monkeypatch, text=text)
    assert out is None
    assert "phase5.5 fidelity: delta declined (verdict-unparsed)" in logs
    assert not os.path.exists(vpath)


@pytest.mark.usefixtures("git_shim")
def test_attempt_declines_when_spawn_raises(tmp_path, monkeypatch):
    class _Boom:
        def call_tooled(self, *a, **kw):
            raise HarnessError("llm-cli", "boom")

    out, logs, _, vpath = _run_attempt(tmp_path, monkeypatch, llm=_Boom())
    assert out is None
    assert "phase5.5 fidelity: delta declined (spawn-failed:llm-cli)" in logs
    assert not os.path.exists(vpath)


@pytest.mark.usefixtures("git_shim")
def test_attempt_writes_verdict_file_with_reviewed_tree(tmp_path, monkeypatch):
    out, logs, llm, vpath = _run_attempt(tmp_path, monkeypatch, text=DELTA_READY_TEXT)
    assert out is not None, logs
    verdict, err, info = out
    assert (verdict, err) == ("READY", "")
    assert info["review_kind"] == "delta"
    assert info["delta_anchor"] == "live-prior" and info["delta_paths"] == ["a.py"]
    assert info["delta_index"] == 1
    assert fidelity.read_reviewed_tree(vpath) == TREE2
    assert pathlib.Path(vpath).read_text().startswith(DELTA_READY_TEXT)
    assert {c.purpose for c in llm.ledger.calls} == {fidelity_delta.PURPOSE}
    assert any(l.startswith("phase5.5 fidelity: review: delta (anchor live-prior") for l in logs)


# --- Phase 4: runner wiring and anchor recording ---------------------------------

def _state_of(tmp_path):
    return str(pathlib.Path(tmp_path) / "state")


@pytest.mark.usefixtures("git_shim")
def test_full_run_writes_anchor_record(tmp_path):
    state = _state_of(tmp_path)
    res, _, logs = _drive(tmp_path, prior_sticky=None, diff=DIFF, state_dir=state, slug="s",
                          canned={"plan-fidelity-review": NOT_READY_TEXT})
    assert res.fidelity["rounds_completed"] == 0, logs
    rec = fidelity_delta.load_record(state, "s")
    assert rec is not None, logs
    assert rec["anchor"]["verdict"] == "NOT READY"
    assert set(rec["anchor"]["per_file_ids"]) == {"a.py", "b.py"}
    assert "phase 2 helper missing" in rec["anchor"]["findings"]
    assert rec["deltas"] == []
    assert rec["anchor"]["reviewed_tree"] == TREE
    assert rec["plan_hash"] == hashlib.sha256(b"# plan\n").hexdigest()


@pytest.mark.usefixtures("git_shim")
def test_moved_tree_clears_record(tmp_path):
    state = _state_of(tmp_path)
    res, _, logs = _drive(tmp_path, prior_sticky=None, diff=DIFF, state_dir=state, slug="s",
                          tree=TREE, head_tree=TREE2,
                          canned={"plan-fidelity-review": NOT_READY_TEXT})
    assert res.fidelity["verdict_1"] == "NOT READY"
    assert not os.path.exists(fidelity_delta.record_path(state, "s")), logs
    assert any("delta record: cleared (tree-moved)" in l for l in logs)


@pytest.mark.usefixtures("git_shim")
def test_indeterminate_review_clears_record(tmp_path):
    state = _state_of(tmp_path)
    seeded = _record({"a.py": _fid("a")})
    assert fidelity_delta.save_record(state, "s", seeded)
    assert os.path.exists(fidelity_delta.record_path(state, "s"))
    res, _, logs = _drive(tmp_path, prior_sticky=None, diff=DIFF, state_dir=state, slug="s",
                          canned={"plan-fidelity-review": {"raise": {"kind": "llm-cli"}}})
    assert res.fidelity["verdict_1"] is None and res.fidelity["error_kind"]
    assert not os.path.exists(fidelity_delta.record_path(state, "s")), logs


@pytest.mark.usefixtures("git_shim")
def test_no_state_dir_skips_record(tmp_path):
    _, _, logs = _drive(tmp_path, prior_sticky=None, diff=DIFF,
                        canned={"plan-fidelity-review": NOT_READY_TEXT})
    assert any("delta record: skipped (no state dir)" in l for l in logs)
    found = [n for _, _, names in os.walk(tmp_path) for n in names
             if n.endswith(fidelity_delta.RECORD_SUFFIX)]
    assert found == []


# --- Phase 5: a delta verdict is never cached --------------------------------------

def _delta_fid(**over):
    fid = {"verdict_1": "READY", "error_kind": None, "verdict_2": None,
           "diff_id": "a" * 40, "plan_hash": "b" * 64, "review_kind": "delta"}
    fid.update(over)
    return fid


def test_vet_fidelity_never_caches_a_delta():
    assert reviewcache.vet_fidelity(_delta_fid(), "run") is None
    full = {k: v for k, v in _delta_fid().items() if k != "review_kind"}
    assert isinstance(reviewcache.vet_fidelity(full, "run"), dict)


def test_fidelity_reusable_sees_no_delta_arm():
    key = {"version": VERSION, "diff_id": "a" * 40, "plan_hash": "b" * 64}
    arm = reviewcache.vet_fidelity(_delta_fid(), "run")
    record = reviewcache.build_record(slug="s", pr_number=99, run_id="run", key=key,
                                      per_file_ids={}, fidelity=arm)
    assert reviewcache.fidelity_reusable(record, VERSION, "a" * 40, "b" * 64) is None
    # Control: the same verdict from a full review is reusable.
    full = {k: v for k, v in _delta_fid().items() if k != "review_kind"}
    record_full = reviewcache.build_record(
        slug="s", pr_number=99, run_id="run", key=key, per_file_ids={},
        fidelity=reviewcache.vet_fidelity(full, "run"))
    assert reviewcache.fidelity_reusable(
        record_full, VERSION, "a" * 40, "b" * 64) is not None
