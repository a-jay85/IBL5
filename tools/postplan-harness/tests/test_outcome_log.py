import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import outcome_log as ol


def _ev(**kw):
    base = dict(pr=1, finding_id=2, agent="B", score=80, outcome="fixed",
                source="phase4.5", basis="fix-verdict", pipeline="harness")
    base.update(kw)
    return ol.build_event(**base)


def test_map_outcome_fix_is_fixed():
    for cat in (None, "wrong", "bogus"):
        assert ol.map_outcome("FIX", cat) == "fixed"


@pytest.mark.parametrize("cat,expected", sorted(ol.DECLINE_CATEGORIES.items()))
def test_map_outcome_decline_categories(cat, expected):
    assert ol.map_outcome("DECLINE", cat) == expected
    assert expected == {
        "wrong": "declined-wrong", "intentional": "intentional",
        "already-fixed": "fixed", "not-worth-it": "didnt-matter",
    }[cat]


@pytest.mark.parametrize("cat", [None, "", "WRONG", "rm -rf"])
def test_map_outcome_decline_unknown_category_is_didnt_matter(cat):
    assert ol.map_outcome("DECLINE", cat) == "didnt-matter"


@pytest.mark.parametrize("verdict", [None, "SKIP", "fix"])
def test_map_outcome_invalid_verdict_returns_none(verdict):
    assert ol.map_outcome(verdict, "wrong") is None


def test_build_event_caps_and_flattens_reason():
    reason = ("line\n\"q\" `tick` $(touch /tmp/x)\t" * 400)[:5000]
    ev = _ev(reason=reason)
    assert len(ev["reason"]) == 300
    assert "\n" not in ev["reason"]
    line = json.dumps(ev, ensure_ascii=True, sort_keys=True)
    assert "\n" not in line
    assert json.loads(line) == ev


def test_build_event_unknown_agent_and_null_score():
    ev = _ev(agent=None, score=None)
    assert ev["agent"] == "unknown"
    assert ev["score"] is None


def test_append_events_appends_not_truncates(tmp_path):
    path = str(tmp_path / "state" / "log.jsonl")
    assert ol.append_events(path, [_ev()], lambda m: None) == 1
    assert ol.append_events(path, [_ev(finding_id=3)], lambda m: None) == 1
    with open(path) as fh:
        assert len(fh.read().splitlines()) == 2


def test_append_events_unwritable_path_never_raises(tmp_path):
    blocker = tmp_path / "blocker"
    blocker.write_text("x")
    logs = []
    assert ol.append_events(str(blocker / "sub" / "log.jsonl"), [_ev()], logs.append) == 0
    assert len(logs) == 1 and logs[0].startswith("outcome-log: append failed")


def test_resolve_log_path_env_override_nonempty_wins(tmp_path):
    assert ol.resolve_log_path(str(tmp_path), env={ol.ENV_OVERRIDE: "/x/y.jsonl"}) == "/x/y.jsonl"


def _git(cwd, *args):
    subprocess.run(["git", "-C", str(cwd), *args], check=True, capture_output=True,
                   env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})


def _expected(main):
    return os.path.join(os.path.realpath(main), "tools", "postplan-harness", "out", "state",
                        ol.LOG_BASENAME)


def test_resolve_log_path_empty_env_falls_back_to_git(tmp_path):
    main = tmp_path / "main"
    main.mkdir()
    _git(main, "init", "-q")
    got = ol.resolve_log_path(str(main), env={ol.ENV_OVERRIDE: ""})
    assert os.path.realpath(got) == _expected(main)


def test_resolve_log_path_from_linked_worktree_is_main_checkout(tmp_path):
    main = tmp_path / "main"
    main.mkdir()
    _git(main, "init", "-q")
    _git(main, "commit", "-q", "--allow-empty", "-m", "c")
    wt = tmp_path / "wt"
    _git(main, "worktree", "add", "-q", str(wt), "-b", "side")
    got = ol.resolve_log_path(str(wt), env={})
    assert os.path.realpath(got) == _expected(main)


def test_resolve_log_path_non_repo_raises(tmp_path):
    env = {}
    with pytest.raises(ol.OutcomeLogError):
        ol.resolve_log_path(str(tmp_path), env=env)


def test_read_events_skips_corrupt_and_foreign_version(tmp_path):
    path = tmp_path / "log.jsonl"
    good = _ev()
    path.write_text(json.dumps(good) + "\n" + '{"v": 1, "pr"' + "\n" + '{"v": 2}\n\n')
    assert ol.read_events(str(path)) == [good]


# ---------------------------------------------------------------------------
# Sweep (resolved / outdated findings that Phase 4.5 never answered)
# ---------------------------------------------------------------------------

def _th(cid, **kw):
    base = {"commentId": cid, "score": 82, "agent": "B", "authorLogin": "a-jay85",
            "authorType": "User", "isResolved": False, "isOutdated": False}
    base.update(kw)
    return base


def test_sweep_outdated_is_fixed():
    events = ol.sweep_events([_th(1, isOutdated=True, isResolved=True)], 9, set(), pipeline="harness")
    assert [(e["outcome"], e["basis"], e["source"]) for e in events] == [("fixed", "outdated", "sweep")]
    assert events[0]["score"] == 82 and events[0]["agent"] == "B"


def test_sweep_resolved_not_outdated_is_didnt_matter():
    events = ol.sweep_events([_th(1, isResolved=True)], 9, set(), pipeline="harness")
    assert [(e["outcome"], e["basis"]) for e in events] == [("didnt-matter", "resolved-no-reply")]


def test_sweep_open_thread_logs_nothing():
    assert ol.sweep_events([_th(1)], 9, set(), pipeline="harness") == []


def test_sweep_skips_unscored_and_untrusted():
    threads = [_th(1, score=None, isOutdated=True),
               _th(2, isOutdated=True, authorLogin="mallory", authorType="User")]
    assert ol.sweep_events(threads, 9, set(), pipeline="harness") == []


def test_sweep_skips_seen_ids():
    threads = [_th(101, isOutdated=True), _th(102, isOutdated=True)]
    events = ol.sweep_events(threads, 9, {101}, pipeline="harness")
    assert [e["findingId"] for e in events] == [102]


def test_run_sweep_twice_is_idempotent(tmp_path):
    path = str(tmp_path / "o.jsonl")
    rows = [_th(1, isOutdated=True), _th(2, isResolved=True), _th(3)]
    assert ol.run_sweep(rows, 9, path, lambda m: None, pipeline="harness") == 2
    assert ol.run_sweep(rows, 9, path, lambda m: None, pipeline="harness") == 0
    assert sorted(e["findingId"] for e in ol.read_events(path)) == [1, 2]


def test_run_sweep_unreadable_log_never_raises(tmp_path):
    seen = []
    assert ol.run_sweep([_th(1, isOutdated=True)], 9, str(tmp_path), seen.append, pipeline="harness") == 0
    assert any("outcome-log: sweep failed" in m for m in seen)
