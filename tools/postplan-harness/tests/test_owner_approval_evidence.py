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
    assert us.approval_covers(KEPT_TEXT, got)
    # CRLF bodies read the same.
    crlf = us.approval_from_comments([_comment(_body().replace("\n", "\r\n"))], FP)
    assert crlf is not None and us.approval_covers(KEPT_TEXT, crlf)


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


def test_short_title_or_empty_span_covers_nothing():
    short_text = "- [BLOCKING] [unplanned-scope] Adds flag"
    short_title = us.title_of(short_text)
    assert len(short_title) < us.MIN_TITLE_LEN
    got = us.approval_from_comments([_comment(_body(span=(f"- {short_title} (`bin/foo`)",)))], FP)
    assert got is not None and short_title in got.kept_span
    assert not us.approval_covers(short_text, got)
    assert not us.approval_covers(KEPT_TEXT, None)
    # No span, or an empty one, is no approval at all.
    assert us.approval_from_comments([_comment(_body(span=None))], FP) is None
    assert us.approval_from_comments([_comment(_body(span=()))], FP) is None
    # A title the owner never saw is not covered.
    other = us.approval_from_comments([_comment(_body(span=("- A different change entirely",)))], FP)
    assert other is not None and not us.approval_covers(KEPT_TEXT, other)


def test_reworded_finding_still_matches_by_title_key():
    """A re-review rewords the text after the colon and may add a tag. The same finding
    still matches the declared kept block and the owner's approved line (PR 4067)."""
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
    assert us.approval_covers(new, got)
    # A different finding with a different key is still not covered.
    other = "- [BLOCKING] [unplanned-scope] Widen the merge line selector: `a.py:2` changes it."
    assert not us._listed(other, declared) and not us.approval_covers(other, got)
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


def test_reworded_finding_without_colon_matches_by_first_sentence():
    """A title with no `: ` keys on its first sentence. The re-review rewords the
    sentences after it, and the owner's approval still covers it (PRs 4202, 4213)."""
    old = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to the claude-perm-ok "
           "marker only. In `bin/check-x`, `mk_range` gained a third parameter. "
           "Three tests pin the edges.")
    new = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to the claude-perm-ok "
           "marker only. In `bin/check-x`, `mk_range` gained a parameter and a new "
           "function. No plan phase asked for this.")
    key = "Scope range_hi widening to the claude-perm-ok marker only"
    assert us.title_key(old) == us.title_key(new) == key
    got = us.approval_from_comments([_comment(_body(span=(f"- {us.title_of(old)}",)))], FP)
    assert us.approval_covers(new, got)
    # A dotted name inside the first sentence does not end it early.
    dotted = ("- [BLOCKING] [unplanned-scope] [MERGE-RULE] Revert the opt-out change in "
              "gate_backtest.py (no path cited). `gate_backtest.py` still rewrites it.")
    assert us.title_key(dotted) == "Revert the opt-out change in gate_backtest.py (no path cited)"
    # A different first sentence is still not covered.
    other = ("- [BLOCKING] [unplanned-scope] Scope range_hi widening to every marker. "
             "In `bin/check-x`, `mk_range` gained a third parameter.")
    assert not us.approval_covers(other, got)


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
