"""Kept unplanned scope counts as owner-approved only with a verified owner quote."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import unplanned_scope as us

FOO = "bin/foo"
OWNER_WORDS = "please also add a json flag to bin/foo"
KEPT = f'- [BLOCKING] [unplanned-scope] Adds a json flag to {FOO}; owner: "{OWNER_WORDS}"'
OTHER_KEPT = f'- [BLOCKING] [unplanned-scope] Adds a yaml mode to {FOO}; owner: "{OWNER_WORDS}"'
BUG = f"- [BLOCKING] {FOO} --json crashes on an empty list (correctness)"
NOTE = "- [NOTE] The help text could mention the new flag."


def _payload(text="Please also add a JSON flag to bin/foo while you are in there."):
    return {"version": 1, "session": "s",
            "turns": [{"timestamp": "t", "text": text, "redactions": 0, "stripped": []}]}


def _ctx():
    return {
        "changed_paths": [FOO],
        "subjects_by_path": {FOO: ["feat: add a json flag"]},
        "must_preserve": set(),
        "removed_guard_paths": set(),
    }


def _verdict(tmp_path, *items, word="NOT READY"):
    path = tmp_path / "verdict.md"
    path.write_text("## FINDINGS\n" + "\n".join(items) + f"\n\n{word}\n", encoding="utf-8")
    return str(path)


def _body(text=KEPT):
    return us.upsert_kept_block("", us.render_kept_block([{"hold": "12", "text": text}], _ctx()))


def test_kept_owner_scope_only_blocker_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    assert us.downgrade_applies(path, _ctx(), _body(), _payload()) is True
    assert us.owner_scope_word(path) == "READY"


def test_kept_owner_scope_with_note_ready_with_notes(tmp_path):
    path = _verdict(tmp_path, KEPT, NOTE)
    assert us.downgrade_applies(path, _ctx(), _body(), _payload()) is True
    assert us.owner_scope_word(path) == "READY WITH NOTES"


def test_bug_in_kept_scope_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT, BUG)
    assert us.downgrade_applies(path, _ctx(), _body(), _payload()) is False


def test_unplanned_not_in_kept_block_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    body = _body(OTHER_KEPT)
    assert us.title_of(KEPT) not in body
    assert us.owner_evidence_ok(KEPT, _payload()) is True
    assert us.downgrade_applies(path, _ctx(), body, _payload()) is False


def test_missing_kept_block_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    assert us.downgrade_applies(path, _ctx(), "intro with no markers\n", _payload()) is False


def test_malformed_kept_block_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    title = us.title_of(KEPT)
    body = f"{us.KEPT_END}\n- {title}\n{us.KEPT_BEGIN}\n"
    assert us.downgrade_applies(path, _ctx(), body, _payload()) is False


def test_no_owner_intent_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    assert us.downgrade_applies(path, _ctx(), _body(), None) is False
    assert us.downgrade_applies(path, _ctx(), _body()) is False


def test_quote_not_in_owner_request_not_ready(tmp_path):
    path = _verdict(tmp_path, KEPT)
    payload = _payload("Fix the roster page sort order.")
    assert us.downgrade_applies(path, _ctx(), _body(), payload) is False


def test_owner_quote_near_miss_forms():
    assert us.owner_quote('- [BLOCKING] [unplanned-scope] x; owner said "please also add a json flag"') is None
    assert us.owner_quote('- [BLOCKING] [unplanned-scope] x; owner: "add a flag."') is None   # 11 chars
    assert us.owner_quote("- [BLOCKING] [unplanned-scope] x; owner: “please also add a json flag”") is None
    assert us.owner_quote("- [BLOCKING] [unplanned-scope] x; owner: `please also add a json flag`") is None
    variant = '- [BLOCKING] [unplanned-scope] x; owner:  "Please ALSO add a json flag to bin/foo"'
    assert us.owner_quote(variant) == "Please ALSO add a json flag to bin/foo"
    assert us.owner_evidence_ok(variant, _payload()) is True
    assert us.owner_evidence_ok('- x; owner: "add a flag."', _payload("add a flag.")) is False
