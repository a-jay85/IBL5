import os
import subprocess
import sys
import time
from datetime import datetime, timedelta, timezone

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import gate_backtest_replay as gbr
from harness.gate_backtest import (GateChange, HistoricalPR, ReplaySpec, Truth, classify_exit,
                                   compute_verdict)
from harness.gate_backtest_replay import (ScratchTree, _fix_patch, ensure_pr_heads, fetch_history,
                                          make_git, resolve_plan, run_backtest)

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
    results = run(repo, shas[:2], tmp_path, script="#!/bin/sh\nsleep 5\n", per_replay_timeout=1,
                  retry_timeouts=False)
    assert {r.outcome for r in results} == {"timeout"}


def test_sleeping_gate_verdict_is_unknown(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas[:1], tmp_path, script="#!/bin/sh\nsleep 5\n", per_replay_timeout=1)
    truth = {r.pr: Truth("clean") for r in results}
    v = compute_verdict([gate()], results, truth)
    assert v.state == "UNKNOWN"
    assert "replay failed (timeout: 1s)" in v.reason


def test_total_cap_marks_remaining(tmp_path):
    repo, shas = build_repo(tmp_path)
    ticks = iter([0, 0, 5000])

    def clock():
        return next(ticks, 5000)

    results = run(repo, shas, tmp_path, total_cap=900, clock=clock)
    assert results[0].outcome in ("pass", "flag")
    assert all(r.outcome == "timeout" and r.detail == "total-cap" for r in results[1:])
    assert len(results) == 6


BASE_FLAG_GATE = "#!/bin/sh\n# gate-backtest-argv:\n" + NEW_GATE.split("\n", 1)[1]
CLEAN_BASE_GATE = "#!/bin/sh\n# gate-backtest-argv:\nexit 0\n"


def base(script, mode=0o100755):
    return {"bin/check-fake": (script.encode(), mode)}


def test_scratch_remove_then_overlay_restores_file(tmp_path):
    """ScratchTree.remove drops a path, tolerates an absent one, and overlay restores it."""
    repo, shas = build_repo(tmp_path)
    with ScratchTree(str(repo), shas[0]) as scratch:
        scratch.overlay(overlay())
        target = os.path.join(scratch.dir, "bin/check-fake")
        scratch.remove(["bin/check-fake", "bin/never-there"])
        assert not os.path.exists(target)
        scratch.overlay(overlay())
        with open(target, "rb") as fh:
            assert fh.read() == NEW_GATE.encode()
        assert os.access(target, os.X_OK)


def test_base_replay_pre_existing_on_same_checkout(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas, tmp_path, base_overlay=base(BASE_FLAG_GATE))
    by_pr = {r.pr: r for r in results}
    for n in (3, 5):
        assert by_pr[n].outcome == "flag" and by_pr[n].base_outcome == "flag"
    for n in (1, 2, 4, 6):
        assert by_pr[n].outcome == "pass" and by_pr[n].base_outcome == ""


def test_base_replay_clean_base_keeps_flag_and_restores_head(tmp_path):
    repo, shas = build_repo(tmp_path)
    twin = "bin/check-twin"
    head = {"bin/check-fake": (NEW_GATE.encode(), 0o100755), twin: (NEW_GATE.encode(), 0o100755)}
    clean = (CLEAN_BASE_GATE.encode(), 0o100755)
    history = fetch_history(fake_gh(shas), make_git(str(repo)))
    results = run_backtest(str(repo), "HEAD", [gate(), gate(path=twin)], history, head,
                           str(tmp_path / "plans"),
                           base_overlay={"bin/check-fake": clean, twin: clean})
    rows = [r for r in results if r.pr in (3, 5)]
    assert len(rows) == 4
    for r in rows:
        assert r.outcome == "flag" and r.base_outcome == "pass", (r.pr, r.gate)


def test_base_replay_absent_gate_is_absent(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas, tmp_path, base_overlay={"bin/check-fake": None})
    for r in (r for r in results if r.pr in (3, 5)):
        assert r.outcome == "flag" and r.base_outcome == "absent"


def test_base_replay_timeout_fails_closed(tmp_path):
    repo, shas = build_repo(tmp_path)
    # 10 s, because on a loaded machine the head replay can take over 2 s and time out too.
    results = run(repo, shas, tmp_path, per_replay_timeout=10, retry_timeouts=False,
                  base_overlay=base("#!/bin/sh\n# gate-backtest-argv:\nsleep 30\n"))
    for r in (r for r in results if r.pr in (3, 5)):
        assert r.outcome == "flag"
        assert r.base_outcome == "timeout" and r.base_detail == "10s"


EXPECTED = {1: "pass", 2: "pass", 3: "flag", 4: "pass", 5: "flag", 6: "pass"}


def flaky_runner(calls, fail_calls=(), drain_stdin=False):
    """Raise TimeoutExpired on the listed 1-based call numbers; run the real replay otherwise."""
    def runner(argv, *, cwd, env, stdin, timeout):
        calls.append(argv)
        if len(calls) in fail_calls:
            if drain_stdin and hasattr(stdin, "read"):
                stdin.read()
            raise subprocess.TimeoutExpired(argv, timeout)
        return gbr._run_in_session(argv, cwd=cwd, env=env, stdin=stdin, timeout=timeout)
    return runner


def test_timeout_then_pass_gives_real_verdict(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []
    results = run(repo, shas, tmp_path, runner=flaky_runner(calls, {3}), per_replay_timeout=60)
    assert {r.pr: r.outcome for r in results} == EXPECTED
    assert len(calls) == 7
    assert sum(1 for r in results if len(r.attempt_secs) == 2) == 1
    assert all(r.attempt_secs == () for r in results if len(r.attempt_secs) != 2)


def test_timeout_twice_stays_timeout(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []
    results = run(repo, shas[:1], tmp_path, runner=flaky_runner(calls, range(1, 10)),
                  per_replay_timeout=60)
    assert len(results) == 1
    assert results[0].outcome == "timeout" and results[0].detail == "60s"
    assert len(results[0].attempt_secs) == 2
    assert len(calls) == 2


def test_first_try_success_runs_once(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []
    results = run(repo, shas, tmp_path, runner=flaky_runner(calls))
    assert len(calls) == 6
    assert {r.pr: r.outcome for r in results} == EXPECTED
    for r in results:
        assert r.attempt_secs == () and r.base_attempt_secs == () and r.fix_attempt_secs == ()


def test_oserror_is_not_retried(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []

    def runner(argv, *, cwd, env, stdin, timeout):
        calls.append(argv)
        raise OSError("boom")

    results = run(repo, shas[:1], tmp_path, runner=runner)
    assert results[0].outcome == "error" and results[0].detail == "boom"
    assert len(calls) == 1


def test_total_cap_never_invokes_runner(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []
    results = run(repo, shas, tmp_path, total_cap=0, runner=flaky_runner(calls))
    assert calls == []
    assert results and all((r.outcome, r.detail) == ("timeout", "total-cap") for r in results)


def test_retry_off_runs_once(tmp_path):
    repo, shas = build_repo(tmp_path)
    calls = []
    results = run(repo, shas[:1], tmp_path, runner=flaky_runner(calls, range(1, 10)),
                  retry_timeouts=False, per_replay_timeout=60)
    assert len(calls) == 1
    assert results[0].outcome == "timeout" and results[0].attempt_secs == ()


def test_retry_reopens_stdin(tmp_path):
    repo, shas = build_repo(tmp_path)
    kw = dict(gates=[gate(STDIN_SPEC)], script=STDIN_GATE, bodies={1: TAGGED, 2: "no marker here"})
    base_pairs = [(r.pr, r.outcome) for r in run(repo, shas[:2], tmp_path, **kw)]
    calls = []
    results = run(repo, shas[:2], tmp_path, runner=flaky_runner(calls, {1}, drain_stdin=True), **kw)
    assert [(r.pr, r.outcome) for r in results] == base_pairs
    assert len(calls) == 3


def test_base_retry_records_base_durations(tmp_path):
    repo, shas = build_repo(tmp_path)
    state = {"armed": False, "fired": False}
    calls = []

    def runner(argv, *, cwd, env, stdin, timeout):
        calls.append(argv)
        if state["armed"] and not state["fired"]:
            state["fired"] = True
            raise subprocess.TimeoutExpired(argv, timeout)
        rc, out = gbr._run_in_session(argv, cwd=cwd, env=env, stdin=stdin, timeout=timeout)
        if rc == 1:
            state["armed"] = True
        return rc, out

    results = run(repo, shas[:3], tmp_path, runner=runner, per_replay_timeout=60,
                  base_overlay=base("#!/bin/sh\n# gate-backtest-argv:\nexit 0\n"))
    r3 = next(r for r in results if r.pr == 3)
    assert r3.outcome == "flag" and r3.base_outcome == "pass"
    assert len(r3.base_attempt_secs) == 2 and r3.attempt_secs == ()


def test_timeout_kills_process_group(tmp_path):
    script = tmp_path / "gate.sh"
    script.write_text('#!/bin/sh\nsleep 30 &\necho $! > "$PIDFILE"\nwait\n')
    script.chmod(0o755)
    pidfile = tmp_path / "pid"
    with pytest.raises(subprocess.TimeoutExpired):
        gbr._run_in_session([str(script)], cwd=str(tmp_path),
                            env=dict(os.environ, PIDFILE=str(pidfile)),
                            stdin=subprocess.DEVNULL, timeout=1)
    pid = int(pidfile.read_text().strip())
    deadline = time.monotonic() + 3
    alive = True
    while time.monotonic() < deadline:
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            alive = False
            break
        stat = subprocess.run(["ps", "-o", "stat=", "-p", str(pid)], capture_output=True,
                              text=True).stdout.strip()
        if stat.startswith("Z"):
            alive = False
            break
        time.sleep(0.1)
    assert not alive, "the gate's sleep child survived the group kill"


def test_base_replay_no_spec_fails_closed(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas, tmp_path, base_overlay=base("#!/bin/sh\nexit 1\n"))
    for r in (r for r in results if r.pr in (3, 5)):
        assert r.base_outcome == "error" and r.base_detail == "base-no-spec"


def test_base_replay_total_cap_fails_closed(tmp_path):
    repo, shas = build_repo(tmp_path)
    ticks = iter([0, 0, 5000])

    def clock():
        return next(ticks, 5000)

    results = run(repo, [shas[2]], tmp_path, base_overlay=base(BASE_FLAG_GATE), total_cap=900,
                  clock=clock)
    assert len(results) == 1
    assert results[0].outcome == "flag"
    assert results[0].base_outcome == "timeout" and results[0].base_detail == "total-cap"


# Flags every tree without config/required.txt; build_repo's history never adds it.
NEEDS_FILE_GATE = ("#!/bin/sh\n# gate-backtest-argv:\n"
                   "if [ ! -f config/required.txt ]; then\n"
                   "    echo 'missing config/required.txt'\n    exit 1\nfi\nexit 0\n")


def _patch_from_master(repo, rel, content):
    """The binary diff of one commit on a side branch off master; the repo is left on master."""
    sh(repo, "checkout", "-q", "-b", "patch-src", "master")
    commit_file(repo, rel, content, "candidate fix")
    data = subprocess.run(["git", "-C", str(repo), "diff", "--binary", "master", "patch-src"],
                          capture_output=True, check=True).stdout
    sh(repo, "checkout", "-q", "master")
    return data


def test_fix_overlay_rescues_flag_when_patch_fixes(tmp_path):
    repo, shas = build_repo(tmp_path)
    patch = _patch_from_master(repo, "config/required.txt", "required\n")
    results = run(repo, [shas[0]], tmp_path, script=NEEDS_FILE_GATE, fix_patch=patch)
    assert len(results) == 1
    assert results[0].outcome == "flag"
    assert results[0].fix_outcome == "pass"


def test_fix_overlay_patch_conflict_is_no_apply(tmp_path):
    repo, shas = build_repo(tmp_path)
    patch = _patch_from_master(repo, "src/f6.txt", "v6\nconfig/required.txt\n")
    results = run(repo, [shas[0]], tmp_path, script=NEEDS_FILE_GATE, fix_patch=patch)
    assert len(results) == 1
    assert results[0].outcome == "flag"
    assert results[0].fix_outcome == "no-apply"


def test_fix_overlay_skipped_when_base_flags(tmp_path):
    repo, shas = build_repo(tmp_path)
    patch = _patch_from_master(repo, "config/required.txt", "required\n")
    results = run(repo, [shas[0]], tmp_path, script=NEEDS_FILE_GATE, fix_patch=patch,
                  base_overlay=base(NEEDS_FILE_GATE))
    assert results[0].outcome == "flag"
    assert results[0].base_outcome == "flag"
    assert results[0].fix_outcome == ""


def test_fix_overlay_skipped_without_patch(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, [shas[0]], tmp_path, script=NEEDS_FILE_GATE)
    assert results[0].outcome == "flag"
    assert results[0].fix_outcome == ""


def test_fix_overlay_reset_before_next_gate(tmp_path):
    repo, shas = build_repo(tmp_path)
    patch = _patch_from_master(repo, "config/required.txt", "required\n")
    twin = "bin/check-twin"
    head = {"bin/check-fake": (NEEDS_FILE_GATE.encode(), 0o100755),
            twin: (NEEDS_FILE_GATE.encode(), 0o100755)}
    history = fetch_history(fake_gh([shas[0]]), make_git(str(repo)))
    results = run_backtest(str(repo), "HEAD", [gate(), gate(path=twin)], history, head,
                           str(tmp_path / "plans"), fix_patch=patch)
    assert [r.gate for r in results] == ["bin/check-fake", twin]
    for r in results:
        assert r.outcome == "flag" and r.fix_outcome == "pass", r.gate


def test_fix_overlay_total_cap_fails_closed(tmp_path):
    repo, shas = build_repo(tmp_path)
    patch = _patch_from_master(repo, "config/required.txt", "required\n")
    ticks = iter([0, 0, 5000])

    def clock():
        return next(ticks, 5000)

    results = run(repo, [shas[0]], tmp_path, script=NEEDS_FILE_GATE, fix_patch=patch,
                  total_cap=900, clock=clock)
    assert len(results) == 1
    assert results[0].outcome == "flag"
    assert results[0].fix_outcome == "timeout" and results[0].fix_detail == "total-cap"


def _fix_repo(tmp_path):
    """build_repo, then `feature` changes the gate, a bin/test-* sibling, and a config file."""
    repo, shas = build_repo(tmp_path)
    sh(repo, "checkout", "-q", "-b", "feature")
    commit_file(repo, "bin/check-fake", HEADER_FAIL_GATE, "feature gate", mode=0o755)
    commit_file(repo, "bin/test-fake", "#!/bin/sh\nexit 0\n", "feature test", mode=0o755)
    commit_file(repo, "config/required.txt", "required\n", "feature config")
    sh(repo, "checkout", "-q", "master")
    return repo, shas


def test_fix_patch_excludes_gate_paths_and_test_siblings(tmp_path):
    repo, _ = _fix_repo(tmp_path)
    data = _fix_patch(str(repo), "master", "feature",
                      {"bin/check-fake": (HEADER_FAIL_GATE.encode(), 0o100755)})
    assert data is not None
    assert b"config/required.txt" in data
    assert b"bin/check-fake" not in data
    assert b"bin/test-fake" not in data


def test_fix_patch_empty_or_unresolvable_is_none(tmp_path):
    repo, _ = _feature_repo(tmp_path)
    gate_only = {"bin/check-fake": (HEADER_FAIL_GATE.encode(), 0o100755)}
    assert _fix_patch(str(repo), "master", "feature", gate_only) is None
    assert _fix_patch(str(repo), "no-such-ref", "feature", gate_only) is None


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


HEADER_FAIL_GATE = "#!/bin/sh\n# gate-backtest-argv:\nexit 1\n"


def _feature_repo(tmp_path, rel="bin/check-fake", content=HEADER_FAIL_GATE):
    """build_repo, then `feature` commits `rel` while master moves bin/check-fake afterwards."""
    repo, shas = build_repo(tmp_path)
    sh(repo, "checkout", "-q", "-b", "feature")
    commit_file(repo, rel, content, "feature gate", mode=0o755)
    sh(repo, "checkout", "-q", "master")
    commit_file(repo, "bin/check-fake", "#!/bin/sh\nexit 3\n", "master moved", mode=0o755)
    return repo, shas


def test_base_overlay_reads_merge_base_bytes(tmp_path):
    from harness.gate_backtest_replay import _base_overlay
    repo, _ = _feature_repo(tmp_path)
    out = _base_overlay(str(repo), "master", "feature", ["bin/check-fake"])
    assert out["bin/check-fake"] == (OLD_GATE.encode(), 0o100755)


def test_base_overlay_marks_absent_path_none(tmp_path):
    from harness.gate_backtest_replay import _base_overlay
    repo, _ = _feature_repo(tmp_path)
    out = _base_overlay(str(repo), "master", "feature", ["bin/check-new"])
    assert out == {"bin/check-new": None}


def test_base_overlay_unresolvable_base_is_none(tmp_path):
    from harness.gate_backtest_replay import _base_overlay
    repo, _ = build_repo(tmp_path)
    assert _base_overlay(str(repo), "no-such-ref", "HEAD", ["bin/check-fake"]) is None


def _record_run_backtest(monkeypatch):
    from harness import gate_backtest_replay as gbr
    seen = {}

    def fake_run(*a, **k):
        seen.update(k)
        return []

    monkeypatch.setattr(gbr, "run_backtest", fake_run)
    return gbr, seen


def test_backtest_changes_passes_base_overlay_to_run_backtest(monkeypatch, tmp_path):
    repo, shas = _feature_repo(tmp_path)
    gbr, seen = _record_run_backtest(monkeypatch)
    gbr.backtest_changes(str(repo), "feature", [("M", "bin/check-fake")], gh_json=fake_gh(shas),
                         fetch=False, base="master", now=T0 + timedelta(days=30))
    assert seen["base_overlay"]["bin/check-fake"][0] == OLD_GATE.encode()


def test_forced_gate_new_at_base_is_absent(monkeypatch, tmp_path):
    repo, shas = _feature_repo(tmp_path, rel="bin/check-new")
    gbr, seen = _record_run_backtest(monkeypatch)
    gbr.backtest_changes(str(repo), "feature", [], gh_json=fake_gh(shas), fetch=False,
                         base="master", now=T0 + timedelta(days=30),
                         forced_gate=("bin/check-new", "feature"))
    assert seen["base_overlay"] == {"bin/check-new": None}


def test_backtest_changes_passes_fix_patch_to_run_backtest(monkeypatch, tmp_path):
    repo, shas = _fix_repo(tmp_path)
    gbr, seen = _record_run_backtest(monkeypatch)
    changes = [("M", "bin/check-fake"), ("A", "bin/test-fake"), ("A", "config/required.txt")]
    gbr.backtest_changes(str(repo), "feature", changes, gh_json=fake_gh(shas), fetch=False,
                         base="master", now=T0 + timedelta(days=30))
    assert isinstance(seen["fix_patch"], bytes)
    assert b"config/required.txt" in seen["fix_patch"]
    seen.clear()
    gbr.backtest_changes(str(repo), "feature", [], gh_json=fake_gh(shas), fetch=False,
                         base="master", now=T0 + timedelta(days=30),
                         forced_gate=("bin/check-fake", "feature"))
    assert "fix_patch" in seen
    assert seen["fix_patch"] is None


def test_total_cap_default_is_1200():
    import inspect
    from harness import gate_backtest_replay as gbr
    assert gbr.TOTAL_CAP == 1200
    assert inspect.signature(gbr.run_backtest).parameters["total_cap"].default == 1200


# --- partial apply of the fix patch ---------------------------------------------------------

from harness.gate_backtest_replay import (_UNKNOWN_PATH, _split_patch,  # noqa: E402
                                          partial_fix_allowed)

RENAME_BODY = "".join(f"line {i}\n" for i in range(1, 21))


def _patch_repo(tmp_path):
    repo = tmp_path / "prepo"
    repo.mkdir()
    sh(repo, "init", "-q", "-b", "master")
    (repo / "m.txt").write_text("one\n")
    (repo / "old.txt").write_text(RENAME_BODY)
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", "root")
    return repo


def test_split_patch_chunks_and_paths(tmp_path):
    repo = _patch_repo(tmp_path)
    # The modified file's added line is a near-miss chunk header; it must stay in its chunk.
    (repo / "m.txt").write_text("one\ndiff --git a/x b/x\n")
    sh(repo, "mv", "old.txt", "new.txt")
    (repo / "new.txt").write_text(RENAME_BODY + "line 21\n")
    (repo / "added.txt").write_text("fresh\n")
    sh(repo, "add", "-A")
    patch = subprocess.run(["git", "-C", str(repo), "diff", "--cached", "-M", "--binary"],
                           capture_output=True, check=True, env=ENV).stdout
    assert b"\n+diff --git a/x b/x\n" in patch
    chunks = _split_patch(patch)
    assert [paths for _, paths in chunks] == [["added.txt"], ["m.txt"], ["old.txt", "new.txt"]]
    assert b"".join(c for c, _ in chunks) == patch


def test_split_patch_quoted_path_is_unknown():
    patch = (b'diff --git "a/sp ace\\tx" "b/sp ace\\tx"\n'
             b'--- "a/sp ace\\tx"\n'
             b'+++ "b/sp ace\\tx"\n'
             b"@@ -1 +1 @@\n-a\n+b\n")
    assert [paths for _, paths in _split_patch(patch)] == [[_UNKNOWN_PATH]]


def test_partial_fix_allowed_fail_closed():
    assert partial_fix_allowed((), ["docs/x.md"]) is False
    assert partial_fix_allowed(("config/*",), ["config/a.txt"]) is False
    assert partial_fix_allowed(("config/*",), ["?"]) is False
    assert partial_fix_allowed(("config/*",), ["docs/x.md"]) is True


def _two_file_tree(tmp_path):
    repo = tmp_path / "trepo"
    repo.mkdir()
    sh(repo, "init", "-q", "-b", "master")
    (repo / "a.txt").write_text("a1\n")
    (repo / "b.txt").write_text("b1\n")
    sh(repo, "add", "-A")
    sh(repo, "commit", "-q", "-m", "root")
    return repo, sh(repo, "rev-parse", "HEAD")


def test_apply_patch_per_file_skips_failing_file(tmp_path):
    repo, root = _two_file_tree(tmp_path)
    patch = (b"diff --git a/a.txt b/a.txt\n--- a/a.txt\n+++ b/a.txt\n@@ -1 +1 @@\n-a1\n+a2\n"
             b"diff --git a/b.txt b/b.txt\n--- a/b.txt\n+++ b/b.txt\n@@ -1 +1 @@\n-bX\n+b2\n")
    with ScratchTree(str(repo), root) as scratch:
        before = open(os.path.join(scratch.dir, "b.txt"), "rb").read()
        assert scratch.apply_patch_per_file(patch) == (["a.txt"], ["b.txt"])
        assert open(os.path.join(scratch.dir, "a.txt"), "rb").read() == b"a2\n"
        assert open(os.path.join(scratch.dir, "b.txt"), "rb").read() == before


def test_apply_patch_per_file_unsplittable_is_no_apply(tmp_path):
    repo, root = _two_file_tree(tmp_path)
    patch = b"--- a/a.txt\n+++ b/a.txt\n@@ -1 +1 @@\n-zz\n+a2\n"
    with ScratchTree(str(repo), root) as scratch:
        assert scratch.apply_patch_per_file(patch) == ([], [_UNKNOWN_PATH])


# --- Plan-bearing sample for plan-needing gates ----------------------------------------------

PLAN_SPEC = ReplaySpec(argv=("{plan_file}",), needs_plan=True)
PLAN_GATE = '#!/bin/sh\n[ -f "$1" ] && exit 0\nexit 1\n'


def _settled_prs(count, now, head_refs=None):
    """`count` settled PRs numbered 1..count, newest first, all older than 48h."""
    from harness.gate_backtest import HistoricalPR
    return [HistoricalPR(i, "feat: x", "a" * 40, 1, now - timedelta(days=4, hours=i),
                         (head_refs or {}).get(i, f"b{i}"), ())
            for i in range(1, count + 1)]


def _touch_plans(plans_dir, refs):
    plans_dir.mkdir(parents=True, exist_ok=True)
    for ref in refs:
        (plans_dir / f"{ref}.md").write_text("# plan\n")


def test_plan_sample_stops_at_limit(tmp_path):
    from harness.gate_backtest import classify_truth
    from harness.gate_backtest_replay import select_plan_sample
    now = T0 + timedelta(days=30)
    fetched = _settled_prs(40, now)
    _touch_plans(tmp_path / "plans", [f"b{i}" for i in range(1, 41)])
    truth = classify_truth(fetched, now)
    sample, depth = select_plan_sample(fetched, truth, str(tmp_path / "plans"), 30)
    assert [p.number for p in sample] == list(range(1, 31))
    assert depth == 30


def test_plan_sample_counts_archived_and_rejects_bad_head_ref(tmp_path):
    from harness.gate_backtest import classify_truth
    from harness.gate_backtest_replay import select_plan_sample
    now = T0 + timedelta(days=30)
    plans = tmp_path / "plans"
    (plans / "_archive").mkdir(parents=True)
    (plans / "b1.md").write_text("# plan\n")
    (plans / "_archive" / "b2.md").write_text("# archived plan\n")
    (tmp_path / "escape.md").write_text("# traversal target\n")
    (plans / "b4.txt").write_text("near miss\n")
    fetched = _settled_prs(4, now, head_refs={3: "../escape"})
    truth = classify_truth(fetched, now)
    sample, depth = select_plan_sample(fetched, truth, str(plans), 30)
    assert [p.number for p in sample] == [1, 2]
    assert depth == 4


def _selection_setup(monkeypatch, tmp_path, plan_refs, flag_numbers=(), count=40, detect=None):
    """Patch backtest_changes' collaborators; return (call, seen) for the recorded run_backtest."""
    from harness import gate_backtest_replay as gbr
    from harness.gate_backtest import ReplayResult
    now = T0 + timedelta(days=30)
    plans = tmp_path / "plans"
    _touch_plans(plans, plan_refs)
    seen = {"history": None, "plan_history": "unset"}
    plan_gate = gate(PLAN_SPEC, path="bin/check-plan")
    monkeypatch.setattr(gbr, "_detect",
                        lambda *a, **k: detect if detect is not None else [plan_gate])
    monkeypatch.setattr(gbr, "fetch_history", lambda *a, **k: _settled_prs(count, now))

    def fake_run(repo, head, gates, history, *a, plan_history=None, **k):
        seen["history"] = [p.number for p in history]
        seen["plan_history"] = None if plan_history is None else [p.number for p in plan_history]
        rows = []
        for g in gates:
            prs = plan_history if g.spec.needs_plan else history
            for pr in prs or []:
                rows.append(ReplayResult(pr.number, g.path,
                                         "flag" if pr.number in flag_numbers else "pass", ""))
        return rows

    monkeypatch.setattr(gbr, "run_backtest", fake_run)

    def call():
        return gbr.backtest_changes(str(tmp_path), "HEAD", [("M", "bin/check-plan")], fetch=False,
                                    plans_dir=str(plans), limit=30, now=now)
    return call, seen


def test_plan_sample_reaches_past_window_and_clears(monkeypatch, tmp_path):
    call, seen = _selection_setup(
        monkeypatch, tmp_path, ["b1", "b2", "b31", "b32", "b33", "b34", "b35"], count=35)
    out = call()
    assert seen["plan_history"] == [1, 2, 31, 32, 33, 34, 35]
    assert out.verdict.state == "CLEARED"
    assert out.window == 30
    assert ("the 7 newest settled merged PRs that have one, found in the newest 35 settled"
            in out.block)


def test_plan_sample_too_few_anywhere_stays_thin_unknown(monkeypatch, tmp_path):
    call, seen = _selection_setup(monkeypatch, tmp_path, ["b1", "b2", "b33"])
    out = call()
    assert seen["plan_history"] == [1, 2, 33]
    assert out.verdict.state == "UNKNOWN"
    assert "thin sample" in out.verdict.reason


def test_plan_sample_false_flags_on_deep_prs_hold(monkeypatch, tmp_path):
    call, _ = _selection_setup(
        monkeypatch, tmp_path, ["b1", "b2", "b31", "b32", "b33", "b34", "b35"],
        flag_numbers={31, 32}, count=35)
    out = call()
    assert out.verdict.state == "HELD"
    assert "flagged 2 clean PRs" in out.verdict.reason


def test_non_plan_gate_history_unchanged_with_plan_gate(monkeypatch, tmp_path):
    detect = [gate(PLAN_SPEC, path="bin/check-plan"), gate(path="bin/check-fake")]
    call, seen = _selection_setup(
        monkeypatch, tmp_path, ["b1", "b2", "b31", "b32", "b33", "b34", "b35"], detect=detect)
    call()
    assert seen["history"] == list(range(1, 31))
    assert seen["plan_history"] == [1, 2, 31, 32, 33, 34, 35]


def test_non_plan_run_skips_plan_sample_selection(monkeypatch, tmp_path):
    from harness import gate_backtest_replay as gbr

    def boom(*a, **k):
        raise AssertionError("resolve_plan must not run for a non-plan candidate")

    call, seen = _selection_setup(monkeypatch, tmp_path, ["b1"],
                                  detect=[gate(path="bin/check-fake")])
    monkeypatch.setattr(gbr, "resolve_plan", boom)
    call()
    assert seen["plan_history"] is None
    assert seen["history"] == list(range(1, 31))


def _plan_engine(tmp_path, plan_numbers, plan_history_given):
    repo, shas = build_repo(tmp_path)
    head_refs = {i: f"b{i}" for i in range(1, 7)}
    history = fetch_history(fake_gh(shas, head_refs=head_refs), make_git(str(repo)))
    plans = tmp_path / "plans"
    _touch_plans(plans, [f"b{n}" for n in plan_numbers])
    gates = [gate(), gate(PLAN_SPEC, path="bin/check-fakeplan")]
    files = {"bin/check-fake": (NEW_GATE.encode(), 0o100755),
             "bin/check-fakeplan": (PLAN_GATE.encode(), 0o100755)}
    plan_history = ([p for p in history if p.number in plan_numbers]
                    if plan_history_given else None)
    return run_backtest(str(repo), "HEAD", gates, history, files, str(plans),
                        plan_history=plan_history)


def test_run_backtest_non_plan_rows_identical_with_plan_history(tmp_path):
    def rows(results):
        return [(r.pr, r.outcome, r.detail) for r in results if r.gate == "bin/check-fake"]

    (tmp_path / "a").mkdir()
    (tmp_path / "b").mkdir()
    without = rows(_plan_engine(tmp_path / "a", {2, 4, 5}, False))
    with_sample = rows(_plan_engine(tmp_path / "b", {2, 4, 5}, True))
    assert without and without == with_sample


def test_plan_gate_records_no_no_plan_skips_outside_sample(tmp_path):
    results = _plan_engine(tmp_path, {2, 4, 5}, True)
    plan_rows = [r for r in results if r.gate == "bin/check-fakeplan"]
    assert not [r for r in plan_rows if r.detail == "no-plan"]
    assert [r.pr for r in plan_rows] == [5, 4, 2]


def test_fetch_history_reads_head_oid(tmp_path):
    """`fetch_history` copies `headRefOid` into `HistoricalPR.head_oid` and requests the field."""
    repo, shas = build_repo(tmp_path)
    oid = "ab" * 20
    requested = []

    def gh_json(argv):
        requested.append(argv)
        items = fake_gh(shas)(argv)
        items[0]["headRefOid"] = oid
        items[1]["headRefOid"] = "not-a-sha"
        return items

    history = fetch_history(gh_json, make_git(str(repo)))
    by_number = {p.number: p for p in history}
    assert by_number[1].head_oid == oid
    assert by_number[2].head_oid == ""
    assert by_number[3].head_oid == ""
    fields = requested[0][requested[0].index("--json") + 1].split(",")
    assert "headRefOid" in fields


def _head_pr(number, head_oid):
    return HistoricalPR(number, "t", "f" * 40, 1, T0, f"b{number}", (), head_oid=head_oid)


def _record_fetches(monkeypatch, raises=None):
    real_run = subprocess.run
    fetches = []

    def wrapped(argv, *args, **kwargs):
        if "fetch" in argv:
            fetches.append(list(argv))
            if raises is not None:
                raise raises
            return subprocess.CompletedProcess(argv, 0, "", "")
        return real_run(argv, *args, **kwargs)

    monkeypatch.setattr(gbr.subprocess, "run", wrapped)
    return fetches


def test_ensure_pr_heads_fetches_only_missing(monkeypatch, tmp_path):
    repo, shas = build_repo(tmp_path)
    fetches = _record_fetches(monkeypatch)
    present = _head_pr(1, shas[0])
    absent = _head_pr(2, "c" * 40)
    unknown = _head_pr(3, "")
    assert ensure_pr_heads(str(repo), [present, absent, unknown]) == 1
    assert len(fetches) == 1
    assert fetches[0][-1] == "refs/pull/2/head"
    assert "refs/pull/1/head" not in fetches[0] and "refs/pull/3/head" not in fetches[0]
    fetches.clear()
    assert ensure_pr_heads(str(repo), [present, unknown]) == 0
    assert fetches == []


def test_ensure_pr_heads_tolerates_fetch_timeout(monkeypatch, tmp_path):
    repo, shas = build_repo(tmp_path)
    fetches = _record_fetches(monkeypatch, raises=subprocess.TimeoutExpired("git", 60))
    prs = [_head_pr(1, "d" * 40), _head_pr(2, "e" * 40)]
    assert ensure_pr_heads(str(repo), prs) == 2
    assert len(fetches) == 1


TWO_LINE_HEAD_GATE = ("#!/bin/sh\n"
                      "if git diff-tree --no-commit-id --name-only -r HEAD | grep -q "
                      "'^src/noisy.txt$'; then echo flagged; echo extra; exit 1; fi\nexit 0\n")


def test_run_backtest_records_base_and_head_flag_lines(tmp_path):
    repo, shas = build_repo(tmp_path)
    results = run(repo, shas, tmp_path, script=TWO_LINE_HEAD_GATE,
                  base_overlay=base(BASE_FLAG_GATE))
    flagged = [r for r in results if r.outcome == "flag"]
    assert {r.pr for r in flagged} == {3, 5}
    for r in flagged:
        assert r.flag_lines == ("flagged", "extra")
        assert r.base_flag_lines == ("flagged",)
    for r in (r for r in results if r.outcome == "pass"):
        assert r.flag_lines == () and r.base_flag_lines == ()
