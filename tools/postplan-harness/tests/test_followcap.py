"""Core tests for the per-PR follow-up cap (harness.followcap)."""
from __future__ import annotations

import json

import pytest

from harness.followcap import (
    CAP,
    ROLLUP_MARKER,
    FollowcapError,
    GhStore,
    Issue,
    MemoryStore,
    checklist_titles,
    file_followup,
    issues_for_pr,
)

URL = "https://github.com/a-jay85/IBL5/pull/900"


def _title(k: int) -> str:
    return f"follow-up item {k}"


def _body(k: int, url: str = URL) -> str:
    return f"{url}\n\nDetail for item {k}: unique-token-{k}"


def _file(store, k: int):
    return file_followup(store, "maintenance", _title(k), _body(k))


def _pr(store, url: str = URL):
    return issues_for_pr(store.list_all(), url)


@pytest.mark.parametrize("n", [2, 3, 4, 8])
def test_issue_count_is_min_n_cap(n):
    store = MemoryStore()
    for k in range(1, n + 1):
        _file(store, k)
    issues = _pr(store)
    assert len(issues) == min(n, CAP)
    rollups = [i for i in issues if ROLLUP_MARKER in i.body]
    if n <= CAP:
        assert rollups == []
        return
    assert len(rollups) == 1
    rollup = rollups[0]
    plain = [i for i in issues if i is not rollup and ROLLUP_MARKER not in i.body]
    assert [i.title for i in plain] == [_title(1), _title(2)]
    assert checklist_titles(rollup.body) == [_title(k) for k in range(3, n + 1)]
    for k in range(3, n + 1):
        assert f"unique-token-{k}" in rollup.body
    assert rollup.title == "Follow-ups from PR #900 (roll-up)"


def test_retry_before_conversion_files_nothing_new():
    store = MemoryStore()
    for k in (1, 2, 3):
        _file(store, k)
    kinds = [_file(store, k)[0] for k in (1, 2, 3)]
    assert kinds == ["exists"] * 3
    assert len(_pr(store)) == 3


def test_retry_after_conversion_files_nothing_new():
    store = MemoryStore()
    for k in range(1, 6):
        _file(store, k)
    kinds = [_file(store, k)[0] for k in range(1, 6)]
    assert kinds == ["exists"] * 5
    issues = _pr(store)
    assert len(issues) == 3
    rollup = next(i for i in issues if ROLLUP_MARKER in i.body)
    assert len(checklist_titles(rollup.body)) == 3


def test_closed_issues_count_toward_cap():
    store = MemoryStore([
        Issue(1, "old a", _body(91), "CLOSED"),
        Issue(2, "old b", _body(92), "CLOSED"),
    ])
    kinds = [_file(store, 1)[0], _file(store, 2)[0]]
    assert kinds == ["create", "convert"]
    assert len(_pr(store)) == 3


def test_closed_slot3_converted_checked_and_reopened():
    store = MemoryStore([
        Issue(1, "old a", _body(91)),
        Issue(2, "old b", _body(92)),
        Issue(3, "old c", _body(93), "CLOSED"),
    ])
    kind, num = _file(store, 1)
    assert kind == "convert"
    assert num == 3
    slot3 = next(i for i in store.list_all() if i.number == 3)
    assert "- [x] old c" in slot3.body
    assert f"- [ ] {_title(1)}" in slot3.body
    assert slot3.state == "OPEN"


def test_append_reopens_closed_rollup():
    store = MemoryStore([
        Issue(1, "old a", _body(91)),
        Issue(2, "old b", _body(92)),
    ])
    for k in (1, 2):
        _file(store, k)  # create slot 3, then convert it
    rollup = next(i for i in store.list_all() if ROLLUP_MARKER in i.body)
    store.issues[[i.number for i in store.issues].index(rollup.number)].state = "CLOSED"
    before = len(checklist_titles(rollup.body))
    kind, _ = _file(store, 3)
    assert kind == "append"
    after = next(i for i in store.list_all() if ROLLUP_MARKER in i.body)
    assert after.state == "OPEN"
    assert len(checklist_titles(after.body)) == before + 1


def test_legacy_over_cap_creates_one_rollup():
    store = MemoryStore([Issue(k, f"old {k}", _body(90 + k)) for k in range(1, 6)])
    kinds = [_file(store, 1)[0], _file(store, 2)[0]]
    assert kinds == ["create-rollup", "append"]
    issues = _pr(store)
    assert len(issues) == 6
    rollups = [i for i in issues if ROLLUP_MARKER in i.body]
    assert len(rollups) == 1
    assert checklist_titles(rollups[0].body) == [_title(1), _title(2)]
    assert [i.title for i in issues if ROLLUP_MARKER not in i.body] == [f"old {k}" for k in range(1, 6)]


def test_other_pr_issues_do_not_count():
    other = "https://github.com/a-jay85/IBL5/pull/9001"
    store = MemoryStore([Issue(k, f"other {k}", _body(k, other)) for k in range(1, 4)])
    kind, _ = _file(store, 1)
    assert kind == "create"


class _FailingStore:
    def __init__(self) -> None:
        self.calls: list[str] = []

    def list_all(self):
        self.calls.append("list_all")
        raise FollowcapError("read failed")

    def create(self, *a):
        self.calls.append("create")
        return 1

    def edit(self, *a):
        self.calls.append("edit")

    def reopen(self, *a):
        self.calls.append("reopen")


def test_list_failure_files_nothing():
    store = _FailingStore()
    with pytest.raises(FollowcapError):
        _file(store, 1)
    assert store.calls == ["list_all"]


def test_body_without_pr_url_rejected():
    store = _FailingStore()
    with pytest.raises(FollowcapError):
        file_followup(store, "maintenance", "t", "plain prose, no URL\n\nmore")
    assert store.calls == []


def test_ghstore_list_argv_uses_state_all_without_search():
    seen: list[tuple[str, ...]] = []

    def run(*args: str) -> str:
        seen.append(args)
        return "[]"

    GhStore(run).list_all()
    argv = seen[0]
    for tok in ("--state", "all", "--limit", "3000"):
        assert tok in argv
    assert "--search" not in argv


def test_ghstore_list_at_limit_fails_closed():
    rows = [{"number": k, "title": "t", "body": "b", "state": "OPEN", "labels": []} for k in range(3000)]
    with pytest.raises(FollowcapError):
        GhStore(lambda *a: json.dumps(rows)).list_all()


def test_ghstore_create_parses_issue_number():
    ok = GhStore(lambda *a: "https://github.com/a-jay85/IBL5-backlog/issues/412\n")
    assert ok.create("t", "b", "maintenance") == 412
    with pytest.raises(FollowcapError):
        GhStore(lambda *a: "created something\n").create("t", "b", "maintenance")
