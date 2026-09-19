"""Phase 5.5 carry-forward predicate and supporting helpers.

Tests are ordered by verification-matrix row. The _sticky() helper builds fixtures
through the real compose_sticky + terminal_line so the parser and writer cannot drift
apart — a future edit to either side that breaks the contract fails these tests instead
of silently disabling carry-forward.
"""
import hashlib
import os
import shutil
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters import ghad
from harness.adapters.ghad import RecordingGh
from harness.armable import evaluate, select_fidelity_verdict
import runner

TREE = "a" * 40
DIFF_ID = "e" * 40
PLAN_HASH = "b" * 64

# A real one-file unified diff — used for patch-id computations
DIFF = """\
diff --git a/tools/postplan-harness/harness/fidelity.py b/tools/postplan-harness/harness/fidelity.py
index aaaaaaa..bbbbbbb 100644
--- a/tools/postplan-harness/harness/fidelity.py
+++ b/tools/postplan-harness/harness/fidelity.py
@@ -1,3 +1,4 @@
 # harness fidelity
+# carry-forward
 import re
 import subprocess
"""

DIFF_EDITED = """\
diff --git a/tools/postplan-harness/harness/fidelity.py b/tools/postplan-harness/harness/fidelity.py
index aaaaaaa..bbbbbbb 100644
--- a/tools/postplan-harness/harness/fidelity.py
+++ b/tools/postplan-harness/harness/fidelity.py
@@ -1,3 +1,4 @@
 # harness fidelity
+# carry-forward edited
 import re
 import subprocess
"""


def _sticky(verdict="READY", diff_id=DIFF_ID, plan_hash=PLAN_HASH, tree=TREE, **fid_extra):
    """Build a sticky body through the real composer, so the parser and the writer
    can never drift apart in this test file."""
    fid = {"verdict_1": verdict, "reviewed_tree": tree, "error_kind": None, **fid_extra}
    return fidelity.compose_sticky(
        "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
        fidelity.terminal_line(verdict, None, None, None, None, 0),
        diff_id=diff_id, plan_hash=plan_hash)


# --- row 1: full match --------------------------------------------------------

def test_full_match_carries_the_prior_ready_verdict():
    assert fidelity.carry_forward_predicate(_sticky(), DIFF_ID, PLAN_HASH) == ("READY", "")


# --- row 2: READY WITH NOTES substring trap -----------------------------------

def test_ready_with_notes_carries_despite_not_ready_substring():
    body = _sticky(verdict="READY WITH NOTES")
    assert "NOT READY" in body  # proves the substring trap is live
    assert fidelity.carry_forward_predicate(body, DIFF_ID, PLAN_HASH) == ("READY WITH NOTES", "")


# --- row 3: diff mismatch -----------------------------------------------------

def test_diff_mismatch_declines():
    assert fidelity.carry_forward_predicate(_sticky(), "c" * 40, PLAN_HASH) == (None, "diff-changed")


# --- row 4: plan changed ------------------------------------------------------

def test_plan_changed_declines():
    assert fidelity.carry_forward_predicate(_sticky(), DIFF_ID, "d" * 64) == (None, "plan-changed")


# --- row 5: missing plan hash line --------------------------------------------

def test_missing_plan_hash_line_declines():
    body = _sticky(plan_hash="")  # composer emits no **Plan hash:** line
    assert fidelity.carry_forward_predicate(body, DIFF_ID, PLAN_HASH) == (None, "no-prior-plan-hash")


# --- row 6: malformed reviewed diff -------------------------------------------

def test_malformed_reviewed_diff_declines():
    # no **Reviewed diff:** line (only the 40-hex **Reviewed tree:** is present)
    body_no_diff = _sticky(diff_id="")
    assert fidelity.carry_forward_predicate(body_no_diff, DIFF_ID, PLAN_HASH) == (None, "no-prior-diff-id")

    # 39-hex (too short)
    body_short = _sticky().replace(f"**Reviewed diff:** {DIFF_ID}",
                                   f"**Reviewed diff:** {'e' * 39}")
    assert fidelity.carry_forward_predicate(body_short, DIFF_ID, PLAN_HASH) == (None, "no-prior-diff-id")

    # 40-hex with trailing prose
    body_prose = _sticky().replace(f"**Reviewed diff:** {DIFF_ID}",
                                   f"**Reviewed diff:** {DIFF_ID} (rebased)")
    assert fidelity.carry_forward_predicate(body_prose, DIFF_ID, PLAN_HASH) == (None, "no-prior-diff-id")


# --- row 7: NOT READY prior ---------------------------------------------------

def test_not_ready_prior_never_carries():
    body = _sticky(verdict="NOT READY")
    assert fidelity.carry_forward_predicate(body, DIFF_ID, PLAN_HASH) == (None, "prior-verdict-not-terminal")


# --- row 8: remediated verdict ------------------------------------------------

def test_re_review_terminal_line_declines():
    terminal = fidelity.terminal_line("NOT READY", None, "abc123", "READY", "b" * 40, 1)
    fid = {"verdict_1": "NOT READY", "reviewed_tree": TREE, "error_kind": None}
    body = fidelity.compose_sticky(
        "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
        terminal, diff_id=DIFF_ID, plan_hash=PLAN_HASH)
    assert fidelity.carry_forward_predicate(body, DIFF_ID, PLAN_HASH) == (None, "prior-verdict-not-terminal")


# --- row 9: empty / None sticky, plan-blind, blank diff id --------------------

def test_empty_and_none_sticky_decline():
    assert fidelity.carry_forward_predicate(None, DIFF_ID, PLAN_HASH) == (None, "no-prior-sticky")
    assert fidelity.carry_forward_predicate("", DIFF_ID, PLAN_HASH) == (None, "no-prior-sticky")


def test_plan_blind_hash_declines():
    assert fidelity.carry_forward_predicate(_sticky(), DIFF_ID, "") == (None, "no-plan-hash")


def test_blank_diff_id_declines():
    assert fidelity.carry_forward_predicate(_sticky(), "", PLAN_HASH) == (None, "no-diff-id")


# --- rows 31-33: diff_patch_id ------------------------------------------------

_DIFF_TEMPLATE = """\
diff --git a/foo.py b/foo.py
{index_line}
--- a/foo.py
+++ b/foo.py
@@ {hunk} @@
 A
-B
+C
 D
"""


def test_patch_id_ignores_rebase_offsets():
    diff_a = _DIFF_TEMPLATE.format(
        index_line="index aaaaaaa..bbbbbbb 100644",
        hunk="-1,3 +1,3")
    diff_b = _DIFF_TEMPLATE.format(
        index_line="index ccccccc..ddddddd 100644",
        hunk="-40,3 +40,3")
    id_a = fidelity.diff_patch_id(diff_a)
    id_b = fidelity.diff_patch_id(diff_b)
    assert id_a == id_b
    assert len(id_a) == 40
    import re as _re
    assert _re.fullmatch(r"[0-9a-f]{40}", id_a)


def test_patch_id_whitespace_edit_changes_id():
    diff_no_space = _DIFF_TEMPLATE.format(
        index_line="index aaaaaaa..bbbbbbb 100644",
        hunk="-1,3 +1,3").replace("+C", "+C")
    diff_with_space = _DIFF_TEMPLATE.format(
        index_line="index aaaaaaa..bbbbbbb 100644",
        hunk="-1,3 +1,3").replace("+C\n", "+C \n")
    assert fidelity.diff_patch_id(diff_no_space) != fidelity.diff_patch_id(diff_with_space)


def test_patch_id_blank_for_empty_diff():
    assert fidelity.diff_patch_id("") == ""
    assert fidelity.diff_patch_id("\n") == ""
    assert fidelity.diff_patch_id("not a diff\n") == ""


# --- rows 12-15: adapter tests -----------------------------------------------

def test_sticky_marker_constants_agree():
    assert ghad.PR_STICKY_MARKER == fidelity.STICKY_MARKER


def test_recording_gh_sticky_body_none_without_fixture(tmp_path):
    from harness.adapters.ghad import RecordingGh
    gh = RecordingGh(str(tmp_path))
    assert gh.pr_sticky_body(99) is None
    gh2 = RecordingGh(str(tmp_path), {"prior_sticky_body": "x"})
    assert gh2.pr_sticky_body(99) == "x"


def test_live_sticky_body_fail_closed(tmp_path):
    from harness.adapters.ghad import LiveGh, PR_STICKY_MARKER
    MARKED = f"hello {PR_STICKY_MARKER} world"

    class PatchedLiveGh(LiveGh):
        def __init__(self, response):
            super().__init__(str(tmp_path), ".", "HEAD")
            self._response = response

        def _gh(self, *args, **kw):
            from harness.state import HarnessError
            if isinstance(self._response, Exception):
                raise self._response
            return self._response

    from harness.state import HarnessError

    # HarnessError → None
    assert PatchedLiveGh(HarnessError("gh", "fail")).pr_sticky_body(1) is None
    # non-JSON → None
    assert PatchedLiveGh("not json").pr_sticky_body(1) is None
    # empty comments → None
    assert PatchedLiveGh('{"comments": []}').pr_sticky_body(1) is None
    # null comments → None
    assert PatchedLiveGh('{"comments": null}').pr_sticky_body(1) is None
    # unrelated comment → None
    assert PatchedLiveGh('{"comments": [{"body": "unrelated"}]}').pr_sticky_body(1) is None
    # two marked comments → None (ambiguous)
    two = f'{{"comments": [{{"body": "{MARKED}"}}, {{"body": "{MARKED}"}}]}}'
    assert PatchedLiveGh(two).pr_sticky_body(1) is None
    # exactly one marked comment → returns body
    one = f'{{"comments": [{{"body": "{MARKED}"}}]}}'
    assert PatchedLiveGh(one).pr_sticky_body(1) == MARKED


# --- Phase 5: integration tests -----------------------------------------------


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


def _git(head_trees=None):
    """Minimal ReplayGit-like object for _run_fidelity."""
    trees = (head_trees or [TREE, TREE])[:]

    class _G:
        def head_tree(self):
            return trees[0] if trees else TREE

        def diff_vs_base(self):
            return DIFF

        def fetch_base(self):
            return "deadbeef" * 5

        def rebase_onto(self, sha):
            if trees:
                trees.pop(0)
            return "dead" * 10

        def push(self, branch):
            pass

    return _G()


def _drive_cf(tmp_path, prior_sticky, plan_body=b"# plan\n", canned=None, diff=DIFF,
              tree=TREE, git_shim_fixture=None):
    """Run _run_fidelity in live mode against RecordingGh."""
    tmp_path = tmp_path if hasattr(tmp_path, "mkdir") else tmp_path
    import pathlib
    pathlib.Path(tmp_path).mkdir(parents=True, exist_ok=True)
    tmp_path = pathlib.Path(tmp_path)
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

    def counting_review(*a, **kw):
        spawn_count[0] += 1
        return orig_review(*a, **kw)

    from harness.state import TerminalState
    res = runner.RunResult(terminal=TerminalState.FAILED)
    try:
        runner.fidelity.review = counting_review
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _git(head_trees=[tree, tree]),
            gh, plan_obj, diff, "body", 99, "dead" * 10, tree, True, logs.append, res)
    finally:
        runner.fidelity.review = orig_review
    return res, spawn_count[0], logs


@pytest.mark.usefixtures("git_shim")
def test_matching_sticky_spawns_no_reviewer(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    assert diff_id, "git shim must pass patch-id through to real git"
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash)
    res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky, plan_body=plan_body)
    assert spawns == 0
    assert res.fidelity.get("carried_forward") is True
    assert res.fidelity["verdict_1"] == "READY"
    assert res.fidelity["verdict_2"] is None
    assert res.fidelity["plan_hash"] == plan_hash


@pytest.mark.usefixtures("git_shim")
def test_rebased_tree_same_diff_spawns_no_reviewer(tmp_path):
    """A clean rebase onto a new master changes the tree but not the patch-id."""
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    # Build sticky with pre-rebase tree "c"*40
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash, tree="c" * 40)
    res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky, plan_body=plan_body,
                                tree=TREE, diff=DIFF)
    assert spawns == 0
    assert res.fidelity["reviewed_tree"] == TREE


@pytest.mark.usefixtures("git_shim")
def test_diff_change_spawns_the_reviewer(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash)
    # Drive with a different diff
    res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky, plan_body=plan_body,
                                diff=DIFF_EDITED,
                                canned={"plan-fidelity-review": "6d checks\n\nREADY\n"})
    assert spawns == 1
    assert not res.fidelity.get("carried_forward")


@pytest.mark.usefixtures("git_shim")
def test_plan_edit_spawns_the_reviewer(tmp_path):
    plan_body_original = b"# plan\n"
    plan_hash_original = hashlib.sha256(plan_body_original).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash_original)
    # Same diff but edited plan
    res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky,
                                plan_body=b"# plan edited\n",
                                canned={"plan-fidelity-review": "6d checks\n\nREADY\n"})
    assert spawns == 1


@pytest.mark.usefixtures("git_shim")
def test_not_ready_prior_spawns_the_reviewer(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="NOT READY", diff_id=diff_id, plan_hash=plan_hash)
    res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky, plan_body=plan_body,
                                canned={"plan-fidelity-review": "6d checks\n\nREADY\n"})
    assert spawns == 1


@pytest.mark.usefixtures("git_shim")
def test_carried_forward_log_line(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash)

    _, _, logs_match = _drive_cf(tmp_path / "m", prior_sticky=sticky, plan_body=plan_body)
    assert any(f"carried forward (patch-id {diff_id[:12]})" in ln for ln in logs_match)

    _, _, logs_miss = _drive_cf(tmp_path / "n", prior_sticky=sticky, plan_body=plan_body,
                                 diff=DIFF_EDITED,
                                 canned={"plan-fidelity-review": "6d checks\n\nREADY\n"})
    assert any("carry-forward declined: diff-changed" in ln for ln in logs_miss)


@pytest.mark.usefixtures("git_shim")
def test_sticky_round_trip_carries_on_the_second_run(tmp_path):
    """Writer and reader must agree: compose_sticky → carry_forward_predicate."""
    plan_body = b"# plan\n"
    canned = {"plan-fidelity-review": "6d checks\n\nREADY\n"}
    res1, spawns1, _ = _drive_cf(tmp_path / "r1", prior_sticky=None, plan_body=plan_body,
                                  canned=canned)
    assert spawns1 == 1

    fid = res1.fidelity
    # Build sticky exactly as runner.py does
    sticky2 = fidelity.compose_sticky(
        "rebased", "ci", fid, None, ["a", "b", "c", "d", "e"], "findings",
        fidelity.terminal_line(fid.get("verdict_1"), fid.get("error_kind"),
                               fid.get("remediation_sha"), fid.get("verdict_2"),
                               fid.get("reviewed_tree_2"), fid.get("rounds_completed", 0)),
        diff_id=fid.get("diff_id", ""), plan_hash=fid.get("plan_hash", ""))

    res2, spawns2, _ = _drive_cf(tmp_path / "r2", prior_sticky=sticky2,
                                  plan_body=plan_body, tree="c" * 40, diff=DIFF)
    assert spawns2 == 0


def _cond12(res, tree):
    from test_armable import inputs
    d = evaluate(inputs(fidelity_verdict=res.fidelity["verdict_1"],
                        fidelity_verdict_2=res.fidelity["verdict_2"],
                        fidelity_tree_2=res.fidelity["reviewed_tree_2"],
                        current_tree=tree))
    return ([(c.number, c.name, c.reason) for c in d.holds if c.number == 12],
            select_fidelity_verdict(res.fidelity["verdict_1"], res.fidelity["verdict_2"],
                                    res.fidelity["reviewed_tree_2"], tree))


@pytest.mark.usefixtures("git_shim")
def test_condition_12_identical_with_and_without_carry_forward(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash)

    res_cf, spawns_cf, _ = _drive_cf(tmp_path / "cf", prior_sticky=sticky,
                                      plan_body=plan_body)
    assert spawns_cf == 0

    canned = {"plan-fidelity-review": "6d checks\n\nREADY\n"}
    res_full, spawns_full, _ = _drive_cf(tmp_path / "full", prior_sticky=None,
                                          plan_body=plan_body, canned=canned)
    assert spawns_full == 1

    assert _cond12(res_cf, TREE) == _cond12(res_full, TREE)
    assert _cond12(res_cf, TREE) == ([], ("READY", "verdict-1"))


@pytest.mark.usefixtures("git_shim")
def test_condition_12_identical_for_ready_with_notes(tmp_path):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(DIFF)
    sticky = _sticky(verdict="READY WITH NOTES", diff_id=diff_id, plan_hash=plan_hash)

    res_cf, spawns_cf, _ = _drive_cf(tmp_path / "cf", prior_sticky=sticky,
                                      plan_body=plan_body)
    assert spawns_cf == 0

    canned = {"plan-fidelity-review": "6d checks\n\nREADY WITH NOTES\n"}
    res_full, spawns_full, _ = _drive_cf(tmp_path / "full", prior_sticky=None,
                                          plan_body=plan_body, canned=canned)
    assert spawns_full == 1

    assert _cond12(res_cf, TREE) == _cond12(res_full, TREE)
    assert _cond12(res_cf, TREE)[1][0] == "READY WITH NOTES"
