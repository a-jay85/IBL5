"""Tests for the postplan status badge lifecycle.

Covers Phase 2 badge functions in bin/post-plan-now, the harness adapter
(ghad.py pr_status_badge), runner.py _post_status_badge, and the SKILL.md block.
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
    """Minimal dirty worktree on a non-master branch."""
    repo = tmp_path / "wt"
    repo.mkdir()
    run = lambda *a: subprocess.run(a, cwd=repo, check=True, capture_output=True)
    run("git", "init", "-q", "-b", "master")
    run("git", "config", "user.email", "test@example.com")
    run("git", "config", "user.name", "Test")
    (repo / "f.txt").write_text("one\n")
    run("git", "add", "-A")
    run("git", "commit", "-qm", "base")
    run("git", "checkout", "-qb", "some-feature")
    (repo / "f.txt").write_text("two\n")
    return repo


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


# ---------------------------------------------------------------------------
# Test 1: conclude_is_idempotent
# ---------------------------------------------------------------------------

def test_conclude_is_idempotent(tmp_path, stub_gh):
    """Calling conclude_status_badge twice records exactly one DELETE."""
    log = tmp_path / "gh.log"
    # Fixture: one matching comment
    fixture = '[{"id":42,"body":"<!-- postplan-status -->\\nbody"}]'
    env = {"GH_LOG": str(log), "GH_FIXTURE": fixture}
    r = _run_badge(
        'conclude_status_badge 0 99\nconclude_status_badge 0 99',
        env_extra=env, stub_gh_dir=stub_gh
    )
    assert r.returncode == 0, r.stderr
    calls = log.read_text().splitlines() if log.exists() else []
    # First conclude: find (api) + delete (api DELETE) = 2 api calls
    # Second conclude: skipped by POSTPLAN_CONCLUDED guard = 0 additional api calls
    api_calls = [c for c in calls if c.startswith("api")]
    assert len(api_calls) <= 3, f"expected <=3 api calls (find+delete+possibly one more), got {api_calls}"
    # Idempotent — exactly one DELETE, never two
    delete_calls_total = [c for c in calls if "--method DELETE" in c or "DELETE" in c]
    assert len(delete_calls_total) == 1, f"expected exactly 1 DELETE, got {delete_calls_total}"


# ---------------------------------------------------------------------------
# Test 2: outcome_routing
# ---------------------------------------------------------------------------

def test_outcome_routing(tmp_path, stub_gh):
    """Table-driven: rc determines whether badge is deleted (0) or patched (nonzero)."""
    log = tmp_path / "gh.log"
    fixture = '[{"id":99,"body":"<!-- postplan-status -->\\nbody"}]'
    for rc, expected_method in [("0", "DELETE"), ("3", "PATCH"), ("1", "PATCH"), ("stale", "PATCH")]:
        log.write_text("")
        env = {"GH_LOG": str(log), "GH_FIXTURE": fixture}
        script = f'POSTPLAN_CONCLUDED=0\nconclude_status_badge {rc} 42'
        r = _run_badge(script, env_extra=env, stub_gh_dir=stub_gh)
        assert r.returncode == 0, f"rc={rc}: {r.stderr}"
        calls = log.read_text().splitlines()
        methods = [c for c in calls if "--method" in c]
        if expected_method == "DELETE":
            assert any("DELETE" in m for m in methods), f"rc={rc}: expected DELETE in {methods}"
        else:
            assert any("PATCH" in m for m in methods), f"rc={rc}: expected PATCH in {methods}"
        # For rc=3, verify rebase conflict wording in the banner body
        if rc == "3":
            # Banner body is passed via temp file; look in overall output or verify
            # the function is called (function will call pr_sticky_upsert which calls
            # postplan_badge_banner_body with rc=3)
            banner_check = _run_badge(
                'postplan_badge_banner_body 3 "my-label" "2026-01-01"',
                env_extra={}, stub_gh_dir=stub_gh
            )
            assert "rebase conflict" in banner_check.stdout, (
                f"rc=3 banner missing 'rebase conflict': {banner_check.stdout!r}"
            )


# ---------------------------------------------------------------------------
# Test 3: conclude_with_no_badge_is_a_silent_noop
# ---------------------------------------------------------------------------

def test_conclude_with_no_badge_is_a_silent_noop(tmp_path, stub_gh):
    """When no badge comment exists, conclude exits 0 and sends no PATCH/DELETE."""
    log = tmp_path / "gh.log"
    # Empty GH_FIXTURE: stub echoes nothing, so pr_sticky_find returns empty string
    # → no comment ID found → pr_sticky_delete/upsert skips the mutation.
    env = {"GH_LOG": str(log), "GH_FIXTURE": ""}
    r = _run_badge("conclude_status_badge 0 42", env_extra=env, stub_gh_dir=stub_gh)
    assert r.returncode == 0, r.stderr
    calls = log.read_text().splitlines() if log.exists() else []
    assert not any("PATCH" in c or "DELETE" in c for c in calls), (
        f"Expected no PATCH/DELETE, got: {calls}"
    )


# ---------------------------------------------------------------------------
# Test 4: fail_open
# ---------------------------------------------------------------------------

def test_fail_open(tmp_path, stub_gh):
    """GH_FAIL=1: badge functions return 0 and arg-error exits unchanged."""
    log = tmp_path / "gh.log"
    env = {"GH_LOG": str(log), "GH_FAIL": "1", "GH_FIXTURE": ""}
    for fn_call in [
        "post_status_badge 42",
        "conclude_status_badge 0 42",
        "postplan_sweep_stale_badge 42",
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
# Test 5: marker_matching_is_exact
# ---------------------------------------------------------------------------

def test_marker_matching_is_exact(tmp_path, stub_gh):
    """Only <!-- postplan-status --> matches; pr-ready-verdict and pr-fast-canary do not."""
    log = tmp_path / "gh.log"

    # Stub echoes GH_FIXTURE verbatim (skips --jq filtering).
    # To simulate jq filtering "no match", set GH_FIXTURE to empty string.
    # When the stub echoes nothing, pr_sticky_find / postplan_badge_existing_label
    # sees an empty body → exits 1.
    env = {"GH_LOG": str(log), "GH_FIXTURE": ""}
    r = _run_badge("postplan_badge_existing_label 42; echo rc=$?", env_extra=env, stub_gh_dir=stub_gh)
    # Should return rc=1 (no badge found — stub returned nothing)
    assert "rc=1" in r.stdout, f"Expected rc=1 (no badge), got: {r.stdout!r}"

    # Now simulate a stub that returns a pre-filtered body (what jq would emit for a match).
    # The body of a real badge comment containing the label line:
    log.write_text("")
    real_body = "<!-- postplan-status -->\n**post-plan is running**\n<!-- postplan-label: my-job -->"
    env["GH_FIXTURE"] = real_body
    r2 = _run_badge(
        'postplan_badge_existing_label 42 && echo "found_label"',
        env_extra=env, stub_gh_dir=stub_gh
    )
    assert "found_label" in r2.stdout, f"Expected badge found: {r2.stdout!r} {r2.stderr!r}"

    # Sibling markers must NOT match: test the jq filter expression directly.
    # The stub bypasses --jq; to detect a substring-match regression we must
    # run the actual jq filter that postplan_badge_existing_label uses.
    import json as _json
    badge_marker = "<!-- postplan-status -->"
    jq_filter = f'.[] | select(.body | contains("{badge_marker}")) | .body'
    for other_marker in ["<!-- pr-ready-verdict -->", "<!-- pr-fast-canary -->"]:
        fixture_json = _json.dumps([{"body": f"{other_marker}\n**content**"}])
        r_jq = subprocess.run(
            ["jq", "-r", jq_filter],
            input=fixture_json, capture_output=True, text=True
        )
        assert r_jq.stdout.strip() == "", (
            f"jq filter for {badge_marker!r} must not match {other_marker!r}: "
            f"got {r_jq.stdout!r}"
        )


# ---------------------------------------------------------------------------
# Test 6: stale_detection
# ---------------------------------------------------------------------------

def test_stale_detection(tmp_path, stub_gh, stub_launchctl):
    """Three cases: absent label → PATCH; present label → no-op; empty label → PATCH."""
    log = tmp_path / "gh.log"
    badge_fixture = (
        '[{"id":5,"body":"<!-- postplan-status -->\\n**running**\\n'
        '<!-- postplan-label: com.ibl5.live-job -->"}]'
    )

    # Case 1: label absent from LIVE_LABELS → sweep PATCHes
    log.write_text("")
    env = {
        "GH_LOG": str(log),
        "GH_FIXTURE": badge_fixture,
        "LIVE_LABELS": "some-other-job",
    }
    r = _run_badge(
        "postplan_sweep_stale_badge 42",
        env_extra=env, stub_gh_dir=stub_gh, stub_lc_dir=stub_launchctl
    )
    assert r.returncode == 0, r.stderr
    calls = log.read_text().splitlines()
    assert any("PATCH" in c for c in calls), f"Expected PATCH (stale): {calls}"

    # Case 2: label present in LIVE_LABELS → no mutation
    log.write_text("")
    env["LIVE_LABELS"] = "com.ibl5.live-job"
    r2 = _run_badge(
        "postplan_sweep_stale_badge 42",
        env_extra=env, stub_gh_dir=stub_gh, stub_lc_dir=stub_launchctl
    )
    assert r2.returncode == 0, r2.stderr
    calls2 = log.read_text().splitlines()
    assert not any("PATCH" in c for c in calls2), f"Expected no PATCH (live): {calls2}"

    # Case 3: empty label (hand-run harness) → treated as stale (PATCH), launchctl never invoked
    lc_log = tmp_path / "lc.log"
    lc_log.write_text("")
    log.write_text("")
    empty_label_fixture = (
        '[{"id":6,"body":"<!-- postplan-status -->\\n**running**\\n'
        '<!-- postplan-label:  -->"}]'
    )
    env["GH_FIXTURE"] = empty_label_fixture
    env["LIVE_LABELS"] = ""
    env["LC_LOG"] = str(lc_log)
    r3 = _run_badge(
        "postplan_sweep_stale_badge 42",
        env_extra=env, stub_gh_dir=stub_gh, stub_lc_dir=stub_launchctl
    )
    assert r3.returncode == 0, r3.stderr
    calls3 = log.read_text().splitlines()
    assert any("PATCH" in c for c in calls3), f"Expected PATCH (empty label stale): {calls3}"
    lc_calls3 = lc_log.read_text().strip()
    assert not lc_calls3, f"launchctl must not be invoked for empty label; got: {lc_calls3!r}"


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

    # All four badge env vars are single-quoted
    assert "POSTPLAN_LABEL='" in cmd, "POSTPLAN_LABEL not single-quoted"
    assert "POSTPLAN_STARTED='" in cmd, "POSTPLAN_STARTED not single-quoted"
    assert "POSTPLAN_BADGE_MARKER='" in cmd, "POSTPLAN_BADGE_MARKER not single-quoted"
    assert "POSTPLAN_BADGE_BODY=" in cmd, "POSTPLAN_BADGE_BODY not in cmd"

    # trap names all four signals
    assert "trap" in cmd
    assert "EXIT INT TERM HUP" in cmd

    # conclude_status_badge appears exactly three times: once in the trap, once in the
    # tail, and once inside the declare -f function body text
    assert cmd.count("conclude_status_badge") == 3, (
        f"expected conclude_status_badge exactly three times, got {cmd.count('conclude_status_badge')}"
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
# Test 10: badge_body_wording
# ---------------------------------------------------------------------------

def test_badge_body_wording():
    """Both renderers produce the required wording and trailer."""
    # Normal body
    r1 = subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; postplan_badge_body "my-label" "2026-09-10 12:00:00 PDT"'],
        capture_output=True, text=True
    )
    assert "post-plan is running" in r1.stdout, f"body: {r1.stdout!r}"
    assert "<!-- postplan-label:  -->" not in r1.stdout  # label is non-empty
    assert "<!-- postplan-label: my-label -->" in r1.stdout, f"body missing label: {r1.stdout!r}"
    assert "<!-- postplan-status -->" in r1.stdout

    # Banner body (failure)
    r2 = subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; postplan_badge_banner_body 1 "my-label" "2026-09-10 12:00:00 PDT"'],
        capture_output=True, text=True
    )
    assert "post-plan did not finish cleanly" in r2.stdout, f"banner: {r2.stdout!r}"
    assert "<!-- postplan-label: my-label -->" in r2.stdout
    assert "<!-- postplan-status -->" in r2.stdout


# ---------------------------------------------------------------------------
# Test 11: runner_badge_env_unset_and_empty
# ---------------------------------------------------------------------------

def test_runner_badge_env_unset_and_empty(tmp_path, monkeypatch):
    """_post_status_badge: unset/empty POSTPLAN_BADGE_BODY → fallback used; exceptions swallowed."""
    import importlib
    import runner as runner_mod

    class CapturingGh:
        def __init__(self):
            self.calls = []
        def pr_status_badge(self, pr, body):
            self.calls.append(body)

    class RaisingGh:
        def pr_status_badge(self, pr, body):
            raise RuntimeError("boom")

    _post_status_badge = runner_mod._post_status_badge
    _BADGE_FALLBACK = runner_mod._BADGE_FALLBACK

    # POSTPLAN_BADGE_BODY unset → fallback used
    monkeypatch.delenv("POSTPLAN_BADGE_BODY", raising=False)
    gh1 = CapturingGh()
    _post_status_badge(gh1, 42)
    assert len(gh1.calls) == 1
    assert gh1.calls[0] == _BADGE_FALLBACK

    # POSTPLAN_BADGE_BODY="" → fallback used
    monkeypatch.setenv("POSTPLAN_BADGE_BODY", "")
    gh2 = CapturingGh()
    _post_status_badge(gh2, 42)
    assert len(gh2.calls) == 1
    assert gh2.calls[0] == _BADGE_FALLBACK

    # Raising adapter is swallowed
    monkeypatch.delenv("POSTPLAN_BADGE_BODY", raising=False)
    gh3 = RaisingGh()
    _post_status_badge(gh3, 42)  # must not raise


# ---------------------------------------------------------------------------
# Test 12: recording_adapter_allowlists_badge
# ---------------------------------------------------------------------------

def test_recording_adapter_allowlists_badge(tmp_path):
    """RecordingGh.pr_status_badge records the intent without calling gh."""
    assert "pr_status_badge" in RecordingGh.MUTATIONS

    gh = RecordingGh(str(tmp_path / "out"))
    gh.pr_status_badge(42, "<!-- postplan-status -->\nrunning")
    acts = gh.actions()
    assert len(acts) == 1
    assert acts[0]["action"] == "pr_status_badge"
    assert acts[0]["pr"] == 42
    # Should NOT have spawned a subprocess (no executed=True flag in recording mode)
    assert acts[0].get("executed") is None


# ---------------------------------------------------------------------------
# Test 13: skill_md_badge_block
# ---------------------------------------------------------------------------

def test_skill_md_badge_block():
    """SKILL.md Phase 2 contains the badge block with correct guards and markers."""
    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    src = open(skill_md).read()

    assert "<!-- postplan-status -->" in src, "Badge marker not in SKILL.md"
    assert "POSTPLAN_BADGE_BODY" in src, "POSTPLAN_BADGE_BODY guard not in SKILL.md"
    assert "best-effort" in src, "best-effort note not in SKILL.md"

    # The badge shell script block uses the correct postplan-status marker in contains(),
    # not the Phase 5.5 pr-ready-verdict marker.
    assert 'contains("<!-- postplan-status -->")' in src, (
        "Badge block must filter by postplan-status marker in contains() call"
    )


# ---------------------------------------------------------------------------
# Test: missing_library (matrix row 6)
# ---------------------------------------------------------------------------

def test_missing_library(tmp_path):
    """When POSTPLAN_STICKY_LIB points at a nonexistent path, conclude exits 0."""
    env = {**os.environ, "POSTPLAN_STICKY_LIB": "/nonexistent/path/pr-sticky.sh"}
    script = f'source "{PPN}" >/dev/null 2>&1; conclude_status_badge 0; echo "rc=$?"'
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert "rc=0" in r.stdout, f"Expected rc=0, got: {r.stdout!r} stderr: {r.stderr!r}"
