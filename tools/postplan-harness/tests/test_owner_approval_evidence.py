"""A bin/owner-approve comment counts as owner evidence only when it is authentic,
in approve mode, bound to the current diff fingerprint, and lists the kept item."""
from __future__ import annotations

import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import unplanned_scope as us

HEAD = "a" * 40
FP = "b" * 40
OLD_FP = "c" * 40
KEPT_TEXT = "- [BLOCKING] [unplanned-scope] [GATE-EDIT] Widens the retry gate in bin/foo"
KEPT_TITLE = us.title_of(KEPT_TEXT)
REPO = Path(__file__).resolve().parents[3]


def _body(mode="approve", diff=FP, span=(f"- {KEPT_TITLE} (`bin/foo`)",), words="ship it",
          option="card A", tail=""):
    lines = [f'Owner approved 2026-11-19: "{words}" ({option}). Head {HEAD}. '
             "Conversation: T3 thread t, session s."]
    if span is not None:
        lines += ["", "Kept unplanned changes this approval accepts:", us.APPROVAL_KEPT_BEGIN,
                  *span, us.APPROVAL_KEPT_END]
    lines += ["", f"<!-- owner-approve mode={mode} head={HEAD} diff={diff} -->"]
    if tail:
        lines.append(tail)
    return "\n".join(lines) + "\n"


def _comment(body, created="2026-11-19T10:00:00Z", utype="User", assoc="OWNER", login="a-jay85"):
    return {"user": {"login": login, "type": utype}, "author_association": assoc,
            "created_at": created, "body": body}


def test_approval_matches_current_diff_fingerprint():
    got = us.approval_from_comments([_comment(_body())], FP)
    assert got is not None
    assert KEPT_TITLE in got.kept_span
    assert got.words == "ship it (card A)"
    assert got.created_at == "2026-11-19T10:00:00Z"
    assert us.approval_covers(KEPT_TEXT, got, ["bin/foo"])
    # CRLF bodies read the same.
    crlf = us.approval_from_comments([_comment(_body().replace("\n", "\r\n"))], FP)
    assert crlf is not None and us.approval_covers(KEPT_TEXT, crlf, ["bin/foo"])


def test_approval_on_older_diff_is_rejected():
    assert us.approval_from_comments([_comment(_body(diff=OLD_FP))], FP) is None
    # The newest approval wins: an older one on the current diff does not rescue a newer stale one.
    older = _comment(_body(diff=FP), created="2026-11-18T10:00:00Z")
    newer = _comment(_body(diff=OLD_FP), created="2026-11-19T10:00:00Z")
    assert us.approval_from_comments([older, newer], FP) is None


def test_missing_or_unread_fingerprint_is_rejected():
    unread = [_comment(_body(diff="unread"))]
    assert us.approval_from_comments(unread, None) is None
    assert us.approval_from_comments(unread, "unread") is None
    assert us.approval_from_comments(unread, "") is None
    assert us.approval_from_comments(unread, FP) is None
    assert us.approval_from_comments([_comment(_body())], FP.upper()) is None
    assert us.approval_from_comments([_comment(_body())], FP[:39]) is None


def test_marker_near_misses_are_rejected():
    old_shape = _body().replace(f" diff={FP}", "")
    short = _body(diff=FP[:39])
    upper = _body(diff=FP.upper())
    trailing = _body(tail="One more prose line after the marker.")
    for body in (old_shape, short, upper, trailing):
        assert us.approval_from_comments([_comment(body)], FP) is None, body
    # A marker in the middle of a body is not the final line either.
    mid = f"<!-- owner-approve mode=approve head={HEAD} diff={FP} -->\n" + _body(diff=OLD_FP)
    assert us.approval_from_comments([_comment(mid)], FP) is None


def test_bot_authored_approval_is_rejected():
    bot = _comment(_body(), utype="Bot", login="github-actions[bot]")
    stranger = _comment(_body(), assoc="NONE", login="someone")
    assert us.approval_from_comments([bot], FP) is None
    assert us.approval_from_comments([stranger], FP) is None
    # A forged newer comment cannot push out the real approval.
    real = _comment(_body(), created="2026-11-18T10:00:00Z")
    forged = _comment(_body(mode="fix-first"), created="2026-11-19T10:00:00Z", utype="Bot")
    assert us.approval_from_comments([real, forged], FP) is not None


def test_sticky_quoting_marker_is_not_an_approval():
    sticky = _comment("**NOT READY** · verdict\n" + us.STICKY_MARKER_TEXT + "\n" + _body())
    assert us.approval_from_comments([sticky], FP) is None
    hs = _comment("<!-- hs-approved-diff: " + FP + " -->\n" + _body())
    assert us.approval_from_comments([hs], FP) is None


def test_fix_first_mode_is_not_acceptance():
    older = _comment(_body(mode="approve"), created="2026-11-18T10:00:00Z")
    newer = _comment(_body(mode="fix-first"), created="2026-11-19T10:00:00Z")
    assert us.approval_from_comments([newer], FP) is None
    assert us.approval_from_comments([older, newer], FP) is None


def test_empty_span_or_no_cited_path_covers_nothing():
    uncited = "- [BLOCKING] [unplanned-scope] Adds a flag to the retry gate"
    got = us.approval_from_comments([_comment(_body(span=("- Adds a flag to the retry gate (`bin/foo`)",)))], FP)
    assert got is not None and "bin/foo" in got.kept_span
    assert us.approval_covers(uncited, got, ["bin/foo"]) is False
    assert us.approval_covers(KEPT_TEXT, got, ["bin/foo"]) is True
    assert not us.approval_covers(KEPT_TEXT, None, ["bin/foo"])
    # No span, or an empty one, is no approval at all.
    assert us.approval_from_comments([_comment(_body(span=None))], FP) is None
    assert us.approval_from_comments([_comment(_body(span=()))], FP) is None
    # A title the owner never saw is not covered.
    other = us.approval_from_comments([_comment(_body(span=("- A different change entirely",)))], FP)
    assert other is not None and not us.approval_covers(KEPT_TEXT, other, ["bin/foo"])


def test_reworded_finding_matches_by_cited_path():
    """A re-review rewords the text after the colon and may add a tag. The same finding
    still matches the declared kept block by title key, and the owner's approved line
    by cited path (PR 4067)."""
    old = ("- [BLOCKING] [unplanned-scope] Approve or plan the terminal_line READY branch: "
           "`a.py:1` adds a branch. The code looks correct.")
    new = ("- [BLOCKING] [unplanned-scope] [MERGE-RULE] Approve or plan the terminal_line "
           "READY branch: `a.py:1` adds a branch. The code is correct and needed.")
    assert us.title_of(old) != us.title_of(new)
    assert us.title_key(old) == us.title_key(new) == "Approve or plan the terminal_line READY branch"
    declared = us.render_kept_block([{"hold": "12", "text": old}], {"changed_paths": ["a.py"]})
    assert us._listed(new, declared)
    got = us.approval_from_comments(
        [_comment(_body(span=(f"- [MERGE-RULE] {us.title_of(old)}",)))], FP)
    assert us.approval_covers(new, got, ["a.py"]) is True
    # A different finding with a different key is not listed in the declared block.
    other = "- [BLOCKING] [unplanned-scope] Widen the merge line selector: `a.py:2` changes it."
    assert not us._listed(other, declared)
    # The approval covers the cited file at the approved diff, so `a.py:2` is covered
    # (owner decision 2026-11-27).
    assert us.approval_covers(other, got, ["a.py"]) is True
    # A key shorter than MIN_TITLE_LEN never matches by key.
    assert not us._listed("- [BLOCKING] [unplanned-scope] Adds flag: x", "- Adds flag: y (`a.py`)")


def test_only_owner_approve_emits_marker():
    allowed = {REPO / "bin" / "owner-approve", REPO / "bin" / "test-owner-approve",
               REPO / "bin" / "test-pr-triage",
               REPO / "tools" / "postplan-harness" / "harness" / "unplanned_scope.py"}
    hits = set()
    for root in (REPO / "bin", REPO / "tools" / "postplan-harness" / "harness"):
        for path in root.rglob("*"):
            if not path.is_file() or "__pycache__" in path.parts:
                continue
            try:
                text = path.read_text(encoding="utf-8")
            except (UnicodeDecodeError, OSError):
                continue
            if "owner-approve mode=" in text:
                hits.add(path)
    assert hits - allowed == set(), sorted(str(p) for p in hits - allowed)
    assert REPO / "bin" / "owner-approve" in hits


def test_reworded_finding_without_colon_matches_by_cited_path():
    """A title with no `: ` keys on its first sentence. The re-review rewords the
    sentences after it, and the owner's approval still covers it by cited path
    (PRs 4202, 4213)."""
    old = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to the claude-perm-ok "
           "marker only. In `bin/check-x`, `mk_range` gained a third parameter. "
           "Three tests pin the edges.")
    new = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to the claude-perm-ok "
           "marker only. In `bin/check-x`, `mk_range` gained a parameter and a new "
           "function. No plan phase asked for this.")
    key = "Scope range_hi widening to the claude-perm-ok marker only"
    assert us.title_key(old) == us.title_key(new) == key
    got = us.approval_from_comments([_comment(_body(span=(f"- {us.title_of(old)}",)))], FP)
    assert us.approval_covers(new, got, ["bin/check-x"]) is True
    # A dotted name inside the first sentence does not end it early.
    dotted = ("- [BLOCKING] [unplanned-scope] [MERGE-RULE] Revert the opt-out change in "
              "gate_backtest.py (no path cited). `gate_backtest.py` still rewrites it.")
    assert us.title_key(dotted) == "Revert the opt-out change in gate_backtest.py (no path cited)"
    # A different first sentence about the same cited file is covered (owner decision
    # 2026-11-27); one that cites no changed file is not.
    other = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to every marker. "
             "In `bin/check-x`, `mk_range` gained a third parameter.")
    assert us.approval_covers(other, got, ["bin/check-x"]) is True
    uncited = "- [BLOCKING] [unplanned-scope] Scope range_hi widening to every marker."
    assert us.approval_covers(uncited, got, ["bin/check-x"]) is False


# Helpers copied from test_kept_downgrade_owner_approval.py (no cross-module import).
_DG_GOTCHAS = ".claude/rules/ci-gotchas.md"
_DG_TITLE = ("[GATE-EDIT] Revert or approve the extra ci-gotchas.md trims. In `.claude/rules/ci-gotchas.md`, "
             "diff lines 17-18 rewrite more of the `All checks settled` paragraph than Phase 5 Edit 3 "
             "asked for")
_DG_BLOCKER = (f"- [BLOCKING] [unplanned-scope] {_DG_TITLE}. Next action: revert the trims or have "
               "the owner approve them.")
_DG_BULLET = f"- {_DG_TITLE} (`{_DG_GOTCHAS}`)"


def _dg_ctx(changed_paths=(_DG_GOTCHAS,)):
    changed = list(changed_paths)
    return {"changed_paths": changed,
            "subjects_by_path": {p: ["chore: trim the lazy rule under its cap"] for p in changed},
            "must_preserve": set(), "removed_guard_paths": set()}


def _dg_verdict(tmp_path, *items, name="verdict.md"):
    path = tmp_path / name
    path.write_text("## FINDINGS\n" + "\n".join(items) + "\n\nNOT READY\n", encoding="utf-8")
    return str(path)


def _dg_body(block):
    return f"intro\n\n{block}\n"


def _dg_approval(span):
    return us.OwnerApproval(words="ship it", kept_span=span, created_at="2026-11-19T10:00:00Z")


def test_fix_first_mode_yields_no_approval():
    marker = f"<!-- owner-approve mode=approve head={HEAD} diff={FP} -->"
    body = _body().replace(marker, marker.replace("mode=approve", "mode=fix-first"))
    assert "mode=fix-first" in body
    assert us.approval_from_comments([_comment(body)], FP) is None


def test_mismatched_fingerprint_yields_no_approval():
    current_fp = "f" * 40
    assert us.approval_from_comments([_comment(_body(diff=FP))], FP) is not None
    assert us.approval_from_comments([_comment(_body(diff=FP))], current_fp) is None


def test_declared_block_short_title_not_listed():
    short = "x" * (us.MIN_TITLE_LEN - 1)
    span = f"- {short}"
    finding = f"- [BLOCKING] [unplanned-scope] {short}"
    assert us._listed(finding, span) is False


def test_downgrade_refuses_when_a_blocker_is_not_unplanned_scope(tmp_path):
    ctx = _dg_ctx()
    block = us.render_kept_block([{"text": _DG_BLOCKER}], ctx)
    rendered = next(ln for ln in block.splitlines() if ln.startswith("- "))
    assert rendered == _DG_BULLET
    approval = _dg_approval(rendered)
    # The approved item alone downgrades, so the refusal below comes from the plain bug.
    alone = _dg_verdict(tmp_path, _DG_BLOCKER, name="alone.md")
    assert us.downgrade_applies(alone, ctx, _dg_body(block), None, approval=approval) is True
    path = _dg_verdict(tmp_path, _DG_BLOCKER, "- [BLOCKING] Plain bug in the same file")
    assert us.downgrade_applies(path, ctx, _dg_body(block), None, approval=approval) is False


ENGINE = ".claude/rules/engine-go.md"
PY_TESTS = ".github/workflows/python-tests.yml"
PR4244_APPROVED = "- [GATE-EDIT] Review the engine-go.md capture-location rule edit (`.claude/rules/engine-go.md`)"
PR4244_REWORDED = ("- [BLOCKING] [GATE-EDIT] [unplanned-scope] Review the engine-go.md capture-location rule edit. "
                   "`.claude/rules/engine-go.md` diff line 10 bumps `last_verified`, and diff line 19 now says raw "
                   "trace captures \"live in IBL5 under `engine/testdata/jsb-captures/` and are public\". No plan "
                   "phase names this file, and no owner amendment asks for this edit. Next action: confirm the edit.")


def _ok(*lines):
    return us.OwnerApproval(words="ok", kept_span="\n".join(lines), created_at="2026-11-27T20:56:54Z")


def _finding(rest):
    return f"- [BLOCKING] [unplanned-scope] {rest}"


def test_pr4244_reworded_finding_covered_by_cited_path():
    approval = _ok(PR4244_APPROVED, f"- [GATE-EDIT] Review the python-tests path list (`{PY_TESTS}`)")
    # The old title match missed this finding: the keys differ.
    assert us.title_key(PR4244_REWORDED) != us.title_key(PR4244_APPROVED)
    assert us.approval_covers(PR4244_REWORDED, approval, [ENGINE, PY_TESTS]) is True


def test_pr4244_downgrade_applies_with_fresh_declared_block(tmp_path):
    ctx = _dg_ctx([ENGINE])
    path = _dg_verdict(tmp_path, PR4244_REWORDED)
    body = _dg_body(us.render_kept_block([{"text": PR4244_REWORDED}], ctx))
    approval = _ok(PR4244_APPROVED)
    assert us.classify(PR4244_REWORDED, **ctx) == "keep"
    assert us.downgrade_applies(path, ctx, body, None, approval=approval) is True
    # Without the approval the same verdict stays NOT READY.
    assert us.downgrade_applies(path, ctx, body, None, approval=None) is False


def test_finding_citing_no_changed_path_not_covered():
    approval = _ok("- Item (no path cited)", "- Item (`bin/check-a`)")
    finding = _finding("Edits `bin/other` without a plan phase")
    assert us.approval_covers(finding, approval, ["bin/check-a"]) is False


def test_superset_finding_not_covered():
    approval = _ok("- Edit (`bin/check-a`)")
    finding = _finding("Edits `bin/check-a` and `bin/check-b` together")
    assert us.approval_covers(finding, approval, ["bin/check-a", "bin/check-b"]) is False


def test_subset_finding_covered():
    approval = _ok("- Edit (`bin/check-a`, `bin/check-b`)")
    finding = _finding("Edits `bin/check-a` only")
    assert us.approval_covers(finding, approval, ["bin/check-a", "bin/check-b"]) is True


def test_tag_dropped_still_covered():
    approval = _ok("- [GATE-EDIT] Edit (`bin/check-a`)")
    finding = "- [BLOCKING] [unplanned-scope] Reworded edit of `bin/check-a`"
    assert us.approval_covers(finding, approval, ["bin/check-a"]) is True


def test_tag_added_still_covered():
    approval = _ok("- Edit (`bin/check-a`)")
    finding = "- [BLOCKING] [GATE-EDIT] [unplanned-scope] Reworded edit of `bin/check-a`"
    assert us.approval_covers(finding, approval, ["bin/check-a"]) is True


def test_paths_split_across_lines_not_covered():
    approval = _ok("- Edit (`bin/check-a`)", "- Edit (`bin/check-b`)")
    finding = _finding("Edits `bin/check-a` and `bin/check-b` together")
    assert us.approval_covers(finding, approval, ["bin/check-a", "bin/check-b"]) is False


def test_non_bullet_span_line_does_not_cover():
    approval = _ok("Approved files `bin/check-a`", "- Other (`bin/check-c`)")
    finding = _finding("Edits `bin/check-a` without a plan phase")
    changed = ["bin/check-a", "bin/check-b", "bin/check-c"]
    assert us.approval_covers(finding, approval, changed) is False


def test_longer_changed_path_does_not_lend_its_prefix():
    approval = _ok("- Edit (`bin/x.sh`)")
    finding = _finding("Edits `bin/x` without a plan phase")
    changed = ["bin/x", "bin/x.sh"]
    assert us.approval_covers(finding, approval, changed) is False
    # The same line does cover a finding about bin/x.sh.
    assert us.approval_covers(_finding("Edits `bin/x.sh`"), approval, changed) is True


def test_sentence_end_path_still_cited():
    approval = _ok("- Edit (`bin/x`)")
    finding = "- [BLOCKING] [unplanned-scope] This run edits bin/x."
    assert us.approval_covers(finding, approval, ["bin/x"]) is True


def test_line_number_suffix_still_cited():
    approval = _ok("- Edit (`a.py`)")
    finding = _finding("Edits `a.py:2` without a plan phase")
    assert us.approval_covers(finding, approval, ["a.py"]) is True


def test_none_approval_covers_nothing():
    finding = _finding("Edits `bin/check-a` without a plan phase")
    assert us.approval_covers(finding, None, ["bin/check-a"]) is False


def test_changed_fingerprint_approval_covers_nothing():
    comments = [_comment(_body(diff=FP, span=("- Edit (`bin/check-a`)",)))]
    finding = _finding("Edits `bin/check-a` without a plan phase")
    # On its own diff the approval covers the finding.
    assert us.approval_covers(finding, us.approval_from_comments(comments, FP), ["bin/check-a"]) is True
    approval = us.approval_from_comments(comments, "f" * 40)
    assert us.approval_covers(finding, approval, ["bin/check-a"]) is False
