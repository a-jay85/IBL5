"""Phase 5.5 carry-forward predicate and supporting helpers.

Tests are ordered by verification-matrix row. The _sticky() helper builds fixtures
through the real compose_sticky + terminal_line so the parser and writer cannot drift
apart — a future edit to either side that breaks the contract fails these tests instead
of silently disabling carry-forward.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters import ghad

TREE = "a" * 40
DIFF_ID = "e" * 40
PLAN_HASH = "b" * 64


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
