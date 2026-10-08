import os
import subprocess
import sys
from datetime import datetime, timedelta, timezone

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.gate_backtest import GateChange, ReplaySpec, classify_exit
from harness.gate_backtest_replay import (ScratchTree, fetch_history, make_git, resolve_plan,
                                          run_backtest)

T0 = datetime(2026, 9, 1, 12, 0, tzinfo=timezone.utc)
ENV = dict(os.environ, GIT_AUTHOR_NAME="T", GIT_AUTHOR_EMAIL="t@t.com",
           GIT_COMMITTER_NAME="T", GIT_COMMITTER_EMAIL="t@t.com")

OLD_GATE = "#!/bin/sh\nexit 0\n"
# Passes only when stdin carries a tagged marker; stands in for
# `bin/check-destructive-migrations --bypass-from-stdin` without a migration tree.
STDIN_GATE = """#!/bin/sh
body=$(cat)
case "$body" in *"destructive-migration["*) exit 0 ;; esac
exit 1
"""
STDIN_SPEC = ReplaySpec(argv=(), stdin="{body_file}")
TAGGED = "## Summary\n<!-- destructive-migration[drop-column]: column superseded by players.pos_v2 -->\n"
NEW_GATE = ("#!/bin/sh\n"
            "if git diff-tree --no-commit-id --name-only -r HEAD | grep -q '^src/noisy.txt$'; "
            "then echo flagged; exit 1; fi\nexit 0\n")


def sh(repo, *argv):
    return subprocess.run(["git", "-C", str(repo), *argv], capture_output=True, text=True,
                          check=True, env=ENV).stdout.strip()


def commit_file(repo, rel, content, msg, mode=None):
    path = repo / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content)
    if mode:
        path.chmod(mode)
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", msg)
    return sh(repo, "rev-parse", "HEAD")


def build_repo(tmp_path, with_merge=False):
    """Six squash-style commits; commits 3 and 5 touch src/noisy.txt."""
    repo = tmp_path / "repo"
    repo.mkdir()
    sh(repo, "init", "-q", "-b", "master")
    commit_file(repo, "bin/check-fake", OLD_GATE, "root", mode=0o755)
    shas = []
    for i in range(1, 7):
        rel = "src/noisy.txt" if i in (3, 5) else f"src/f{i}.txt"
        shas.append(commit_file(repo, rel, f"v{i}\n", f"chore: change {i}"))
    if with_merge:
        sh(repo, "checkout", "-q", "-b", "side", shas[1])
        commit_file(repo, "src/side.txt", "s\n", "side work")
        sh(repo, "checkout", "-q", "master")
        sh(repo, "merge", "--no-ff", "-q", "-m", "merge side", "side")
        shas.append(sh(repo, "rev-parse", "HEAD"))
    return repo, shas


def fake_gh(shas, titles=None, head_refs=None):
    def gh_json(argv):
        items = []
        for i, sha in enumerate(shas, 1):
            items.append({"number": i, "title": (titles or {}).get(i, f"chore: change {i}"),
                          "mergeCommit": {"oid": sha},
                          "mergedAt": (T0 + timedelta(hours=i)).strftime("%Y-%m-%dT%H:%M:%SZ"),
                          "headRefName": (head_refs or {}).get(i, f"branch-{i}"),
                          "body": f"body {i}"})
        return items
    return gh_json


def gate(spec=None, path="bin/check-fake"):
    return GateChange(path, "check-script", "replayable", spec or ReplaySpec(argv=()), "header")


def overlay(script=NEW_GATE):
    return {"bin/check-fake": (script.encode(), 0o100755)}


def run(repo, shas, tmp_path, gates=None, script=NEW_GATE, **kw):
    git = make_git(str(repo))
    history = fetch_history(fake_gh(shas), git)
    return run_backtest(str(repo), "HEAD", gates or [gate()], history, overlay(script),
                        str(tmp_path / "plans"), **kw)


def test_stdin_spec_feeds_pr_body(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas[:2], tmp_path, gates=[gate(STDIN_SPEC)], script=STDIN_GATE,
                  bodies={1: TAGGED, 2: "no marker here"})
    assert {r.pr: r.outcome for r in results} == {1: "pass", 2: "flag"}


def test_stdin_spec_empty_body_still_flags(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas[:2], tmp_path, gates=[gate(STDIN_SPEC)], script=STDIN_GATE)
    assert {r.pr: r.outcome for r in results} == {1: "flag", 2: "flag"}


def test_missing_stdin_file_is_error_not_crash(tmp_path):
    repo, shas = build_repo(tmp_path)
    spec = ReplaySpec(argv=(), stdin="{tree}/no-such-file.body")
    results = run(repo, shas[:2], tmp_path, gates=[gate(spec)], script=STDIN_GATE)
    assert len(results) == 2
    assert all(r.outcome == "error" and "stdin:" in r.detail for r in results)


def test_default_spec_keeps_devnull_even_with_body(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas[:2], tmp_path, gates=[gate(ReplaySpec(argv=()))], script=STDIN_GATE,
                  bodies={1: TAGGED, 2: TAGGED})
    assert {r.pr: r.outcome for r in results} == {1: "flag", 2: "flag"}


def test_classify_exit_table():
    spec = ReplaySpec(argv=())
    assert classify_exit(spec, 1, "x\n")[0] == "flag"
    assert classify_exit(spec, 0, "")[0] == "pass"
    assert classify_exit(spec, 2, "usage")[0] == "error"
    assert classify_exit(spec, 127, "")[0] == "error"
    assert classify_exit(spec, -9, "")[0] == "error"
    rx = ReplaySpec(argv=(), flag_exits=frozenset(), flag_stdout_re=r"^UNREALISED-ASSERTION:")
    assert classify_exit(rx, 0, "ok\nUNREALISED-ASSERTION: row 1\n")[0] == "flag"
    assert classify_exit(rx, 0, "ok\n")[0] == "pass"
    assert classify_exit(rx, 2, "UNREALISED-ASSERTION: row 1\n")[0] == "error"


def test_replay_flags_and_passes_on_synthetic_history(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas, tmp_path)
    assert len(results) == 6
    outcomes = {r.pr: r.outcome for r in results}
    assert outcomes == {1: "pass", 2: "pass", 3: "flag", 4: "pass", 5: "flag", 6: "pass"}


def test_overlay_uses_candidate_version(tmp_path):
    repo, shas = build_repo(tmp_path)
    flagged = [r for r in run(repo, shas, tmp_path, script=NEW_GATE) if r.outcome == "flag"]
    assert len(flagged) == 2
    quiet = [r for r in run(repo, shas, tmp_path, script=OLD_GATE) if r.outcome == "flag"]
    assert quiet == []


def test_replay_timeout_is_timeout(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas[:2], tmp_path, script="#!/bin/sh\nsleep 5\n", per_replay_timeout=1)
    assert {r.outcome for r in results} == {"timeout"}


def test_total_cap_marks_remaining(tmp_path):
    repo, shas = build_repo(tmp_path)
    ticks = iter([0, 0, 5000])

    def clock():
        return next(ticks, 5000)

    results = run(repo, shas, tmp_path, total_cap=900, clock=clock)
    assert results[0].outcome in ("pass", "flag")
    assert all(r.outcome == "timeout" and r.detail == "total-cap" for r in results[1:])
    assert len(results) == 6


def test_non_squash_skipped(tmp_path):
    repo, shas = build_repo(tmp_path, with_merge=True)
    results = run(repo, shas, tmp_path)
    merged = next(r for r in results if r.pr == 7)
    assert merged.outcome == "skipped" and merged.detail == "non-squash"
    assert len(results) == 7


def test_missing_plan_skips(tmp_path):
    repo, shas = build_repo(tmp_path)
    spec = ReplaySpec(argv=("{plan_file}",), needs_plan=True)
    (tmp_path / "plans").mkdir()
    results = run(repo, shas[:2], tmp_path, gates=[gate(spec)])
    assert {(r.outcome, r.detail) for r in results} == {("skipped", "no-plan")}


def test_head_ref_traversal_rejected(tmp_path):
    plans = tmp_path / "plans"
    plans.mkdir()
    assert resolve_plan(str(plans), "../../etc/passwd") == (None, "bad-head-ref")
    assert resolve_plan(str(plans), "/etc/passwd") == (None, "bad-head-ref")
    repo, shas = build_repo(tmp_path)
    spec = ReplaySpec(argv=("{plan_file}",), needs_plan=True)
    git = make_git(str(repo))
    history = fetch_history(fake_gh(shas[:1], head_refs={1: "../../etc/passwd"}), git)
    results = run_backtest(str(repo), "HEAD", [gate(spec)], history, overlay(), str(plans))
    assert [(r.outcome, r.detail) for r in results] == [("skipped", "bad-head-ref")]


def test_plan_resolves_from_archive(tmp_path):
    plans = tmp_path / "plans"
    (plans / "_archive").mkdir(parents=True)
    (plans / "_archive" / "old.md").write_text("plan")
    found, _ = resolve_plan(str(plans), "old")
    assert found and found.endswith("_archive/old.md")


def test_scratch_worktree_removed_on_exception(tmp_path):
    repo, shas = build_repo(tmp_path)
    seen = {}
    with pytest.raises(RuntimeError):
        with ScratchTree(str(repo), shas[0]) as scratch:
            seen["dir"] = scratch.dir
            assert os.path.isdir(scratch.dir)
            raise RuntimeError("boom")
    listing = sh(repo, "worktree", "list", "--porcelain")
    assert listing.count("worktree ") == 1
    assert not os.path.exists(seen["dir"])


def test_no_shell_with_hostile_title(tmp_path):
    repo, shas = build_repo(tmp_path)
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / "x;touch pwned2.md").write_text("plan")
    spec = ReplaySpec(argv=("{plan_file}",), needs_plan=True)
    git = make_git(str(repo))
    gh = fake_gh(shas[:1], titles={1: "$(touch pwned)"}, head_refs={1: "x;touch pwned2"})
    history = fetch_history(gh, git)
    cwd_before = os.getcwd()
    results = run_backtest(str(repo), "HEAD", [gate(spec)], history, overlay(), str(plans))
    assert [r.outcome for r in results] == ["pass"]
    for root in (str(repo), str(tmp_path), cwd_before):
        for name in os.listdir(root):
            assert not name.startswith("pwned"), (root, name)


def test_fetch_history_excludes_candidate_and_orders_newest_first(tmp_path):
    repo, shas = build_repo(tmp_path)
    bodies = {}
    history = fetch_history(fake_gh(shas), make_git(str(repo)), exclude=3, bodies_out=bodies)
    assert [p.number for p in history] == [6, 5, 4, 2, 1]
    assert history[1].files == ("src/noisy.txt",)
    assert bodies[6] == "body 6"


def test_fetch_history_missing_sha_has_zero_parents(tmp_path):
    repo, shas = build_repo(tmp_path)
    history = fetch_history(fake_gh(["f" * 40]), make_git(str(repo)))
    assert history[0].parent_count == 0
    results = run_backtest(str(repo), "HEAD", [gate()], history, overlay(), str(tmp_path))
    assert [(r.outcome, r.detail) for r in results] == [("skipped", "sha-missing")]


def test_backtest_changes_replays_settled_prs_past_the_young_ones(monkeypatch, tmp_path):
    from harness import gate_backtest_replay as gbr
    from harness.gate_backtest import HistoricalPR
    now = T0 + timedelta(days=10)
    young = [HistoricalPR(100 + i, "feat: new", "a" * 40, 1, now - timedelta(hours=i + 1), "b", ())
             for i in range(5)]
    old = [HistoricalPR(i, "feat: old", "c" * 40, 1, now - timedelta(days=4, hours=i), "b", ())
           for i in range(1, 4)]
    seen = {}
    monkeypatch.setattr(gbr, "_detect", lambda *a, **k: [gate()])
    monkeypatch.setattr(gbr, "fetch_history", lambda *a, **k: young + old)

    def fake_run(repo, head, gates, history, *a, **k):
        seen["numbers"] = [p.number for p in history]
        return []

    monkeypatch.setattr(gbr, "run_backtest", fake_run)
    out = gbr.backtest_changes(str(tmp_path), "HEAD", [("D", "bin/check-x")], fetch=False,
                               limit=2, now=now)
    assert seen["numbers"] == [1, 2]
    assert out.window == 2
