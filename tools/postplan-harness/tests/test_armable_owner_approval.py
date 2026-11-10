"""Condition (7): a plan's `auto_merge: false` hold clears only on an owner
approval bound to the current diff (the human-approved label plus a matching
github-actions[bot] fingerprint record)."""
import subprocess

import pytest

from harness import armable
from harness.armable import (C7_APPROVAL_REASONS, evaluate, owner_approval_state,
                             resolve_owner_approval)
from harness.state import HarnessError

from tests.test_armable import inputs

BOT = {"login": "github-actions[bot]", "type": "Bot"}


def _c7(decision):
    return next(c for c in decision.conditions if c.number == 7)


def _git(repo, *args):
    return subprocess.run(["git", "-C", str(repo), "-c", "user.name=t", "-c", "user.email=t@t",
                           *args], check=True, capture_output=True, text=True).stdout.strip()


def _write30(repo, line20="line20"):
    lines = [f"line{i}" for i in range(1, 31)]
    lines[19] = line20
    (repo / "f.txt").write_text("\n".join(lines) + "\n")


def _fingerprint(repo, base, head):
    out = subprocess.run(
        ["bash", "-c", 'source "$1" && hs_diff_fingerprint "$2" "$3"', "_",
         str(armable.HS_CLASSIFIER), base, head],
        cwd=repo, check=True, capture_output=True, text=True)
    return out.stdout.strip()


def _record(fp, at="2026-11-01T00:00:00Z", user=None):
    return {"created_at": at, "user": user or BOT,
            "body": f"<!-- hs-approved-diff: {fp} -->\nRecorded."}


@pytest.fixture
def scratch(tmp_path):
    """Phase 1 layout: a 30-line file, branch `pr` changes line 20 and is checked out."""
    repo = tmp_path / "r"
    repo.mkdir()
    _git(repo, "init", "-q")
    _git(repo, "checkout", "-q", "-b", "master")
    _write30(repo)
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", "base")
    _git(repo, "checkout", "-q", "-b", "older")
    _write30(repo, "line20 older")
    _git(repo, "commit", "-q", "-am", "older")
    _git(repo, "checkout", "-q", "-b", "pr", "master")
    _write30(repo, "line20 pr")
    _git(repo, "commit", "-q", "-am", "pr")
    return repo


# ---- evaluate(): condition (7) reads owner_approval ----

def test_c7_false_clears_on_matching_approval():
    d = evaluate(inputs(plan_auto_merge_value="false", pr_labels=["human-approved"],
                        owner_approval="match"))
    assert not _c7(d).blocked


def test_c7_bool_false_clears_on_matching_approval():
    d = evaluate(inputs(plan_auto_merge_false=True, plan_auto_merge_value=None,
                        pr_labels=["human-approved"], owner_approval="match"))
    assert not _c7(d).blocked


def test_c7_false_holds_on_mismatch_with_label():
    d = evaluate(inputs(plan_auto_merge_value="false", pr_labels=["human-approved"],
                        owner_approval="mismatch"))
    c7 = _c7(d)
    assert c7.blocked
    assert c7.reason.endswith("human-approved was given for an older diff")


@pytest.mark.parametrize("token,expected", [
    ("no-label", C7_APPROVAL_REASONS["no-label"]),
    ("no-record", C7_APPROVAL_REASONS["no-record"]),
    ("error", C7_APPROVAL_REASONS["error"]),
    ("bogus", C7_APPROVAL_REASONS["error"]),
])
def test_c7_false_hold_reasons_name_missing_piece(token, expected):
    c7 = _c7(evaluate(inputs(plan_auto_merge_value="false", owner_approval=token)))
    assert c7.blocked
    assert c7.reason == f"plan declares auto_merge: false; {expected}"


def test_c7_false_hold_reasons_name_missing_piece_unchecked_keeps_old_reason():
    c7 = _c7(evaluate(inputs(plan_auto_merge_value="false")))
    assert c7.blocked
    assert c7.reason == "plan declares auto_merge: false"


def test_c7_other_auto_merge_values_ignore_approval():
    assert _c7(evaluate(inputs(plan_auto_merge_value="maybe", owner_approval="match"))).blocked
    assert not _c7(evaluate(inputs(plan_auto_merge_value="true", owner_approval="match"))).blocked
    assert not _c7(evaluate(inputs(plan_auto_merge_value=None, owner_approval="match"))).blocked


def test_second_review_seat_still_sees_c7_without_approval():
    kw = dict(plan_auto_merge_false=True, plan_auto_merge_value="false",
              plan_hold_triggers=("gate-weakening",))
    before = evaluate(inputs(**kw)).second_review_candidates
    after = evaluate(inputs(owner_approval="no-label", **kw)).second_review_candidates
    assert 7 in after
    assert set(after) == set(before)


# ---- owner_approval_state(): the shell helper on a scratch repo ----

@pytest.mark.parametrize("case", ["match", "revoked", "non-bot", "older", "none", "bad-head"])
def test_owner_approval_state_scratch_repo(scratch, case):
    fp = _fingerprint(scratch, "master", "pr")
    head = "pr"
    comments = [_record(fp)]
    want = "match"
    if case == "revoked":
        comments.append({"created_at": "2026-11-02T00:00:00Z", "user": BOT,
                         "body": "<!-- hs-approved-diff: revoked -->\nRemoved."})
        want = "no-record"
    elif case == "non-bot":
        comments = [_record(fp, user={"login": "someone", "type": "User"})]
        want = "no-record"
    elif case == "older":
        comments = [_record(_fingerprint(scratch, "master", "older"))]
        want = "mismatch"
    elif case == "none":
        comments = None
        want = "error"
    elif case == "bad-head":
        head = "no-such-ref"
        want = "error"
    assert owner_approval_state(["human-approved"], comments, "master", head, scratch) == want


def test_owner_approval_state_no_label_skips_shell(monkeypatch):
    def boom(*a, **k):
        raise AssertionError("subprocess.run must not run without the label")
    monkeypatch.setattr(armable.subprocess, "run", boom)
    assert owner_approval_state([], [], "master", "HEAD", ".") == "no-label"


# ---- resolve_owner_approval(): the runner entry ----

class _RaisingGh:
    def __getattr__(self, name):
        def fail(*a, **k):
            raise AssertionError(f"gh.{name} must not be called")
        return fail


class _FakeGit:
    def __init__(self):
        self.fetched = []

    def fetch_base(self, base):
        self.fetched.append(base)


def test_resolve_owner_approval_skips_when_plan_allows_merge():
    assert resolve_owner_approval(_RaisingGh(), _FakeGit(), 1, ["human-approved"], ".",
                                  plan_false=False) == "unchecked"


def test_resolve_owner_approval_read_error_holds():
    class Gh:
        def pr_base_ref(self):
            return "master"

        def pr_comments_json(self, pr):
            raise HarnessError("gh", "comment read failed")

    assert resolve_owner_approval(Gh(), _FakeGit(), 1, ["human-approved"], ".",
                                  plan_false=True) == "error"


def test_resolve_owner_approval_uses_pr_base(tmp_path):
    """A stacked PR's record is taken against origin/parent, never origin/master."""
    origin = tmp_path / "origin.git"
    subprocess.run(["git", "init", "-q", "--bare", str(origin)], check=True)
    repo = tmp_path / "w"
    repo.mkdir()
    _git(repo, "init", "-q")
    _git(repo, "checkout", "-q", "-b", "master")
    _write30(repo)
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", "base")
    _git(repo, "checkout", "-q", "-b", "parent")
    (repo / "g.txt").write_text("parent\n")
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", "parent")
    _git(repo, "checkout", "-q", "-b", "child")
    _write30(repo, "line20 child")
    _git(repo, "commit", "-q", "-am", "child")
    _git(repo, "remote", "add", "origin", str(origin))
    _git(repo, "push", "-q", "origin", "master", "parent")
    _git(repo, "fetch", "-q", "origin")
    fp = _fingerprint(repo, "origin/parent", "HEAD")
    assert fp != _fingerprint(repo, "origin/master", "HEAD")

    class Gh:
        def pr_base_ref(self):
            return "parent"

        def pr_comments_json(self, pr):
            return [_record(fp)]

    git = _FakeGit()
    assert resolve_owner_approval(Gh(), git, 1, ["human-approved"], repo,
                                  plan_false=True) == "match"
    assert git.fetched == ["origin/parent"]
