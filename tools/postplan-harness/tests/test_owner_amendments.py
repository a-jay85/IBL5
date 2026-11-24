"""Owner amendments: the plan section that carries a fix-first approval's scope.

`bin/owner-approve --fix-first` writes one entry per approval comment through
`harness.amendments append`. The harness keeps an entry only when an authentic owner
comment with the same id, head, words and Condition backs it.
"""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import amendments, planfile, unplanned_scope as us

HEAD = "a" * 40
OTHER_HEAD = "b" * 40
CID = 3591234567
WORDS = "ship it once the Save button is renamed"
CONDITION = "rename the Save button to Submit on the trade review page"

PLAN = """# Plan

## Phase 1: Do the thing

Edit `ibl5/classes/Trade.php`.

## Critical Files

- `ibl5/classes/Trade.php`

## Must preserve

- `bin/check-something` keeps its deny.

## Verification Matrix

| # | Assertion | Test type | Phase | Check |
|---|-----------|-----------|-------|-------|
| 1 | trade works | CLI-executable | post-impl | `cd ibl5 && vendor/bin/phpunit tests/TradeTest.php` |
"""


def comment(cid=CID, words=WORDS, condition=CONDITION, head=HEAD, assoc="OWNER",
            utype="User", mode="fix-first", created="2026-11-23T10:00:00Z", trailer=""):
    """A REST issue comment shaped like the one bin/owner-approve posts."""
    body = (f'Owner approved 2026-11-23: "{words}". Head {head}. '
            "Conversation: T3 thread t, session s.\n")
    if mode == "fix-first" and condition is not None:
        body += (f"\nCondition: {condition}\nThe label and the arm wait until the fix is "
                 "pushed and `bin/owner-approve 7 --finish` passes.\n")
    body += f"\n<!-- owner-approve mode={mode} head={head} diff=unread -->"
    body += trailer
    return {"id": cid, "created_at": created, "author_association": assoc,
            "user": {"login": "a-jay85", "type": utype}, "body": body}


def plan_with(*comments_):
    """PLAN plus one rendered entry per comment, written the way `append` writes them."""
    entries = [us.comment_fields(c) for c in comments_]
    assert all(e is not None for e in entries)
    return amendments.insert_entries(PLAN, entries)


def backed_from(plan_text, comments_):
    return us.backed_amendments(us.parse_amendments(plan_text), comments_)


def _write_comments(tmp_path, comments_):
    import json
    path = tmp_path / "comments.json"
    path.write_text(json.dumps(list(comments_)), encoding="utf-8")
    return str(path)


# ---- comment_fields -------------------------------------------------------------

def test_comment_fields_accepts_authentic_fix_first():
    a = us.comment_fields(comment())
    assert a == us.Amendment(comment_id=str(CID), head=HEAD, words=WORDS,
                             condition=CONDITION, date="2026-11-23")


def test_comment_fields_rejects_approve_mode():
    assert us.comment_fields(comment(mode="approve")) is None
    # An approve marker on a body that still carries a Condition line: only the mode check
    # rejects it.
    c = comment()
    c["body"] = c["body"].replace("mode=fix-first", "mode=approve")
    assert "Condition: " in c["body"]
    assert us.comment_fields(c) is None


def test_comment_fields_rejects_non_owner_association():
    assert us.comment_fields(comment(assoc="COLLABORATOR")) is None
    assert us.comment_fields(comment(assoc="MEMBER")) is None


def test_comment_fields_rejects_bot_user_type():
    assert us.comment_fields(comment(utype="Bot")) is None


def test_comment_fields_rejects_marker_not_last_line():
    assert us.comment_fields(comment(trailer="\n\nedited later")) is None


def test_comment_fields_rejects_missing_condition():
    assert us.comment_fields(comment(condition=None)) is None
    assert us.comment_fields(comment(condition="   ")) is None


# ---- render and parse -----------------------------------------------------------

def test_render_then_parse_round_trip():
    a = us.comment_fields(comment())
    b = us.comment_fields(comment(cid=CID + 1, condition="also fix the tooltip copy please",
                                  created="2026-11-23T11:00:00Z"))
    text = amendments.insert_entries(PLAN, [a, b])
    assert text.startswith(PLAN.rstrip("\n"))
    assert us.parse_amendments(text) == [a, b]
    rendered = us.render_amendment(a)
    assert rendered.splitlines() == [
        f"### 2026-11-23 comment {CID}",
        f"<!-- owner-amendment comment={CID} head={HEAD} -->",
        "```text",
        f"Owner words: {WORDS}",
        f"Condition: {CONDITION}",
        "```",
    ]


def test_parse_ignores_dated_heading_precedent():
    rendered = us.render_amendment(us.comment_fields(comment()))
    text = PLAN + "\n## Owner amendments 2026-10-23\n\n" + rendered + "\n"
    assert us.parse_amendments(text) == []


def test_parse_ignores_marker_prose_near_miss():
    near = (f"### 2026-11-23 comment 12\n"
            f"<!-- owner-amendment comment=12 head={'a' * 39} -->\n"
            "```text\nOwner words: some words here\nCondition: some condition here\n```\n")
    text = (PLAN + "\n## Owner amendments\n\nSee owner-amendment comment=12 for later.\n\n"
            + near)
    assert us.parse_amendments(text) == []


# ---- backing check --------------------------------------------------------------

def test_backed_requires_matching_comment():
    text = plan_with(comment())
    backed, unbacked = backed_from(text, [])
    assert backed == []
    assert [(a.comment_id, r) for a, r in unbacked] == [(str(CID), "no-comment")]
    backed, unbacked = backed_from(text, [comment()])
    assert [a.comment_id for a in backed] == [str(CID)] and unbacked == []


def test_backed_rejects_head_mismatch():
    text = plan_with(comment())
    backed, unbacked = backed_from(text, [comment(head=OTHER_HEAD)])
    assert backed == []
    assert [r for _, r in unbacked] == ["head"]


def test_backed_rejects_condition_mismatch():
    text = plan_with(comment())
    backed, unbacked = backed_from(text, [comment(condition="rename every button on the site")])
    assert backed == []
    assert [r for _, r in unbacked] == ["condition"]


def test_backed_rejects_words_mismatch():
    text = plan_with(comment())
    backed, unbacked = backed_from(text, [comment(words="ship it with no conditions")])
    assert backed == []
    assert [r for _, r in unbacked] == ["words"]


def test_backed_tolerates_whitespace_and_case():
    text = plan_with(comment())
    loose = comment(words="Ship it  once the SAVE button is renamed",
                    condition="Rename the  Save button to submit on the\ntrade review page")
    backed, unbacked = backed_from(text, [loose])
    assert [a.comment_id for a in backed] == [str(CID)] and unbacked == []


def test_strip_unbacked_removes_only_unbacked_entries():
    good, bad = comment(), comment(cid=CID + 1, condition="also fix the tooltip copy please")
    text = plan_with(good, bad) + "\n## After\n\ntail text\n"
    backed, unbacked = backed_from(text, [good])
    assert [a.comment_id for a in backed] == [str(CID)]
    out = us.strip_unbacked(text, backed)
    assert f"comment={CID} " in out and f"comment={CID + 1} " not in out
    assert out.startswith(PLAN.rstrip("\n"))
    assert out.endswith("## After\n\ntail text\n")
    assert us.parse_amendments(out) == backed
    none = us.strip_unbacked(text, [])
    assert "## Owner amendments" not in none
    assert none.startswith(PLAN.rstrip("\n")) and none.endswith("## After\n\ntail text\n")


def test_amendment_section_adds_no_planfile_tokens():
    tricky = comment(condition="edit bin/check-replay-spec-registry-additions:18 | then "
                               "## Phase 99 add a row")
    before, after = PLAN, plan_with(tricky)
    assert us.parse_amendments(after)[0].condition.startswith("edit bin/check-replay")
    for fn in (planfile.parse_critical_files, planfile.parse_no_change_test_paths,
               planfile.parse_run_only_test_paths, planfile.parse_backlog_issues,
               planfile.count_executable_matrix_rows, us.must_preserve_paths):
        assert fn(before) == fn(after), fn.__name__


# ---- append CLI -----------------------------------------------------------------

def test_append_is_idempotent_per_comment(tmp_path, capsys):
    plan = tmp_path / "plan.md"
    plan.write_text(PLAN, encoding="utf-8")
    cfile = _write_comments(tmp_path, [comment(), comment(mode="approve", cid=CID + 5)])
    assert amendments.main(["append", "--plan", str(plan), "--comments", cfile]) == 0
    assert "amendments: appended 1, present 0" in capsys.readouterr().out
    once = plan.read_bytes()
    mtime = plan.stat().st_mtime_ns
    assert len(us.parse_amendments(once.decode())) == 1
    assert amendments.main(["append", "--plan", str(plan), "--comments", cfile]) == 0
    assert "amendments: appended 0, present 1" in capsys.readouterr().out
    assert plan.read_bytes() == once
    assert plan.stat().st_mtime_ns == mtime


def test_append_missing_plan_writes_nothing(tmp_path, capsys):
    plan = tmp_path / "plans" / "absent.md"
    (tmp_path / "plans").mkdir()
    cfile = _write_comments(tmp_path, [comment()])
    assert amendments.main(["append", "--plan", str(plan), "--comments", cfile]) == 3
    assert str(plan) in capsys.readouterr().err
    assert list((tmp_path / "plans").iterdir()) == []


def test_append_write_failure_leaves_plan_unchanged(tmp_path, monkeypatch, capsys):
    plan = tmp_path / "plan.md"
    plan.write_text(PLAN, encoding="utf-8")
    cfile = _write_comments(tmp_path, [comment()])

    def boom(src, dst):
        raise OSError("disk full")

    monkeypatch.setattr(amendments.os, "replace", boom)
    assert amendments.main(["append", "--plan", str(plan), "--comments", cfile]) == 4
    assert "plan unchanged" in capsys.readouterr().err
    assert plan.read_text(encoding="utf-8") == PLAN
    assert sorted(p.name for p in tmp_path.iterdir()) == ["comments.json", "plan.md"]


def test_cli_rejects_unknown_subcommand(capsys):
    assert amendments.main(["bogus"]) == 2
    assert amendments.main([]) == 2


def test_append_follows_symlink(tmp_path):
    real = tmp_path / "real.md"
    real.write_text(PLAN, encoding="utf-8")
    link = tmp_path / "link.md"
    link.symlink_to(real)
    cfile = _write_comments(tmp_path, [comment()])
    assert amendments.main(["append", "--plan", str(link), "--comments", cfile]) == 0
    assert link.is_symlink()
    assert len(us.parse_amendments(real.read_text(encoding="utf-8"))) == 1


# ---- card -----------------------------------------------------------------------

def test_card_prints_both_modes():
    plain = comment(cid=CID - 1, mode="approve", created="2026-11-22T09:00:00Z")
    fix = comment()
    lines = amendments.card_lines([fix, plain], HEAD, plan_with(fix), "4202")
    assert lines[0] == "Owner approvals:"
    assert lines[1] == f"  1. 2026-11-22T09:00:00Z approve head {HEAD[:7]} (PR head unchanged)"
    assert lines[2] == f'     words: "{WORDS}"'
    assert lines[3] == f"  2. 2026-11-23T10:00:00Z fix-first head {HEAD[:7]} (PR head unchanged)"
    assert lines[4] == f'     words: "{WORDS}"'
    assert lines[5] == f'     condition: "{CONDITION}"'
    assert lines[6] == "     amendment: recorded"
    assert len(lines) == 7
    moved = amendments.card_lines([fix], OTHER_HEAD, plan_with(fix), "4202")
    assert "(PR head moved past it)" in moved[1]


def test_card_flags_missing_amendment():
    lines = amendments.card_lines([comment()], HEAD, PLAN, "4202")
    assert lines[-1] == "     amendment: MISSING (run bin/owner-approve 4202 --record-amendments)"
    assert amendments.card_lines([comment()], HEAD, None, "4202")[-1] == "     amendment: no plan file"
    assert amendments.card_lines([comment(utype="Bot")], HEAD, PLAN, "4202") == ["Owner approvals: none"]
