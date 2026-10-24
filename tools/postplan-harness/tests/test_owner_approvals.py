"""Owner-approval record module: parse, owner-only trust, fail-closed, drift."""
from __future__ import annotations

import json

import pytest

from harness import owner_approvals as oa
from harness import thread_ingestion
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.state import HarnessError


def _rec(comment_id, login, typ, *, rid="oad-0123abcd", path="ibl5/x.php",
         keep=("if ($teamId === 0) { return; }",), gone=()):
    payload = {
        "schema": 1, "id": rid, "summary": "owner approved deviation",
        "files": [{"path": path, "keep": list(keep), "gone": list(gone)}],
    }
    body = f"{oa.APPROVAL_MARKER}\n```json\n{json.dumps(payload)}\n```\n"
    return {"id": comment_id, "body": body, "login": login, "type": typ}


def _gh(tmp_path, comments=None, **extra):
    fixture = {"pr_issue_comments": comments or []}
    fixture.update(extra)
    return RecordingGh(str(tmp_path / "out"), fixture)


def test_owner_record_parsed(tmp_path):
    c = _rec(1, "a-jay85", "User", keep=("    if ($teamId === 0)   { return; }  ",))
    snap = oa.snapshot(_gh(tmp_path, [c]), 5)
    assert len(snap.records) == 1
    assert snap.records[0].files[0].keep == ("if ($teamId === 0) { return; }",)
    assert snap.ignored_untrusted == 0


def test_untrusted_author_record_ignored(tmp_path):
    snap = oa.snapshot(_gh(tmp_path, [_rec(1, "someone", "User")]), 5)
    assert snap.records == ()
    assert snap.ignored_untrusted == 1


def test_bot_type_owner_login_lookalike_ignored(tmp_path):
    snap = oa.snapshot(_gh(tmp_path, [_rec(1, "a-jay85", "Bot")]), 5)
    assert snap.records == ()
    assert snap.ignored_untrusted == 1


def test_bot_suffix_login_ignored(tmp_path):
    snap = oa.snapshot(_gh(tmp_path, [_rec(1, "a-jay85[bot]", "Bot")]), 5)
    assert snap.records == ()
    assert snap.ignored_untrusted == 1


def test_pr_body_block_contributes_nothing(tmp_path):
    body = _rec(1, "a-jay85", "User")["body"]
    gh = _gh(tmp_path, [], pr_body=body)
    snap = oa.snapshot(gh, 5)
    assert snap.records == ()


def test_owner_malformed_record_fails_closed(tmp_path):
    bad = {"id": 1, "login": "a-jay85", "type": "User",
           "body": f"{oa.APPROVAL_MARKER}\n```json\n{{not json\n```\n"}
    with pytest.raises(HarnessError) as ei:
        oa.snapshot(_gh(tmp_path, [bad]), 5)
    assert ei.value.kind == "owner-approval-record"


def test_record_with_only_trivial_anchors_rejected(tmp_path):
    c = _rec(1, "a-jay85", "User", keep=("}", "else"), gone=("{", "return;"))
    with pytest.raises(HarnessError) as ei:
        oa.snapshot(_gh(tmp_path, [c]), 5)
    assert ei.value.kind == "owner-approval-record"


def test_path_traversal_rejected(tmp_path):
    c = _rec(1, "a-jay85", "User", path="../x.php")
    with pytest.raises(HarnessError) as ei:
        oa.snapshot(_gh(tmp_path, [c]), 5)
    assert ei.value.kind == "owner-approval-record"


def test_duplicate_id_fails_closed(tmp_path):
    a = _rec(1, "a-jay85", "User", keep=("first anchor line here();",))
    b = _rec(2, "a-jay85", "User", keep=("second anchor line here();",))
    with pytest.raises(HarnessError) as ei:
        oa.snapshot(_gh(tmp_path, [a, b]), 5)
    assert ei.value.kind == "owner-approval-record"


def test_gh_error_fails_closed(tmp_path):
    gh = _gh(tmp_path, [_rec(1, "a-jay85", "User")], pr_issue_comments_error=True)
    with pytest.raises(HarnessError):
        oa.snapshot(gh, 5)


def test_verify_unchanged_detects_new_record(tmp_path):
    gh = _gh(tmp_path, [_rec(1, "a-jay85", "User")])
    snap = oa.snapshot(gh, 5)
    assert oa.verify_unchanged(gh, 5, snap) is None
    gh.fixture["pr_issue_comments"].append(
        _rec(2, "a-jay85", "User", rid="oad-89abcdef"))
    with pytest.raises(HarnessError) as ei:
        oa.verify_unchanged(gh, 5, snap)
    assert ei.value.kind == "owner-approval-drift"


def test_owner_login_matches_trusted_login():
    assert oa.OWNER_LOGIN == thread_ingestion.TRUSTED_LOGIN


def test_live_gh_pr_issue_comments_parses_ndjson(tmp_path, monkeypatch):
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    lines = "\n".join([
        json.dumps({"id": 1, "body": "a", "login": "a-jay85", "type": "User"}),
        json.dumps({"id": 2, "body": "b", "login": "x[bot]", "type": "Bot"}),
    ]) + "\n"
    seen = {}

    def fake(*args, **kw):
        seen["args"] = args
        return lines

    monkeypatch.setattr(gh, "_gh", fake)
    rows = gh.pr_issue_comments(7)
    assert [r["login"] for r in rows] == ["a-jay85", "x[bot]"]
    assert rows[1]["type"] == "Bot"
    assert "login: .user.login" in seen["args"][-1]


def test_anchors_by_path_returns_keep_gone_counters(tmp_path):
    c = _rec(1, "a-jay85", "User", keep=("keep line number one",),
             gone=("gone line number one",))
    snap = oa.snapshot(_gh(tmp_path, [c]), 5)
    keep, gone = oa.anchors_by_path(snap)["ibl5/x.php"]
    assert keep["keep line number one"] == 1
    assert gone["gone line number one"] == 1
