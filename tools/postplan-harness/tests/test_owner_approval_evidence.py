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


GO_FINDING = ("- [BLOCKING] [unplanned-scope] [GATE-EDIT] Review the engine-go.md capture-location "
              "rule edit (`.claude/rules/engine-go.md` line 9 bumps `last_verified`, and line 18 now "
              "says raw trace captures \"live in IBL5 under `engine/testdata/jsb-captures/` and are "
              "public\"). No plan phase names this file.")
GO_APPROVED = ("- [GATE-EDIT] Review the engine-go.md capture-location rule edit "
               "(`.claude/rules/engine-go.md`)")
CI_APPROVED = ('- [GATE-EDIT] Add capture and evidence paths to python-tests.yml triggers owner: '
               '"CI path filters added for capture and evidence paths" '
               '(`.github/workflows/python-tests.yml`)')
GO_KEY = "Review the engine-go.md capture-location rule edit"


def _go_approval():
    return us.approval_from_comments([_comment(_body(span=(CI_APPROVED, GO_APPROVED)))], FP)


def _go_declared():
    return "\n".join([us.KEPT_BEGIN, "## Kept unplanned changes", CI_APPROVED, GO_APPROVED,
                      us.KEPT_END])


def test_path_parenthetical_title_matches_owner_approval():
    """PR 4244: the finding's title carries the path plus prose inside ` (`...`)`, and the
    approved and declared line is the short title plus ` (`path`)`. Both key to the
    words before the first ` (` and backtick."""
    assert us.title_key(GO_FINDING) == us.title_key(GO_APPROVED) == GO_KEY
    got = _go_approval()
    assert got is not None and us.approval_covers(GO_FINDING, got)
    assert us._listed(GO_FINDING, _go_declared())


def test_path_parenthetical_different_lead_words_do_not_match():
    """Words that differ before the path citation still give a different key."""
    other = GO_FINDING.replace("capture-location rule edit", "merge rule edit")
    assert other != GO_FINDING
    assert us.title_key(other) != GO_KEY
    got = _go_approval()
    assert got is not None and not us.approval_covers(other, got)
    assert not us._listed(other, _go_declared())


def test_colon_before_path_keeps_colon_key():
    """A `: ` before the path still decides the key, as it did before PR 4244."""
    colon = ("- [BLOCKING] [unplanned-scope] Widen the retry gate: `bin/foo` "
             "(`bin/foo` line 3) now retries twice.")
    assert us.title_key(colon) == "Widen the retry gate"
    ci_finding = "- [BLOCKING] [unplanned-scope] " + CI_APPROVED[2:]
    ci_key = "Add capture and evidence paths to python-tests.yml triggers owner"
    assert us.title_key(ci_finding) == us.title_key(CI_APPROVED) == ci_key
    got = _go_approval()
    assert got is not None and us.approval_covers(ci_finding, got)


def test_path_parenthetical_different_path_does_not_match():
    """Same words before ` (` and backtick give the same key, but a different cited
    path is a change the owner did not approve."""
    other_path = GO_FINDING.replace("(`.claude/rules/engine-go.md` line 9",
                                    "(`.claude/rules/other.md` line 9")
    assert other_path != GO_FINDING
    assert us.title_key(other_path) == GO_KEY
    got = _go_approval()
    assert got is not None and not us.approval_covers(other_path, got)
    assert not us._listed(other_path, _go_declared())


GO_PATH = ".claude/rules/engine-go.md"
GO_CHANGED = [GO_PATH, "bin/check-x"]
GO_EXTRA_PATH = ("- [BLOCKING] [unplanned-scope] [GATE-EDIT] Review the engine-go.md capture-location "
                 f"rule edit (`{GO_PATH}` and `bin/check-x` line 4 now exits 0)")
GO_NO_CITATION = f"- [BLOCKING] [unplanned-scope] {GO_KEY}: `bin/check-x` line 4 now exits 0"
GO_EXACT_TITLE = ("- [BLOCKING] [unplanned-scope] " + GO_APPROVED[2:]
                  + "\n  `bin/check-x` line 4 now exits 0")


def test_extra_changed_path_is_not_covered():
    """An approval for one file never covers a finding that also names another changed
    file, whether the key cut a citation, cut at `: `, or the title matches exactly."""
    got = _go_approval()
    for text in (GO_EXTRA_PATH, GO_NO_CITATION, GO_EXACT_TITLE):
        assert not us._listed(text, _go_declared(), GO_CHANGED), text
        assert not us.approval_covers(text, got, GO_CHANGED), text


def test_line_naming_every_changed_path_is_covered():
    approved = f"- [GATE-EDIT] {GO_KEY} (`{GO_PATH}`, `bin/check-x`)"
    got = us.approval_from_comments([_comment(_body(span=(approved,)))], FP)
    for text in (GO_EXTRA_PATH, GO_NO_CITATION):
        assert us._listed(text, approved, GO_CHANGED), text
        assert us.approval_covers(text, got, GO_CHANGED), text
    # The two paths must sit on the matching line, not elsewhere in the span.
    split = GO_APPROVED + "\n- Another change entirely (`bin/check-x`)"
    assert not us._listed(GO_EXTRA_PATH, split, GO_CHANGED)


def test_downgrade_passes_changed_paths_to_the_match(tmp_path):
    verdict = tmp_path / "verdict.md"
    verdict.write_text("## FINDINGS\n"
                       f'{GO_EXTRA_PATH} owner: "keep the engine rule edit"\n\nNOT READY\n',
                       encoding="utf-8")
    ctx = {"changed_paths": GO_CHANGED, "subjects_by_path": {},
           "must_preserve": set(), "removed_guard_paths": set()}
    payload = {"turns": [{"text": "keep the engine rule edit"}]}
    one = _go_declared()
    both = "\n".join([us.KEPT_BEGIN, f"- {GO_KEY} (`{GO_PATH}`, `bin/check-x`)", us.KEPT_END])
    assert us.downgrade_applies(str(verdict), ctx, both, payload) is True
    assert us.downgrade_applies(str(verdict), ctx, one, payload) is False


def test_mid_title_citation_compares_the_cited_path():
    approved = "- Remove the guard (`bin/check-a`) from the merge gate (`bin/check-a`)"
    same = "- [BLOCKING] [unplanned-scope] Remove the guard (`bin/check-a`) from the merge gate"
    reworded = "- [BLOCKING] [unplanned-scope] Remove the guard (`bin/check-a`) from the gate"
    other = "- [BLOCKING] [unplanned-scope] Remove the guard (`bin/check-b`) from the merge gate"
    assert us._listed(same, approved)
    assert not us._listed(reworded, approved)
    assert not us._listed(other, approved)


def test_words_after_a_citation_stay_in_the_key():
    """An approval for a narrow change never covers a wider one that differs only after
    the cited path, with or without the rendered path suffix."""
    approved = "- Relax the check (`bin/check-a`) for docs-only PRs (`bin/check-a`)"
    wider = "- [BLOCKING] [unplanned-scope] Relax the check (`bin/check-a`) for all PRs"
    assert us.title_key(approved) == "Relax the check (`bin/check-a`) for docs-only PRs"
    assert us.title_key(wider) == "Relax the check (`bin/check-a`) for all PRs"
    for line in (approved, "- Relax the check (`bin/check-a`) for docs-only PRs"):
        assert not us._listed(wider, line)
        assert not us._listed(wider + " (`bin/check-a` line 4)", line)


def test_shortened_title_does_not_match_a_narrower_line_by_substring():
    """A wider finding whose title is the front of an approved narrow line is not covered."""
    approved = "- Relax the check (`bin/check-a`) for docs-only PRs (`bin/check-a`)"
    wider = "- [BLOCKING] [unplanned-scope] Relax the check (`bin/check-a`)"
    got = us.approval_from_comments([_comment(_body(span=(approved,)))], FP)
    assert got is not None
    assert not us._listed(wider, approved, ["bin/check-a"])
    assert not us.approval_covers(wider, got, ["bin/check-a"])


def test_exact_title_matches_with_only_tags_and_citations_around_it():
    wider = "- [BLOCKING] [unplanned-scope] Relax the check (`bin/check-a`)"
    title = us.title_of(wider)
    for line in (f"- {title}", f"- [GATE-EDIT] {title} (`bin/check-a`)",
                 f"- {title} (`bin/check-a`, `bin/check-b`)\r", f"- {title} (no path cited)"):
        assert us._is_whole_bullet(title, line), line
    for line in (title, f"- Also {title}", f"- {title} for docs-only PRs",
                 f"- {title} (for docs-only PRs)", f"- {title} (`bin/check-a`) for docs-only PRs"):
        assert not us._is_whole_bullet(title, line), line


def test_sentence_cut_inside_parens_keeps_the_words_after_them():
    """A `. ` or `: ` inside the parentheses, or an unbalanced backtick there, never
    drops the words after the closing paren."""
    approved = "- Relax the check (`bin/check-a`)"
    for inside in ("line 3", "e.g. line 3", "vs. line 3", "line 3: guard",
                   "line 3. Also line 9", "line `3"):
        wider = (f"- [BLOCKING] [unplanned-scope] Relax the check (`bin/check-a` {inside}) "
                 "for all PRs and drop the signoff hold")
        assert not us._listed(wider, approved), inside


def test_sentence_cut_inside_parens_still_drops_a_trailing_citation():
    for tail in ("", ". Later prose.", ": later prose"):
        line = f"- Relax the check (`bin/check-a` e.g. line 3: guard. Also line 9){tail}"
        assert us.title_key(line) == "Relax the check", line


def test_stacked_trailing_citations_are_all_dropped():
    """A finding that ends in a path parenthetical gets the rendered path suffix after
    it. Both trailing groups drop, including when the first one holds nested parens."""
    for suffix in ("(`bin/check-a`)", "(no path cited)"):
        line = f"- Relax the check (`bin/check-a` now calls `f()` (twice)) {suffix}"
        assert us.title_key(line) == "Relax the check", line
