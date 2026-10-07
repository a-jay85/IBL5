"""Tests for the post-plan running label and failure-comment lifecycle.

Covers the label/failure-comment functions in bin/post-plan-now, the harness adapter
(ghad.py pr_status_label), runner.py _post_status_label, and the SKILL.md blocks.
"""
import os
import re
import shutil
import stat
import subprocess
import sys

import pytest

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
PPN = os.path.join(REPO, "bin", "post-plan-now")

sys.path.insert(0, os.path.join(REPO, "tools", "postplan-harness"))

from harness.adapters.ghad import RecordingGh


# ---------------------------------------------------------------------------
# Fixtures
# ---------------------------------------------------------------------------

@pytest.fixture()
def stub_gh(tmp_path):
    """Stub gh script: appends args to $GH_LOG, echoes $GH_FIXTURE, exits 1 when $GH_FAIL=1."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text(
        '#!/bin/sh\n'
        'echo "$*" >> "${GH_LOG:-/dev/null}"\n'
        'if [ "${GH_FAIL:-0}" = "1" ]; then echo "stub gh error" >&2; exit 1; fi\n'
        'if [ -n "${GH_FIXTURE:-}" ]; then printf "%s" "$GH_FIXTURE"; fi\n'
        'exit 0\n'
    )
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    return bindir


@pytest.fixture()
def stub_launchctl(tmp_path):
    """Stub launchctl: exits 0 only when $1=list and $2 is in $LIVE_LABELS."""
    bindir = tmp_path / "lbin"
    bindir.mkdir()
    lc = bindir / "launchctl"
    lc.write_text(
        '#!/usr/bin/env bash\n'
        'if [ -n "${LC_LOG:-}" ]; then echo "$*" >> "$LC_LOG"; fi\n'
        'if [ "$1" = "list" ]; then\n'
        '  label="$2"\n'
        '  IFS=":" read -ra live_arr <<< "${LIVE_LABELS:-}"\n'
        '  for live in "${live_arr[@]}"; do\n'
        '    [ "$live" = "$label" ] && exit 0\n'
        '  done\n'
        '  exit 1\n'
        'fi\n'
        'exit 1\n'
    )
    lc.chmod(lc.stat().st_mode | stat.S_IEXEC)
    return bindir


def _run_badge(script, env_extra=None, stub_gh_dir=None, stub_lc_dir=None):
    """Source bin/post-plan-now and run a badge-related script snippet."""
    env = os.environ.copy()
    if stub_gh_dir:
        env["GH_CMD"] = str(stub_gh_dir / "gh")
        env["PATH"] = f"{stub_gh_dir}:{env['PATH']}"
    if stub_lc_dir:
        env["LAUNCHCTL_CMD"] = str(stub_lc_dir / "launchctl")
    if env_extra:
        env.update(env_extra)
    full = f'source "{PPN}" >/dev/null 2>&1\n{script}'
    return subprocess.run(["bash", "-c", full], capture_output=True, text=True, env=env)


def _fixture_repo(tmp_path):
    """Minimal dirty linked worktree on a non-master branch.

    Creates a main checkout at tmp_path/wt with bin/lib/git-helpers.sh committed,
    then adds a linked worktree at tmp_path/wt-base. Returns tmp_path/wt-base so
    is_in_worktree() passes in callers (post-plan-now requires a linked worktree).
    """
    repo = tmp_path / "wt"
    repo.mkdir()
    run = lambda *a: subprocess.run(a, cwd=str(repo), check=True, capture_output=True)
    run("git", "init", "-q", "-b", "master")
    run("git", "config", "user.email", "test@example.com")
    run("git", "config", "user.name", "Test")
    (repo / "bin" / "lib").mkdir(parents=True)
    shutil.copy(os.path.join(REPO, "bin", "lib", "git-helpers.sh"),
                str(repo / "bin" / "lib" / "git-helpers.sh"))
    (repo / "f.txt").write_text("one\n")
    run("git", "add", "-A")
    run("git", "commit", "-qm", "base")
    run("git", "checkout", "-qb", "some-feature")
    linked = tmp_path / "wt-base"
    run("git", "worktree", "add", str(linked), "-b", "wt-feature")
    (linked / "f.txt").write_text("two\n")
    return linked


def _generate_cmd(tmp_path, extra_env=None):
    """Run bin/post-plan-now with launchctl stubbed and HOME redirected; return $CMD."""
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    env.update(extra_env or {})
    repo = _fixture_repo(tmp_path)
    r = subprocess.run(["bash", PPN], cwd=repo, env=env, capture_output=True, text=True)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"

    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one generated plist, got {plists}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    return cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")



def _calls(log):
    return log.read_text().splitlines() if log.exists() else []


def _label_deletes(calls):
    return [c for c in calls if c.startswith("api") and "--method DELETE" in c
            and "/labels/post-plan-running" in c]


def _pr_comments(calls):
    return [c for c in calls if c.startswith("pr comment")]


# ---------------------------------------------------------------------------
# Test 1: conclude_is_idempotent
# ---------------------------------------------------------------------------

def test_conclude_is_idempotent(tmp_path, stub_gh):
    """Calling conclude_status_badge twice runs one set of calls."""
    log = tmp_path / "gh.log"
    env = {"GH_LOG": str(log), "GH_FIXTURE": ""}
    r = _run_badge(
        'conclude_status_badge 1 99\nconclude_status_badge 1 99',
        env_extra=env, stub_gh_dir=stub_gh
    )
    assert r.returncode == 0, r.stderr
    calls = _calls(log)
    assert len(_label_deletes(calls)) == 1, calls
    assert len(_pr_comments(calls)) == 1, calls


# ---------------------------------------------------------------------------
# Test 2: outcome_routing
# ---------------------------------------------------------------------------

def test_conclude_clean_removes_label_and_legacy_sticky_without_comment(tmp_path, stub_gh):
    """rc=0: label DELETE + legacy sticky delete, never a `pr comment`."""
    log = tmp_path / "gh.log"
    env = {"GH_LOG": str(log), "GH_FIXTURE": "7"}  # stub answers every lookup with id 7
    r = _run_badge('conclude_status_badge 0 42', env_extra=env, stub_gh_dir=stub_gh)
    assert r.returncode == 0, r.stderr
    calls = _calls(log)
    assert len(_label_deletes(calls)) == 1, calls
    assert any("DELETE" in c and "issues/comments/7" in c for c in calls), (
        f"legacy sticky delete missing: {calls}")
    assert _pr_comments(calls) == [], calls


def test_conclude_failure_removes_label_and_posts_one_comment(tmp_path, stub_gh):
    """Any non-zero rc: label DELETE + exactly one new `pr comment` carrying the marker."""
    for rc in ("1", "3", "75", "143"):
        log = tmp_path / f"gh-{rc}.log"
        keep = tmp_path / f"body-{rc}.txt"
        # Stub gh that also copies the --body-file content aside.
        gh = stub_gh / "gh"
        gh.write_text(
            '#!/bin/sh\n'
            'echo "$*" >> "${GH_LOG:-/dev/null}"\n'
            'if [ "$1" = "pr" ] && [ "$2" = "comment" ]; then\n'
            '  while [ $# -gt 0 ]; do\n'
            '    if [ "$1" = "--body-file" ]; then cat "$2" > "$KEEP"; fi\n'
            '    shift\n'
            '  done\n'
            'fi\n'
            'exit 0\n'
        )
        env = {"GH_LOG": str(log), "KEEP": str(keep)}
        r = _run_badge(f'conclude_status_badge {rc} 42', env_extra=env, stub_gh_dir=stub_gh)
        assert r.returncode == 0, f"rc={rc}: {r.stderr}"
        calls = _calls(log)
        assert len(_label_deletes(calls)) == 1, f"rc={rc}: {calls}"
        assert len(_pr_comments(calls)) == 1, f"rc={rc}: {calls}"
        body = keep.read_text()
        assert body.splitlines()[0] == "<!-- postplan-failure -->", f"rc={rc}: {body!r}"
        assert "<!-- postplan-status -->" not in body
        assert "<!-- postplan-label:" not in body


# ---------------------------------------------------------------------------
# Test 3: label add
# ---------------------------------------------------------------------------

def test_post_status_badge_creates_and_applies_label(tmp_path, stub_gh):
    log = tmp_path / "gh.log"
    r = _run_badge("post_status_badge 42", env_extra={"GH_LOG": str(log)}, stub_gh_dir=stub_gh)
    assert r.returncode == 0, r.stderr
    calls = _calls(log)
    create = [c for c in calls if c.startswith("label create post-plan-running")]
    assert len(create) == 1, calls
    assert "--color FBCA04" in create[0]
    assert "post-plan is running on this PR; removed when the run ends" in create[0]
    posts = [c for c in calls if c.startswith("api") and "--method POST" in c]
    assert len(posts) == 1, calls
    assert "issues/42/labels" in posts[0]
    assert "labels[]=post-plan-running" in posts[0]
    assert _pr_comments(calls) == []


# ---------------------------------------------------------------------------
# Test 4: fail_open
# ---------------------------------------------------------------------------

def test_fail_open(tmp_path, stub_gh):
    """GH_FAIL=1: label/failure functions return 0 and arg-error exits unchanged."""
    log = tmp_path / "gh.log"
    env = {"GH_LOG": str(log), "GH_FAIL": "1", "GH_FIXTURE": ""}
    for fn_call in [
        "post_status_badge 42",
        "conclude_status_badge 0 42",
        "conclude_status_badge 1 42",
        "postplan_sweep_stale_badge 42",
        "postplan_label_add 42",
        "postplan_label_remove 42",
        "postplan_failure_comment 42 1",
    ]:
        log.write_text("")
        env["POSTPLAN_CONCLUDED"] = "0"
        r = _run_badge(fn_call, env_extra=env, stub_gh_dir=stub_gh)
        assert r.returncode == 0, f"{fn_call} failed with GH_FAIL=1: {r.stderr}"

    # Arg-error exit codes unchanged under GH_FAIL=1
    r2 = subprocess.run(
        ["bash", "bin/post-plan-now", "--pln", "/tmp/x.md"],
        capture_output=True, text=True, cwd=REPO,
        env={**os.environ, "GH_FAIL": "1"}
    )
    assert r2.returncode == 1
    assert "unknown argument" in r2.stderr


# ---------------------------------------------------------------------------
# Test 5: label_present
# ---------------------------------------------------------------------------

def test_label_present_compares_to_true(tmp_path, stub_gh):
    log = tmp_path / "gh.log"
    r = _run_badge('postplan_label_present 42 && echo yes || echo no',
                   env_extra={"GH_LOG": str(log), "GH_FIXTURE": "true"}, stub_gh_dir=stub_gh)
    assert r.stdout.strip() == "yes", r.stdout
    assert any(c.startswith("pr view 42 --json labels") for c in _calls(log))
    r2 = _run_badge('postplan_label_present 42 && echo yes || echo no',
                    env_extra={"GH_FIXTURE": "false"}, stub_gh_dir=stub_gh)
    assert r2.stdout.strip() == "no", r2.stdout
    r3 = _run_badge('postplan_label_present 42 && echo yes || echo no',
                    env_extra={"GH_FAIL": "1"}, stub_gh_dir=stub_gh)
    assert r3.stdout.strip() == "no", r3.stdout


# ---------------------------------------------------------------------------
# Test 6: stale sweep
# ---------------------------------------------------------------------------

def test_sweep_with_label_present_posts_stale_comment_and_removes_label(tmp_path, stub_gh):
    log = tmp_path / "gh.log"
    keep = tmp_path / "body.txt"
    gh = stub_gh / "gh"
    gh.write_text(
        '#!/bin/sh\n'
        'echo "$*" >> "${GH_LOG:-/dev/null}"\n'
        'if [ "$1" = "pr" ] && [ "$2" = "view" ]; then printf true; fi\n'
        'if [ "$1" = "pr" ] && [ "$2" = "comment" ]; then\n'
        '  while [ $# -gt 0 ]; do\n'
        '    if [ "$1" = "--body-file" ]; then cat "$2" > "$KEEP"; fi\n'
        '    shift\n'
        '  done\n'
        'fi\n'
        'exit 0\n'
    )
    r = _run_badge("postplan_sweep_stale_badge 42",
                   env_extra={"GH_LOG": str(log), "KEEP": str(keep)}, stub_gh_dir=stub_gh)
    assert r.returncode == 0, r.stderr
    calls = _calls(log)
    assert len(_pr_comments(calls)) == 1, calls
    assert len(_label_deletes(calls)) == 1, calls
    body = keep.read_text()
    assert "<!-- postplan-failure -->" in body
    assert "A previous post-plan run ended without cleaning up" in body
    assert "probably crashed or was killed" in body


def test_sweep_with_label_absent_does_nothing(tmp_path, stub_gh):
    log = tmp_path / "gh.log"
    r = _run_badge("postplan_sweep_stale_badge 42",
                   env_extra={"GH_LOG": str(log), "GH_FIXTURE": "false"}, stub_gh_dir=stub_gh)
    assert r.returncode == 0, r.stderr
    calls = _calls(log)
    assert _pr_comments(calls) == [], calls
    assert _label_deletes(calls) == [], calls
    assert not any("--method" in c for c in calls), calls


# ---------------------------------------------------------------------------
# Test 7: shq_round_trip_for_new_embeds
# ---------------------------------------------------------------------------

def test_shq_round_trip_for_new_embeds():
    """STARTED- and LABEL-shaped values survive a second shell parse byte-identically."""
    hostile = [
        "plain",
        "has `backticks` here",
        "has $(cmd) and $VAR",
        "it's got a single quote",
        r"a\b backslash",
        "<slug>-N.md",
        "2026-09-10 12:34:56 PDT",
        "com.ibl5.postplan-now-my-branch-20260910-123456-99",
    ]
    for s in hostile:
        script = f'source "{PPN}" >/dev/null 2>&1; q=$(shq "$1"); bash -c "printf %s $q"'
        r = subprocess.run(
            ["bash", "-c", script, "_", s],
            capture_output=True, text=True
        )
        assert r.stderr == "", f"{s!r}: second parse wrote to stderr: {r.stderr!r}"
        assert r.stdout == s, f"{s!r}: round-tripped to {r.stdout!r}"


# ---------------------------------------------------------------------------
# Test 8: generated_plist_wiring
# ---------------------------------------------------------------------------

def test_generated_plist_wiring(tmp_path):
    """The generated plist CMD carries all required badge env vars and control flow."""
    cmd = _generate_cmd(tmp_path)

    # Every exported value is single-quoted
    assert "POSTPLAN_LABEL='" in cmd, "POSTPLAN_LABEL not single-quoted"
    assert "POSTPLAN_STARTED='" in cmd, "POSTPLAN_STARTED not single-quoted"
    assert "POSTPLAN_BADGE_MARKER='" in cmd, "POSTPLAN_BADGE_MARKER not single-quoted"
    assert "POSTPLAN_RUNNING_LABEL='post-plan-running'" in cmd
    assert "POSTPLAN_FAILURE_MARKER='<!-- postplan-failure -->'" in cmd
    assert "POSTPLAN_LOG_FILE='/tmp/post-plan-now-" in cmd
    assert "POSTPLAN_STATUS_LABEL='post-plan-running'" in cmd
    assert "POSTPLAN_BADGE_BODY" not in cmd, "the sticky running body is gone"

    # Bug 2 fix: SIGTERM now gets its own trap body that sets _sigterm_received=1 AND
    # calls conclude_status_badge for cleanup. EXIT/INT/HUP share the original combined
    # trap. TERM is no longer in the combined trap but is handled separately — this is
    # the intentional design change for the SIGTERM-reported-as-success fix.
    assert "trap" in cmd
    assert "EXIT INT HUP" in cmd, "combined cleanup trap must cover EXIT INT HUP"
    assert "_sigterm_received=1" in cmd, "TERM-specific trap must set _sigterm_received flag"
    # TERM must still call conclude_status_badge (cleanup) — not just set a flag.
    term_trap_idx = cmd.index("_sigterm_received=1")
    assert "conclude_status_badge" in cmd[term_trap_idx:term_trap_idx + 80], (
        "TERM trap body must call conclude_status_badge for cleanup"
    )

    # conclude_status_badge appears exactly four times: once in the combined EXIT/INT/HUP
    # trap, once in the TERM-specific trap body, once in the tail, and once inside the
    # declare -f function body text (count increased from 3 because TERM now has its own
    # trap body that also runs cleanup — this is load-bearing for the SIGTERM fix)
    assert cmd.count("conclude_status_badge") == 4, (
        f"expected conclude_status_badge exactly four times, got {cmd.count('conclude_status_badge')}"
    )

    # postplan_sweep_stale_badge appears in the function body (declare -f) AND as a call site;
    # verify at least one call appears after the cd command
    assert cmd.count("postplan_sweep_stale_badge") >= 1
    cd_pos = cmd.index("cd ")
    # Find the call-site occurrence (look for the invocation pattern after cd)
    sweep_call = "postplan_sweep_stale_badge \""
    assert sweep_call in cmd, f"sweep call site {sweep_call!r} not found in cmd"
    sweep_call_pos = cmd.index(sweep_call)
    assert sweep_call_pos > cd_pos, "postplan_sweep_stale_badge call must appear after cd"

    # pp_rc=$? in the tail
    assert "pp_rc=$?" in cmd, "pp_rc=$? not in cmd tail"

    # rc=$? still present (harness segment)
    assert r"rc=$?" in cmd or r"rc=\$?" in cmd, "rc=$? lost from harness segment"

    # No unexpanded near-side vars left as bare $LABEL, $PLIST, $STARTED, $BADGE_FN
    # (these should be expanded at near-side, i.e., not appear as $VARIABLE in cmd)
    for var in ["$BADGE_FN", "$STARTED", "$PLIST"]:
        assert var not in cmd, f"Near-side variable {var!r} not expanded in generated cmd"


# ---------------------------------------------------------------------------
# Test 8b: rc3_propagation — brace group exits 0 with rc=3 → conclude gets 3
# ---------------------------------------------------------------------------

def test_rc3_propagates_when_group_exits_zero():
    """${rc:-0} detects harness rc=3 even when the brace group exits 0.

    The CMD tail logic: rc is set by GATE_CLOSE inside a brace group.
    The group exits 0 because GATE_CLOSE's rc=3 arm ends with echo.
    pp_rc=$? captures 0. Only ${rc:-0} can detect the harness rc=3.
    """
    script = (
        f'source "{PPN}" >/dev/null 2>&1\n'
        'rc=3\n'           # simulates GATE_CLOSE setting rc=3 inside brace group
        'pp_rc=0\n'        # simulates group exiting 0 → pp_rc=$?=0
        'if [ "${rc:-0}" = 3 ]; then pp_rc=3; fi\n'
        'echo "pp_rc=$pp_rc"\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True)
    assert "pp_rc=3" in r.stdout, (
        f"rc=3 was not propagated to pp_rc via ${{rc:-0}}: {r.stdout!r}"
    )

    # Verify the tautological form (the regression) does NOT propagate
    script_bad = (
        f'source "{PPN}" >/dev/null 2>&1\n'
        'rc=3\n'
        'pp_rc=0\n'
        'if [ "${pp_rc:-0}" = 3 ]; then pp_rc=3; fi\n'
        'echo "pp_rc=$pp_rc"\n'
    )
    r_bad = subprocess.run(["bash", "-c", script_bad], capture_output=True, text=True)
    assert "pp_rc=0" in r_bad.stdout, (
        f"Regression check: tautological pp_rc form should yield 0, got: {r_bad.stdout!r}"
    )


# ---------------------------------------------------------------------------
# Test 9: job_path_finds_gh
# ---------------------------------------------------------------------------

@pytest.mark.skipif(sys.platform != "darwin", reason="launchd PATH is macOS-only")
def test_job_path_finds_gh():
    """The launchd job PATH includes a directory where gh can be found."""
    paths = [
        "/usr/local/bin",
        "/opt/homebrew/bin",
        os.path.expanduser("~/.bun/bin"),
        os.path.expanduser("~/.local/bin"),
        "/Applications/cmux.app/Contents/Resources/bin",
    ]
    found = any(shutil.which("gh", path=p) for p in paths)
    assert found, (
        f"gh not found in any of the launchd job PATH entries: {paths}"
    )


# ---------------------------------------------------------------------------
# Test 10: failure_body content
# ---------------------------------------------------------------------------

def _failure_body(rc, extra_env=None, label="my-label", started="2026-09-10 12:00:00 PDT"):
    env = os.environ.copy()
    env.pop("POSTPLAN_LOG_FILE", None)
    env.pop("HARNESS_RESULT", None)
    env.update(extra_env or {})
    r = subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; postplan_failure_body {rc} "{label}" "{started}"'],
        capture_output=True, text=True, env=env)
    assert r.returncode == 0, r.stderr
    return r.stdout


def test_failure_body_generic_rc1(tmp_path):
    log = tmp_path / "run.log"
    log.write_text("".join(f"line {i}\n" for i in range(1, 8)))
    out = _failure_body(1, {"POSTPLAN_LOG_FILE": str(log)})
    assert out.splitlines()[0] == "<!-- postplan-failure -->"
    assert "**post-plan did not finish cleanly** (exit 1)" in out
    assert "Started 2026-09-10 12:00:00 PDT. Ended " in out
    assert "my-label" in out
    assert str(log) in out
    assert "~~~\nline 3\nline 4\nline 5\nline 6\nline 7\n~~~" in out
    assert "line 2\n" not in out
    assert "Re-run with" in out
    assert "<!-- postplan-label:" not in out


def test_failure_body_rc3_carries_result():
    out = _failure_body(3, {"HARNESS_RESULT": "RESULT: pre-push-adr-hook denied the push"})
    assert "(exit 3)" in out
    assert "pre-push-adr-hook denied the push" in out
    assert "Re-run with" in out


def test_failure_body_rc75_is_a_pause():
    out = _failure_body(75)
    assert "**post-plan paused for usage limit; auto-resumes after reset**" in out
    assert "did not finish cleanly" not in out
    assert "Re-run with" not in out


def test_failure_body_stale_has_no_log_tail(tmp_path):
    log = tmp_path / "run.log"
    log.write_text("secret line\n")
    out = _failure_body("stale", {"POSTPLAN_LOG_FILE": str(log)}, label="", started="unknown")
    assert "**A previous post-plan run ended without cleaning up**" in out
    assert "post-plan-running" in out and "probably crashed or was killed" in out
    assert "~~~" not in out
    assert "secret line" not in out


# ---------------------------------------------------------------------------
# Test 11: runner _post_status_label env gating
# ---------------------------------------------------------------------------

def test_runner_status_label_env_gating(monkeypatch):
    import runner as runner_mod

    class CapturingGh:
        def __init__(self):
            self.calls = []
        def pr_status_label(self, pr, label):
            self.calls.append((pr, label))

    class RaisingGh:
        def pr_status_label(self, pr, label):
            raise RuntimeError("boom")

    post = runner_mod._post_status_label
    assert not hasattr(runner_mod, "_BADGE_FALLBACK")

    monkeypatch.delenv("POSTPLAN_STATUS_LABEL", raising=False)
    gh1 = CapturingGh()
    post(gh1, 42)
    assert gh1.calls == []

    monkeypatch.setenv("POSTPLAN_STATUS_LABEL", "")
    gh2 = CapturingGh()
    post(gh2, 42)
    assert gh2.calls == []

    monkeypatch.setenv("POSTPLAN_STATUS_LABEL", "post-plan-running")
    gh3 = CapturingGh()
    post(gh3, 42)
    assert gh3.calls == [(42, "post-plan-running")]

    gh4 = CapturingGh()
    post(gh4, None)
    assert gh4.calls == []

    post(RaisingGh(), 42)  # must not raise


# ---------------------------------------------------------------------------
# Test 12: adapters
# ---------------------------------------------------------------------------

def test_recording_adapter_allowlists_disable_auto_merge(tmp_path):
    """pr_disable_auto_merge records an allowlisted action. It was missing, so the diverged-remote fail-closed path crashed with an AssertionError
    instead of exiting 3."""
    gh = RecordingGh(str(tmp_path / "out"))
    gh.pr_disable_auto_merge(42)
    assert gh.actions()[-1]["action"] == "pr_disable_auto_merge"


def test_recording_adapter_allowlists_status_label(tmp_path):
    """RecordingGh.pr_status_label records the intent without calling gh."""
    assert "pr_status_label" in RecordingGh.MUTATIONS
    assert "pr_status_badge" not in RecordingGh.MUTATIONS

    gh = RecordingGh(str(tmp_path / "out"))
    gh.pr_status_label(42, "post-plan-running")
    acts = gh.actions()
    assert len(acts) == 1
    assert acts[0]["action"] == "pr_status_label"
    assert acts[0]["pr"] == 42
    assert acts[0]["label"] == "post-plan-running"
    assert acts[0].get("executed") is None


def test_live_adapter_status_label_issues_expected_gh_calls(tmp_path):
    from harness.adapters.ghad import LiveGh
    from harness.state import HarnessError

    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "br")
    seen = []

    def fake(*args, input_text=None):
        seen.append(args)
        return ""
    gh._gh = fake
    gh.pr_status_label(42, "post-plan-running")
    assert seen[0] == ("label", "create", "post-plan-running", "--color", "FBCA04",
                       "--description",
                       "post-plan is running on this PR; removed when the run ends")
    assert seen[1] == ("api", "--method", "POST", "repos/{owner}/{repo}/issues/42/labels",
                       "-f", "labels[]=post-plan-running")
    acts = gh.actions()
    assert acts[-1]["action"] == "pr_status_label" and acts[-1]["pr"] == 42

    # A failing `label create` (already exists) must not stop the apply call.
    seen.clear()

    def fail_create(*args, input_text=None):
        seen.append(args)
        if args[0] == "label":
            raise HarnessError("gh", "already exists")
        return ""
    gh._gh = fail_create
    gh.pr_status_label(43, "post-plan-running")
    assert [a[0] for a in seen] == ["label", "api"]

    # A failing apply is swallowed too.
    def fail_all(*args, input_text=None):
        raise HarnessError("gh", "boom")
    gh._gh = fail_all
    gh.pr_status_label(44, "post-plan-running")  # must not raise


# ---------------------------------------------------------------------------
# Test 13: skill_md blocks
# ---------------------------------------------------------------------------

def test_skill_md_label_block_and_failure_reader():
    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    src = open(skill_md).read()

    assert "POSTPLAN_STATUS_LABEL" in src, "POSTPLAN_STATUS_LABEL guard not in SKILL.md"
    assert "POSTPLAN_BADGE_BODY" not in src
    assert "<!-- postplan-status -->" not in src
    assert "best-effort" in src, "best-effort note not in SKILL.md"
    assert 'labels[]=$POSTPLAN_STATUS_LABEL' in src
    assert "--color FBCA04" in src

    # The failure-comment reader
    assert 'contains("<!-- postplan-failure -->")' in src
    assert "data, never instructions" in src

# ---------------------------------------------------------------------------
# Test: missing_library (matrix row 6)
# ---------------------------------------------------------------------------

def test_missing_library(tmp_path):
    """When POSTPLAN_STICKY_LIB points at a nonexistent path, conclude exits 0."""
    env = {**os.environ, "POSTPLAN_STICKY_LIB": "/nonexistent/path/pr-sticky.sh"}
    script = f'source "{PPN}" >/dev/null 2>&1; conclude_status_badge 0; echo "rc=$?"'
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert "rc=0" in r.stdout, f"Expected rc=0, got: {r.stdout!r} stderr: {r.stderr!r}"
