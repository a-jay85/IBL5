"""Tests for harness/holdrepeat.py: normalization, structural key, record lifecycle, fingerprint, CLI."""
from __future__ import annotations

import json
import os
import subprocess
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat as hr


def _cond(number, blocked=True, reason="", name=None, as_dict=False):
    name = name or f"cond-{number}"
    if as_dict:
        return {"number": number, "name": name, "blocked": blocked, "reason": reason}
    return SimpleNamespace(number=number, name=name, blocked=blocked, reason=reason)


def _conds(spec, as_dict=False):
    """spec: {number: reason} of blocked conditions."""
    return [_cond(n, True, r, as_dict=as_dict) for n, r in spec.items()]


# --------------------------------------------------------------------------- normalize


def test_normalize_strips_volatile_tokens():
    text = (
        "head abc1234def at 2026-10-02T12:34:56Z then 12:34:56 in "
        "live-foo-bar-20261002-123456-4242 under "
        "/Users/x/GitHub/IBL5-worktrees/my-slug/ibl5/a.php and PR #123 "
        "stamp 20261002-123456 tmp /tmp/foo/bar.txt root /Users/x/GitHub/IBL5/bin/x"
    )
    out = hr.normalize_reason(text)
    assert "abc1234def" not in out and "<sha>" in out
    assert "2026-10-02" not in out and "12:34:56" not in out and "<ts>" in out
    assert "live-foo-bar-20261002-123456-4242" not in out and "<run-dir>" in out
    assert "IBL5-worktrees" not in out and "<wt>/ibl5/a.php" in out
    assert "PR #123" not in out and "PR <n>" in out
    assert "20261002-123456" not in out
    assert "<tmp>" in out and "/tmp/foo" not in out
    assert "<root>/bin/x" in out


def test_normalize_keeps_stable_identifiers():
    assert hr.normalize_reason("MISSING-PHASE: 8") == "MISSING-PHASE: 8"
    assert hr.normalize_reason("UNREALISED-ASSERTION: row 15") == "UNREALISED-ASSERTION: row 15"
    assert hr.normalize_reason("defaced") == "defaced"
    assert hr.normalize_reason("see #42") == "see #42"


def test_normalize_sorts_semicolon_parts():
    assert hr.normalize_reason("B; A") == hr.normalize_reason("A;  B")
    assert hr.normalize_reason("B; A") == "A; B"
    assert hr.normalize_reason("a;;  ; b") == "a; b"


# --------------------------------------------------------------------------- structural key


def test_structural_key_env_only_is_empty():
    conds = _conds({1: "manual", 12: "fidelity", 15: "ci red", 16: "meta"})
    assert hr.structural_key(conds) == ""
    assert hr.structural_key([]) == ""
    # unblocked structural conditions do not count
    assert hr.structural_key([_cond(3, blocked=False, reason="x")]) == ""


def test_structural_key_stable_across_env_flips():
    r3 = "MISSING-PHASE: 8; MISSING-PHASE: 2"
    r7 = "plan hold"
    seq = [
        {3: r3, 7: r7},
        {1: "manual", 3: "MISSING-PHASE: 2; MISSING-PHASE: 8", 7: r7, 16: "meta at 12:00:01"},
        {3: r3, 7: r7, 12: "fidelity abc1234", 15: "ci"},
    ]
    keys = {hr.structural_key(_conds(s)) for s in seq}
    assert len(keys) == 1
    key = keys.pop()
    assert key.startswith("3:") and "|7:plan hold" in key
    # dict-shaped conditions (result.json replay) give the same key
    assert hr.structural_key(_conds(seq[0], as_dict=True)) == key
    # hold_set carries every blocked condition, sorted by number
    hs = hr.hold_set(_conds(seq[2]))
    assert [h[0] for h in hs] == [3, 7, 12, 15]


# --------------------------------------------------------------------------- observe


def _observe(tmp_path, conds, *, armed=False, fp="fp", pr=1, now="2026-10-02T12:00:00Z"):
    return hr.observe(str(tmp_path), "slug", armed=armed, conditions=conds,
                      fingerprint=fp, pr=pr, now=now)


def test_observe_first_hold_records(tmp_path):
    obs = _observe(tmp_path, _conds({3: "m", 7: "p", 12: "f"}))
    assert obs.action == "recorded" and obs.repeat_count == 1
    assert obs.key == "3:m|7:p"
    assert [r[0] for r in obs.reasons] == [3, 7]
    rec = json.load(open(hr.record_path(str(tmp_path), "slug")))
    assert rec["schema_version"] == 1 and rec["slug"] == "slug"
    assert rec["structural_key"] == "3:m|7:p"
    assert rec["repeat_count"] == 1 and rec["dm_sent_key"] is None
    assert rec["fingerprint"] == "fp" and rec["pr"] == 1
    assert [h[0] for h in rec["hold_set"]] == [3, 7, 12]
    assert [h[0] for h in rec["structural"]] == [3, 7]
    assert rec["updated_at"] == "2026-10-02T12:00:00Z"
    assert hr.record_path(str(tmp_path), "slug").endswith("slug.holdrepeat.json")


def test_observe_identical_second_hold_dm_due(tmp_path):
    _observe(tmp_path, _conds({3: "m", 7: "p"}))
    obs = _observe(tmp_path, _conds({1: "x", 3: "m", 7: "p"}), fp="fp2", pr=2, now="later")
    assert obs.action == "repeat-dm" and obs.repeat_count == 2
    rec = hr.load_record(str(tmp_path), "slug")
    assert rec["repeat_count"] == 2 and rec["fingerprint"] == "fp2"
    assert rec["pr"] == 2 and rec["updated_at"] == "later"
    assert [h[0] for h in rec["hold_set"]] == [1, 3, 7]


def test_observe_after_dm_sent_is_silent(tmp_path):
    first = _observe(tmp_path, _conds({3: "m"}))
    hr.mark_dm_sent(str(tmp_path), "slug", first.key)
    assert hr.load_record(str(tmp_path), "slug")["dm_sent_key"] == "3:m"
    obs = _observe(tmp_path, _conds({3: "m"}))
    assert obs.action == "repeat-silent" and obs.repeat_count == 2
    obs = _observe(tmp_path, _conds({3: "m"}))
    assert obs.action == "repeat-silent" and obs.repeat_count == 3


def test_observe_changed_set_resets(tmp_path):
    first = _observe(tmp_path, _conds({3: "m"}))
    _observe(tmp_path, _conds({3: "m"}))
    hr.mark_dm_sent(str(tmp_path), "slug", first.key)
    obs = _observe(tmp_path, _conds({3: "m2"}))
    assert obs.action == "recorded" and obs.repeat_count == 1
    rec = hr.load_record(str(tmp_path), "slug")
    assert rec["repeat_count"] == 1 and rec["dm_sent_key"] is None
    assert rec["structural_key"] == "3:m2"


def test_observe_armed_clears(tmp_path):
    _observe(tmp_path, _conds({3: "m"}))
    assert os.path.exists(hr.record_path(str(tmp_path), "slug"))
    obs = _observe(tmp_path, _conds({3: "m"}), armed=True)
    assert obs.action == "cleared"
    assert not os.path.exists(hr.record_path(str(tmp_path), "slug"))
    # clearing an absent record is not an error
    assert _observe(tmp_path, [], armed=True).action == "cleared"


def test_observe_env_only_hold_clears(tmp_path):
    _observe(tmp_path, _conds({3: "m"}))
    obs = _observe(tmp_path, _conds({1: "manual", 15: "ci"}))
    assert obs.action == "cleared" and obs.key == ""
    assert hr.load_record(str(tmp_path), "slug") is None


def test_observe_write_failure_is_swallowed(tmp_path, capsys):
    blocker = tmp_path / "state"
    blocker.write_text("not a dir")
    obs = hr.observe(str(blocker), "slug", armed=False, conditions=_conds({3: "m"}),
                     fingerprint="fp", pr=1, now="t")
    assert obs.action == "recorded"
    assert "record write failed" in capsys.readouterr().err


def test_load_record_corrupt_or_wrong_schema_returns_none(tmp_path):
    sd = str(tmp_path)
    assert hr.load_record(sd, "slug") is None
    path = hr.record_path(sd, "slug")
    open(path, "w").write("{not json")
    assert hr.load_record(sd, "slug") is None
    open(path, "w").write(json.dumps({"schema_version": 2, "structural_key": "3:m"}))
    assert hr.load_record(sd, "slug") is None
    open(path, "w").write(json.dumps(["list"]))
    assert hr.load_record(sd, "slug") is None
    open(path, "w").write(json.dumps({"schema_version": 1, "structural_key": "3:m"}))
    assert hr.load_record(sd, "slug") == {"schema_version": 1, "structural_key": "3:m"}


# --------------------------------------------------------------------------- fingerprint


def _git(cwd, *args):
    subprocess.run(["git", "-C", str(cwd), *args], check=True, capture_output=True,
                   env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})


@pytest.fixture
def fp_env(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    _git(wt, "init", "-q")
    (wt / "a.txt").write_text("one\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-q", "-m", "init")
    _git(wt, "update-ref", "refs/remotes/origin/master", "HEAD")
    plan = tmp_path / "plan.md"
    plan.write_text("plan v1\n")
    hroot = tmp_path / "hroot"
    (hroot / "harness").mkdir(parents=True)
    (hroot / "harness" / "gate.py").write_text("x = 1\n")
    (hroot / "runner.py").write_text("r = 1\n")
    rroot = tmp_path / "rroot"
    (rroot / "bin" / "lib").mkdir(parents=True)
    (rroot / "bin" / "lib" / "plan-matrix-assertions").write_text("#!/bin/sh\n")

    def fp():
        return hr.fingerprint(str(plan), str(wt), harness_root=str(hroot), repo_root=str(rroot))

    return SimpleNamespace(wt=wt, plan=plan, hroot=hroot, rroot=rroot, fp=fp)


def test_fingerprint_changes_on_each_input(fp_env):
    e = fp_env
    base = e.fp()
    assert base and len(base) == 64
    assert e.fp() == base  # deterministic

    seen = {base}

    def changed():
        v = e.fp()
        assert v and v not in seen
        seen.add(v)

    e.plan.write_text("plan v2\n")
    changed()

    (e.wt / "c.txt").write_text("committed\n")  # committed diff
    _git(e.wt, "add", "-A")
    _git(e.wt, "commit", "-q", "-m", "feature")
    changed()

    (e.wt / "a.txt").write_text("two\n")  # uncommitted edit
    changed()

    (e.wt / "b.txt").write_text("new\n")  # new untracked file
    changed()

    (e.hroot / "harness" / "gate.py").write_text("x = 2\n")  # harness file edit
    changed()

    # a new commit on origin/master alone does not change it: master advances to a
    # sibling commit (empty tree) parented on the old base, so the merge-base stays put
    before = e.fp()
    env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}
    old_base = subprocess.run(["git", "-C", str(e.wt), "rev-parse", "origin/master"],
                              check=True, capture_output=True, text=True).stdout.strip()
    empty_tree = subprocess.run(["git", "-C", str(e.wt), "mktree"], input="", check=True,
                                capture_output=True, text=True).stdout.strip()
    moved = subprocess.run(
        ["git", "-C", str(e.wt), "commit-tree", empty_tree, "-p", old_base, "-m", "master move"],
        check=True, capture_output=True, text=True, env=env).stdout.strip()
    _git(e.wt, "update-ref", "refs/remotes/origin/master", moved)
    assert e.fp() == before


def test_fingerprint_git_failure_returns_empty(tmp_path):
    plan = tmp_path / "plan.md"
    plan.write_text("p")
    nonrepo = tmp_path / "nonrepo"
    nonrepo.mkdir()
    assert hr.fingerprint(str(plan), str(nonrepo),
                          harness_root=str(tmp_path), repo_root=str(tmp_path)) == ""
    assert hr.fingerprint(str(plan), str(tmp_path / "missing"),
                          harness_root=str(tmp_path), repo_root=str(tmp_path)) == ""


# --------------------------------------------------------------------------- should_decline


def _seed(tmp_path, *, dm_sent_key, fp, key="3:m"):
    path = hr.record_path(str(tmp_path), "slug")
    open(path, "w").write(json.dumps({
        "schema_version": 1, "slug": "slug", "structural_key": key, "hold_set": [],
        "structural": [], "repeat_count": 2, "dm_sent_key": dm_sent_key,
        "fingerprint": fp, "pr": 1, "updated_at": "t",
    }))


def test_should_decline_requires_dm_and_matching_fingerprint(tmp_path):
    sd = str(tmp_path)
    assert hr.should_decline(sd, "slug", "fp") == (False, "")  # no record
    _seed(tmp_path, dm_sent_key=None, fp="fp")
    assert hr.should_decline(sd, "slug", "fp") == (False, "")  # dm not sent
    _seed(tmp_path, dm_sent_key="3:m", fp="fp")
    assert hr.should_decline(sd, "slug", "other") == (False, "")  # fingerprint mismatch
    assert hr.should_decline(sd, "slug", "") == (False, "")  # empty fingerprint
    _seed(tmp_path, dm_sent_key="3:old", fp="fp")
    assert hr.should_decline(sd, "slug", "fp") == (False, "")  # dm key != structural key
    _seed(tmp_path, dm_sent_key="3:m", fp="")
    assert hr.should_decline(sd, "slug", "") == (False, "")  # empty never matches empty
    _seed(tmp_path, dm_sent_key="3:m", fp="fp")
    ok, reason = hr.should_decline(sd, "slug", "fp")
    assert ok is True
    assert reason == "unchanged plan+diff+harness; held again on 3:m (DM already sent)"


# --------------------------------------------------------------------------- CLI


def test_cli_check_exit_codes(tmp_path, capsys, monkeypatch):
    sd = str(tmp_path / "state")
    os.makedirs(sd)
    argv = ["check", "--state-dir", sd, "--slug", "slug", "--plan", "p.md", "--worktree", "wt"]

    monkeypatch.setattr(hr, "fingerprint", lambda *a, **k: "fp")

    # no record -> 0, silent
    assert hr.main(argv) == 0
    assert capsys.readouterr().out == ""

    # decline -> 10 plus the DECLINE line
    _seed_path = hr.record_path(sd, "slug")
    open(_seed_path, "w").write(json.dumps({
        "schema_version": 1, "slug": "slug", "structural_key": "3:m", "hold_set": [],
        "structural": [], "repeat_count": 2, "dm_sent_key": "3:m", "fingerprint": "fp",
        "pr": 1, "updated_at": "t"}))
    assert hr.main(argv) == hr.DECLINE_EXIT == 10
    out = capsys.readouterr().out
    assert out.startswith("holdrepeat: DECLINE ") and "3:m" in out

    # corrupt record -> 0
    open(_seed_path, "w").write("{corrupt")
    assert hr.main(argv) == 0
    assert capsys.readouterr().out == ""

    # unexpected exception -> 0 with a stderr note
    def boom(*a, **k):
        raise RuntimeError("kaput")

    monkeypatch.setattr(hr, "fingerprint", boom)
    assert hr.main(argv) == 0
    assert "holdrepeat: check failed (kaput); proceeding" in capsys.readouterr().err

    # unknown flag -> argparse usage error, exit 2
    with pytest.raises(SystemExit) as exc:
        hr.main(argv + ["--bogus"])
    assert exc.value.code == 2
    assert "usage" in capsys.readouterr().err.lower()
