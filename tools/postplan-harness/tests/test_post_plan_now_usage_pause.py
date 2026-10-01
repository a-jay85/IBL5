"""bin/post-plan-now integration tests for the usage gate (ADR-0143 addendum).

Every test here drives the real `bin/post-plan-now` and the real `bin/lib/usage-gate.sh`
against a fake harness (`HARNESS/run`) whose body the test supplies. The fixture repo,
the generated-command helpers and the shared stubs live in
`test_post_plan_now_fallback.py`; this file reuses them and edits nothing there.
"""
import json
import os
import pathlib
import re
import subprocess
import types

import pytest

from test_post_plan_now_fallback import (  # noqa: F401  (the fixture import re-arms its autouse reaper here)
    PPN, REPO, _fixture_repo, _generate_cmd, _reap_tmp_sidecars, _run_gate, _run_ppn,
)

# The exact text test_postrun_pause_limit_rc1 in bin/test-usage-gate feeds usage_postrun_pause.
LIMIT_HIT_LINE = "You've hit your weekly limit"
PAUSED_LINE = "post-plan-now: RESULT paused (usage gate); auto-resumes after reset."
BLOCKED_OPEN = "=== post-plan blocked ship ==="
UUID_RE = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$")

# A normal-zone usage reading (same shape as bin/test-usage-gate's usage_json).
NORMAL_USAGE = {
    "five_hour": {"utilization": 41.0, "resets_at": "2026-09-28T20:00:00.123+00:00"},
    "seven_day": {"utilization": 12.0, "resets_at": "2026-10-02T00:00:00+00:00"},
}


def _markers(state_dir):
    """Parsed JSON of every markers/*.json file under the usage-gate state dir."""
    mdir = pathlib.Path(state_dir) / "markers"
    return [json.loads(p.read_text()) for p in sorted(mdir.glob("*.json"))]


def _link_gate_libs(repo):
    """Make the fixture root carry the real gate library and the real post-plan-now.

    usage_marker_write rejects a resume bin that is not executable, and the generated command
    sources "$ROOT/bin/lib/usage-gate.sh". Both resolve against the fixture root, so symlink
    the real libraries in (a symlinked script resolves its libs next to the symlink, so the
    resumed bin/post-plan-now needs them all). Untracked, so
    the fixture's committed tree and its dirty-tree guards are untouched.
    """
    lib = repo / "bin" / "lib"
    for src in sorted(pathlib.Path(REPO, "bin", "lib").glob("*.sh")):
        if not (lib / src.name).exists():    # the fixture already carries its own copies of two
            (lib / src.name).symlink_to(src)
    (repo / "bin" / "post-plan-now").symlink_to(PPN)


def _stub_exec(path, text):
    path.write_text(text)
    path.chmod(0o755)


def _run_foreground_with(tmp_path, harness_body, extra_env=None, with_claude_stub=True):
    """Run `post-plan-now --foreground` with a fake harness whose script body is `harness_body`.

    Modelled on _run_foreground in test_post_plan_now_fallback.py; the one difference is the
    body. The usage gate is isolated: a private state dir, a `security` stub that finds no
    token (so usage_fetch never reaches the network), and an empty transcript root.
    Returns a namespace: r (CompletedProcess), repo, state, claude_log, dm_calls, env, argv.
    """
    home = tmp_path / "home"
    bun_bin = home / ".bun" / "bin"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    bun_bin.mkdir(parents=True)
    shim = tmp_path / "shim"
    shim.mkdir()

    caffeinate = '#!/bin/sh\nwhile [ $# -gt 0 ] && [ "${1#-}" != "$1" ]; do shift; done\nexec "$@"\n'
    _stub_exec(bun_bin / "caffeinate", caffeinate)
    for d in (bun_bin, shim):    # bun/bin shadows inside the generated CMD, shim in the parent
        _stub_exec(d / "security", "#!/bin/sh\nexit 1\n")
        _stub_exec(d / "curl", "#!/bin/sh\nexit 1\n")
        _stub_exec(d / "launchctl", "#!/bin/sh\nexit 0\n")

    harness = tmp_path / "fake-harness"
    harness.mkdir(exist_ok=True)
    _stub_exec(harness / "run", "#!/usr/bin/env bash\n" + harness_body + "\n")

    state = tmp_path / "ugstate"
    projects = tmp_path / "projects"
    projects.mkdir()
    claude_log = tmp_path / "claude-log.txt"
    dm_calls = tmp_path / "dm-calls.txt"

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}",
               IBL5_USAGE_GATE_STATE_DIR=str(state),
               IBL5_USAGE_CLAUDE_PROJECTS=str(projects))
    for name in ("POST_PLAN_SKILL", "IBL5_USAGE_GATE", "IBL5_USAGE_GATE_RUNNER",
                 "IBL5_USAGE_GATE_RESUME_BIN", "IBL5_USAGE_GATE_SESSION_ID"):
        env.pop(name, None)
    if with_claude_stub:
        env["CLAUDE_LOG"] = str(claude_log)
        _stub_exec(bun_bin / "claude",
                   '#!/bin/sh\nprintf "CLAUDE-ARG: %s\\n" "$@" >> "$CLAUDE_LOG"\nexit 0\n')
    env.update(extra_env or {})

    repo = _fixture_repo(tmp_path)
    _link_gate_libs(repo)
    # post-plan-fail-dm lives at "$ROOT/bin/post-plan-fail-dm"; record calls instead of DMing.
    _stub_exec(repo / "bin" / "post-plan-fail-dm",
               f'#!/bin/sh\nprintf "%s\\n" "$4" >> "{dm_calls}"\n')

    r = subprocess.run(["bash", PPN, "--foreground"], cwd=repo, env=env,
                       capture_output=True, text=True)
    return types.SimpleNamespace(r=r, repo=repo, state=state, claude_log=claude_log,
                                 dm_calls=dm_calls, env=env)


def _claude_ran(run):
    return run.claude_log.exists() and run.claude_log.read_text() != ""


# ---------------------------------------------------------------- Phase 1: characterization


def test_harness_limit_hit_rc1_pauses_without_fallback(tmp_path):
    """Backlog #1229: a harness that prints the limit-hit line and exits 1 pauses the run.

    GATE_OPEN's usage_postrun_pause writes a limit-hit marker, so the skill fallback is
    skipped and PAUSE_SEG reports the pause.
    """
    run = _run_foreground_with(tmp_path, f"echo {LIMIT_HIT_LINE!r}\nexit 1")
    markers = _markers(run.state)
    assert len(markers) == 1, f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    m = markers[0]
    assert m["runner"] == "post-plan-now"
    assert m["reason"] == "limit-hit"
    assert m["resume_argv"][0].endswith("bin/post-plan-now")
    assert m["resume_argv"][1] == "--resume-paused"
    assert not _claude_ran(run), "the skill fallback must not run on a limit-hit pause"
    assert PAUSED_LINE in run.r.stdout.splitlines()


def test_harness_rc3_limit_hit_stays_blocked(tmp_path):
    """rc 3 with limit text and no marker stays blocked: usage_postrun_pause excludes rc 3."""
    run = _run_foreground_with(tmp_path, f"echo {LIMIT_HIT_LINE!r}\nexit 3")
    assert _markers(run.state) == []
    assert BLOCKED_OPEN in run.r.stdout
    assert PAUSED_LINE not in run.r.stdout


# ---------------------------------------------------------------- Phase 3: launch context


def _pinned_session_id(cmd):
    matches = re.findall(r"--session-id '([0-9a-f-]{36})'", cmd)
    assert matches, "no --session-id found in cmd"
    return matches[0]


def test_harness_launch_carries_gate_context(tmp_path):
    """The harness launch carries the three context vars and never the gate flag.

    Flipped from the Phase 1 characterization test_harness_launch_has_no_gate_env_today.
    """
    cmd = _generate_cmd(tmp_path)
    sid = _pinned_session_id(cmd)
    harness_seg = cmd.split("rc=$?; ", 1)[0]    # everything before the harness's rc capture
    assert "IBL5_USAGE_GATE_RUNNER=post-plan-now" in harness_seg
    assert re.search(rf"IBL5_USAGE_GATE_SESSION_ID='?{sid}'?(\s|$)", harness_seg), harness_seg
    m = re.search(r"IBL5_USAGE_GATE_RESUME_BIN=(\S+)", harness_seg)
    assert m and m.group(1).strip("'\"").endswith("/bin/post-plan-now"), harness_seg
    assert "IBL5_USAGE_GATE=1" not in harness_seg


def test_foreground_harness_env_has_context_without_gate_flag(tmp_path):
    """What the launched harness process sees: the context, never IBL5_USAGE_GATE.

    This is the old-harness safety proof. A harness that ignores the context runs ungated.
    """
    dump = tmp_path / "env-dump.txt"
    run = _run_foreground_with(tmp_path, 'env > "$ENV_DUMP"; exit 0',
                               extra_env={"ENV_DUMP": str(dump)})
    assert run.r.returncode == 0, f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    lines = dump.read_text().splitlines()
    seen = dict(line.split("=", 1) for line in lines if "=" in line)
    assert seen.get("IBL5_USAGE_GATE_RUNNER") == "post-plan-now"
    assert seen.get("IBL5_USAGE_GATE_RESUME_BIN", "").endswith("/bin/post-plan-now")
    assert UUID_RE.match(seen.get("IBL5_USAGE_GATE_SESSION_ID", "")), seen.get("IBL5_USAGE_GATE_SESSION_ID")
    assert not any(line.startswith("IBL5_USAGE_GATE=") for line in lines)


def test_skill_leg_gate_env_unchanged(tmp_path):
    """The skill leg's GATE_ENV stays byte-identical, and carries no session-id var."""
    cmd = _generate_cmd(tmp_path)
    literal = ("IBL5_USAGE_GATE=1 IBL5_USAGE_GATE_RUNNER=post-plan-now "
               "IBL5_USAGE_GATE_MODEL=claude-sonnet-5-5 IBL5_USAGE_GATE_RESUME_BIN=")
    assert cmd.count(literal) == 1
    skill_leg = cmd.split("should_fallback", 1)
    assert len(skill_leg) >= 2
    after = cmd[cmd.rindex("should_fallback"):]
    assert "IBL5_USAGE_GATE_SESSION_ID" not in after


# ---------------------------------------------------------------- Phase 9: pause, resume, fallback

PAUSE_BODY = """\
. "$(dirname "$IBL5_USAGE_GATE_RESUME_BIN")/lib/usage-gate.sh"
usage_marker_write "$IBL5_USAGE_GATE_SESSION_ID" post-plan-now \
  "$IBL5_USAGE_GATE_RESUME_BIN" usage-pause stop 100 five_hour "" || exit 3
echo 'RESULT: post-plan PAUSED at review (usage gate); the usage-gate coordinator resumes it after the reset.'
exit 75"""


def test_harness_pause_with_marker_pauses_without_faildm(tmp_path):
    """A harness exit 75 with a marker for the run's S pauses: no fallback, no fail-DM."""
    run = _run_foreground_with(tmp_path, PAUSE_BODY)
    markers = _markers(run.state)
    assert len(markers) == 1, f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    m = markers[0]
    sid = m["session_id"]
    assert UUID_RE.match(sid)
    assert m["runner"] == "post-plan-now"
    assert m["resume_argv"] == [str(run.repo / "bin" / "post-plan-now"), "--resume-paused", sid]
    assert os.path.realpath(m["resume_cwd"]) == os.path.realpath(str(run.repo))
    assert PAUSED_LINE in run.r.stdout.splitlines()
    assert BLOCKED_OPEN not in run.r.stdout
    assert not _claude_ran(run)
    assert not run.dm_calls.exists(), "the fail-DM must not fire on a pause"


def test_stray_75_without_marker_falls_back(tmp_path):
    """A reserved exit 75 with no marker never reads as a clean exit 0: the skill fallback runs."""
    run = _run_foreground_with(tmp_path, "exit 75")
    assert _markers(run.state) == []
    assert _claude_ran(run), f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    assert run.claude_log.read_text().count("then execute every phase") == 1
    assert PAUSED_LINE not in run.r.stdout


def test_harness_marker_write_failure_stays_blocked(tmp_path):
    """A failed marker write then exit 3 (what runner.py does) leaves the run blocked."""
    body = """\
. "$(dirname "$IBL5_USAGE_GATE_RESUME_BIN")/lib/usage-gate.sh"
usage_marker_write "$IBL5_USAGE_GATE_SESSION_ID" post-plan-now \
  /nonexistent/not-executable usage-pause stop 100 five_hour "" || exit 3
exit 75"""
    run = _run_foreground_with(tmp_path, body)
    assert run.r.returncode == 3, f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    assert _markers(run.state) == []
    assert BLOCKED_OPEN in run.r.stdout
    assert PAUSED_LINE not in run.r.stdout


RESUME_BODY = """\
echo run >> "$RUN_LOG"
if [ -e "$RESUME_FLAG" ]; then
  env > "$ENV_DUMP"
  exit 0
fi
""" + PAUSE_BODY


def test_resume_replays_marker_argv_and_clears_marker(tmp_path):
    """The marker's own resume_argv reruns the harness under the same S and clears the marker.

    The saved argv drops --foreground (bin/post-plan-now: a resume always goes through the
    launchd job), so the replay writes a plist instead of running in-process. The test then
    runs that plist's command the way launchd would. No double fire: the fallback never runs.
    """
    extra = {"RUN_LOG": str(tmp_path / "run-log.txt"),
             "ENV_DUMP": str(tmp_path / "env-dump.txt"),
             "RESUME_FLAG": str(tmp_path / "resume-flag")}
    run = _run_foreground_with(tmp_path, RESUME_BODY, extra_env=extra)
    markers = _markers(run.state)
    assert len(markers) == 1, f"stdout={run.r.stdout!r} stderr={run.r.stderr!r}"
    marker = markers[0]
    sid = marker["session_id"]
    marker_file = pathlib.Path(run.state) / "markers" / f"{sid}.json"
    assert marker_file.exists()

    pathlib.Path(extra["RESUME_FLAG"]).touch()
    # a normal-zone reading, so usage_prestart_gate lets the resume through
    cache = dict(NORMAL_USAGE, fetched_at=int(__import__("time").time()))
    (pathlib.Path(run.state) / "cache.json").write_text(json.dumps(cache))
    resume = subprocess.run(marker["resume_argv"], cwd=marker["resume_cwd"], env=run.env,
                            capture_output=True, text=True)
    assert resume.returncode == 0, f"stdout={resume.stdout!r} stderr={resume.stderr!r}"
    assert not marker_file.exists(), "the resume must clear the pause marker"

    plists = list((tmp_path / "home" / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, plists
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    cmd = cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")
    launched = subprocess.run(["/bin/bash", "-lc", cmd], cwd=marker["resume_cwd"], env=run.env,
                              capture_output=True, text=True)
    assert launched.returncode == 0, f"stdout={launched.stdout!r} stderr={launched.stderr!r}"

    assert pathlib.Path(extra["RUN_LOG"]).read_text().splitlines() == ["run", "run"]
    dumped = dict(line.split("=", 1) for line in pathlib.Path(extra["ENV_DUMP"]).read_text().splitlines()
                  if "=" in line)
    assert dumped["IBL5_USAGE_GATE_SESSION_ID"] == sid
    assert not _claude_ran(run), "no fallback on either launch"


def test_resume_without_marker_rejects(tmp_path):
    """--resume-paused <uuid> with no marker exits 2 and launches no harness."""
    run_log = tmp_path / "run-log.txt"
    run = _run_foreground_with(tmp_path, 'echo run >> "$RUN_LOG"\nexit 0',
                               extra_env={"RUN_LOG": str(run_log)})
    assert run.r.returncode == 0
    run_log.unlink()
    sid = "5f0d9c1e-3a7b-4c28-9e61-0a1b2c3d4e5f"
    r = subprocess.run(["bash", PPN, "--resume-paused", sid], cwd=run.repo, env=run.env,
                       capture_output=True, text=True)
    assert r.returncode == 2, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "no pause marker" in r.stderr
    assert not run_log.exists(), "no harness may launch on a rejected resume"
