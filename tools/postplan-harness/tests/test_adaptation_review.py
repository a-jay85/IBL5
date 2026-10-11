"""Tests for the read-only adaptation reviewer in adaptations.py."""
from __future__ import annotations

import os
import shutil
import sys
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adaptations import (
    ADAPT_ABSENT,
    ADAPT_CONFIRMED,
    ADAPT_DENIED,
    NearMatch,
    Transform,
    parse_adaptation_verdicts,
    review_adaptations,
)

_STUB_DIFF = "diff --git a/bin/a.sh b/bin/a.sh\n-/tmp/x\n+\"$RL\"/x\n"


class _StubLlm:
    """Records call_tooled kwargs; replies are driven by a list — strings or exceptions."""
    def __init__(self, replies):
        self.replies = list(replies)
        self.calls: list[dict] = []

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({
            "purpose": purpose, "model": model,
            "allowed_tools": allowed_tools, "denied_tools": denied_tools,
            "add_dirs": add_dirs, "prompt": prompt,
        })
        if not self.replies:
            return "FAILED"
        reply = self.replies.pop(0)
        if isinstance(reply, BaseException):
            raise reply
        return reply


class _DiffRun:
    """Git-run stub returning a canned diff for ("diff", ...)."""
    def __init__(self):
        self.calls: list[tuple] = []

    def __call__(self, *args, check=True) -> str:
        self.calls.append(args)
        if args and args[0] == "diff":
            return _STUB_DIFF
        return ""


def _matches() -> tuple[NearMatch, ...]:
    return (
        NearMatch("bin/a.sh", "cp /tmp/x y", 'cp "$RL"/x y', (Transform("/tmp/", '"$RL"/'),)),
        NearMatch("bin/b.sh", "rm /tmp/z", 'rm "$RL"/z', (Transform("/tmp/", '"$RL"/'),)),
        NearMatch("bin/a.sh", "ls /tmp/w", 'ls "$RL"/w', (Transform("/tmp/", '"$RL"/'),)),
    )


@pytest.fixture
def key():
    k = uuid.uuid4().hex
    yield k
    shutil.rmtree(f"/tmp/postplan-adapt-review-{k}", ignore_errors=True)


def _review(llm, key, matches=None):
    return review_adaptations(
        llm, _DiffRun(), worktree="/tmp", key=key,
        matches=_matches() if matches is None else matches,
        merge_base="base", master_sha="tip",
    )


def _verdict_text(key: str) -> str:
    return Path(f"/tmp/postplan-adapt-review-{key}/verdict.txt").read_text()


def test_parse_adaptation_verdicts_exact_tokens_only():
    reply = (
        "I think ADAPTED-LINE-1=CONFIRMED is right\n"
        "ADAPTED-LINE-2=CONFIRMED because it matches\n"
        "\tADAPTED-LINE-1=CONFIRMED\r\n"
        "ADAPTED-LINE-7=CONFIRMED\n"
    )
    assert parse_adaptation_verdicts(reply, 2) == {1: ADAPT_CONFIRMED, 2: ADAPT_ABSENT}


def test_parse_adaptation_verdicts_both_tokens_same_index_is_denied():
    reply = "ADAPTED-LINE-1=CONFIRMED\nADAPTED-LINE-1=DENIED\nADAPTED-LINE-2=CONFIRMED\n"
    assert parse_adaptation_verdicts(reply, 2) == {1: ADAPT_DENIED, 2: ADAPT_CONFIRMED}


def test_review_adaptations_all_confirmed_returns_ok(key):
    reply = (
        "All three follow master's rename.\n"
        "ADAPTED-LINE-1=CONFIRMED\nADAPTED-LINE-2=CONFIRMED\nADAPTED-LINE-3=CONFIRMED\n"
    )
    ok, reason, verdicts = _review(_StubLlm([reply]), key)
    assert ok is True
    assert reason == ""
    assert verdicts == {1: ADAPT_CONFIRMED, 2: ADAPT_CONFIRMED, 3: ADAPT_CONFIRMED}
    assert _verdict_text(key).splitlines()[0] == "ADAPTED-LINE-1=CONFIRMED"


def test_review_adaptations_missing_index_fails_closed(key):
    reply = "ADAPTED-LINE-1=CONFIRMED\nADAPTED-LINE-2=CONFIRMED\n"
    ok, reason, verdicts = _review(_StubLlm([reply]), key)
    assert ok is False
    assert "[3]=ABSENT" in reason
    assert verdicts[3] == ADAPT_ABSENT


def test_review_adaptations_denied_index_fails_closed(key):
    reply = "ADAPTED-LINE-1=CONFIRMED\nADAPTED-LINE-2=DENIED\nADAPTED-LINE-3=CONFIRMED\n"
    ok, reason, verdicts = _review(_StubLlm([reply]), key)
    assert ok is False
    assert "[2]=DENIED" in reason
    assert verdicts[2] == ADAPT_DENIED
    lines = _verdict_text(key).splitlines()
    assert lines[:3] == [
        "ADAPTED-LINE-1=CONFIRMED", "ADAPTED-LINE-2=DENIED", "ADAPTED-LINE-3=CONFIRMED",
    ]


def test_review_adaptations_call_raises_fails_closed(key):
    ok, reason, verdicts = _review(_StubLlm([RuntimeError("boom")]), key)
    assert ok is False
    assert verdicts == {1: ADAPT_ABSENT, 2: ADAPT_ABSENT, 3: ADAPT_ABSENT}
    assert "[1]=ABSENT" in reason
    assert os.path.exists(f"/tmp/postplan-adapt-review-{key}/verdict.txt")


def test_review_adaptations_reviewer_is_read_only_and_sees_evidence(key):
    llm = _StubLlm(["ADAPTED-LINE-1=CONFIRMED\nADAPTED-LINE-2=CONFIRMED\nADAPTED-LINE-3=CONFIRMED\n"])
    run = _DiffRun()
    review_adaptations(
        llm, run, worktree="/tmp", key=key, matches=_matches(),
        merge_base="base", master_sha="tip",
    )
    review_dir = f"/tmp/postplan-adapt-review-{key}"
    assert len(llm.calls) == 1
    call = llm.calls[0]
    assert call["purpose"] == "conflict-adapt-review"
    assert call["allowed_tools"] == ("Read",)
    for tool in ("Write", "Edit", "Bash", "Agent"):
        assert tool in call["denied_tools"]
    assert review_dir in call["add_dirs"]

    adaptations = Path(f"{review_dir}/adaptations.txt").read_text()
    for n, m in enumerate(_matches(), start=1):
        assert f"[{n}] file: {m.path}" in adaptations
        assert f"original: {m.original}" in adaptations
        assert f"adapted:  {m.adapted}" in adaptations
        assert f"{m.transforms[0].old} -> {m.transforms[0].new}" in adaptations
    assert Path(f"{review_dir}/master-side.diff").read_text() == _STUB_DIFF
    assert run.calls == [
        ("diff", "-U3", "--no-color", "base", "tip", "--", "bin/a.sh", "bin/b.sh"),
    ]
    assert "ADAPTED-LINE-<n>=CONFIRMED" in call["prompt"]
    assert "[1] file: bin/a.sh" in call["prompt"]
    assert "[3] file: bin/a.sh" in call["prompt"]


def test_review_adaptations_stale_review_dir_is_cleared(key):
    review_dir = Path(f"/tmp/postplan-adapt-review-{key}")
    review_dir.mkdir(parents=True)
    (review_dir / "verdict.txt").write_text("ADAPTED-LINE-1=CONFIRMED\nstale\n")
    (review_dir / "stale-extra.txt").write_text("old")
    ok, _, _ = _review(_StubLlm(["no tokens at all"]), key)
    assert ok is False
    assert not (review_dir / "stale-extra.txt").exists()
    assert "stale" not in _verdict_text(key)


def test_review_adaptations_empty_matches_fails_closed(key):
    llm = _StubLlm(["ADAPTED-LINE-1=CONFIRMED"])
    ok, reason, verdicts = _review(llm, key, matches=())
    assert ok is False
    assert reason == "adaptation review: nothing to review"
    assert verdicts == {}
    assert llm.calls == []
